<?php
/**
 * Customer - Place Order API
 * Requires customer session
 * POST: { shipping: {...}, items: [{id, quantity}], payment_method: "cod" }
 */

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] !== 'customer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login to place an order']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$customer_id = (int)$_SESSION['user_id'];
require_once '../config/database.php';

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
    $database = new Database();
    $conn     = $database->getConnection();

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
        $product_id    = (int)($item['id'] ?? 0);
        $quantity      = max(1, (int)($item['quantity'] ?? 1));
        $selected_size = isset($item['size']) && $item['size'] !== null ? trim($item['size']) : null;

        if ($product_id <= 0) continue;

        $pStmt->bindValue(':pid', $product_id, PDO::PARAM_INT);
        $pStmt->execute();
        $product = $pStmt->fetch(PDO::FETCH_ASSOC);

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
            'selected_size' => $selected_size,
            'quantity'      => $quantity,
            'price'         => $effective_price,
            'subtotal'      => $line_total,
        ];
    }

    if (empty($validated_items)) {
        echo json_encode(['success' => false, 'message' => 'No valid products found in cart']);
        exit;
    }

    $tax_amount      = round($subtotal * 0.18, 2);
    $shipping_amount = 0.00; // Free shipping always
    $total_amount    = round($subtotal + $tax_amount + $shipping_amount, 2);

    // Generate unique order number: TC + YYYYMMDD + 4-digit random
    do {
        $order_number = 'TC' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $chk = $conn->prepare("SELECT order_id FROM orders WHERE order_number = :on LIMIT 1");
        $chk->bindValue(':on', $order_number);
        $chk->execute();
    } while ($chk->fetch());

    $conn->beginTransaction();

    // Insert order
    $oStmt = $conn->prepare(
        "INSERT INTO orders
            (order_number, customer_id, subtotal, tax_amount, shipping_amount, total_amount,
             order_status, payment_status, payment_method,
             shipping_name, shipping_email, shipping_phone,
             shipping_address, shipping_city, shipping_state, shipping_pincode)
         VALUES
            (:order_number, :customer_id, :subtotal, :tax, :shipping_amount, :total,
             'pending', 'pending', :payment_method,
             :sname, :semail, :sphone,
             :saddress, :scity, :sstate, :spincode)"
    );
    $oStmt->bindValue(':order_number',   $order_number);
    $oStmt->bindValue(':customer_id',    $customer_id,                    PDO::PARAM_INT);
    $oStmt->bindValue(':subtotal',       $subtotal);
    $oStmt->bindValue(':tax',            $tax_amount);
    $oStmt->bindValue(':shipping_amount',$shipping_amount);
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
            (order_id, shop_id, product_id, product_name, selected_size, quantity, price, subtotal)
         VALUES
            (:order_id, :shop_id, :product_id, :product_name, :selected_size, :quantity, :price, :subtotal)"
    );
    $ocStmt = $conn->prepare(
        "UPDATE products SET orders_count = orders_count + :qty WHERE product_id = :pid"
    );
    foreach ($validated_items as $item) {
        $iStmt->bindValue(':order_id',      $order_id,                  PDO::PARAM_INT);
        $iStmt->bindValue(':shop_id',       $item['shop_id'],           PDO::PARAM_INT);
        $iStmt->bindValue(':product_id',    $item['product_id'],        PDO::PARAM_INT);
        $iStmt->bindValue(':product_name',  $item['product_name']);
        $iStmt->bindValue(':selected_size', $item['selected_size']);
        $iStmt->bindValue(':quantity',      $item['quantity'],           PDO::PARAM_INT);
        $iStmt->bindValue(':price',         $item['price']);
        $iStmt->bindValue(':subtotal',      $item['subtotal']);
        $iStmt->execute();

        // Increment orders_count so Top Seller filter stays accurate
        $ocStmt->bindValue(':qty', $item['quantity'], PDO::PARAM_INT);
        $ocStmt->bindValue(':pid', $item['product_id'], PDO::PARAM_INT);
        $ocStmt->execute();
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
                 city=:city, state=:state, pincode=:pincode
                 WHERE address_id=:aid"
            );
            $upd->bindValue(':aid', $existingAddr['address_id'], PDO::PARAM_INT);
        } else {
            $upd = $conn->prepare(
                "INSERT INTO addresses (user_id, address_type, full_name, phone, address_line1, city, state, pincode, is_default)
                 VALUES (:uid, 'home', :name, :phone, :addr, :city, :state, :pincode, 1)"
            );
            $upd->bindValue(':uid', $customer_id, PDO::PARAM_INT);
        }
        $upd->bindValue(':name',   trim($shipping['full_name']));
        $upd->bindValue(':phone',  trim($shipping['phone']));
        $upd->bindValue(':addr',   trim($shipping['address']));
        $upd->bindValue(':city',   trim($shipping['city']));
        $upd->bindValue(':state',  trim($shipping['state']));
        $upd->bindValue(':pincode',trim($shipping['pincode']));
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
        $items_by_shop = [];
        foreach ($validated_items as $item) {
            $items_by_shop[$item['shop_id']][] = $item;
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

    echo json_encode([
        'success'      => true,
        'order_id'     => $order_id,
        'order_number' => $order_number,
        'total'        => $total_amount,
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
