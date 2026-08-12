<?php
// Central session configuration for app.2rich.capital
// Include this file at the TOP of every PHP file that uses sessions

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', 3600);  // 1 hour

    // Custom session name to avoid conflicts
    session_name('TWORICH_SESSION');

    if (!headers_sent()) {
        session_start();
    }
}

// Generate CSRF token once per session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
