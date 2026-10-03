<?php

$page_css = 'assets/css/login.css';

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';
$activation_available = false;
$activation_user = null;
$login_value = '';
$password_value = '';

/* Manager -> User Dashboard always requires a fresh regular-user login. */
if (isset($_GET['manager_reauth']) && $_GET['manager_reauth'] === '1') {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
    session_start();
}

if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}
$login_csrf = $_SESSION['login_csrf'];

$registered = isset($_GET['registered']);

/* =========================
   TOTAL MEMBERS COUNT
========================= */

$member_count = 0;

$count_result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total_members FROM users"
);

if ($count_result) {

    $count_data = mysqli_fetch_assoc($count_result);

    $member_count = (int) ($count_data['total_members'] ?? 0);
}

if (!empty($_SESSION['oauth_error'])) {
    $error = $_SESSION['oauth_error'];
    unset($_SESSION['oauth_error']);
}


/* =========================
   NORMAL LOGIN
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = (string) ($_POST['login_action'] ?? 'login');
    $login_value = trim((string) ($_POST['email_mobile'] ?? ''));
    $password_value = (string) ($_POST['password'] ?? '');

    if ($action === 'activate_inactive_account') {
        $posted_token = (string) ($_POST['login_csrf'] ?? '');
        $candidate = $_SESSION['inactive_activation'] ?? null;

        if (!hash_equals($login_csrf, $posted_token)) {
            $error = 'The security token is invalid. Please refresh the page and try again.';
        } elseif (!is_array($candidate) || empty($candidate['user_id']) || empty($candidate['verified_at']) || (time() - (int) $candidate['verified_at']) > 600) {
            unset($_SESSION['inactive_activation']);
            $error = 'Your activation session has expired. Please enter your Email/Mobile and Password again.';
        } elseif ($login_value === '' || $password_value === '') {
            $error = 'Enter your Email/Mobile and Password before activating your account.';
            $activation_available = true;
        } else {
            $activation_user_id = (int) $candidate['user_id'];
            $activation_stmt = mysqli_prepare($conn, "SELECT user_id, first_name, last_name, email, mobile, password, role, account_status FROM users WHERE user_id=? LIMIT 1");
            if ($activation_stmt) {
                mysqli_stmt_bind_param($activation_stmt, 'i', $activation_user_id);
                mysqli_stmt_execute($activation_stmt);
                $activation_result = mysqli_stmt_get_result($activation_stmt);
                $activation_user = $activation_result ? mysqli_fetch_assoc($activation_result) : null;
                mysqli_stmt_close($activation_stmt);
            }

            if (!$activation_user || ($activation_user['role'] ?? '') !== 'User' || ($activation_user['account_status'] ?? '') !== 'Inactive') {
                unset($_SESSION['inactive_activation']);
                $error = 'This account cannot be activated from the current state.';
            } elseif ($login_value !== (string) $activation_user['email'] && $login_value !== (string) $activation_user['mobile']) {
                $error = 'The Email/Mobile does not match the deactivated account.';
                $activation_available = true;
            } elseif (!password_verify($password_value, $activation_user['password'])) {
                $error = 'Incorrect password. Please enter your password again.';
                $activation_available = true;
            } else {
                $activate_stmt = mysqli_prepare($conn, "UPDATE users SET account_status='Active' WHERE user_id=? AND role='User' AND account_status='Inactive' LIMIT 1");
                $activated = false;
                if ($activate_stmt) {
                    mysqli_stmt_bind_param($activate_stmt, 'i', $activation_user_id);
                    $activated = mysqli_stmt_execute($activate_stmt) && mysqli_stmt_affected_rows($activate_stmt) === 1;
                    mysqli_stmt_close($activate_stmt);
                }

                if ($activated) {
                    unset($_SESSION['inactive_activation']);
                    $activation_available = false;
                    $activation_user = null;
                    $success = 'Your account has been activated successfully. Please click Login to continue.';
                } else {
                    $error = 'Unable to activate your account right now. Please try again.';
                    $activation_available = true;
                }
            }
        }
    } else {
        if ($login_value === '' || $password_value === '') {
            $error = 'Please enter your Email/Mobile and Password.';
        } else {
            $stmt = mysqli_prepare($conn, "SELECT user_id, first_name, last_name, email, mobile, password, role, account_status FROM users WHERE email=? OR mobile=? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'ss', $login_value, $login_value);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($result && mysqli_num_rows($result) === 1) {
                $user = mysqli_fetch_assoc($result);

                if (!password_verify($password_value, $user['password'])) {
                    $error = 'Incorrect password. Please try again.';
                } elseif ($user['account_status'] === 'Inactive' && $user['role'] === 'User') {
                    $_SESSION['inactive_activation'] = [
                        'user_id' => (int) $user['user_id'],
                        'verified_at' => time()
                    ];
                    $activation_available = true;
                    $activation_user = $user;
                    $error = 'Your account is currently deactivated. You can activate it now.';
                } elseif ($user['account_status'] !== 'Active') {
                    $error = 'Your account is not active. Please contact support.';
                } else {
                    if ($user['role'] === 'Admin') {
                        $_SESSION = [];
                        session_write_close();
                        session_name('SMART_ADMIN_SESSION');
                        session_start();
                        session_regenerate_id(true);
                        $_SESSION['admin_user_id'] = (int) $user['user_id'];
                        $_SESSION['admin_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
                        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
                        header('Location: admin/dashboard.php');
                        exit;
                    }

                    if ($user['role'] === 'Authenticator') {
                        $_SESSION = [];
                        session_write_close();
                        session_name('SMART_AUTH_SESSION');
                        session_start();
                        session_regenerate_id(true);
                        $_SESSION['authenticator_user_id'] = (int) $user['user_id'];
                        $_SESSION['authenticator_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
                        $_SESSION['auth_csrf'] = bin2hex(random_bytes(32));
                        header('Location: authenticator/dashboard.php');
                        exit;
                    }

                    if ($user['role'] === 'Manager') {
                        $_SESSION = [];
                        session_write_close();
                        session_name('SMART_MANAGER_SESSION');
                        ini_set('session.use_cookies', '0');
                        ini_set('session.use_only_cookies', '0');
                        ini_set('session.use_trans_sid', '0');
                        $manager_sid = bin2hex(random_bytes(32));
                        session_id($manager_sid);
                        session_start();
                        $_SESSION['user_id'] = (int) $user['user_id'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['manager_id'] = (int) $user['user_id'];
                        $_SESSION['manager_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
                        header('Location: manager/dashboard.php?manager_sid=' . urlencode($manager_sid));
                        exit;
                    }

                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int) $user['user_id'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['role'] = $user['role'];
                    header('Location: home.php');
                    exit;
                }
            } else {
                $error = 'No account was found with that Email/Mobile.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}


include 'includes/header.php';
include 'includes/navbar.php';

?>

<div class="login-page">

    <div class="container">

        <div class="row align-items-center gy-5">


            <!-- =========================
                 LEFT SIDE
            ========================== -->

            <div class="col-lg-7">

                <div class="login-left login-left-modern">

                    <div class="login-brand-mark">
                        <img src="<?= BASE_URL ?>assets/images/logo/matrimony_title.png" alt="Smart Matrimony">
                    </div>

                    <div class="login-modern-eyebrow">
                        <i class="fa-solid fa-heart"></i> Meaningful connections, thoughtfully made
                    </div>

                    <h1 class="login-modern-heading">
                        Find Your<br>
                        <span>Meaningful Connection.</span>
                    </h1>

                    <p class="login-modern-description">
                        A secure, respectful and family-friendly matrimony platform built around trust, privacy and Islamic values.
                    </p>

                    <div class="login-visual-stage" aria-hidden="true">
                        <div class="login-visual-glow"></div>
                        <div class="login-profile-card login-profile-card-back">
                            <div class="mini-avatar mini-avatar-one"><i class="fa-solid fa-user"></i></div>
                            <div class="mini-lines"><span></span><span></span></div>
                        </div>
                        <div class="login-profile-card login-profile-card-main">
                            <div class="match-avatar"><i class="fa-solid fa-user"></i></div>
                            <div class="match-card-copy">
                                <strong>Profile Match</strong>
                                <span>Compatible connection</span>
                            </div>
                            <div class="match-score">96%</div>
                            <div class="match-progress"><span></span></div>
                        </div>
                        <div class="login-profile-card login-profile-card-front">
                            <div class="mini-avatar mini-avatar-two"><i class="fa-solid fa-user"></i></div>
                            <div class="mini-lines"><span></span><span></span></div>
                        </div>
                        <div class="login-float-icon login-float-heart"><i class="fa-solid fa-heart"></i></div>
                        <div class="login-float-icon login-float-shield"><i class="fa-solid fa-shield-heart"></i></div>
                    </div>

                    <div class="login-trust-row">
                        <div><i class="fa-solid fa-shield-halved"></i><span>Secure &amp; Private</span></div>
                        <div><i class="fa-solid fa-heart"></i><span>Respectful Community</span></div>
                        <div><i class="fa-solid fa-users"></i><span>Family Friendly</span></div>
                    </div>

                    <div class="login-stats login-stats-compact">
                        <div class="stats-logo">
                            <img src="<?= BASE_URL ?>assets/images/logo/logo.png" alt="Smart Matrimony">
                        </div>

                        <div class="stat-box">
                            <i class="fa-solid fa-users"></i>
                            <h4><?= number_format($member_count); ?>+</h4>
                            <span>Happy Members</span>
                        </div>

                        <div class="stat-box">
                            <i class="fa-solid fa-shield-halved"></i>
                            <h4>100%</h4>
                            <span>Verified Profiles</span>
                        </div>

                        <div class="stat-box">
                            <i class="fa-solid fa-lock"></i>
                            <h4>Secure</h4>
                            <span>Data Protection</span>
                        </div>

                        <div class="stat-box">
                            <i class="fa-solid fa-headset"></i>
                            <h4>24/7</h4>
                            <span>Support</span>
                        </div>
                    </div>

                </div>

            </div>


            <!-- =========================
                 LOGIN CARD
            ========================== -->

            <div class="col-lg-5 col-xl-4">

                <div class="login-card">

                    <div class="login-card-body">


                        <div class="login-card-header text-center">

                            <h2>
                                Login
                            </h2>

                            <p>
                                Access your Smart Matrimony account
                            </p>

                        </div>


                        <?php if (
                            $registered &&
                            empty($error)
                        ): ?>

                            <div class="alert alert-success login-alert">

                                Registration completed successfully.
                                Please login.

                            </div>

                        <?php endif; ?>

                        <?php if (!empty($error)): ?>
                            <div class="alert <?= $activation_available ? 'alert-warning' : 'alert-danger' ?> login-alert">
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success login-alert" role="status">
                                <?= htmlspecialchars($success) ?>
                            </div>
                        <?php endif; ?>

                        <!-- NORMAL LOGIN -->

                        <form
                            id="loginForm"
                            method="POST"
                            class="login-form"
                            novalidate>

                            <input type="hidden" name="login_csrf" value="<?= htmlspecialchars($login_csrf) ?>">

                            <div class="mb-3">

                                <label
                                    class="form-label"
                                    for="email_mobile">

                                    Email or Mobile

                                </label>


                                <div class="login-input">

                                    <i
                                        class="fa-regular fa-user input-icon-left">
                                    </i>


                                    <input
                                        type="text"
                                        id="email_mobile"
                                        name="email_mobile"
                                        class="form-control"
                                        placeholder="Enter your email or mobile number"
                                        value="<?= htmlspecialchars($login_value) ?>"
                                        autocomplete="username"
                                        required>

                                </div>

                            </div>



                            <div class="mb-3">

                                <label
                                    class="form-label"
                                    for="password">

                                    Password

                                </label>


                                <div class="login-input password-wrapper">

                                    <i
                                        class="fa-solid fa-lock input-icon-left">
                                    </i>


                                    <input
                                        type="password"
                                        id="password"
                                        name="password"
                                        class="form-control"
                                        placeholder="Enter your password"
                                        value="<?= htmlspecialchars($password_value) ?>"
                                        autocomplete="current-password"
                                        required>


                                    <button
                                        type="button"
                                        class="password-toggle"
                                        id="togglePassword"
                                        aria-label="Show password">

                                        <i class="fa-regular fa-eye"></i>

                                    </button>

                                </div>

                            </div>

                            <?php if ($activation_available && is_array($activation_user)): ?>
                                <div class="activation-panel" role="status">
                                    <div class="activation-copy">
                                        <strong>Account deactivated</strong>
                                        <span>Password verified. Activate first, then login normally.</span>
                                    </div>
                                    <button
                                        type="submit"
                                        name="login_action"
                                        value="activate_inactive_account"
                                        class="activation-btn">
                                        <i class="fa-solid fa-power-off"></i>
                                        Activate Account
                                    </button>
                                </div>
                            <?php endif; ?>

                            <button
                                type="submit"
                                name="login"
                                class="login-btn"
                                id="loginBtn">

                                <i
                                    class="fa-solid fa-right-to-bracket me-2">
                                </i>

                                <span>
                                    Login
                                </span>

                            </button>

                        </form>

                        <div class="forgot-password-wrap">
                            <a href="forgot_password.php" class="forgot-password-link">
                                Forgot Password?
                            </a>
                        </div>



                        <!-- SOCIAL DIVIDER -->

                        <div class="login-divider">

                            <span>
                                OR CONTINUE WITH
                            </span>

                        </div>



                        <!-- GOOGLE + FACEBOOK -->

                        <div class="social-login-buttons">


                            <a href="social_login.php?provider=google"
   class="social-btn google-btn icon-only"
   title="Continue with Google"
   aria-label="Continue with Google">
    <span class="social-icon google-icon">
        <i class="fab fa-google"></i>
    </span>
</a>

<a href="social_login.php?provider=facebook"
   class="social-btn facebook-btn icon-only"
   title="Continue with Facebook"
   aria-label="Continue with Facebook">
    <span class="social-icon facebook-icon">
        <i class="fab fa-facebook-f"></i>
    </span>
</a>


                        </div>


                        <p class="social-note">

                            Use your verified Google or Facebook account
                            to sign in.

                        </p>


                        <!-- REGISTER -->

                        <div class="register-prompt">

                            Don't have an account?

                            <a
                                href="register.php"
                                class="register-link">

                                Create Account

                            </a>

                        </div>


                    </div>

                </div>

            </div>

        </div>

    </div>

</div>



<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {


        /* PASSWORD TOGGLE */

        const password =
            document.getElementById('password');

        const togglePassword =
            document.getElementById('togglePassword');


        if (
            password &&
            togglePassword
        ) {

            togglePassword.addEventListener(
                'click',
                function () {

                    const icon =
                        this.querySelector('i');

                    const isPassword =
                        password.type === 'password';


                    password.type =
                        isPassword
                            ? 'text'
                            : 'password';


                    icon.classList.toggle(
                        'fa-eye',
                        !isPassword
                    );

                    icon.classList.toggle(
                        'fa-eye-slash',
                        isPassword
                    );


                    this.setAttribute(
                        'aria-label',
                        isPassword
                            ? 'Hide password'
                            : 'Show password'
                    );

                }
            );

        }



        /* LOGIN LOADING STATE */

        const loginForm =
            document.getElementById('loginForm');

        const loginBtn =
            document.getElementById('loginBtn');


        if (
            loginForm &&
            loginBtn
        ) {

            loginForm.addEventListener(
                'submit',
                function () {

                    if (
                        !loginForm.checkValidity()
                    ) {
                        return;
                    }


                    loginBtn.disabled = true;


                    loginBtn.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-2"></span>' +
                        '<span>Signing in...</span>';

                }
            );


            window.addEventListener(
                'pageshow',
                function () {

                    loginBtn.disabled = false;


                    loginBtn.innerHTML =
                        '<i class="fa-solid fa-right-to-bracket me-2"></i>' +
                        '<span>Login</span>';

                }
            );

        }

    }
);

</script>


<?php

include 'includes/footer.php';

?>