<?php
/**
 * Public - which map/search provider the location chooser should use.
 * GET -> { success, provider: "google" | "osm", key }
 *
 * The key is a browser key (it ends up in the page anyway when the Google
 * map loads); it must be restricted to the website in Google Cloud Console.
 */
header('Content-Type: application/json');
header('Cache-Control: public, max-age=3600');

$key = '';
if (file_exists(__DIR__ . '/config/maps_config.php')) {
    require_once __DIR__ . '/config/maps_config.php';
    $key = defined('GOOGLE_MAPS_BROWSER_KEY') ? trim((string)GOOGLE_MAPS_BROWSER_KEY) : '';
}

echo json_encode([
    'success'  => true,
    'provider' => $key !== '' ? 'google' : 'osm',
    'key'      => $key !== '' ? $key : null,
]);
