<?php
$page_css = 'assets/css/password-reset.css';

require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = (int) ($_SESSION['password_reset_user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: forgot_password.php');
    exit;
}

$error = '';
$success = '';

$validatePassword = static function ($password) {
    if (strlen($password) < 8) return 'Password must be at least 8 characters long.';
    if (!preg_match('/[A-Z]/', $password)) return 'Password must contain at least one uppercase letter.';
    if (!preg_match('/[a-z]/', $password)) return 'Password must contain at least one lowercase letter.';
    if (!preg_match('/[0-9]/', $password)) return 'Password must contain at least one number.';
    if (!preg_match('/[^A-Za-z0-9]/', $password)) return 'Password must contain at least one special character.';
    return '';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $validationError = $validatePassword($password);

    if ($validationError !== '') {
        $error = $validationError;
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT pr.reset_id
             FROM password_resets pr
             INNER JOIN users u ON u.user_id = pr.user_id
             WHERE pr.user_id=?
               AND pr.verified_at IS NOT NULL
               AND u.account_status='Active'
             ORDER BY pr.verified_at DESC
             LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $reset = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if (!$reset) {
            $error = 'Your password reset session is no longer valid. Please start again.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            mysqli_begin_transaction($conn);

            $updateUser = mysqli_prepare($conn, "UPDATE users SET password=? WHERE user_id=?");
            mysqli_stmt_bind_param($updateUser, 'si', $passwordHash, $userId);
            $userUpdated = mysqli_stmt_execute($updateUser);
            mysqli_stmt_close($updateUser);

            $deleteReset = mysqli_prepare($conn, "DELETE FROM password_resets WHERE user_id=?");
            mysqli_stmt_bind_param($deleteReset, 'i', $userId);
            $resetDeleted = mysqli_stmt_execute($deleteReset);
            mysqli_stmt_close($deleteReset);

            if ($userUpdated && $resetDeleted) {
                mysqli_commit($conn);
                unset($_SESSION['password_reset_email'], $_SESSION['password_reset_user_id']);
                $success = 'Your password has been updated successfully. You can now login with your new password.';
            } else {
                mysqli_rollback($conn);
                $error = 'We could not update your password. Please try again.';
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
                <div class="password-reset-icon"><i class="fa-solid fa-lock"></i></div>
                <div class="password-reset-heading">
                    <span class="password-reset-eyebrow"><?= sm_text('SECURE YOUR ACCOUNT', 'আপনার অ্যাকাউন্ট সুরক্ষিত করুন'); ?></span>
                    <h1><?= sm_text('Set New Password', 'নতুন পাসওয়ার্ড সেট করো'); ?></h1>
                    <p><?= sm_text('Create a new password for your Smart Matrimony account using the same security requirements as registration.', 'নিবন্ধনের সময়ের একই নিরাপত্তার শর্ত মেনে আপনার স্মার্ট ম্যাট্রিমনি অ্যাকাউন্টের জন্য নতুন পাসওয়ার্ড তৈরি করুন।'); ?></p>
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
                    <a href="login.php" class="reset-primary-btn reset-success-link">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        <?= sm_text('Go to Login', 'লগইনে যান'); ?>
                    </a>
                <?php else: ?>
                    <form method="POST" class="password-reset-form" id="resetPasswordForm" novalidate>
                        <label for="new_password"><?= sm_text('New Password', 'নতুন পাসওয়ার্ড'); ?></label>
                        <div class="reset-input-wrap reset-password-wrap">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" id="new_password" name="password" placeholder="<?= sm_text('Enter your new password', 'নতুন পাসওয়ার্ড লিখো'); ?>" autocomplete="new-password" required>
                            <button type="button" class="reset-password-toggle" data-target="new_password" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
                        </div>

                        <div class="reset-requirements">
                            <span data-rule="length"><i class="fa-solid fa-circle"></i> <?= sm_text('8+ characters', 'কমপক্ষে ৮টি অক্ষর'); ?></span>
                            <span data-rule="upper"><i class="fa-solid fa-circle"></i> <?= sm_text('One uppercase letter', 'কমপক্ষে একটি বড় হাতের ইংরেজি অক্ষর'); ?></span>
                            <span data-rule="lower"><i class="fa-solid fa-circle"></i> <?= sm_text('One lowercase letter', 'কমপক্ষে একটি ছোট হাতের ইংরেজি অক্ষর'); ?></span>
                            <span data-rule="number"><i class="fa-solid fa-circle"></i> <?= sm_text('One number', 'কমপক্ষে একটি সংখ্যা'); ?></span>
                            <span data-rule="special"><i class="fa-solid fa-circle"></i> <?= sm_text('One special character', 'কমপক্ষে একটি বিশেষ চিহ্ন'); ?></span>
                        </div>

                        <label for="confirm_password"><?= sm_text('Confirm New Password', 'নতুন পাসওয়ার্ড নিশ্চিত করুন'); ?></label>
                        <div class="reset-input-wrap reset-password-wrap">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="<?= sm_text('Confirm your new password', 'নতুন পাসওয়ার্ড নিশ্চিত করো'); ?>" autocomplete="new-password" required>
                            <button type="button" class="reset-password-toggle" data-target="confirm_password" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
                        </div>

                        <div class="reset-match" id="resetMatch"></div>

                        <button type="submit" class="reset-primary-btn">
                            <i class="fa-solid fa-key"></i>
                            <?= sm_text('Update Password', 'পাসওয়ার্ড আপডেট করুন'); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.reset-password-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(this.dataset.target);
            const icon = this.querySelector('i');
            if (!input) return;
            const visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            icon.classList.toggle('fa-eye', visible);
            icon.classList.toggle('fa-eye-slash', !visible);
            this.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
        });
    });

    const password = document.getElementById('new_password');
    const confirm = document.getElementById('confirm_password');
    const match = document.getElementById('resetMatch');
    if (!password) return;

    const rules = {
        length: value => value.length >= 8,
        upper: value => /[A-Z]/.test(value),
        lower: value => /[a-z]/.test(value),
        number: value => /[0-9]/.test(value),
        special: value => /[^A-Za-z0-9]/.test(value)
    };

    function updateRules() {
        Object.keys(rules).forEach(function (key) {
            const item = document.querySelector('[data-rule="' + key + '"]');
            if (!item) return;
            const ok = rules[key](password.value);
            item.classList.toggle('valid', ok);
            item.querySelector('i').className = ok ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle';
        });
    }

    function updateMatch() {
        if (!match || !confirm.value) {
            if (match) match.textContent = '';
            return;
        }
        const same = password.value === confirm.value;
        match.textContent = same ? '✓ Passwords Match' : 'Passwords do not match';
        match.className = 'reset-match ' + (same ? 'valid' : 'invalid');
    }

    password.addEventListener('input', function () { updateRules(); updateMatch(); });
    confirm.addEventListener('input', updateMatch);
    updateRules();
});
</script>

<?php include 'includes/footer.php'; ?>
