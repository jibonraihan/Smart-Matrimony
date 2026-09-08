<?php
/*
 * Event Manager uses a separate PHP session cookie from the regular
 * customer session. This allows a user account and a manager account
 * to stay signed in independently in different browser tabs.
 */
/*
 * Manager authentication is isolated per browser tab.
 * PHP cookies cannot provide tab-isolated sessions because cookies are shared
 * by all tabs. We therefore carry a cryptographically-random session id in
 * the Manager URL and disable the Manager session cookie.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_name('SMART_MANAGER_SESSION');
    ini_set('session.use_cookies', '0');
    ini_set('session.use_only_cookies', '0');
    ini_set('session.use_trans_sid', '0');

    $manager_sid = $_GET['manager_sid'] ?? '';
    if (!preg_match('/^[a-f0-9]{64}$/', $manager_sid)) {
        $manager_sid = bin2hex(random_bytes(32));
    }
    session_id($manager_sid);
    session_start();
}

require_once '../config/db.php';

$manager_sid = session_id();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT user_id, first_name, last_name, email, password, role, account_status FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Invalid email or password.';
        } elseif ($user['account_status'] !== 'Active') {
            $error = 'This account is not active.';
        } elseif ($user['role'] !== 'Manager') {
            $error = 'This account does not have Event Manager access.';
        } else {
            // The tab-specific session ID is already a fresh 256-bit random ID.
            // Do not regenerate it here: the new ID would not match the manager_sid
            // carried by this tab's URL and would make the login appear to stall.
            $_SESSION['user_id'] = (int)$user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['manager_id'] = (int)$user['user_id'];
            $_SESSION['manager_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
            header('Location: dashboard.php?manager_sid=' . urlencode(session_id()));
            exit;
        }
    }
}

$page_css = 'assets/css/manager.css';
include '../includes/header.php';
?>
<div class="manager-auth-page">
    <div class="manager-auth-card">
        <a class="manager-brand" href="<?= BASE_URL; ?>index.php"><i class="fa-solid fa-calendar-check"></i> Smart Matrimony</a>
        <span class="manager-kicker">EVENT MANAGEMENT</span>
        <h1>Event Manager Login</h1>
        <p class="manager-auth-copy">Sign in to manage wedding service packages and booking requests.</p>
        <?php if ($error): ?>
            <div class="manager-alert error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" action="login.php?manager_sid=<?= urlencode(session_id()); ?>" class="manager-auth-form" novalidate>
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" autocomplete="email" required value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>">
            <label for="password">Password</label>
            <div class="password-wrap">
                <input id="password" type="password" name="password" autocomplete="current-password" required>
                <button type="button" class="password-toggle" data-target="password" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
            </div>
            <button class="manager-primary-btn" type="submit"><i class="fa-solid fa-right-to-bracket"></i> Sign in as Manager</button>
        </form>
        <a class="back-dashboard" href="<?= BASE_URL; ?>login.php"><i class="fa-solid fa-arrow-left"></i> Regular user login</a>
    </div>
</div>
<script>
document.querySelectorAll('.password-toggle').forEach(function(button) {
    button.addEventListener('click', function() {
        const input = document.getElementById(button.dataset.target);
        const icon = button.querySelector('i');
        input.type = input.type === 'password' ? 'text' : 'password';
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    });
});
</script>
<?php include '../includes/footer.php'; ?>
