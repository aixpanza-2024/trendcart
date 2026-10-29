<?php
/**
 * "Trending Now" — what shoppers are interested in this week.
 *
 * Most products are a single piece, so an all-time sales count can't say
 * what's popular (an item sells once and is gone). Instead each product
 * gets a score from the last 7 days:
 *
 *   views  x 1   (product page opened; once per visitor per day)
 *   saves  x 5   (added to a wishlist)
 *   orders x 10  (units ordered; cancelled / refunded orders don't count)
 *
 * Views need database/product_views_daily.sql; without that table the score
 * just leaves them out.
 */
class TrendingProducts
{
    const DAYS = 7;

    /** Count a product page view — once per visitor (session) per product per day. */
    public static function recordView(PDO $conn, int $productId): void
    {
        $today = date('Y-m-d');
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (($_SESSION['tc_viewed'][$productId] ?? '') === $today) return;
            // keep only today's entries so the session doesn't grow forever
            $_SESSION['tc_viewed'] = array_filter($_SESSION['tc_viewed'] ?? [], fn($d) => $d === $today);
            $_SESSION['tc_viewed'][$productId] = $today;
        }
        try {
            $conn->prepare("INSERT INTO product_views_daily (product_id, view_date, views) VALUES (:pid, CURDATE(), 1)
                            ON DUPLICATE KEY UPDATE views = views + 1")
                 ->execute([':pid' => $productId]);
            $conn->prepare("UPDATE products SET views_count = views_count + 1 WHERE product_id = :pid")
                 ->execute([':pid' => $productId]);
        } catch (\PDOException $e) { /* table not created yet — skip */ }
    }

    /** SELECT expression "... AS trend_score" for a products query aliased p. */
    public static function scoreSql(PDO $conn): string
    {
        $days = self::DAYS;
        $views = '0';
        try {
            $conn->query("SELECT 1 FROM product_views_daily LIMIT 0");
            $views = "COALESCE((SELECT SUM(v.views) FROM product_views_daily v
                                WHERE v.product_id = p.product_id
                                  AND v.view_date > CURDATE() - INTERVAL $days DAY), 0)";
        } catch (\PDOException $e) { /* table absent */ }

        return "($views
                 + 5 * (SELECT COUNT(*) FROM wishlist w
                        WHERE w.product_id = p.product_id
                          AND w.added_at > NOW() - INTERVAL $days DAY)
                 + 10 * COALESCE((SELECT SUM(oi.quantity) FROM order_items oi
                                  INNER JOIN orders o ON o.order_id = oi.order_id
                                  WHERE oi.product_id = p.product_id
                                    AND o.order_status NOT IN ('cancelled', 'refunded')
                                    AND o.order_date > NOW() - INTERVAL $days DAY), 0)
                ) AS trend_score";
    }
}
