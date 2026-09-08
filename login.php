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

    $login = trim($_POST['email_mobile'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {

        $error = 'Please enter your Email/Mobile and Password.';

    } else {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                user_id,
                first_name,
                last_name,
                email,
                mobile,
                password,
                role,
                account_status

             FROM users

             WHERE email=? OR mobile=?

             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmt,
            'ss',
            $login,
            $login
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if (
            $result &&
            mysqli_num_rows($result) === 1
        ) {

            $user = mysqli_fetch_assoc($result);

            if ($user['account_status'] !== 'Active') {

                $error =
                    'Your account is not active. Please contact support.';

            } elseif (
                password_verify(
                    $password,
                    $user['password']
                )
            ) {

                session_regenerate_id(true);

                $_SESSION['user_id'] =
                    $user['user_id'];

                $_SESSION['first_name'] =
                    $user['first_name'];

                $_SESSION['last_name'] =
                    $user['last_name'];

                $_SESSION['role'] =
                    $user['role'];

                header('Location: home.php');
                exit;

            } else {

                $error =
                    'Incorrect password. Please try again.';
            }

        } else {

            $error =
                'No account was found with that Email/Mobile.';
        }

        mysqli_stmt_close($stmt);
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

                <div class="login-left">

                    <div class="login-badge">

                        <i class="fa-solid fa-shield-heart"></i>

                        Trusted by thousands of Muslim families

                    </div>


                    <div class="login-heading-wrap">

                        <h1 class="login-heading">
                            Welcome Back
                        </h1>

                        <span class="login-wave">
                            👋
                        </span>

                    </div>


                    <p class="login-description">

                        Find your compatible life partner in a secure,
                        trusted and family-friendly environment built on
                        Islamic values.

                    </p>


                    <div class="login-features">


                        <div class="feature-item">

                            <div class="feature-icon green">

                                <i class="fa-solid fa-lock"></i>

                            </div>

                            <div>

                                <h5>
                                    Secure &amp; Private
                                </h5>

                                <p>
                                    Your personal information is protected
                                    with secure authentication.
                                </p>

                            </div>

                        </div>


                        <div class="feature-item">

                            <div class="feature-icon purple">

                                <i class="fa-solid fa-user-check"></i>

                            </div>

                            <div>

                                <h5>
                                    Verified Profiles
                                </h5>

                                <p>
                                    Authentic members help create a safer
                                    matchmaking experience.
                                </p>

                            </div>

                        </div>


                        <div class="feature-item">

                            <div class="feature-icon orange">

                                <i class="fa-solid fa-heart"></i>

                            </div>

                            <div>

                                <h5>
                                    Halal &amp; Family Friendly
                                </h5>

                                <p>
                                    Built around Islamic values, privacy
                                    and respectful communication.
                                </p>

                            </div>

                        </div>


                    </div>


                    <div class="login-stats">

                        <div class="stats-logo">

                            <img
                                src="<?= BASE_URL ?>assets/images/logo/logo.png"
                                alt="Smart Matrimony">

                        </div>


                        <div class="stat-box">

                            <i class="fa-solid fa-users"></i>

                            <h4><?= number_format($member_count); ?>+</h4>

                            <span>
                                Happy Members
                            </span>

                        </div>


                        <div class="stat-box">

                            <i class="fa-solid fa-shield-halved"></i>

                            <h4>100%</h4>

                            <span>
                                Verified Profiles
                            </span>

                        </div>


                        <div class="stat-box">

                            <i class="fa-solid fa-lock"></i>

                            <h4>Secure</h4>

                            <span>
                                Data Protection
                            </span>

                        </div>


                        <div class="stat-box">

                            <i class="fa-solid fa-headset"></i>

                            <h4>24/7</h4>

                            <span>
                                Support
                            </span>

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

                            <div class="alert alert-danger login-alert">

                                <?= htmlspecialchars($error) ?>

                            </div>

                        <?php endif; ?>


                        <!-- NORMAL LOGIN -->

                        <form
                            id="loginForm"
                            method="POST"
                            class="login-form"
                            novalidate>


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
                                        value="<?= htmlspecialchars(
                                            $_POST['email_mobile'] ?? ''
                                        ) ?>"
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