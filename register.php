<?php
$page_css = "assets/css/register.css";
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/db.php';
require_once 'includes/functions.php';

$error = "";
ini_set('display_errors', 1);
error_reporting(E_ALL);
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $gender     = trim($_POST['gender']);
    $mobile     = trim($_POST['mobile']);
    $email      = trim($_POST['email']);
    $password   = $_POST['password'];
    $confirm    = $_POST['confirm_password'];

    // Required Fields

    if(
        empty($first_name) ||
        empty($last_name) ||
        empty($gender) ||
        empty($mobile) ||
        empty($email) ||
        empty($password) ||
        empty($confirm)
    ){

        $error = "Please fill all required fields.";

    }

    elseif(!preg_match("/^[A-Za-z ]+$/",$first_name)){

        $error="Invalid first name.";

    }

    elseif(!preg_match("/^[A-Za-z ]+$/",$last_name)){

        $error="Invalid last name.";

    }

    elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)){

        $error="Invalid email address.";

    }

    elseif(!preg_match("/^01[3-9][0-9]{8}$/",$mobile)){

        $error="Invalid mobile number.";

    }

    elseif(strlen($password)<8){

        $error="Password must be at least 8 characters.";

    }

    elseif($password!=$confirm){

        $error="Passwords do not match.";

    }

    elseif(!isset($_POST['terms'])){

    $error = "Please accept the Terms & Conditions.";

    }

    else{

        $check = mysqli_prepare(

            $conn,

            "SELECT user_id
             FROM users
             WHERE email=?
             OR mobile=?"

        );

        mysqli_stmt_bind_param(

            $check,

            "ss",

            $email,

            $mobile

        );

        mysqli_stmt_execute($check);

        mysqli_stmt_store_result($check);

        if(mysqli_stmt_num_rows($check)>0){

            $error="Email or Mobile already exists.";

        }

        else{

            $hash=password_hash(

                $password,

                PASSWORD_DEFAULT

            );

            $insert=mysqli_prepare(

                $conn,

                "INSERT INTO users(

                    first_name,
                    last_name,
                    gender,
                    mobile,
                    email,
                    password

                )

                VALUES(

                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?

                )"

            );

            mysqli_stmt_bind_param(

                $insert,

                "ssssss",

                $first_name,
                $last_name,
                $gender,
                $mobile,
                $email,
                $hash

            );

            if(mysqli_stmt_execute($insert)){

                header("Location: login.php?registered=1");

                exit();

            }

            else{

                $error = "Registration failed.";

            }

        }

    }

}

include 'includes/header.php';
include 'includes/navbar.php';

?>
<div class="container py-5">

    <div class="row justify-content-center align-items-center">

        <!-- Left Side -->

        <div class="col-lg-6 register-left">

    <span class="trust-badge">
        <i class="fa-solid fa-shield-heart"></i>
        Trusted by thousands of Muslim families
    </span>

    <h1 class="register-heading">
        Begin Your Halal Journey
        <span class="wave-hand">💍</span>
    </h1>

    <p class="register-description">
        Create your trusted matrimonial profile and connect with compatible
        life partners in a safe, verified and family-friendly environment.
    </p>

    <div class="feature-item">
        <div class="feature-icon green">
            <i class="fa-solid fa-circle-check"></i>
        </div>

        <div>
            <h5>Verified Profiles</h5>
            <p>Every profile goes through verification.</p>
        </div>
    </div>

    <div class="feature-item">
        <div class="feature-icon pink">
            <i class="fa-solid fa-heart"></i>
        </div>

        <div>
            <h5>Smart Matching</h5>
            <p>Compatibility based partner suggestions.</p>
        </div>
    </div>

    <div class="feature-item">
        <div class="feature-icon orange">
            <i class="fa-solid fa-lock"></i>
        </div>

        <div>
            <h5>Privacy Protected</h5>
            <p>Your personal information stays protected.</p>
        </div>
    </div>

    <div class="feature-item">
        <div class="feature-icon blue">
            <i class="fa-solid fa-handshake"></i>
        </div>

        <div>
            <h5>Family Friendly</h5>
            <p>Built with Islamic values and respect.</p>
        </div>
    </div>
    <div class="stats-card">

    <div class="stats-logo">

        <img src="<?php echo BASE_URL; ?>assets/images/logo/logo.png" alt="Smart Matrimony Logo">

    </div>

    <div class="stats-item">

        <i class="fa-solid fa-users"></i>

        <h4>50K+</h4>

        <p>Happy Members</p>

    </div>

    <div class="stats-item">

        <i class="fa-solid fa-shield"></i>

        <h4>100%</h4>

        <p>Verified Profiles</p>

    </div>

    <div class="stats-item">

        <i class="fa-solid fa-lock"></i>

        <h4>Secure</h4>

        <p>Data Protection</p>

    </div>

    <div class="stats-item">

        <i class="fa-solid fa-headset"></i>

        <h4>24/7</h4>

        <p>Support</p>

    </div>

</div>
</div>

        <!-- Registration Card -->

        <div class="col-lg-5">

            <div class="register-card">

                <div class="register-card-body">

                    <div class="register-card-header">

    <h2>Create Account</h2>

    <p>Join Smart Matrimony today</p>

</div>

<?php if($error!=""){ ?>

<div class="alert alert-danger">

<?= $error; ?>

</div>

<?php } ?>

<form id="registerForm" method="POST" class="register-form">

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label">

First Name

</label>

<div class="input-group input-icon-group">

    <span class="input-group-text">

        <i class="fa-regular fa-user"></i>

    </span>

    <input
        type="text"
        name="first_name"
        class="form-control register-input"
        placeholder="First Name"
        value="<?php echo htmlspecialchars($first_name ?? ''); ?>">

</div>

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Last Name

</label>

<div class="input-group input-icon-group">

    <span class="input-group-text">

        <i class="fa-regular fa-user"></i>

    </span>

    <input
        type="text"
        name="last_name"
        class="form-control register-input"
        placeholder="Last Name"
        value="<?php echo htmlspecialchars($last_name ?? ''); ?>">

</div>

</div>

</div>

<div class="mb-3">

<label class="form-label">

Gender

</label>

<select

name="gender"

class="form-select register-input"

required>

<option value="">Select Gender</option>

<option value="Male"

<?= (($_POST['gender'] ?? '')=="Male")?'selected':''; ?>>

Male

</option>

<option value="Female"

<?= (($_POST['gender'] ?? '')=="Female")?'selected':''; ?>>

Female

</option>

</select>

</div>

<div class="mb-3">

<label class="form-label">

Mobile Number

</label>

<div class="input-group input-icon-group">

    <span class="input-group-text">

        <i class="fa-solid fa-phone"></i>

    </span>

    <input
        type="text"
        name="mobile"
        class="form-control register-input"
        placeholder="01XXXXXXXXX">

    </div>
</div>

<div class="mb-3">

<label class="form-label">

Email Address

</label>

<div class="input-group input-icon-group">

    <span class="input-group-text">

        <i class="fa-regular fa-envelope"></i>

    </span>

    <input
        type="email"
        name="email"
        class="form-control register-input"
        placeholder="example@email.com">
    </div>
</div>

<div class="mb-3">

    <label class="form-label">

        Password

    </label>

    <div class="login-input">

        <i class="fa-solid fa-lock input-icon-left"></i>

        <input
            type="password"
            name="password"
            id="password"
            class="form-control register-input"
            placeholder="Enter Password"
            required>

        <i class="fa-regular fa-eye input-icon-right toggle-password"
           data-target="password"></i>

    </div>

    <div id="passwordStrength" class="password-strength mt-2"></div>

</div>

<div class="mb-3">

    <label class="form-label">

        Confirm Password

    </label>

    <div class="login-input">

        <i class="fa-solid fa-lock input-icon-left"></i>

        <input
            type="password"
            name="confirm_password"
            id="confirm_password"
            class="form-control register-input"
            placeholder="Confirm Password"
            required>

        <i class="fa-regular fa-eye input-icon-right toggle-password"
           data-target="confirm_password"></i>

    </div>

    <div id="passwordMatch" class="password-match mt-2"></div>

</div>

<div class="form-check terms-check">

    <input
        class="form-check-input"
        type="checkbox"
        id="terms"
        name="terms">

    <label class="form-check-label" for="terms">

        I agree to the
        <a href="#">Terms & Conditions</a>

    </label>

</div>

<button

type="submit"

name="register"
id="registerBtn"
class="btn btn-success w-100 py-2">

Create Account

</button>

</form>

<div class="register-divider">

    <span>OR</span>

</div>

<p class="text-center mb-0">

    Already have an account?

    <a href="login.php" class="login-link">

        Login

    </a>

</p>

                </div>

            </div>

        </div>

    </div>

</div>

<script>

document.querySelectorAll(".toggle-password").forEach(function(icon){

    icon.addEventListener("click", function(){

        const input = document.getElementById(this.dataset.target);

        if(input.type === "password"){

            input.type = "text";

            this.classList.remove("fa-eye");
            this.classList.add("fa-eye-slash");

        }else{

            input.type = "password";

            this.classList.remove("fa-eye-slash");
            this.classList.add("fa-eye");

        }

    });

});

const password = document.getElementById("password");
const confirm = document.getElementById("confirm_password");

const strength = document.getElementById("passwordStrength");
const match = document.getElementById("passwordMatch");

password.addEventListener("input", function(){

    const value = this.value;

    let text = "";
    let color = "";

    if(value.length < 8){

        text = "Weak";
        color = "#dc2626";

    }else if(
        /[A-Z]/.test(value) &&
        /[0-9]/.test(value)
    ){

        text = "Strong";
        color = "#16a34a";

    }else{

        text = "Medium";
        color = "#d97706";

    }

    strength.textContent = "Password Strength: " + text;
    strength.style.color = color;

});

confirm.addEventListener("input", function(){

    if(this.value === ""){

        match.textContent = "";
        return;

    }

    if(this.value === password.value){

        match.textContent = "✓ Password Matched";
        match.style.color = "#16a34a";

    }else{

        match.textContent = "✕ Password Not Matched";
        match.style.color = "#dc2626";

    }

});

const registerForm = document.getElementById("registerForm");
const registerBtn = document.getElementById("registerBtn");

registerForm.addEventListener("submit", function () {

    registerBtn.disabled = true;

    registerBtn.innerHTML = `
        <span class="spinner-border spinner-border-sm me-2"></span>
        Creating Account...
    `;

});

window.addEventListener("pageshow", function () {

    registerBtn.disabled = false;

    registerBtn.innerHTML = "Create Account";

});

</script>

<?php
if (!empty($error)) {
    echo "<pre style='background:#fee;padding:10px;border:1px solid red'>";
    echo "ERROR = " . $error;
    echo "</pre>";
}
?>

<?php include 'includes/footer.php'; ?>
