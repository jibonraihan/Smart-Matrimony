<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
require_once 'includes/functions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
<div class="container py-5">

    <div class="row justify-content-center align-items-center">

        <!-- Left Side -->

        <div class="col-lg-6 d-none d-lg-block">

            <div class="pe-lg-5">

                <h1 class="display-4 fw-bold text-success mb-4">

                    Welcome Back 👋

                </h1>

                <p class="lead text-muted">

                    Continue your halal journey and connect with your future life partner.

                </p>

                <div class="mt-5">

                    <div class="mb-4">

                        <h5>🔒 Secure Login</h5>

                        <small class="text-muted">

                            Your account is protected with encrypted passwords.

                        </small>

                    </div>

                    <div class="mb-4">

                        <h5>💍 Smart Matrimony</h5>

                        <small class="text-muted">

                            Find compatible Muslim life partners.

                        </small>

                    </div>

                    <div class="mb-4">

                        <h5>👨‍👩‍👧 Family Friendly</h5>

                        <small class="text-muted">

                            Respectful communication with Islamic values.

                        </small>

                    </div>

                </div>

            </div>

        </div>

        <!-- Login Card -->

        <div class="col-lg-5">

            <div class="card shadow-lg border-0 rounded-4">

                <div class="card-body p-4 p-lg-5">

                    <div class="text-center mb-4">

                        <h2 class="fw-bold">

                            Login

                        </h2>

                        <p class="text-muted">

                            Smart Matrimony

                        </p>

                    </div>

<?php if($registered){ ?>

<div class="alert alert-success">

Registration completed successfully. Please login.

</div>

<?php } ?>

<?php if($error!=""){ ?>

<div class="alert alert-danger">

<?= $error ?>

</div>

<?php } ?>

<form method="POST">

<div class="mb-3">

<label class="form-label">

Email or Mobile

</label>

<input

type="text"

name="email_mobile"

class="form-control"

placeholder="Email or Mobile"

value="<?= htmlspecialchars($_POST['login'] ?? '') ?>"

required>

</div>

<div class="mb-3">

<label class="form-label">

Password

</label>

<input

type="password"

id="password"

name="password"

class="form-control"

required>

</div>

<div class="d-flex justify-content-between mb-4">

<div class="form-check">

<input

class="form-check-input"

type="checkbox"

name="remember"

id="remember">

<label class="form-check-label">

Remember Me

</label>

</div>

<a href="#">

Forgot Password?

</a>

</div>

<button

type="submit"

name="login"

class="btn btn-success w-100 py-2">

Login

</button>

</form>

<hr>

<div class="text-center">

Don't have an account?

<a href="register.php">

Create Account

</a>

</div>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>

// Show / Hide Password

const password = document.getElementById("password");

password.insertAdjacentHTML(

    "afterend",

    '<button type="button" id="togglePassword" class="btn btn-sm btn-outline-secondary mt-2">Show Password</button>'

);

document

.getElementById("togglePassword")

.addEventListener("click",function(){

    if(password.type==="password"){

        password.type="text";

        this.innerHTML="Hide Password";

    }

    else{

        password.type="password";

        this.innerHTML="Show Password";

    }

});

</script>

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

<?php

include 'includes/footer.php';

?>