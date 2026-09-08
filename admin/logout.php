<?php
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_name('SMART_ADMIN_SESSION'); session_start(); }
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: login.php'); exit;
