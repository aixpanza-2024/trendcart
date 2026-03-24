<?php
/**
 * Central Session Configuration
 * Cookie lifetime = 365 days (persists across browser closes).
 * Server-side session data also kept for 365 days.
 */
if (session_status() === PHP_SESSION_NONE) {
    $serverLifetime = 365 * 24 * 60 * 60; // 365 days
    ini_set('session.gc_maxlifetime', $serverLifetime);
    session_set_cookie_params([
        'lifetime' => $serverLifetime, // persist for 365 days even after browser close
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
?>
