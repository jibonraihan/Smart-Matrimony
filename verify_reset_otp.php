<?php
$page_css = 'assets/css/password-reset.css';

require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$email = $_SESSION['password_reset_email'] ?? '';
if ($email === '') {
    header('Location: forgot_password.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = preg_replace('/\D/', '', $_POST['code'] ?? '');

    if (strlen($code) !== 6) {
        $error = 'Please enter the 6-digit verification code.';
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT pr.reset_id, pr.user_id, pr.code_hash, pr.expires_at, pr.attempts, pr.verified_at
             FROM password_resets pr
             INNER JOIN users u ON u.user_id = pr.user_id
             WHERE pr.email=? AND u.account_status='Active'
             LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $reset = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if (!$reset) {
            $error = 'This verification request is no longer available. Please request a new code.';
        } elseif ($reset['verified_at'] !== null) {
            $error = 'This code has already been verified. Please continue to set your new password.';
            $_SESSION['password_reset_user_id'] = (int) $reset['user_id'];
            header('Location: reset_password.php');
            exit;
        } elseif (strtotime($reset['expires_at']) < time()) {
            $error = 'This verification code has expired. Please request a new code.';
        } elseif ((int) $reset['attempts'] >= 5) {
            $error = 'Too many incorrect attempts. Please request a new code.';
        } elseif (!password_verify($code, $reset['code_hash'])) {
            $attempt = (int) $reset['attempts'] + 1;
            $update = mysqli_prepare($conn, "UPDATE password_resets SET attempts=? WHERE reset_id=?");
            mysqli_stmt_bind_param($update, 'ii', $attempt, $reset['reset_id']);
            mysqli_stmt_execute($update);
            mysqli_stmt_close($update);
            $remaining = max(0, 5 - $attempt);
            $error = $remaining > 0
                ? "Incorrect verification code. {$remaining} attempts remaining."
                : 'Too many incorrect attempts. Please request a new code.';
        } else {
            $verifiedAt = date('Y-m-d H:i:s');
            $update = mysqli_prepare(
                $conn,
                "UPDATE password_resets SET verified_at=?, attempts=0 WHERE reset_id=?"
            );
            mysqli_stmt_bind_param($update, 'si', $verifiedAt, $reset['reset_id']);
            $ok = mysqli_stmt_execute($update);
            mysqli_stmt_close($update);

            if ($ok) {
                $_SESSION['password_reset_user_id'] = (int) $reset['user_id'];
                header('Location: reset_password.php');
                exit;
            }
            $error = 'We could not verify the code. Please try again.';
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
                <div class="password-reset-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <div class="password-reset-heading">
                    <span class="password-reset-eyebrow">VERIFY YOUR EMAIL</span>
                    <h1>Enter Verification Code</h1>
                    <p>We sent a 6-digit code to <strong><?= htmlspecialchars($email) ?></strong>. The code is valid for 2 minutes.</p>
                </div>

                <?php if ($error): ?>
                    <div class="password-reset-alert error">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" class="password-reset-form otp-form" novalidate>
                    <label for="reset_code">Verification Code</label>
                    <div class="reset-input-wrap otp-input-wrap">
                        <i class="fa-solid fa-hashtag"></i>
                        <input
                            type="text"
                            id="reset_code"
                            name="code"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            placeholder="Enter 6-digit code"
                            autocomplete="one-time-code"
                            required
                            autofocus
                        >
                    </div>

                    <button type="submit" class="reset-primary-btn">
                        <i class="fa-solid fa-circle-check"></i>
                        Verify Code
                    </button>
                </form>

                <div class="reset-secondary-actions">
                    <a href="forgot_password.php">Request a New Code</a>
                    <span>•</span>
                    <a href="login.php">Back to Login</a>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const code = document.getElementById('reset_code');
    if (code) {
        code.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
