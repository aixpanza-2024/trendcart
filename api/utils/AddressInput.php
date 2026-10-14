<?php
/**
 * Shared input cleanup for address endpoints.
 */
class AddressInput {
    /**
     * Indian mobile number -> 10 digits, or null if it isn't one.
     * Accepts what people actually type or paste: "+91 98765 43210",
     * "098765-43210", "9876543210".
     */
    public static function mobile($raw) {
        $digits = preg_replace('/\D/', '', (string)$raw);
        if (strlen($digits) === 12 && substr($digits, 0, 2) === '91') $digits = substr($digits, 2);
        if (strlen($digits) === 11 && $digits[0] === '0')              $digits = substr($digits, 1);
        return preg_match('/^[6-9]\d{9}$/', $digits) ? $digits : null;
    }

    /**
     * Map pin from the request -> [lat, lng], or [null, null] when absent,
     * blank or out of range (a bad pin falls back to pincode-based delivery).
     */
    public static function coords($data) {
        $lat = $data['latitude']  ?? null;
        $lng = $data['longitude'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lng)) return [null, null];
        $lat = (float)$lat;
        $lng = (float)$lng;
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || ($lat == 0 && $lng == 0)) return [null, null];
        return [round($lat, 7), round($lng, 7)];
    }
}
