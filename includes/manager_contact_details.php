<?php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$viewer_id = (int) $_SESSION['user_id'];
$manager_id = (int) ($_POST['manager_id'] ?? 0);
if ($manager_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid manager']);
    exit;
}

$viewer_stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_id = ? AND account_status = 'Active' LIMIT 1");
if (!$viewer_stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to verify session']);
    exit;
}
mysqli_stmt_bind_param($viewer_stmt, 'i', $viewer_id);
mysqli_stmt_execute($viewer_stmt);
$viewer = mysqli_fetch_assoc(mysqli_stmt_get_result($viewer_stmt));
mysqli_stmt_close($viewer_stmt);
if (!$viewer) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Active login required']);
    exit;
}

$stmt = mysqli_prepare($conn, "
    SELECT u.mobile, u.email
    FROM users u
    WHERE u.user_id = ?
      AND u.role = 'Manager'
      AND u.account_status = 'Active'
      AND EXISTS (
          SELECT 1 FROM service_providers sp
          WHERE sp.manager_id = u.user_id AND sp.status = 'Active'
      )
    LIMIT 1
");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load contact details']);
    exit;
}
mysqli_stmt_bind_param($stmt, 'i', $manager_id);
mysqli_stmt_execute($stmt);
$manager = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$manager) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Manager not found']);
    exit;
}

echo json_encode([
    'success' => true,
    'mobile' => (string) ($manager['mobile'] ?? ''),
    'email' => (string) ($manager['email'] ?? ''),
]);
