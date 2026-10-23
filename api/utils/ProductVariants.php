<?php
/**
 * Product variants — one row per colour + size, each with its own stock and SKU.
 *
 * product_variants is the source of truth for stock. product_sizes,
 * product_colors and products.stock_quantity/size/color are summaries rebuilt
 * by syncSummaries() after every change, so the listing pages and app
 * endpoints that read them keep working unchanged.
 *
 * Colour '' means "not split by colour": products converted from the old
 * system keep a plain colour list (product_colors) and per-size stock until
 * the shop splits the stock per colour in Edit Product.
 */
class ProductVariants {

    /** All variants of a product, in display order. */
    public static function forProduct(PDO $conn, int $product_id, bool $lock = false): array {
        $stmt = $conn->prepare(
            "SELECT variant_id, product_id, shop_id, color_name, size_label, sku,
                    stock_quantity, price_adjustment, display_order
             FROM product_variants
             WHERE product_id = :pid
             ORDER BY display_order ASC, variant_id ASC" . ($lock ? " FOR UPDATE" : "")
        );
        $stmt->execute([':pid' => $product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Variant rows from the shop's Add/Edit Product form.
     * variants_json: [{color_name, size_label, stock_quantity, sku}]
     * sizes_json:    [{size_label, price_adjustment}]  (price is set per size)
     * Older clients without variants_json: one variant per size (sizes_json
     * stock), or a single variant holding stock_quantity.
     * Throws InvalidArgumentException with a message for the shop.
     */
    public static function fromForm(array $post): array {
        $sizes = json_decode($post['sizes_json'] ?? '[]', true);
        if (!is_array($sizes)) $sizes = [];
        $adjBySize = [];
        foreach ($sizes as $s) {
            $label = self::clean($s['size_label'] ?? '', 30);
            if ($label !== '') $adjBySize[mb_strtolower($label)] = round((float)($s['price_adjustment'] ?? 0), 2);
        }

        $raw = json_decode($post['variants_json'] ?? 'null', true);
        if (!is_array($raw)) {
            $raw = [];
            foreach ($sizes as $s) {
                $raw[] = ['color_name' => '', 'size_label' => $s['size_label'] ?? '', 'stock_quantity' => $s['stock_quantity'] ?? 0];
            }
        }
        if (!$raw) {
            $raw = [['color_name' => '', 'size_label' => '', 'stock_quantity' => $post['stock_quantity'] ?? 0]];
        }

        $rows = [];
        $seenCombo = [];
        $seenSku = [];
        foreach (array_values($raw) as $i => $v) {
            $color = self::clean($v['color_name'] ?? '', 50);
            $size  = self::clean($v['size_label'] ?? '', 30);
            $sku   = self::clean($v['sku'] ?? '', 50);
            $key   = mb_strtolower($color) . '|' . mb_strtolower($size);
            if (isset($seenCombo[$key])) {
                throw new InvalidArgumentException('The same colour and size is listed twice: ' . trim("$color $size"));
            }
            $seenCombo[$key] = true;
            if ($sku !== '') {
                if (isset($seenSku[mb_strtolower($sku)])) {
                    throw new InvalidArgumentException("SKU \"$sku\" is used for two variants of this product. Each colour/size needs its own SKU.");
                }
                $seenSku[mb_strtolower($sku)] = true;
            }
            $rows[] = [
                'color_name'       => $color,
                'size_label'       => $size,
                'sku'              => $sku !== '' ? $sku : null,
                'stock_quantity'   => max(0, (int)($v['stock_quantity'] ?? 0)),
                'price_adjustment' => $adjBySize[mb_strtolower($size)] ?? 0.0,
                'display_order'    => $i,
            ];
        }
        return $rows;
    }

    /**
     * SKUs are unique per shop across both variant SKUs and product codes,
     * among products that still exist (a deleted product's SKU is free again).
     */
    public static function checkSkus(PDO $conn, int $shop_id, ?int $product_id, ?string $product_code, array $rows): void {
        $pid = $product_id ?? 0;
        $usedBy = function (string $sku) use ($conn, $shop_id, $pid) {
            $q = $conn->prepare(
                "SELECT p.product_name FROM product_variants v
                 INNER JOIN products p ON p.product_id = v.product_id
                 WHERE v.shop_id = :sid AND v.sku = :sku AND v.product_id <> :pid
                 UNION
                 SELECT product_name FROM products
                 WHERE shop_id = :sid2 AND product_code = :sku2 AND product_id <> :pid2
                 LIMIT 1"
            );
            $q->execute([':sid' => $shop_id, ':sku' => $sku, ':pid' => $pid, ':sid2' => $shop_id, ':sku2' => $sku, ':pid2' => $pid]);
            return $q->fetchColumn();
        };

        foreach ($rows as $r) {
            if ($r['sku'] === null) continue;
            if ($product_code !== null && mb_strtolower($r['sku']) === mb_strtolower($product_code) && count($rows) > 1) {
                throw new InvalidArgumentException("SKU \"{$r['sku']}\" is the same as the product code. Give each colour/size its own SKU, or clear the product code.");
            }
            if ($name = $usedBy($r['sku'])) {
                throw new InvalidArgumentException("SKU \"{$r['sku']}\" is already used by \"$name\" in your shop. Use a different SKU, or delete that product first.");
            }
        }
        if ($product_code !== null && ($name = $usedBy($product_code))) {
            throw new InvalidArgumentException("SKU \"$product_code\" is already used by \"$name\" in your shop. Use a different SKU, or delete that product first.");
        }
    }

    /**
     * Save the form's variants. Existing rows are updated in place (same
     * variant_id, so pending orders can still restock them), new ones added,
     * removed ones deleted. $legacyColors is the plain colour list, used only
     * while the product isn't split by colour.
     */
    public static function save(PDO $conn, int $product_id, int $shop_id, array $rows, array $legacyColors = []): void {
        $existing = [];
        foreach (self::forProduct($conn, $product_id) as $v) {
            $existing[mb_strtolower($v['color_name']) . '|' . mb_strtolower($v['size_label'])] = (int)$v['variant_id'];
        }

        // Clear SKUs first so swapping two SKUs between rows doesn't trip the unique key
        $conn->prepare("UPDATE product_variants SET sku = NULL WHERE product_id = :pid")->execute([':pid' => $product_id]);

        $upd = $conn->prepare(
            "UPDATE product_variants SET color_name = :color, size_label = :size, sku = :sku,
                    stock_quantity = :stock, price_adjustment = :adj, display_order = :ord, shop_id = :sid
             WHERE variant_id = :vid"
        );
        $ins = $conn->prepare(
            "INSERT INTO product_variants (product_id, shop_id, color_name, size_label, sku, stock_quantity, price_adjustment, display_order)
             VALUES (:pid, :sid, :color, :size, :sku, :stock, :adj, :ord)"
        );

        $keep = [];
        foreach ($rows as $r) {
            $params = [
                ':color' => $r['color_name'], ':size' => $r['size_label'], ':sku' => $r['sku'],
                ':stock' => $r['stock_quantity'], ':adj' => $r['price_adjustment'], ':ord' => $r['display_order'],
                ':sid'   => $shop_id,
            ];
            $key = mb_strtolower($r['color_name']) . '|' . mb_strtolower($r['size_label']);
            if (isset($existing[$key])) {
                $upd->execute($params + [':vid' => $existing[$key]]);
                $keep[] = $existing[$key];
            } else {
                $ins->execute($params + [':pid' => $product_id]);
                $keep[] = (int)$conn->lastInsertId();
            }
        }

        $gone = array_diff(array_values($existing), $keep);
        if ($gone) {
            $conn->prepare("DELETE FROM product_variants WHERE variant_id IN (" . implode(',', array_map('intval', $gone)) . ")")->execute();
        }

        self::syncSummaries($conn, $product_id, $legacyColors);
    }

    /**
     * Rebuild product_sizes / product_colors / products.stock_quantity etc.
     * from the variants. $legacyColors replaces the plain colour list when
     * the product isn't split by colour (null = leave that list alone).
     */
    public static function syncSummaries(PDO $conn, int $product_id, ?array $legacyColors = null): void {
        $variants = self::forProduct($conn, $product_id);

        $sizes = [];
        $colors = [];
        $total = 0;
        foreach ($variants as $v) {
            $total += (int)$v['stock_quantity'];
            if ($v['size_label'] !== '') {
                $k = mb_strtolower($v['size_label']);
                if (!isset($sizes[$k])) $sizes[$k] = ['label' => $v['size_label'], 'stock' => 0, 'adj' => $v['price_adjustment']];
                $sizes[$k]['stock'] += (int)$v['stock_quantity'];
            }
            if ($v['color_name'] !== '') $colors[mb_strtolower($v['color_name'])] = $v['color_name'];
        }

        $conn->prepare("DELETE FROM product_sizes WHERE product_id = :pid")->execute([':pid' => $product_id]);
        $insSize = $conn->prepare(
            "INSERT INTO product_sizes (product_id, size_label, stock_quantity, price_adjustment, display_order)
             VALUES (:pid, :label, :stock, :adj, :ord)"
        );
        $i = 0;
        foreach ($sizes as $s) {
            $insSize->execute([':pid' => $product_id, ':label' => $s['label'], ':stock' => $s['stock'], ':adj' => $s['adj'], ':ord' => $i++]);
        }

        $colorList = $colors ? array_values($colors) : $legacyColors;
        if ($colorList !== null) {
            $conn->prepare("DELETE FROM product_colors WHERE product_id = :pid")->execute([':pid' => $product_id]);
            $insColor = $conn->prepare("INSERT INTO product_colors (product_id, color_name, display_order) VALUES (:pid, :name, :ord)");
            foreach (array_values($colorList) as $i => $name) {
                $insColor->execute([':pid' => $product_id, ':name' => $name, ':ord' => $i]);
            }
        } else {
            $q = $conn->prepare("SELECT color_name FROM product_colors WHERE product_id = :pid ORDER BY display_order, color_id");
            $q->execute([':pid' => $product_id]);
            $colorList = $q->fetchAll(PDO::FETCH_COLUMN);
        }

        $conn->prepare("UPDATE products SET stock_quantity = :stock, size = :size, color = :color WHERE product_id = :pid")->execute([
            ':stock' => $total,
            ':size'  => mb_substr(implode(',', array_column($sizes, 'label')), 0, 50),
            ':color' => mb_substr(implode(',', $colorList), 0, 500),
            ':pid'   => $product_id,
        ]);
    }

    /**
     * A product with no variant rows (e.g. created before add_product_variants.sql
     * or by a path that bypasses save()) gets them now, the same way the
     * migration converts: one per old size row, or one holding the stock.
     */
    public static function ensure(PDO $conn, int $product_id): void {
        $q = $conn->prepare("SELECT COUNT(*) FROM product_variants WHERE product_id = :pid");
        $q->execute([':pid' => $product_id]);
        if ((int)$q->fetchColumn() > 0) return;

        $conn->prepare(
            "INSERT IGNORE INTO product_variants (product_id, shop_id, color_name, size_label, stock_quantity, price_adjustment, display_order)
             SELECT ps.product_id, p.shop_id, '', ps.size_label, ps.stock_quantity, ps.price_adjustment, ps.display_order
             FROM product_sizes ps INNER JOIN products p ON p.product_id = ps.product_id
             WHERE ps.product_id = :pid"
        )->execute([':pid' => $product_id]);
        $conn->prepare(
            "INSERT IGNORE INTO product_variants (product_id, shop_id, color_name, size_label, stock_quantity)
             SELECT product_id, shop_id, '', '', GREATEST(0, COALESCE(stock_quantity, 0)) FROM products
             WHERE product_id = :pid AND NOT EXISTS (SELECT 1 FROM product_variants WHERE product_id = :pid2)"
        )->execute([':pid' => $product_id, ':pid2' => $product_id]);
    }

    /**
     * The variant a customer picked (cart sends size + colour labels).
     * A product not split by colour matches on size alone — the colour is
     * then just the label the customer chose.
     */
    public static function resolve(PDO $conn, int $product_id, ?string $size, ?string $color, bool $lock = false): ?array {
        $size  = mb_strtolower(trim((string)$size));
        $color = mb_strtolower(trim((string)$color));
        $variants = self::forProduct($conn, $product_id, $lock);

        foreach ($variants as $v) {
            if (mb_strtolower($v['size_label']) === $size && mb_strtolower($v['color_name']) === $color) return $v;
        }
        foreach ($variants as $v) {
            if ($v['color_name'] === '' && mb_strtolower($v['size_label']) === $size) return $v;
        }
        return null;
    }

    /** Add (or with a negative delta, take) stock on one variant and refresh the summaries. */
    public static function changeStock(PDO $conn, int $variant_id, int $delta): void {
        $conn->prepare("UPDATE product_variants SET stock_quantity = GREATEST(0, stock_quantity + :d) WHERE variant_id = :vid")
             ->execute([':d' => $delta, ':vid' => $variant_id]);
        $q = $conn->prepare("SELECT product_id FROM product_variants WHERE variant_id = :vid");
        $q->execute([':vid' => $variant_id]);
        if ($pid = $q->fetchColumn()) self::syncSummaries($conn, (int)$pid);
    }

    /**
     * Put one cancelled order line's quantity back in stock. Lines from before
     * variants existed have no variant_id and are matched by size/colour; a
     * product deleted since has nothing to restock.
     * $item: product_id, variant_id, selected_size, selected_color, quantity
     */
    public static function restockOrderItem(PDO $conn, array $item): void {
        $variant_id = $item['variant_id'] !== null ? (int)$item['variant_id'] : null;
        if ($variant_id === null && $item['product_id'] !== null) {
            self::ensure($conn, (int)$item['product_id']);
            $v = self::resolve($conn, (int)$item['product_id'], $item['selected_size'], $item['selected_color']);
            $variant_id = $v ? (int)$v['variant_id'] : null;
        }
        if ($variant_id !== null) {
            self::changeStock($conn, $variant_id, (int)$item['quantity']);
        }
    }

    /**
     * Customer-facing shape for the product page: per colour, which sizes
     * exist and their stock. Empty when the product isn't split by colour.
     */
    public static function optionsForPage(array $variants): array {
        $byColor = [];
        $colorStock = [];
        foreach ($variants as $v) {
            if ($v['color_name'] === '') continue;
            $c = $v['color_name'];
            $byColor[$c] = $byColor[$c] ?? [];
            $colorStock[$c] = ($colorStock[$c] ?? 0) + (int)$v['stock_quantity'];
            if ($v['size_label'] !== '') {
                $byColor[$c][] = [
                    'size_label'       => $v['size_label'],
                    'stock_quantity'   => (int)$v['stock_quantity'],
                    'price_adjustment' => $v['price_adjustment'],
                ];
            }
        }
        return ['sizes_by_color' => $byColor ?: null, 'color_stock' => $colorStock ?: null];
    }

    private static function clean($value, int $max): string {
        return mb_substr(trim(preg_replace('/\s+/', ' ', (string)$value)), 0, $max);
    }
}
