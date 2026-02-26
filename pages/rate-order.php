<?php
/**
 * Rate Order Page
 * Shows all delivered items for an order.
 *   - Already rated: display stars + Delete button
 *   - Not yet rated: CSS-only star selector form
 * Forms POST to this page directly (PHP handles everything, minimal JS).
 */

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] !== 'customer') {
    $redirect = urlencode('rate-order.php?order_id=' . (int)($_GET['order_id'] ?? 0));
    header('Location: login.html?redirect=' . $redirect);
    exit;
}

$customer_id = (int)$_SESSION['user_id'];

require_once '../api/config/database.php';

$database = new Database();
$conn     = $database->getConnection();

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

// ── Verify order belongs to this customer ─────────────────────────────
$orderStmt = $conn->prepare("
    SELECT order_id, order_number FROM orders
    WHERE order_id = :oid AND customer_id = :cid
    LIMIT 1
");
$orderStmt->bindValue(':oid', $order_id,    PDO::PARAM_INT);
$orderStmt->bindValue(':cid', $customer_id, PDO::PARAM_INT);
$orderStmt->execute();
$order = $orderStmt->fetch();

if (!$order) {
    header('Location: orders.html');
    exit;
}

// ── Helper: recalculate product + shop ratings after a review change ──
function recalcRatings(PDO $conn, int $product_id): void {
    // Product
    $conn->prepare("
        UPDATE products
        SET rating_average = COALESCE(
                (SELECT AVG(rating) FROM product_reviews
                 WHERE product_id = :pid AND is_approved = 1), 0),
            total_ratings  = (SELECT COUNT(*) FROM product_reviews
                              WHERE product_id = :pid2 AND is_approved = 1)
        WHERE product_id = :pid3
    ")->execute([':pid' => $product_id, ':pid2' => $product_id, ':pid3' => $product_id]);

    // Shop
    $shopRow = $conn->prepare("SELECT shop_id FROM products WHERE product_id = :pid LIMIT 1");
    $shopRow->execute([':pid' => $product_id]);
    $shop = $shopRow->fetch();
    if (!$shop) return;

    $shop_id = (int)$shop['shop_id'];
    $conn->prepare("
        UPDATE shops
        SET rating_average = COALESCE(
                (SELECT AVG(pr.rating)
                 FROM product_reviews pr
                 INNER JOIN products p ON pr.product_id = p.product_id
                 WHERE p.shop_id = :sid AND pr.is_approved = 1), 0),
            total_ratings  = (
                SELECT COUNT(*)
                FROM product_reviews pr
                INNER JOIN products p ON pr.product_id = p.product_id
                WHERE p.shop_id = :sid2 AND pr.is_approved = 1)
        WHERE shop_id = :sid3
    ")->execute([':sid' => $shop_id, ':sid2' => $shop_id, ':sid3' => $shop_id]);
}

// ── Handle DELETE a review ────────────────────────────────────────────
$flash = null; // ['type'=>'success|danger', 'msg'=>'...']

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $review_id = (int)($_POST['review_id'] ?? 0);

    // Fetch review — must belong to this customer
    $revStmt = $conn->prepare("
        SELECT pr.review_id, pr.product_id
        FROM product_reviews pr
        WHERE pr.review_id = :rid AND pr.user_id = :uid
        LIMIT 1
    ");
    $revStmt->bindValue(':rid', $review_id,   PDO::PARAM_INT);
    $revStmt->bindValue(':uid', $customer_id, PDO::PARAM_INT);
    $revStmt->execute();
    $rev = $revStmt->fetch();

    if ($rev) {
        $conn->prepare("DELETE FROM product_reviews WHERE review_id = :rid AND user_id = :uid")
             ->execute([':rid' => (int)$rev['review_id'], ':uid' => $customer_id]);

        recalcRatings($conn, (int)$rev['product_id']);
        $flash = ['type' => 'success', 'msg' => 'Your rating has been removed.'];
    } else {
        $flash = ['type' => 'danger', 'msg' => 'Rating not found or already removed.'];
    }
}

// ── Handle SUBMIT new ratings ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rate') {
    $submitted = 0;

    foreach ((array)($_POST['items'] ?? []) as $order_item_id => $fields) {
        $order_item_id = (int)$order_item_id;
        $rating        = (int)($fields['rating']      ?? 0);
        $review_text   = trim($fields['review_text']  ?? '');

        if ($rating < 1 || $rating > 5) continue;

        // Verify item: delivered, belongs to this customer & order
        $verifyStmt = $conn->prepare("
            SELECT oi.product_id, oi.order_id
            FROM order_items oi
            INNER JOIN orders o ON oi.order_id = o.order_id
            WHERE oi.order_item_id = :iid
              AND oi.item_status   = 'delivered'
              AND o.customer_id    = :cid
              AND o.order_id       = :oid
            LIMIT 1
        ");
        $verifyStmt->bindValue(':iid', $order_item_id, PDO::PARAM_INT);
        $verifyStmt->bindValue(':cid', $customer_id,   PDO::PARAM_INT);
        $verifyStmt->bindValue(':oid', $order_id,      PDO::PARAM_INT);
        $verifyStmt->execute();
        $item = $verifyStmt->fetch();
        if (!$item) continue;

        $product_id    = (int)$item['product_id'];
        $item_order_id = (int)$item['order_id'];

        // Skip duplicates
        $dupStmt = $conn->prepare("
            SELECT review_id FROM product_reviews
            WHERE product_id = :pid AND user_id = :uid AND order_id = :oid
            LIMIT 1
        ");
        $dupStmt->bindValue(':pid', $product_id,    PDO::PARAM_INT);
        $dupStmt->bindValue(':uid', $customer_id,   PDO::PARAM_INT);
        $dupStmt->bindValue(':oid', $item_order_id, PDO::PARAM_INT);
        $dupStmt->execute();
        if ($dupStmt->fetch()) continue;

        $insStmt = $conn->prepare("
            INSERT INTO product_reviews
                (product_id, user_id, order_id, rating, review_text, is_verified_purchase, is_approved)
            VALUES (:pid, :uid, :oid, :rating, :text, 1, 1)
        ");
        $insStmt->bindValue(':pid',    $product_id,    PDO::PARAM_INT);
        $insStmt->bindValue(':uid',    $customer_id,   PDO::PARAM_INT);
        $insStmt->bindValue(':oid',    $item_order_id, PDO::PARAM_INT);
        $insStmt->bindValue(':rating', $rating,        PDO::PARAM_INT);
        $insStmt->bindValue(':text',   $review_text ?: null,
                            $review_text ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $insStmt->execute();
        $submitted++;
    }

    header('Location: orders.html?rated=' . $submitted);
    exit;
}

// ── Fetch ALL delivered items + existing review for each ──────────────
$itemsStmt = $conn->prepare("
    SELECT oi.order_item_id, oi.product_id, p.product_name, s.shop_name,
           (SELECT image_url FROM product_images
            WHERE product_id = p.product_id
            ORDER BY is_primary DESC, display_order ASC
            LIMIT 1) AS product_image,
           pr.review_id, pr.rating AS review_rating, pr.review_text AS review_comment
    FROM order_items oi
    INNER JOIN products p ON oi.product_id = p.product_id
    INNER JOIN shops   s ON oi.shop_id     = s.shop_id
    LEFT JOIN product_reviews pr
           ON pr.product_id = oi.product_id
          AND pr.user_id    = :cid
          AND pr.order_id   = oi.order_id
    WHERE oi.order_id    = :oid
      AND oi.item_status = 'delivered'
    ORDER BY oi.order_item_id ASC
");
$itemsStmt->bindValue(':oid', $order_id,    PDO::PARAM_INT);
$itemsStmt->bindValue(':cid', $customer_id, PDO::PARAM_INT);
$itemsStmt->execute();
$allItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// If the order has no delivered items at all, redirect away
if (!$allItems) {
    header('Location: orders.html');
    exit;
}

$ratedItems   = array_filter($allItems, fn($i) => $i['review_id'] !== null);
$unratedItems = array_filter($allItems, fn($i) => $i['review_id'] === null);

$LABELS = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
$STARS  = ['', '★', '★★', '★★★', '★★★★', '★★★★★'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Ratings — TrenCart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container-fluid px-4">
            <a class="navbar-brand" href="../index.html">
                <img src="../assets/images/trencartlogo.png" alt="TrenCart" style="height:36px;">
            </a>
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link" href="orders.html">
                        <i class="fas fa-arrow-left me-1"></i> Back to Orders
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Banner -->
    <section class="page-banner">
        <div class="container-xl px-4">
            <div class="pb-eyebrow">Order <?= htmlspecialchars($order['order_number']) ?></div>
            <h1 class="pb-title">Product Ratings</h1>
            <p class="pb-sub">Rate delivered items or remove a rating you've already given.</p>
        </div>
    </section>

    <div class="page-body">
        <div class="container" style="max-width:680px;">

            <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
                <?= htmlspecialchars($flash['msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- ── Already Rated ─────────────────────────────────────── -->
            <?php if ($ratedItems): ?>
            <h6 class="fw-bold mb-3 text-grey">
                <i class="fas fa-star text-warning me-1"></i> Already Rated
            </h6>

            <?php foreach ($ratedItems as $item):
                $img = $item['product_image']
                    ? '../' . ltrim($item['product_image'], '/')
                    : 'https://placehold.co/72x72/1a1a1a/ffffff?text=Item';
                $r   = (int)$item['review_rating'];
            ?>
            <div class="bg-light-grey rounded p-4 mb-3 d-flex align-items-start gap-3">
                <img src="<?= htmlspecialchars($img) ?>"
                     alt="<?= htmlspecialchars($item['product_name']) ?>"
                     style="width:68px;height:68px;object-fit:cover;border-radius:10px;border:1px solid var(--border-color);flex-shrink:0;"
                     onerror="this.src='https://placehold.co/68x68/1a1a1a/ffffff?text=Item'">

                <div class="flex-grow-1">
                    <div class="fw-semibold"><?= htmlspecialchars($item['product_name']) ?></div>
                    <div class="text-grey small mb-2">
                        <i class="fas fa-store me-1"></i><?= htmlspecialchars($item['shop_name']) ?>
                    </div>
                    <!-- Static star display -->
                    <div class="mb-1">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fa<?= $i <= $r ? 's' : 'r' ?> fa-star text-warning"
                               style="font-size:1.15rem;"></i>
                        <?php endfor; ?>
                        <span class="ms-1 text-grey small"><?= $LABELS[$r] ?></span>
                    </div>
                    <?php if ($item['review_comment']): ?>
                    <p class="text-grey small mb-2"><?= htmlspecialchars($item['review_comment']) ?></p>
                    <?php endif; ?>

                    <!-- Delete form -->
                    <form method="POST"
                          action="rate-order.php?order_id=<?= $order_id ?>"
                          onsubmit="return confirm('Remove your rating for this product?')">
                        <input type="hidden" name="action"    value="delete">
                        <input type="hidden" name="review_id" value="<?= (int)$item['review_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-trash-alt me-1"></i> Remove Rating
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if ($unratedItems): ?>
            <hr class="my-4">
            <?php endif; ?>
            <?php endif; ?>

            <!-- ── Not Yet Rated ─────────────────────────────────────── -->
            <?php if ($unratedItems): ?>
            <h6 class="fw-bold mb-3 text-grey">
                <i class="far fa-star text-warning me-1"></i> Add Your Rating
            </h6>

            <form method="POST" action="rate-order.php?order_id=<?= $order_id ?>">
                <input type="hidden" name="action" value="rate">

                <?php foreach ($unratedItems as $item):
                    $img = $item['product_image']
                        ? '../' . ltrim($item['product_image'], '/')
                        : 'https://placehold.co/72x72/1a1a1a/ffffff?text=Item';
                    $iid = (int)$item['order_item_id'];
                ?>
                <div class="bg-light-grey rounded p-4 mb-3">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <img src="<?= htmlspecialchars($img) ?>"
                             alt="<?= htmlspecialchars($item['product_name']) ?>"
                             style="width:68px;height:68px;object-fit:cover;border-radius:10px;border:1px solid var(--border-color);"
                             onerror="this.src='https://placehold.co/68x68/1a1a1a/ffffff?text=Item'">
                        <div>
                            <div class="fw-semibold"><?= htmlspecialchars($item['product_name']) ?></div>
                            <div class="text-grey small">
                                <i class="fas fa-store me-1"></i><?= htmlspecialchars($item['shop_name']) ?>
                            </div>
                        </div>
                    </div>

                    <!-- CSS-only star selector -->
                    <div class="mb-1">
                        <label class="fw-semibold small d-block mb-2">
                            Your Rating <span class="text-danger">*</span>
                        </label>
                        <div class="star-rating-group"
                             role="radiogroup"
                             aria-label="Rating for <?= htmlspecialchars($item['product_name']) ?>">
                            <?php for ($v = 5; $v >= 1; $v--): ?>
                            <input type="radio"
                                   name="items[<?= $iid ?>][rating]"
                                   id="star_<?= $iid ?>_<?= $v ?>"
                                   value="<?= $v ?>"
                                   class="sr-star-input">
                            <label for="star_<?= $iid ?>_<?= $v ?>"
                                   class="sr-star-label"
                                   title="<?= $LABELS[$v] ?>">
                                <i class="fas fa-star"></i>
                            </label>
                            <?php endfor; ?>
                        </div>
                        <div class="d-flex mt-1">
                            <small class="text-grey" style="font-size:11px;">Poor</small>
                            <small class="text-grey ms-auto" style="font-size:11px;">Excellent</small>
                        </div>
                    </div>

                    <!-- Optional comment -->
                    <div class="mt-3">
                        <label class="fw-semibold small mb-1 d-block"
                               for="review_<?= $iid ?>">
                            Comment <span class="text-grey fw-normal">(optional)</span>
                        </label>
                        <textarea class="form-control form-control-sm"
                                  id="review_<?= $iid ?>"
                                  name="items[<?= $iid ?>][review_text]"
                                  rows="2"
                                  placeholder="Share your experience with this product…"
                                  maxlength="500"></textarea>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="d-flex gap-3 mt-2 mb-5">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="fas fa-paper-plane me-2"></i>Submit Rating<?= count($unratedItems) > 1 ? 's' : '' ?>
                    </button>
                    <a href="orders.html" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>

            <?php else: ?>
            <!-- All items rated -->
            <div class="text-center py-4 text-grey">
                <i class="fas fa-check-circle fa-2x text-success mb-2 d-block"></i>
                All delivered items have been rated.
                <div class="mt-3">
                    <a href="orders.html" class="btn btn-outline-dark btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Back to Orders
                    </a>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Footer (minimal) -->
    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; 2024 TrenCart. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
