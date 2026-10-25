<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$language = (string)($_POST['language'] ?? $_GET['language'] ?? 'bn');
if (!in_array($language, ['bn', 'en'], true)) {
    $language = 'bn';
}
$_SESSION['sm_language'] = $language;

$redirect = (string)($_POST['redirect'] ?? $_GET['redirect'] ?? 'index.php');
$redirect = basename(parse_url($redirect, PHP_URL_PATH) ?: 'index.php');
$allowed = ['settings.php', 'index.php', 'dashboard.php'];
if (!in_array($redirect, $allowed, true)) $redirect = 'index.php';
header('Location: ' . $redirect);
exit;
