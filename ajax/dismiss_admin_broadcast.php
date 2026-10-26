<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}
$expected = (string) ($_SESSION['matching_csrf'] ?? '');
$posted = (string) ($_POST['csrf_token'] ?? '');
if ($expected === '' || !hash_equals($expected, $posted)) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}
$_SESSION['admin_broadcast_dismissed'] = true;
echo json_encode(['ok' => true]);
