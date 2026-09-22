<?php
// Authenticator uses its own dedicated session. Select it before loading
// config.php so the normal user session is never mixed with this session.
if (session_status() !== PHP_SESSION_NONE) {
    if (session_name() !== 'SMART_AUTH_SESSION') {
        session_write_close();
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_name('SMART_AUTH_SESSION');
    session_start();
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $p['path'],
        $p['domain'],
        $p['secure'],
        $p['httponly']
    );
}

session_destroy();

// Return to the single main login page, not authenticator/login.php.
header('Location: ../login.php?logged_out=1');
exit;
