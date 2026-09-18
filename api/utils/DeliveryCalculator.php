<?php
/**
 * Delivery fee calculation.
 *
 * Primary method: straight-line (Haversine) distance between the shop and
 * the customer's delivery address — used whenever both have coordinates.
 * Fallback: the original pincode/zone system — used whenever either side
 * is missing coordinates (e.g. shop hasn't set a map location yet, or the
 * client didn't send delivery coordinates).
 */

class DeliveryCalculator {

    const DISTANCE_THRESHOLD_KM = 20;

    /** Great-circle distance between two coordinates, in kilometers. */
    public static function haversineKm($lat1, $lon1, $lat2, $lon2) {
        $earthRadiusKm = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadiusKm * $c;
    }

    /** Reads the two admin-configurable fee amounts from platform_settings. */
    private static function getDistanceFees($conn) {
        $stmt = $conn->query(
            "SELECT setting_key, setting_value FROM platform_settings
             WHERE setting_key IN ('delivery_fee_under_20km', 'delivery_fee_above_20km')"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return [
            'under' => (float)($rows['delivery_fee_under_20km'] ?? 29),
            'above' => (float)($rows['delivery_fee_above_20km'] ?? 49),
        ];
    }

    /**
     * Fee for ONE shop → customer leg.
     * Returns null if either side has no coordinates (caller should fall
     * back to the zone-based lookup in that case).
     * @return array{fee:float,distance_km:float,method:string}|null
     */
    public static function distanceFee($conn, $shopLat, $shopLng, $customerLat, $customerLng) {
        if ($shopLat === null || $shopLng === null || $customerLat === null || $customerLng === null) {
            return null;
        }

        $distanceKm = self::haversineKm((float)$shopLat, (float)$shopLng, (float)$customerLat, (float)$customerLng);
        $fees = self::getDistanceFees($conn);
        $fee  = $distanceKm < self::DISTANCE_THRESHOLD_KM ? $fees['under'] : $fees['above'];

        return [
            'fee'         => $fee,
            'distance_km' => round($distanceKm, 2),
            'method'      => 'distance',
        ];
    }

    /**
     * Fallback: fee for ONE shop → customer leg using the pincode/zone system.
     * Same logic as the original single-shop calculation, just callable per shop.
     * @return array{fee:float,zone_name:string,method:string}
     */
    public static function zoneFee($conn, $shopId, $customerPincode) {
        // Customer's zone
        $stmt = $conn->prepare("
            SELECT z.zone_id, z.zone_name, z.delivery_fee, dp.area_name
            FROM delivery_pincodes dp
            INNER JOIN delivery_zones z ON dp.zone_id = z.zone_id
            WHERE dp.pincode = :pin AND z.is_active = 1
            LIMIT 1
        ");
        $stmt->bindValue(':pin', $customerPincode);
        $stmt->execute();
        $customerZone = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customerZone) {
            $defStmt = $conn->query("SELECT zone_id, zone_name, delivery_fee FROM delivery_zones WHERE is_default_zone = 1 AND is_active = 1 LIMIT 1");
            $defZone = $defStmt->fetch(PDO::FETCH_ASSOC) ?: ['zone_id' => 0, 'zone_name' => 'Standard Zone', 'delivery_fee' => 49.00];
            // zone_id = -1 so it never matches the shop's zone below — ensures default fee is charged
            $customerZone = array_merge($defZone, ['zone_id' => -1, 'area_name' => null]);
        }

        // Shop's zone (via its registered pincode)
        $shopStmt = $conn->prepare("
            SELECT z.zone_id
            FROM shops s
            INNER JOIN delivery_pincodes dp ON dp.pincode = s.shop_pincode
            INNER JOIN delivery_zones z    ON dp.zone_id  = z.zone_id
            WHERE s.shop_id = :sid AND z.is_active = 1
            LIMIT 1
        ");
        $shopStmt->bindValue(':sid', $shopId, PDO::PARAM_INT);
        $shopStmt->execute();
        $shopZone = $shopStmt->fetch(PDO::FETCH_ASSOC);

        $sameZone = ($shopZone && (int)$shopZone['zone_id'] === (int)$customerZone['zone_id']);
        $fee      = $sameZone ? 0.0 : (float)$customerZone['delivery_fee'];

        return [
            'fee'       => $fee,
            'zone_name' => $customerZone['zone_name'],
            'area_name' => $customerZone['area_name'] ?? null,
            'method'    => 'zone',
        ];
    }
}
?>
