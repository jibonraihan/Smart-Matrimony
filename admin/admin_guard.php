<?php
// Central Admin access guard. Start the dedicated Admin session BEFORE db.php,
// because config.php starts a default PHP session when none is active.
if (session_status() === PHP_SESSION_NONE) {
    session_name('SMART_ADMIN_SESSION');
    session_start();
}

require_once __DIR__ . '/../config/db.php';

$admin_user_id = (int)($_SESSION['admin_user_id'] ?? 0);
if ($admin_user_id <= 0) {
    header('Location: ../login.php');
    exit;
}

$guard_stmt = mysqli_prepare($conn, "SELECT role, account_status FROM users WHERE user_id=? LIMIT 1");
mysqli_stmt_bind_param($guard_stmt, 'i', $admin_user_id);
mysqli_stmt_execute($guard_stmt);
$guard_result = mysqli_stmt_get_result($guard_stmt);
$guard_user = mysqli_fetch_assoc($guard_result);
mysqli_stmt_close($guard_stmt);

if (!$guard_user || $guard_user['role'] !== 'Admin' || $guard_user['account_status'] !== 'Active') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: ../login.php');
    exit;
}

$admin_id = $admin_user_id;
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['admin_csrf'];
