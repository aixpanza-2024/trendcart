<?php
/**
 * Central Session Configuration
 * Sessions persist indefinitely — users stay logged in until they manually logout.
 */
if (session_status() === PHP_SESSION_NONE) {
    $lifetime = 10 * 365 * 24 * 60 * 60; // ~10 years (effectively permanent)
    ini_set('session.gc_maxlifetime', $lifetime);
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
?>
