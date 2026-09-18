<?php
/**
 * Customer - Place Order API
 * Requires customer session
 * POST: { shipping: {...}, items: [{id, quantity}], payment_method: "cod" }
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/TokenAuth.php';
require_once __DIR__ . '/../utils/DeliveryCalculator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$customer_id = TokenAuth::requireCustomer($conn, 'Please login to place an order');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request body']);
    exit;
}

$shipping       = $input['shipping']       ?? [];
$cart_items     = $input['items']          ?? [];
$payment_method = $input['payment_method'] ?? 'cod';

// Validate required shipping fields
$required_fields = ['full_name', 'phone', 'address', 'city', 'state', 'pincode'];
foreach ($required_fields as $field) {
    if (empty(trim($shipping[$field] ?? ''))) {
        echo json_encode(['success' => false, 'message' => 'Missing required field: ' . $field]);
        exit;
    }
}

if (empty($cart_items)) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty']);
    exit;
}

try {
    // Validate payment method against platform settings
    $psStmt = $conn->query(
        "SELECT setting_key, setting_value FROM platform_settings
         WHERE setting_key IN ('cod_enabled')"
    );
    $psRows = $psStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $cod_enabled = (int)($psRows['cod_enabled'] ?? 1) === 1;

    if ($payment_method === 'cod' && !$cod_enabled) {
        echo json_encode(['success' => false, 'message' => 'Cash on Delivery is currently not available. Please try again later.']);
        exit;
    }

    // Ensure at least one payment method is available
    if (!$cod_enabled) {
        echo json_encode(['success' => false, 'message' => 'No payment method is currently available. Please try again later.']);
        exit;
    }

    // Validate products against DB — use DB price for security
    $validated_items = [];
    $subtotal        = 0.0;

    $pStmt = $conn->prepare(
        "SELECT p.product_id, p.product_name, p.price, p.shop_id
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

        // If a size with a price_adjustment exists, add it to the price
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
            'product_id'    => $product['product_id'],
            'product_name'  => $product['product_name'],
            'shop_id'       => $product['shop_id'],
            'selected_size'  => $selected_size,
            'selected_color' => $selected_color,
            'quantity'       => $quantity,
            'price'          => $effective_price,
            'subtotal'       => $line_total,
        ];
    }

    if (empty($validated_items)) {
        echo json_encode(['success' => false, 'message' => 'No valid products found in cart']);
        exit;
    }

    $tax_amount    = 0.00;
    $order_pincode = trim($shipping['pincode']);
    $cust_lat      = isset($shipping['latitude'])  && $shipping['latitude']  !== '' ? (float)$shipping['latitude']  : null;
    $cust_lng      = isset($shipping['longitude']) && $shipping['longitude'] !== '' ? (float)$shipping['longitude'] : null;

    // Load platform settings: handling_fee + first_order_free_delivery
    $psStmt2 = $conn->query(
        "SELECT setting_key, setting_value FROM platform_settings
         WHERE setting_key IN ('handling_fee', 'first_order_free_delivery')"
    );
    $psRows2 = $psStmt2->fetchAll(PDO::FETCH_KEY_PAIR);
    $handling_fee_amount       = (float)($psRows2['handling_fee'] ?? 0);
    $first_order_free_delivery = (int)($psRows2['first_order_free_delivery'] ?? 1) === 1;

    // Check if this is the customer's first order (server-side — never trust client)
    $orderCountStmt = $conn->prepare(
        "SELECT COUNT(*) FROM orders WHERE customer_id = :id AND order_status != 'cancelled'"
    );
    $orderCountStmt->bindValue(':id', $customer_id, PDO::PARAM_INT);
    $orderCountStmt->execute();
    $is_first_order = (int)$orderCountStmt->fetchColumn() === 0;

    // Delivery fee is calculated PER SHOP and summed — a cart can contain
    // items from more than one shop. Distance-based when both the shop and
    // the customer have coordinates, otherwise the pincode/zone fallback.
    $items_by_shop = [];
    foreach ($validated_items as $item) {
        $items_by_shop[$item['shop_id']][] = $item;
    }

    $shipping_amount   = 0.0;
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

    // First order: override delivery to free
    if ($is_first_order && $first_order_free_delivery) {
        $shipping_amount    = 0.00;
        $delivery_zone_name = ($delivery_zone_name ? $delivery_zone_name . ' ' : '') . '(First Order - Free)';
    }

    $total_amount = round($subtotal + $shipping_amount + $handling_fee_amount, 2);

    // Generate unique order number: TC + YYYYMMDD + 4-digit random
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
                echo json_encode([
                    'success' => false,
                    'message' => $available > 0
                        ? "{$item['product_name']} (Size: {$item['selected_size']}) only has {$available} left in stock."
                        : "{$item['product_name']} (Size: {$item['selected_size']}) is out of stock.",
                ]);
                exit;
            }
        } else {
            $prodStockStmt->bindValue(':pid', $item['product_id'], PDO::PARAM_INT);
            $prodStockStmt->execute();
            $stockRow = $prodStockStmt->fetch(PDO::FETCH_ASSOC);
            $prodStockStmt->closeCursor();
            $available = $stockRow ? (int)$stockRow['stock_quantity'] : 0;
            if ($available < $item['quantity']) {
                $conn->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => $available > 0
                        ? "{$item['product_name']} only has {$available} left in stock."
                        : "{$item['product_name']} is out of stock.",
                ]);
                exit;
            }
        }
    }

    // Insert order
    $oStmt = $conn->prepare(
        "INSERT INTO orders
            (order_number, customer_id, subtotal, tax_amount, shipping_amount, delivery_zone, handling_fee, total_amount,
             order_status, payment_status, payment_method,
             shipping_name, shipping_email, shipping_phone,
             shipping_address, shipping_city, shipping_state, shipping_pincode)
         VALUES
            (:order_number, :customer_id, :subtotal, :tax, :shipping_amount, :delivery_zone, :handling_fee, :total,
             'pending', 'pending', :payment_method,
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
    $oStmt->bindValue(':total',          $total_amount);
    $oStmt->bindValue(':payment_method', $payment_method);
    $oStmt->bindValue(':sname',          trim($shipping['full_name']));
    $oStmt->bindValue(':semail',         trim($shipping['email'] ?? ''));
    $oStmt->bindValue(':sphone',         trim($shipping['phone']));
    $oStmt->bindValue(':saddress',       trim($shipping['address']));
    $oStmt->bindValue(':scity',          trim($shipping['city']));
    $oStmt->bindValue(':sstate',         trim($shipping['state']));
    $oStmt->bindValue(':spincode',       trim($shipping['pincode']));
    $oStmt->execute();

    $order_id = (int)$conn->lastInsertId();

    // Insert order items
    $iStmt = $conn->prepare(
        "INSERT INTO order_items
            (order_id, shop_id, product_id, product_name, selected_size, selected_color, quantity, price, subtotal)
         VALUES
            (:order_id, :shop_id, :product_id, :product_name, :selected_size, :selected_color, :quantity, :price, :subtotal)"
    );
    $ocStmt = $conn->prepare(
        "UPDATE products SET orders_count = orders_count + :qty WHERE product_id = :pid"
    );
    foreach ($validated_items as $item) {
        $iStmt->bindValue(':order_id',       $order_id,                  PDO::PARAM_INT);
        $iStmt->bindValue(':shop_id',        $item['shop_id'],           PDO::PARAM_INT);
        $iStmt->bindValue(':product_id',     $item['product_id'],        PDO::PARAM_INT);
        $iStmt->bindValue(':product_name',   $item['product_name']);
        $iStmt->bindValue(':selected_size',  $item['selected_size']);
        $iStmt->bindValue(':selected_color', $item['selected_color']);
        $iStmt->bindValue(':quantity',      $item['quantity'],           PDO::PARAM_INT);
        $iStmt->bindValue(':price',         $item['price']);
        $iStmt->bindValue(':subtotal',      $item['subtotal']);
        $iStmt->execute();

        // Increment orders_count so Top Seller filter stays accurate
        $ocStmt->bindValue(':qty', $item['quantity'], PDO::PARAM_INT);
        $ocStmt->bindValue(':pid', $item['product_id'], PDO::PARAM_INT);
        $ocStmt->execute();

        // Decrement stock
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
            // Sync products.stock_quantity = sum of remaining size stocks
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

    // Save/update shipping address as the customer's default for next checkout
    try {
        $chkAddr = $conn->prepare("SELECT address_id FROM addresses WHERE user_id = :uid AND is_default = 1 LIMIT 1");
        $chkAddr->bindValue(':uid', $customer_id, PDO::PARAM_INT);
        $chkAddr->execute();
        $existingAddr = $chkAddr->fetch(PDO::FETCH_ASSOC);

        if ($existingAddr) {
            $upd = $conn->prepare(
                "UPDATE addresses SET full_name=:name, phone=:phone, address_line1=:addr,
                 city=:city, state=:state, pincode=:pincode, latitude=:lat, longitude=:lng
                 WHERE address_id=:aid"
            );
            $upd->bindValue(':aid', $existingAddr['address_id'], PDO::PARAM_INT);
        } else {
            $upd = $conn->prepare(
                "INSERT INTO addresses (user_id, address_type, full_name, phone, address_line1, city, state, pincode, latitude, longitude, is_default)
                 VALUES (:uid, 'home', :name, :phone, :addr, :city, :state, :pincode, :lat, :lng, 1)"
            );
            $upd->bindValue(':uid', $customer_id, PDO::PARAM_INT);
        }
        $upd->bindValue(':name',   trim($shipping['full_name']));
        $upd->bindValue(':phone',  trim($shipping['phone']));
        $upd->bindValue(':addr',   trim($shipping['address']));
        $upd->bindValue(':city',   trim($shipping['city']));
        $upd->bindValue(':state',  trim($shipping['state']));
        $upd->bindValue(':pincode',trim($shipping['pincode']));
        $upd->bindValue(':lat',    $cust_lat);
        $upd->bindValue(':lng',    $cust_lng);
        $upd->execute();
    } catch (Exception $addrErr) {
        error_log("Address save error: " . $addrErr->getMessage());
    }

    // Send order confirmation emails (non-critical — don't fail the response if email fails)
    try {
        require_once '../utils/EmailManager.php';
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

        // Customer email
        $cStmt = $conn->prepare("SELECT email, full_name FROM users WHERE user_id = :id LIMIT 1");
        $cStmt->bindValue(':id', $customer_id, PDO::PARAM_INT);
        $cStmt->execute();
        $customer_row = $cStmt->fetch(PDO::FETCH_ASSOC);
        if ($customer_row) {
            $emailManager->sendOrderConfirmationEmail($customer_row['email'], $customer_row['full_name'], $order_data);
        }

        // Admin email
        $aStmt = $conn->query("SELECT email FROM users WHERE user_type = 'admin' LIMIT 1");
        $admin_row = $aStmt->fetch(PDO::FETCH_ASSOC);
        if ($admin_row) {
            $emailManager->sendNewOrderAdminEmail($admin_row['email'], $order_data);
        }

        // Shop owner emails — each shop receives only their own items
        // ($items_by_shop was already computed above for the delivery fee calculation)
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

    echo json_encode([
        'success'            => true,
        'order_id'           => $order_id,
        'order_number'       => $order_number,
        'total'              => $total_amount,
        'shipping_amount'    => $shipping_amount,
        'delivery_breakdown' => $delivery_breakdown,
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
