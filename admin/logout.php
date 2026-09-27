<?php
// Admin uses a dedicated session. Always target that session explicitly.
if (session_status() === PHP_SESSION_ACTIVE && session_name() !== 'SMART_ADMIN_SESSION') {
    session_write_close();
}
if (session_status() === PHP_SESSION_NONE) {
    session_name('SMART_ADMIN_SESSION');
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

// All staff logout actions return to the single main login page.
header('Location: ../login.php?logged_out=1');
exit;
