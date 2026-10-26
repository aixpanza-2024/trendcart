<?php
/**
 * The customer's chosen location, for "X km away" on public pages.
 *
 * Taken from the request (?lat=..&lng=.., sent by the navbar location
 * chooser, which also covers guests) or, failing that, the location saved
 * on the logged-in customer's profile. Returns null when neither is known.
 */
require_once __DIR__ . '/DeliveryCalculator.php';
require_once __DIR__ . '/TokenAuth.php';

class CustomerLocation {
    /** @return array{lat:float,lng:float}|null */
    public static function current(PDO $conn): ?array {
        $lat = $_GET['lat'] ?? null;
        $lng = $_GET['lng'] ?? null;
        if (is_numeric($lat) && is_numeric($lng) && abs((float)$lat) <= 90 && abs((float)$lng) <= 180) {
            return ['lat' => (float)$lat, 'lng' => (float)$lng];
        }

        $user = TokenAuth::getUser($conn);
        if ($user && ($user['user_type'] ?? '') === 'customer') {
            $q = $conn->prepare("SELECT latitude, longitude FROM customer_profiles WHERE user_id = :uid AND latitude IS NOT NULL LIMIT 1");
            $q->execute([':uid' => $user['user_id']]);
            if ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                return ['lat' => (float)$row['latitude'], 'lng' => (float)$row['longitude']];
            }
        }
        return null;
    }

    /** Straight-line km from the customer to a shop, or null when either location is unknown. */
    public static function kmTo(?array $from, $shopLat, $shopLng): ?float {
        if (!$from || $shopLat === null || $shopLng === null || $shopLat === '' || $shopLng === '') {
            return null;
        }
        return round(DeliveryCalculator::haversineKm($from['lat'], $from['lng'], (float)$shopLat, (float)$shopLng), 1);
    }
}
