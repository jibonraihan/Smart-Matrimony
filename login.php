<?php
$page_css = "assets/css/login.css";
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
require_once 'includes/functions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// session_start();

// if (isset($_SESSION['user_id'])) {

//     header("Location: dashboard.php");

//     exit;

// }

$error = "";

// Registration Success Message

$registered = false;

if(isset($_GET['registered'])){

    $registered = true;

}

// Login
if($_SERVER["REQUEST_METHOD"]=="POST"){


    $login = trim($_POST['email_mobile']);

    $password = $_POST['password'];

    if(empty($login) || empty($password)){

        $error = "Please enter Email/Mobile and Password.";

    }

    else{

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

            WHERE email=?

            OR mobile=?"

        );

        mysqli_stmt_bind_param(

            $stmt,

            "ss",

            $login,

            $login

        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if(mysqli_num_rows($result)==1){

            $user = mysqli_fetch_assoc($result);
           
            if($user['account_status']!="Active"){

                $error="Your account is inactive.";

            }

         elseif(password_verify($password,$user['password'])){

    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['user_id'];

    $_SESSION['first_name'] = $user['first_name'];

    $_SESSION['last_name'] = $user['last_name'];

    $_SESSION['role'] = $user['role'];

    header("Location: dashboard.php");

    exit();

}

            else{

                $error="Incorrect password.";

            }

        }

        else{

            $error="Account not found.";

        }

    }

}



include 'includes/header.php';
include 'includes/navbar.php';

?>
<div class="login-page">

    <div class="container">

        <div class="row align-items-center gy-5">

            <!-- Left Side -->

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

    <span class="login-wave">👋</span>

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

                                <h5>Secure & Private</h5>

                                <p>Your personal information is encrypted and protected.</p>

                            </div>

                        </div>

                        <div class="feature-item">

                            <div class="feature-icon purple">

                                <i class="fa-solid fa-user-check"></i>

                            </div>

                            <div>

                                <h5>Verified Profiles</h5>

                                <p>Every profile is verified for a safer matchmaking experience.</p>

                            </div>

                        </div>

                        <div class="feature-item">

                            <div class="feature-icon orange">

                                <i class="fa-solid fa-heart"></i>

                            </div>

                            <div>

                                <h5>Halal & Family Friendly</h5>

                                <p>Built with Islamic values and respectful communication.</p>

                            </div>

                        </div>

                    </div>
                                        <div class="login-stats">

    <div class="stats-logo">

        <img src="<?= BASE_URL ?>assets/images/logo/logo.png"
             alt="Smart Matrimony">

    </div>

    <div class="stat-box">

        <i class="fa-solid fa-users"></i>

        <h4>50K+</h4>

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

 <!-- Login Card -->
<!-- Right Side -->

<div class="col-lg-5">

    <div class="login-card">

    <div class="login-card-body">

        <div class="login-card-header">
            <h2>Login</h2>

            <p>
                Access your Smart Matrimony account
            </p>

        </div>

        <?php if($registered && empty($error)){ ?>

<div class="alert alert-success">

    Registration completed successfully. Please login.

</div>

<?php } ?>

        <?php if (!empty($error)): ?>

            <div class="alert alert-danger">
                <?= $error ?>
            </div>

        <?php endif; ?>

        <form id="loginForm" method="POST" class="login-form">
            <div class="mb-3">

    <label class="form-label">

        Email or Mobile

    </label>

    <div class="login-input">

        <i class="fa-regular fa-user input-icon-left"></i>

        <input
            type="text"
            name="email_mobile"
            class="form-control"
            placeholder="Enter your email or mobile number"
            value="<?= htmlspecialchars($_POST['email_mobile'] ?? '') ?>"
            required>

    </div>

</div>
<div class="mb-3">

    <label class="form-label">

        Password

    </label>

    <div class="login-input password-wrapper">

        <i class="fa-solid fa-lock input-icon-left"></i>

        <input
            type="password"
            id="password"
            name="password"
            class="form-control"
            placeholder="Enter your password"
            required>

        <i
            id="togglePassword"
            class="fa-regular fa-eye input-icon-right"></i>

    </div>

</div>
<div class="d-flex justify-content-between align-items-center mb-4">

    <div class="form-check">

        <input
            class="form-check-input"
            type="checkbox"
            id="remember"
            name="remember">

        <label
            class="form-check-label"
            for="remember">

            Remember Me

        </label>

    </div>

    <a href="forgot_password.php" class="forgot-link">

        Forgot Password?

    </a>

</div>

<button
    type="submit"
    name="login"
    class="login-btn"
    id="loginBtn">

    <i class="fa-solid fa-right-to-bracket me-2"></i>

    <span>Login</span>

</button>

</form>
<div class="login-divider">

    <span>OR</span>

</div>

<p class="text-center mb-0">

    Don't have an account?

    <a href="register.php" class="register-link">

        Create Account

    </a>

</p>
</div>
</div>
</div>
</div>

</div>

</div>

</div>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<?php if($registered){ ?>

<script>

Swal.fire({

icon:'success',

title:'Registration Successful',

text:'Please login to continue.',

confirmButtonColor:'#198754'

});

</script>

<?php } ?>

<?php if($error!=""){ ?>

<script>

Swal.fire({

icon:'error',

title:'Login Failed',

text:'<?= addslashes($error); ?>',

confirmButtonColor:'#dc3545'

});

</script>

<?php } ?>

<script>
document.addEventListener("DOMContentLoaded",function(){

const password=document.getElementById("password");

const toggle=document.getElementById("togglePassword");

if(password && toggle){

toggle.addEventListener("click",function(){

if(password.type==="password"){

password.type="text";

this.classList.replace("fa-eye","fa-eye-slash");

}else{

password.type="password";

this.classList.replace("fa-eye-slash","fa-eye");

}

});

}

});
</script>
<script>

const loginForm = document.getElementById("loginForm");
const loginBtn  = document.getElementById("loginBtn");

loginForm.addEventListener("submit", function () {

    loginBtn.disabled = true;

    loginBtn.innerHTML = `
        <span class="spinner-border spinner-border-sm me-2"></span>
        Signing in...
    `;

});

// Reset button when page is restored from browser cache (Back button)
window.addEventListener("pageshow", function () {

    loginBtn.disabled = false;

    loginBtn.innerHTML = `
        <i class="fa-solid fa-right-to-bracket me-2"></i>
        <span>Login</span>
    `;

});

</script>
<?php

include 'includes/footer.php';

?>