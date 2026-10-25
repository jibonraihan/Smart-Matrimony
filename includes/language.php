<?php
// Session-only language preference. No persistent cookie or database setting.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$allowed_languages = ['bn', 'en'];
// Remove a persistent cookie left by an older version, if present.
if (isset($_COOKIE['sm_language'])) {
    setcookie('sm_language', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE['sm_language']);
}
if (!isset($_SESSION['sm_language']) || !in_array($_SESSION['sm_language'], $allowed_languages, true)) {
    $_SESSION['sm_language'] = 'bn';
}
$current_language = $_SESSION['sm_language'];
if (!function_exists('sm_text')) {
    function sm_text(string $english, string $bengali): string {
        return (($GLOBALS['current_language'] ?? 'bn') === 'en') ? $english : $bengali;
    }
}
