<?php
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_name('SMART_ADMIN_SESSION');
    session_start();
}
if (!empty($_SESSION['admin_user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    if ($email === '' || $password === '') {
        $error = 'Please enter email and password.';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT user_id, first_name, last_name, email, password, role, account_status FROM users WHERE email=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$user || $user['role'] !== 'Admin' || $user['account_status'] !== 'Active' || !password_verify($password, $user['password'])) {
            $error = 'Invalid admin credentials or inactive account.';
        } else {
            session_regenerate_id(true);
            $_SESSION['admin_user_id'] = (int)$user['user_id'];
            $_SESSION['admin_name'] = trim($user['first_name'].' '.$user['last_name']);
            $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Login | Smart Matrimony</title><link rel="stylesheet" href="../assets/css/admin.css"></head><body>
<div class="auth-shell"><div class="auth-card"><div class="brand">Smart Matrimony</div><span class="eyebrow">SECURE ADMIN AREA</span><h1>Admin Login</h1><p>Sign in with an active administrator account.</p>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" autocomplete="off"><label>Email<input type="email" name="email" required autocomplete="username"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><button type="submit">Sign in to Admin</button></form><a class="back" href="../dashboard.php">← Back to User Dashboard</a></div></div>
</body></html>
