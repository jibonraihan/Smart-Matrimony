<?php
$page_css = 'assets/css/password-reset.css';

require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'config/mail.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT user_id, first_name, email, account_status
             FROM users
             WHERE email=?
             LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if (!$user || $user['account_status'] !== 'Active') {
            // Keep account existence private. Do not reveal whether an email is registered.
            $success = 'If an active account is associated with this email, a password reset code has been sent.';
        } else {
            $check = mysqli_prepare(
                $conn,
                "SELECT last_sent_at
                 FROM password_resets
                 WHERE user_id=?
                 LIMIT 1"
            );
            mysqli_stmt_bind_param($check, 'i', $user['user_id']);
            mysqli_stmt_execute($check);
            $checkResult = mysqli_stmt_get_result($check);
            $existing = $checkResult ? mysqli_fetch_assoc($checkResult) : null;
            mysqli_stmt_close($check);

            if ($existing && strtotime($existing['last_sent_at']) > time() - 60) {
                $success = 'A reset code was recently sent. Please check your email or wait a moment before requesting another code.';
            } else {
                $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $codeHash = password_hash($code, PASSWORD_DEFAULT);
                $expiresAt = date('Y-m-d H:i:s', time() + 120);
                $lastSentAt = date('Y-m-d H:i:s');

                mysqli_begin_transaction($conn);

                $upsert = mysqli_prepare(
                    $conn,
                    "INSERT INTO password_resets
                        (user_id, email, code_hash, expires_at, last_sent_at, attempts, verified_at)
                     VALUES (?, ?, ?, ?, ?, 0, NULL)
                     ON DUPLICATE KEY UPDATE
                        email=VALUES(email),
                        code_hash=VALUES(code_hash),
                        expires_at=VALUES(expires_at),
                        last_sent_at=VALUES(last_sent_at),
                        attempts=0,
                        verified_at=NULL"
                );
                mysqli_stmt_bind_param(
                    $upsert,
                    'issss',
                    $user['user_id'],
                    $user['email'],
                    $codeHash,
                    $expiresAt,
                    $lastSentAt
                );
                $saved = mysqli_stmt_execute($upsert);
                mysqli_stmt_close($upsert);

                if (!$saved) {
                    mysqli_rollback($conn);
                    $error = 'We could not start the password reset process. Please try again.';
                } elseif (!send_password_reset_email($user['email'], $user['first_name'], $code)) {
                    mysqli_rollback($conn);
                    $error = 'We could not send the reset code. Please try again.';
                } else {
                    mysqli_commit($conn);
                    $_SESSION['password_reset_email'] = $user['email'];
                    header('Location: verify_reset_otp.php');
                    exit;
                }
            }
        }
    }
}

include 'includes/header.php';
include 'includes/navbar.php';
?>

<main class="password-reset-page">
    <div class="container">
        <div class="password-reset-shell">
            <div class="password-reset-card">
                <div class="password-reset-icon"><i class="fa-solid fa-key"></i></div>
                <div class="password-reset-heading">
                    <span class="password-reset-eyebrow">ACCOUNT RECOVERY</span>
                    <h1>Forgot Password?</h1>
                    <p>Enter the email address linked to your Smart Matrimony account and we’ll send you a verification code.</p>
                </div>

                <?php if ($error): ?>
                    <div class="password-reset-alert error">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="password-reset-alert success">
                        <i class="fa-solid fa-circle-check"></i>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" class="password-reset-form" novalidate>
                    <label for="reset_email">Email Address</label>
                    <div class="reset-input-wrap">
                        <i class="fa-regular fa-envelope"></i>
                        <input
                            type="email"
                            id="reset_email"
                            name="email"
                            placeholder="Enter your registered email"
                            autocomplete="email"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            required
                        >
                    </div>

                    <button type="submit" class="reset-primary-btn">
                        <i class="fa-solid fa-paper-plane"></i>
                        Send Verification Code
                    </button>
                </form>

                <a href="login.php" class="reset-back-link">
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Login
                </a>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
