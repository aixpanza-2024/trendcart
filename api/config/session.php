<?php
/**
 * Central Session Configuration
 * Cookie lifetime = 0 (expires when browser closes).
 * Server-side session data kept for 365 days.
 */
if (session_status() === PHP_SESSION_NONE) {
    $serverLifetime = 365 * 24 * 60 * 60; // keep server session data for 365 days
    ini_set('session.gc_maxlifetime', $serverLifetime);
    session_set_cookie_params([
        'lifetime' => 0,   // 0 = browser-session cookie (deleted on browser close)
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
?>
