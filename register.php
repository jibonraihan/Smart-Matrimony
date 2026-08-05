<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/db.php';
require_once 'includes/functions.php';

$error = "";
$success = "";

if(isset($_POST['register'])){

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

                $error="Registration failed.";

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

        <div class="col-lg-6 d-none d-lg-block">

            <div class="pe-lg-5">

                <h1 class="display-4 fw-bold text-success mb-4">

                    Begin Your Halal Journey 💍

                </h1>

                <p class="lead text-muted">

                    Find your compatible life partner while maintaining
                    Islamic values, privacy and family traditions.

                </p>

                <div class="mt-5">

                    <div class="mb-4">

                        <h5>✅ Verified Profiles</h5>

                        <small class="text-muted">

                            Every profile goes through verification.

                        </small>

                    </div>

                    <div class="mb-4">

                        <h5>❤️ Smart Matching</h5>

                        <small class="text-muted">

                            Compatibility based partner suggestions.

                        </small>

                    </div>

                    <div class="mb-4">

                        <h5>🔒 Privacy Protected</h5>

                        <small class="text-muted">

                            Your personal information stays protected.

                        </small>

                    </div>

                    <div>

                        <h5>🤝 Family Friendly</h5>

                        <small class="text-muted">

                            Respectful communication before marriage.

                        </small>

                    </div>

                </div>

            </div>

        </div>

        <!-- Registration Card -->

        <div class="col-lg-5">

            <div class="card shadow-lg border-0 rounded-4">

                <div class="card-body p-4 p-lg-5">

                    <div class="text-center mb-4">

                        <h2 class="fw-bold">

                            Create Account

                        </h2>

                        <p class="text-muted">

                            Smart Matrimony

                        </p>

                    </div>

<?php if($error!=""){ ?>

<div class="alert alert-danger">

<?= $error; ?>

</div>

<?php } ?>

<form method="POST">

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label">

First Name

</label>

<input

type="text"

name="first_name"

class="form-control"

value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"

required>

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Last Name

</label>

<input

type="text"

name="last_name"

class="form-control"

value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"

required>

</div>

</div>

<div class="mb-3">

<label class="form-label">

Gender

</label>

<select

name="gender"

class="form-select"

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

<input

type="text"

name="mobile"

maxlength="11"

class="form-control"

placeholder="01XXXXXXXXX"

value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>"

required>

</div>

<div class="mb-3">

<label class="form-label">

Email Address

</label>

<input

type="email"

name="email"

class="form-control"

placeholder="example@email.com"

value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"

required>

</div>

<div class="mb-3">

<label class="form-label">

Password

</label>

<input

type="password"

name="password"

id="password"

class="form-control"

required>

</div>

<div class="mb-3">

<label class="form-label">

Confirm Password

</label>

<input

type="password"

name="confirm_password"

id="confirm_password"

class="form-control"

required>

</div>

<div class="form-check mb-4">

<input

class="form-check-input"

type="checkbox"

required>

<label class="form-check-label">

I agree to the

<a href="#">

Terms & Conditions

</a>

</label>

</div>

<button

type="submit"

name="register"

class="btn btn-success w-100 py-2">

Create Account

</button>

</form>

<hr>

<div class="text-center">

Already have an account?

<a href="login.php">

Login

</a>

</div>

                </div>

            </div>

        </div>

    </div>

</div>

