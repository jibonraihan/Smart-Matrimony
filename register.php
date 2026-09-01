<?php
$page_css = "assets/css/register.css";

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'config/mail.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = "";
$success = "";

/*
 * Dynamic member count for the registration page.
 */
$memberCount = 0;

$countResult = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total_users FROM users"
);

if ($countResult) {
    $countRow = mysqli_fetch_assoc($countResult);
    $memberCount = (int)($countRow['total_users'] ?? 0);
}

/*
 * Social registration data is stored server-side in the session.
 */
$socialRegistration = $_SESSION['social_registration'] ?? null;

if (
    $socialRegistration &&
    (
        !is_array($socialRegistration) ||
        empty($socialRegistration['provider']) ||
        empty($socialRegistration['provider_id']) ||
        empty($socialRegistration['email']) ||
        empty($socialRegistration['created_at']) ||
        (time() - (int)$socialRegistration['created_at']) > 600
    )
) {
    unset($_SESSION['social_registration']);
    $socialRegistration = null;
}

/*
 * A normal visit to register.php starts a fresh registration.
 * Social registration is preserved only when ?social=1 is present.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    !isset($_GET['social'])
) {
    unset($_SESSION['social_registration']);
    $socialRegistration = null;
}

$isSocialRegistration = is_array($socialRegistration);

/*
 * Load Google/Facebook profile data for the registration form.
 */
$first_name = '';
$last_name  = '';
$email      = '';

if ($isSocialRegistration) {
    $first_name = trim($socialRegistration['first_name'] ?? '');
    $last_name  = trim($socialRegistration['last_name'] ?? '');
    $email      = strtolower(trim($socialRegistration['email'] ?? ''));
}

/*
 * Generate a cryptographically secure 6-digit verification code.
 */
function generate_verification_code()
{
    return str_pad(
        (string) random_int(0, 999999),
        6,
        '0',
        STR_PAD_LEFT
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $gender     = trim($_POST['gender'] ?? '');
    $mobile     = trim($_POST['mobile'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    /*
     * For social registration, name/email come only from the
     * authenticated provider session, never from the browser.
     */
    if ($isSocialRegistration) {
        $first_name = trim($socialRegistration['first_name'] ?? '');
        $last_name  = trim($socialRegistration['last_name'] ?? '');
        $email      = strtolower(trim($socialRegistration['email'] ?? ''));
    }

    // Required Fields
    if (
        empty($first_name) ||
        empty($last_name) ||
        empty($gender) ||
        empty($mobile) ||
        empty($email) ||
        empty($password) ||
        empty($confirm)
    ) {
        $error = "Please fill all required fields.";
    }
    elseif (!preg_match("/^[A-Za-z ]+$/", $first_name)) {
        $error = "Invalid first name.";
    }
    elseif (!preg_match("/^[A-Za-z ]+$/", $last_name)) {
        $error = "Invalid last name.";
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    }
    elseif (!preg_match("/^01[3-9][0-9]{8}$/", $mobile)) {
        $error = "Invalid mobile number.";
    }
    elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    }
    elseif (!preg_match("/[A-Z]/", $password)) {
        $error = "Password must contain at least one uppercase letter.";
    }
    elseif (!preg_match("/[a-z]/", $password)) {
        $error = "Password must contain at least one lowercase letter.";
    }
    elseif (!preg_match("/[^A-Za-z0-9]/", $password)) {
        $error = "Password must contain at least one special character.";
    }
    elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    }
    elseif (!isset($_POST['terms'])) {
        $error = "Please accept the Terms & Conditions.";
    }
    else {

        $duplicate = false;

        $check = mysqli_prepare(
            $conn,
            "SELECT user_id
             FROM users
             WHERE email=?
             OR mobile=?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $check,
            "ss",
            $email,
            $mobile
        );

        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            $duplicate = true;
        }

        mysqli_stmt_close($check);

        /*
         * Also protect the provider ID from being linked twice.
         */
        if (!$duplicate && $isSocialRegistration) {

            $providerColumn =
                $socialRegistration['provider'] === 'google'
                    ? 'google_id'
                    : 'facebook_id';

            $providerId = $socialRegistration['provider_id'];

            $providerCheck = mysqli_prepare(
                $conn,
                "SELECT user_id
                 FROM users
                 WHERE {$providerColumn}=?
                 LIMIT 1"
            );

            mysqli_stmt_bind_param(
                $providerCheck,
                "s",
                $providerId
            );

            mysqli_stmt_execute($providerCheck);
            mysqli_stmt_store_result($providerCheck);

            if (mysqli_stmt_num_rows($providerCheck) > 0) {
                $duplicate = true;
            }

            mysqli_stmt_close($providerCheck);
        }

        if ($duplicate) {
            $error = "Email or Mobile already exists.";
        }
        else {

            $hash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            if ($isSocialRegistration) {

                $providerColumn =
                    $socialRegistration['provider'] === 'google'
                        ? 'google_id'
                        : 'facebook_id';

                $providerId = $socialRegistration['provider_id'];

                $sql = "
                    INSERT INTO users(
                        first_name,
                        last_name,
                        gender,
                        mobile,
                        email,
                        {$providerColumn},
                        password,
                        account_status
                    )
                    VALUES(?,?,?,?,?,?,?,'Inactive')
                ";

                $insert = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $insert,
                    "sssssss",
                    $first_name,
                    $last_name,
                    $gender,
                    $mobile,
                    $email,
                    $providerId,
                    $hash
                );

            } else {

                $insert = mysqli_prepare(
                    $conn,
                    "INSERT INTO users(
                        first_name,
                        last_name,
                        gender,
                        mobile,
                        email,
                        password,
                        account_status
                    )
                    VALUES(?,?,?,?,?,?, 'Inactive')"
                );

                $accountStatus = 'Inactive';

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
            }

            /*
             * The account status is explicitly set to Inactive until
             * the email address is verified.
             */
            if (mysqli_stmt_execute($insert)) {

                $user_id = mysqli_insert_id($conn);

                /*
                 * Generate and hash the 6-digit verification code.
                 * The code is valid for 2 minutes.
                 */
                $verification_code = generate_verification_code();

                $code_hash = password_hash(
                    $verification_code,
                    PASSWORD_DEFAULT
                );

                $expires_at = date(
                    'Y-m-d H:i:s',
                    time() + 120
                );

                $last_sent_at = date('Y-m-d H:i:s');

                /*
                 * Store verification information.
                 */
                $verification_stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO email_verifications
                    (
                        user_id,
                        email,
                        code_hash,
                        expires_at,
                        last_sent_at
                    )
                    VALUES (?, ?, ?, ?, ?)"
                );

                mysqli_stmt_bind_param(
                    $verification_stmt,
                    "issss",
                    $user_id,
                    $email,
                    $code_hash,
                    $expires_at,
                    $last_sent_at
                );

                if (mysqli_stmt_execute($verification_stmt)) {

                    mysqli_stmt_close($verification_stmt);

                    /*
                     * Send the verification email through PHPMailer.
                     */
                    if (
                        send_verification_email(
                            $email,
                            $first_name,
                            $verification_code
                        )
                    ) {

                        $_SESSION['pending_verification_user_id'] = $user_id;

                        header("Location: verify_email.php");
                        exit();

                    } else {

                        /*
                         * If email sending fails, remove both the
                         * verification record and the newly created user.
                         */
                        $deleteVerification = mysqli_prepare(
                            $conn,
                            "DELETE FROM email_verifications WHERE user_id=?"
                        );

                        mysqli_stmt_bind_param(
                            $deleteVerification,
                            "i",
                            $user_id
                        );

                        mysqli_stmt_execute($deleteVerification);
                        mysqli_stmt_close($deleteVerification);

                        $deleteUser = mysqli_prepare(
                            $conn,
                            "DELETE FROM users WHERE user_id=?"
                        );

                        mysqli_stmt_bind_param(
                            $deleteUser,
                            "i",
                            $user_id
                        );

                        mysqli_stmt_execute($deleteUser);
                        mysqli_stmt_close($deleteUser);

                        $error =
                            "We could not send the verification email. Please try again.";
                    }

                } else {

                    mysqli_stmt_close($verification_stmt);

                    /*
                     * Roll back the newly created account manually because
                     * this flow does not currently use a DB transaction.
                     */
                    $deleteUser = mysqli_prepare(
                        $conn,
                        "DELETE FROM users WHERE user_id=?"
                    );

                    mysqli_stmt_bind_param(
                        $deleteUser,
                        "i",
                        $user_id
                    );

                    mysqli_stmt_execute($deleteUser);
                    mysqli_stmt_close($deleteUser);

                    $error = "Registration could not be completed.";
                }

            } else {
                $error = "Registration failed.";
            }

            mysqli_stmt_close($insert);
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

        <h4><?= number_format($memberCount) ?>+</h4>

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

        <div class="col-lg-6 register-right">

            <div class="register-card">

                <div class="register-card-body">

                    <div class="register-card-header">

    <h2>Create Account</h2>

    <p>Join Smart Matrimony community</p>

</div>

<?php if ($isSocialRegistration): ?>

<div class="social-registration-status">

    <i class="fa-solid fa-circle-check"></i>

    <div>
        <strong><?= ucfirst(htmlspecialchars($socialRegistration['provider'])) ?> account verified</strong>
        <small>
            Complete the remaining details below to create your Smart Matrimony account.
        </small>
    </div>

</div>

<?php endif; ?>

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
        value="<?php echo htmlspecialchars($first_name ?? ''); ?>"
        <?php if($isSocialRegistration) echo 'readonly'; ?>>

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
        value="<?php echo htmlspecialchars($last_name ?? ''); ?>"
        <?php if($isSocialRegistration) echo 'readonly'; ?>>

</div>

</div>

</div>

<div class="row register-gender-mobile">

<div class="col-md-6 mb-3">

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

<div class="col-md-6 mb-3">

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
        placeholder="01XXXXXXXXX"
        value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>">

    </div>
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
        placeholder="example@email.com"
        value="<?php echo htmlspecialchars($email ?? ''); ?>"
        <?php if($isSocialRegistration) echo 'readonly'; ?>>
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

    <div class="password-requirements" id="passwordRequirements">

        <div class="password-requirement" data-rule="length">
            <i class="fa-solid fa-circle-xmark"></i>
            <span>At least 8 characters</span>
        </div>

        <div class="password-requirement" data-rule="upper">
            <i class="fa-solid fa-circle-xmark"></i>
            <span>One uppercase letter (A-Z)</span>
        </div>

        <div class="password-requirement" data-rule="lower">
            <i class="fa-solid fa-circle-xmark"></i>
            <span>One lowercase letter (a-z)</span>
        </div>

        <div class="password-requirement" data-rule="special">
            <i class="fa-solid fa-circle-xmark"></i>
            <span>One special character (!@#$...)</span>
        </div>

    </div>

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

<?php if ($isSocialRegistration): ?>

<div class="social-password-note">

    <i class="fa-solid fa-shield-halved"></i>

    <span>
        Your <?= ucfirst(htmlspecialchars($socialRegistration['provider'])) ?> account is verified.
        Set a password so you can also use normal email/password login.
    </span>

</div>

<?php endif; ?>

<button

type="submit"

name="register"
id="registerBtn"
class="btn btn-success w-100 py-2">

Create Account

</button>

</form>

<?php if (!$isSocialRegistration): ?>

<div class="register-divider">

    <span>OR</span>

</div>

<div class="register-social-section">

    <p class="register-social-title">
        Sign up with
    </p>

    <div class="register-social-buttons">

        <a
            href="social_login.php?provider=google&mode=register"
            class="register-social-btn google"
            title="Sign up with Google"
            aria-label="Sign up with Google">

            <i class="fab fa-google"></i>

        </a>

        <a
            href="social_login.php?provider=facebook&mode=register"
            class="register-social-btn facebook"
            title="Sign up with Facebook"
            aria-label="Sign up with Facebook">

            <i class="fab fa-facebook-f"></i>

        </a>

    </div>

</div>

<?php endif; ?>

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

const requirementElements = {
    length: document.querySelector('[data-rule="length"]'),
    upper: document.querySelector('[data-rule="upper"]'),
    lower: document.querySelector('[data-rule="lower"]'),
    special: document.querySelector('[data-rule="special"]')
};

function updateRequirement(element, fulfilled) {

    const icon = element.querySelector("i");

    element.classList.toggle("fulfilled", fulfilled);

    icon.classList.toggle("fa-circle-check", fulfilled);
    icon.classList.toggle("fa-circle-xmark", !fulfilled);

}

function updatePasswordStatus() {

    const value = password.value;

    const rules = {
        length: value.length >= 8,
        upper: /[A-Z]/.test(value),
        lower: /[a-z]/.test(value),
        special: /[^A-Za-z0-9]/.test(value)
    };

    Object.keys(rules).forEach(function(rule){

        updateRequirement(
            requirementElements[rule],
            rules[rule]
        );

    });

    const fulfilled = Object.values(rules).filter(Boolean).length;

    if(value === ""){

        strength.textContent = "";

    }else if(fulfilled === 4){

        strength.textContent = "Password Strength: Strong";
        strength.style.color = "#16a34a";

    }else if(fulfilled >= 2){

        strength.textContent = "Password Strength: Medium";
        strength.style.color = "#d97706";

    }else{

        strength.textContent = "Password Strength: Weak";
        strength.style.color = "#dc2626";

    }

    updatePasswordMatch();

}

function updatePasswordMatch() {

    if(confirm.value === ""){

        match.textContent = "";
        match.style.color = "";

        return;

    }

    if(confirm.value === password.value){

        match.textContent = "✓ Password Matched";
        match.style.color = "#16a34a";

    }else{

        match.textContent = "✕ Password Not Matched";
        match.style.color = "#dc2626";

    }

}

password.addEventListener("input", updatePasswordStatus);
confirm.addEventListener("input", updatePasswordMatch);

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

<?php include 'includes/footer.php'; ?>
