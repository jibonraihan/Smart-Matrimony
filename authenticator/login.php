<?php
// Start the Authenticator session BEFORE loading config.php.
// config.php starts the normal PHP session when no session exists,
// so the session name must be selected first.
if (session_status() === PHP_SESSION_NONE) {
    session_name('SMART_AUTH_SESSION');
    session_start();
}

require_once '../config/db.php';

if (!empty($_SESSION['authenticator_user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Enter your email and password.';
    } else {
        $st = mysqli_prepare(
            $conn,
            "SELECT user_id, first_name, last_name, password, role, account_status
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param($st, 's', $email);
        mysqli_stmt_execute($st);
        $result = mysqli_stmt_get_result($st);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($st);

        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Invalid email or password.';
        } elseif ($user['role'] !== 'Authenticator') {
            $error = 'This account is not an Authenticator account.';
        } elseif ($user['account_status'] !== 'Active') {
            $error = 'This Authenticator account is not active.';
        } else {
            session_regenerate_id(true);

            $_SESSION['authenticator_user_id'] = (int)$user['user_id'];
            $_SESSION['authenticator_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
            $_SESSION['auth_csrf'] = bin2hex(random_bytes(32));

            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Authenticator Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/authenticator.css">
</head>
<body>
<main class="auth-login">
    <div class="auth-login-card">
        <span class="eyebrow">SMART MATRIMONY</span>
        <h1>Authenticator Login</h1>
        <p>Review and verify member profiles securely.</p>

        <?php if ($error): ?>
            <div class="flash error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <label>
                Email
                <input type="email" name="email" autocomplete="username" required>
            </label>
            <label>
                Password
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button type="submit">Login as Authenticator</button>
        </form>

        <a href="../index.php">← Public Home</a>
    </div>
</main>
</body>
</html>
