<?php
/**
 * Order Service — shared cart pricing + order creation, used by both the
 * Cash on Delivery flow (place-order.php) and the online payment flow
 * (create-razorpay-order.php for pricing, verify-razorpay-payment.php for the
 * actual order). Keeping this in one place means the price quoted before
 * payment and the price actually charged can never drift apart.
 */

require_once __DIR__ . '/DeliveryCalculator.php';
require_once __DIR__ . '/CouponValidator.php';
require_once __DIR__ . '/SubscriptionService.php';

class OrderService {

    /**
     * Validate the cart against the database and price it: subtotal, per-shop
     * delivery, handling fee, coupon discount, grand total. Read-only — never
     * locks stock or writes anything, so it is safe to call as a "quote" before
     * payment and then call again for real when actually placing the order.
     *
     * @return array{success:bool, message?:string, ...pricing fields on success}
     */
    public static function priceCart($conn, $customer_id, $shipping, $cart_items, $coupon_code) {
        $required_fields = ['full_name', 'phone', 'address', 'city', 'state', 'pincode'];
        foreach ($required_fields as $field) {
            if (empty(trim($shipping[$field] ?? ''))) {
                return ['success' => false, 'message' => 'Missing required field: ' . $field];
            }
        }
        if (empty($cart_items)) {
            return ['success' => false, 'message' => 'Cart is empty'];
        }

        // Validate products against DB — use DB price for security
        $validated_items = [];
        $subtotal        = 0.0;

        $pStmt = $conn->prepare(
            "SELECT p.product_id, p.product_name, p.product_code, p.price, p.shop_id,
                    (SELECT pi.image_url FROM product_images pi
                     WHERE pi.product_id = p.product_id
                     ORDER BY pi.is_primary DESC, pi.display_order ASC
                     LIMIT 1) AS product_image
             FROM products p
             INNER JOIN shops s ON p.shop_id = s.shop_id
             WHERE p.product_id = :pid AND p.product_status = 'active' AND s.shop_status = 'open'
             LIMIT 1"
        );

        foreach ($cart_items as $item) {
            $product_id     = (int)($item['id'] ?? 0);
            $quantity       = max(1, (int)($item['quantity'] ?? 1));
            $selected_size  = isset($item['size'])  && $item['size']  !== null ? trim($item['size'])  : null;
            $selected_color = isset($item['color']) && $item['color'] !== null ? trim($item['color']) : null;

            if ($product_id <= 0) continue;

            $pStmt->bindValue(':pid', $product_id, PDO::PARAM_INT);
            $pStmt->execute();
            $product = $pStmt->fetch(PDO::FETCH_ASSOC);
            $pStmt->closeCursor();

            if (!$product) continue; // skip inactive / not found

            $effective_price = (float)$product['price'];
            if ($selected_size) {
                $adjStmt = $conn->prepare(
                    "SELECT price_adjustment FROM product_sizes
                     WHERE product_id = :pid AND size_label = :size LIMIT 1"
                );
                $adjStmt->bindValue(':pid',  $product_id, PDO::PARAM_INT);
                $adjStmt->bindValue(':size', $selected_size);
                $adjStmt->execute();
                $adjRow = $adjStmt->fetch(PDO::FETCH_ASSOC);
                $adjStmt->closeCursor();
                if ($adjRow) {
                    $effective_price += (float)$adjRow['price_adjustment'];
                }
            }

            $line_total       = round($effective_price * $quantity, 2);
            $subtotal        += $line_total;
            $validated_items[] = [
                'product_id'     => $product['product_id'],
                'product_name'   => $product['product_name'],
                'product_code'   => $product['product_code'],
                'product_image'  => $product['product_image'],
                'shop_id'        => $product['shop_id'],
                'selected_size'  => $selected_size,
                'selected_color' => $selected_color,
                'quantity'       => $quantity,
                'price'          => $effective_price,
                'subtotal'       => $line_total,
            ];
        }

        if (empty($validated_items)) {
            return ['success' => false, 'message' => 'No valid products found in cart'];
        }

        $tax_amount    = 0.00;
        $order_pincode = trim($shipping['pincode']);
        $cust_lat      = isset($shipping['latitude'])  && $shipping['latitude']  !== '' ? (float)$shipping['latitude']  : null;
        $cust_lng      = isset($shipping['longitude']) && $shipping['longitude'] !== '' ? (float)$shipping['longitude'] : null;

        // Delivering to a saved address: its own map pin decides the distance
        // fee (not wherever the customer's device happens to be right now)
        $address_id = (int)($shipping['address_id'] ?? 0);
        if ($address_id > 0) {
            $addrStmt = $conn->prepare("SELECT latitude, longitude FROM addresses WHERE address_id = :aid AND user_id = :uid LIMIT 1");
            $addrStmt->execute([':aid' => $address_id, ':uid' => $customer_id]);
            $addr = $addrStmt->fetch(PDO::FETCH_ASSOC);
            if (!$addr) {
                $address_id = 0;
            } elseif ($addr['latitude'] !== null && $addr['longitude'] !== null) {
                $cust_lat = (float)$addr['latitude'];
                $cust_lng = (float)$addr['longitude'];
            }
        }

        $psStmt2 = $conn->query(
            "SELECT setting_key, setting_value FROM platform_settings
             WHERE setting_key IN ('handling_fee', 'first_order_free_delivery')"
        );
        $psRows2 = $psStmt2->fetchAll(PDO::FETCH_KEY_PAIR);
        $handling_fee_amount       = (float)($psRows2['handling_fee'] ?? 0);
        $first_order_free_delivery = (int)($psRows2['first_order_free_delivery'] ?? 1) === 1;

        $orderCountStmt = $conn->prepare(
            "SELECT COUNT(*) FROM orders WHERE customer_id = :id AND order_status != 'cancelled'"
        );
        $orderCountStmt->bindValue(':id', $customer_id, PDO::PARAM_INT);
        $orderCountStmt->execute();
        $is_first_order = (int)$orderCountStmt->fetchColumn() === 0;

        $items_by_shop = [];
        foreach ($validated_items as $item) {
            $items_by_shop[$item['shop_id']][] = $item;
        }

        $shipping_amount    = 0.0;
        $delivery_breakdown = [];
        $shopLookupStmt = $conn->prepare("SELECT shop_name, latitude, longitude FROM shops WHERE shop_id = :sid LIMIT 1");

        foreach ($items_by_shop as $shop_id_for_fee => $shop_items) {
            $shopLookupStmt->bindValue(':sid', $shop_id_for_fee, PDO::PARAM_INT);
            $shopLookupStmt->execute();
            $shopRow = $shopLookupStmt->fetch(PDO::FETCH_ASSOC);
            $shopLat = $shopRow && $shopRow['latitude']  !== null ? (float)$shopRow['latitude']  : null;
            $shopLng = $shopRow && $shopRow['longitude'] !== null ? (float)$shopRow['longitude'] : null;

            $leg = DeliveryCalculator::distanceFee($conn, $shopLat, $shopLng, $cust_lat, $cust_lng);
            if (!$leg) {
                $leg = DeliveryCalculator::zoneFee($conn, $shop_id_for_fee, $order_pincode);
            }

            $shipping_amount += $leg['fee'];
            $delivery_breakdown[] = [
                'shop_id'     => $shop_id_for_fee,
                'shop_name'   => $shopRow['shop_name'] ?? '',
                'method'      => $leg['method'],
                'distance_km' => $leg['distance_km'] ?? null,
                'zone_name'   => $leg['zone_name'] ?? null,
                'fee'         => $leg['fee'],
            ];
        }

        $delivery_zone_name = implode('; ', array_map(function ($d) {
            $label = $d['method'] === 'distance' ? "{$d['distance_km']}km" : ($d['zone_name'] ?: 'Zone');
            return "{$d['shop_name']}: {$label} (₹{$d['fee']})";
        }, $delivery_breakdown));

        if ($is_first_order && $first_order_free_delivery) {
            $shipping_amount    = 0.00;
            $delivery_zone_name = ($delivery_zone_name ? $delivery_zone_name . ' ' : '') . '(First Order - Free)';
        }

        if (SubscriptionService::activeSubscription($conn, $customer_id)) {
            $shipping_amount    = 0.00;
            $delivery_zone_name = ($delivery_zone_name ? $delivery_zone_name . ' ' : '') . '(Subscriber - Free)';
        }

        // Coupon — never trust a discount amount from the client
        $applied_coupon  = null;
        $discount_amount = 0.00;
        $coupon_code = trim((string)$coupon_code);
        if ($coupon_code !== '') {
            $couponResult = CouponValidator::validate($conn, $coupon_code, $customer_id, $subtotal);
            if (!$couponResult['valid']) {
                return ['success' => false, 'message' => $couponResult['message']];
            }
            $applied_coupon  = $couponResult['coupon'];
            $discount_amount = $couponResult['discount_amount'];
        }

        $total_amount = round($subtotal + $shipping_amount + $handling_fee_amount - $discount_amount, 2);

        return [
            'success'             => true,
            'validated_items'     => $validated_items,
            'items_by_shop'       => $items_by_shop,
            'subtotal'            => $subtotal,
            'tax_amount'          => $tax_amount,
            'shipping_amount'     => $shipping_amount,
            'delivery_breakdown'  => $delivery_breakdown,
            'delivery_zone_name'  => $delivery_zone_name,
            'handling_fee_amount' => $handling_fee_amount,
            'discount_amount'     => $discount_amount,
            'applied_coupon'      => $applied_coupon,
            'total_amount'        => $total_amount,
            'cust_lat'            => $cust_lat,
            'cust_lng'            => $cust_lng,
            'address_id'          => $address_id,
        ];
    }

    /**
     * Price the cart fresh, then actually place the order: lock and decrement
     * stock, insert the order + items, redeem the coupon, save the shipping
     * address, send confirmation emails.
     *
     * $payment_status/$payment_method/$razorpay_* describe how this order was
     * (or is being) paid for — COD passes payment_status 'pending' with no
     * Razorpay fields; the online-payment verify step passes 'paid' plus the
     * verified Razorpay order/payment ids.
     *
     * @return array{success:bool, message?:string, ...order fields on success}
     */
    public static function placeOrder($conn, $customer_id, $shipping, $cart_items, $coupon_code,
                                       $payment_method, $payment_status,
                                       $razorpay_order_id = null, $razorpay_payment_id = null) {

        $priced = self::priceCart($conn, $customer_id, $shipping, $cart_items, $coupon_code);
        if (!$priced['success']) {
            return $priced;
        }

        $validated_items     = $priced['validated_items'];
        $items_by_shop       = $priced['items_by_shop'];
        $subtotal            = $priced['subtotal'];
        $tax_amount          = $priced['tax_amount'];
        $shipping_amount     = $priced['shipping_amount'];
        $delivery_breakdown  = $priced['delivery_breakdown'];
        $delivery_zone_name  = $priced['delivery_zone_name'];
        $handling_fee_amount = $priced['handling_fee_amount'];
        $discount_amount     = $priced['discount_amount'];
        $applied_coupon      = $priced['applied_coupon'];
        $total_amount        = $priced['total_amount'];
        $cust_lat            = $priced['cust_lat'];
        $cust_lng            = $priced['cust_lng'];
        $address_id          = $priced['address_id'];

        do {
            $order_number = 'TC' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $chk = $conn->prepare("SELECT order_id FROM orders WHERE order_number = :on LIMIT 1");
            $chk->bindValue(':on', $order_number);
            $chk->execute();
            $chkExists = $chk->fetch();
            $chk->closeCursor();
        } while ($chkExists);

        $conn->beginTransaction();

        // Lock rows and validate stock (FOR UPDATE prevents race conditions)
        $sizeStockStmt = $conn->prepare(
            "SELECT stock_quantity FROM product_sizes
             WHERE product_id = :pid AND size_label = :size FOR UPDATE"
        );
        $prodStockStmt = $conn->prepare(
            "SELECT stock_quantity FROM products WHERE product_id = :pid FOR UPDATE"
        );

        foreach ($validated_items as $item) {
            if ($item['selected_size']) {
                $sizeStockStmt->bindValue(':pid',  $item['product_id'],   PDO::PARAM_INT);
                $sizeStockStmt->bindValue(':size', $item['selected_size']);
                $sizeStockStmt->execute();
                $stockRow = $sizeStockStmt->fetch(PDO::FETCH_ASSOC);
                $sizeStockStmt->closeCursor();
                $available = $stockRow ? (int)$stockRow['stock_quantity'] : 0;
                if ($available < $item['quantity']) {
                    $conn->rollBack();
                    return ['success' => false, 'message' => $available > 0
                        ? "{$item['product_name']} (Size: {$item['selected_size']}) only has {$available} left in stock."
                        : "{$item['product_name']} (Size: {$item['selected_size']}) is out of stock."];
                }
            } else {
                $prodStockStmt->bindValue(':pid', $item['product_id'], PDO::PARAM_INT);
                $prodStockStmt->execute();
                $stockRow = $prodStockStmt->fetch(PDO::FETCH_ASSOC);
                $prodStockStmt->closeCursor();
                $available = $stockRow ? (int)$stockRow['stock_quantity'] : 0;
                if ($available < $item['quantity']) {
                    $conn->rollBack();
                    return ['success' => false, 'message' => $available > 0
                        ? "{$item['product_name']} only has {$available} left in stock."
                        : "{$item['product_name']} is out of stock."];
                }
            }
        }

        // A coupon can be redeemed by only usage_limit_total slots across all customers — re-check
        // for that race here, inside the transaction, right before it is actually consumed.
        if ($applied_coupon && $applied_coupon['usage_limit_total'] !== null) {
            $raceStmt = $conn->prepare("SELECT times_used FROM coupons WHERE coupon_id = :cid FOR UPDATE");
            $raceStmt->bindValue(':cid', $applied_coupon['coupon_id'], PDO::PARAM_INT);
            $raceStmt->execute();
            if ((int)$raceStmt->fetchColumn() >= (int)$applied_coupon['usage_limit_total']) {
                $conn->rollBack();
                return ['success' => false, 'message' => 'This coupon has just reached its usage limit'];
            }
        }

        // Insert order
        $oStmt = $conn->prepare(
            "INSERT INTO orders
                (order_number, customer_id, subtotal, tax_amount, shipping_amount, delivery_zone, handling_fee,
                 coupon_code, discount_amount, total_amount,
                 order_status, payment_status, payment_method, razorpay_order_id, razorpay_payment_id,
                 shipping_name, shipping_email, shipping_phone,
                 shipping_address, shipping_city, shipping_state, shipping_pincode)
             VALUES
                (:order_number, :customer_id, :subtotal, :tax, :shipping_amount, :delivery_zone, :handling_fee,
                 :coupon_code, :discount_amount, :total,
                 'pending', :payment_status, :payment_method, :razorpay_order_id, :razorpay_payment_id,
                 :sname, :semail, :sphone,
                 :saddress, :scity, :sstate, :spincode)"
        );
        $oStmt->bindValue(':order_number',   $order_number);
        $oStmt->bindValue(':customer_id',    $customer_id,        PDO::PARAM_INT);
        $oStmt->bindValue(':subtotal',       $subtotal);
        $oStmt->bindValue(':tax',            $tax_amount);
        $oStmt->bindValue(':shipping_amount',$shipping_amount);
        $oStmt->bindValue(':delivery_zone',  $delivery_zone_name ?: null);
        $oStmt->bindValue(':handling_fee',   $handling_fee_amount);
        $oStmt->bindValue(':coupon_code',    $applied_coupon ? $applied_coupon['code'] : null);
        $oStmt->bindValue(':discount_amount',$discount_amount);
        $oStmt->bindValue(':total',          $total_amount);
        $oStmt->bindValue(':payment_status', $payment_status);
        $oStmt->bindValue(':payment_method', $payment_method);
        $oStmt->bindValue(':razorpay_order_id',   $razorpay_order_id);
        $oStmt->bindValue(':razorpay_payment_id', $razorpay_payment_id);
        $oStmt->bindValue(':sname',          trim($shipping['full_name']));
        $oStmt->bindValue(':semail',         trim($shipping['email'] ?? ''));
        $oStmt->bindValue(':sphone',         trim($shipping['phone']));
        $oStmt->bindValue(':saddress',       trim($shipping['address']));
        $oStmt->bindValue(':scity',          trim($shipping['city']));
        $oStmt->bindValue(':sstate',         trim($shipping['state']));
        $oStmt->bindValue(':spincode',       trim($shipping['pincode']));

        try {
            $oStmt->execute();
        } catch (PDOException $e) {
            $conn->rollBack();
            // A duplicate razorpay_payment_id means this exact payment already created an
            // order (e.g. the verify call was made twice) — treat as a benign no-op, not an error.
            if ($razorpay_payment_id !== null && strpos($e->getMessage(), 'razorpay_payment_id') !== false) {
                $existing = $conn->prepare("SELECT order_id, order_number, total_amount FROM orders WHERE razorpay_payment_id = :pid LIMIT 1");
                $existing->bindValue(':pid', $razorpay_payment_id);
                $existing->execute();
                $row = $existing->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    return [
                        'success'         => true,
                        'order_id'        => (int)$row['order_id'],
                        'order_number'    => $row['order_number'],
                        'total'           => (float)$row['total_amount'],
                        'shipping_amount' => $shipping_amount,
                        'discount_amount' => $discount_amount,
                        'coupon_code'     => $applied_coupon ? $applied_coupon['code'] : null,
                        'delivery_breakdown' => $delivery_breakdown,
                        'already_existed' => true,
                    ];
                }
            }
            throw $e;
        }

        $order_id = (int)$conn->lastInsertId();

        if ($applied_coupon) {
            CouponValidator::recordUsage($conn, $applied_coupon['coupon_id'], $customer_id, $order_id, $discount_amount);
        }

        // Insert order items. Name, SKU and image are snapshotted so the line
        // still reads correctly after the product is deleted or its SKU reused.
        $iStmt = $conn->prepare(
            "INSERT INTO order_items
                (order_id, shop_id, product_id, product_name, product_code, product_image, selected_size, selected_color, quantity, price, subtotal)
             VALUES
                (:order_id, :shop_id, :product_id, :product_name, :product_code, :product_image, :selected_size, :selected_color, :quantity, :price, :subtotal)"
        );
        $ocStmt = $conn->prepare(
            "UPDATE products SET orders_count = orders_count + :qty WHERE product_id = :pid"
        );
        foreach ($validated_items as $item) {
            $iStmt->bindValue(':order_id',       $order_id,                  PDO::PARAM_INT);
            $iStmt->bindValue(':shop_id',        $item['shop_id'],           PDO::PARAM_INT);
            $iStmt->bindValue(':product_id',     $item['product_id'],        PDO::PARAM_INT);
            $iStmt->bindValue(':product_name',   $item['product_name']);
            $iStmt->bindValue(':product_code',   $item['product_code']);
            $iStmt->bindValue(':product_image',  $item['product_image']);
            $iStmt->bindValue(':selected_size',  $item['selected_size']);
            $iStmt->bindValue(':selected_color', $item['selected_color']);
            $iStmt->bindValue(':quantity',      $item['quantity'],           PDO::PARAM_INT);
            $iStmt->bindValue(':price',         $item['price']);
            $iStmt->bindValue(':subtotal',      $item['subtotal']);
            $iStmt->execute();

            $ocStmt->bindValue(':qty', $item['quantity'], PDO::PARAM_INT);
            $ocStmt->bindValue(':pid', $item['product_id'], PDO::PARAM_INT);
            $ocStmt->execute();

            if ($item['selected_size']) {
                $decSize = $conn->prepare(
                    "UPDATE product_sizes
                     SET stock_quantity = GREATEST(0, stock_quantity - :qty)
                     WHERE product_id = :pid AND size_label = :size"
                );
                $decSize->bindValue(':qty',  $item['quantity'],     PDO::PARAM_INT);
                $decSize->bindValue(':pid',  $item['product_id'],   PDO::PARAM_INT);
                $decSize->bindValue(':size', $item['selected_size']);
                $decSize->execute();
                $syncStock = $conn->prepare(
                    "UPDATE products
                     SET stock_quantity = (SELECT COALESCE(SUM(stock_quantity),0) FROM product_sizes WHERE product_id = :pid)
                     WHERE product_id = :pid2"
                );
                $syncStock->bindValue(':pid',  $item['product_id'], PDO::PARAM_INT);
                $syncStock->bindValue(':pid2', $item['product_id'], PDO::PARAM_INT);
                $syncStock->execute();
            } else {
                $decProd = $conn->prepare(
                    "UPDATE products
                     SET stock_quantity = GREATEST(0, stock_quantity - :qty)
                     WHERE product_id = :pid"
                );
                $decProd->bindValue(':qty', $item['quantity'],   PDO::PARAM_INT);
                $decProd->bindValue(':pid', $item['product_id'], PDO::PARAM_INT);
                $decProd->execute();
            }
        }

        $conn->commit();

        // Remember a newly typed shipping address for next checkout. A saved
        // address picked at checkout (address_id) is left exactly as it is —
        // this used to overwrite the default address (and its map pin) with
        // whatever was delivered to, even when that was a different address.
        if (!$address_id) {
            try {
                $cntStmt = $conn->prepare(
                    "SELECT COUNT(*) AS total,
                            SUM(address_line1 = :addr AND pincode = :pincode) AS same
                     FROM addresses WHERE user_id = :uid"
                );
                $cntStmt->execute([
                    ':uid'     => $customer_id,
                    ':addr'    => trim($shipping['address']),
                    ':pincode' => trim($shipping['pincode']),
                ]);
                $cnt = $cntStmt->fetch(PDO::FETCH_ASSOC);

                // Skip if already saved, or the book is full (same limit as addresses.php)
                if (!(int)$cnt['same'] && (int)$cnt['total'] < 10) {
                    $ins = $conn->prepare(
                        "INSERT INTO addresses (user_id, address_type, full_name, phone, address_line1, city, state, pincode, latitude, longitude, is_default)
                         VALUES (:uid, 'home', :name, :phone, :addr, :city, :state, :pincode, :lat, :lng, :is_default)"
                    );
                    $ins->bindValue(':uid',     $customer_id, PDO::PARAM_INT);
                    $ins->bindValue(':name',    trim($shipping['full_name']));
                    $ins->bindValue(':phone',   trim($shipping['phone']));
                    $ins->bindValue(':addr',    trim($shipping['address']));
                    $ins->bindValue(':city',    trim($shipping['city']));
                    $ins->bindValue(':state',   trim($shipping['state']));
                    $ins->bindValue(':pincode', trim($shipping['pincode']));
                    $ins->bindValue(':lat',     $cust_lat);
                    $ins->bindValue(':lng',     $cust_lng);
                    $ins->bindValue(':is_default', (int)$cnt['total'] === 0 ? 1 : 0, PDO::PARAM_INT);
                    $ins->execute();
                }
            } catch (Exception $addrErr) {
                error_log("Address save error: " . $addrErr->getMessage());
            }
        }

        // Send order confirmation emails (non-critical — don't fail the response if email fails)
        try {
            require_once __DIR__ . '/EmailManager.php';
            $emailManager = new EmailManager();

            $order_data = [
                'order_number' => $order_number,
                'items'        => $validated_items,
                'subtotal'     => $subtotal,
                'tax'          => $tax_amount,
                'shipping'     => $shipping_amount,
                'total'        => $total_amount,
                'shipping_info'=> $shipping,
            ];

            $cStmt = $conn->prepare("SELECT email, full_name FROM users WHERE user_id = :id LIMIT 1");
            $cStmt->bindValue(':id', $customer_id, PDO::PARAM_INT);
            $cStmt->execute();
            $customer_row = $cStmt->fetch(PDO::FETCH_ASSOC);
            if ($customer_row && !empty($customer_row['email'])) {
                $emailManager->sendOrderConfirmationEmail($customer_row['email'], $customer_row['full_name'], $order_data);
            }

            $aStmt = $conn->query("SELECT email FROM users WHERE user_type = 'admin' LIMIT 1");
            $admin_row = $aStmt->fetch(PDO::FETCH_ASSOC);
            if ($admin_row) {
                $emailManager->sendNewOrderAdminEmail($admin_row['email'], $order_data);
            }

            $shopInfoStmt = $conn->prepare(
                "SELECT u.email, s.shop_name FROM shops s
                 INNER JOIN users u ON s.user_id = u.user_id
                 WHERE s.shop_id = :sid LIMIT 1"
            );
            foreach ($items_by_shop as $sid => $shop_items) {
                $shopInfoStmt->bindValue(':sid', $sid, PDO::PARAM_INT);
                $shopInfoStmt->execute();
                $shop_row = $shopInfoStmt->fetch(PDO::FETCH_ASSOC);
                if ($shop_row) {
                    $emailManager->sendShopOrderEmail(
                        $shop_row['email'],
                        $shop_row['shop_name'],
                        $order_data,
                        $shop_items
                    );
                }
            }
        } catch (Exception $emailErr) {
            error_log("Order email error: " . $emailErr->getMessage());
        }

        return [
            'success'            => true,
            'order_id'           => $order_id,
            'order_number'       => $order_number,
            'total'              => $total_amount,
            'shipping_amount'    => $shipping_amount,
            'discount_amount'    => $discount_amount,
            'coupon_code'        => $applied_coupon ? $applied_coupon['code'] : null,
            'delivery_breakdown' => $delivery_breakdown,
        ];
    }
}
?>
