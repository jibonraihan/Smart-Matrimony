<?php
/*
 * Destroy only the Event Manager session. The regular customer session
 * (PHPSESSID) must remain untouched.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_name('SMART_MANAGER_SESSION');
    ini_set('session.use_cookies', '0');
    ini_set('session.use_only_cookies', '0');
    ini_set('session.use_trans_sid', '0');
    $manager_sid = $_GET['manager_sid'] ?? '';
    if (!preg_match('/^[a-f0-9]{64}$/', $manager_sid)) {
        $manager_sid = bin2hex(random_bytes(32));
    }
    session_id($manager_sid);
    session_start();
}

require_once '../config/config.php';
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();
header('Location: login.php');
exit;
