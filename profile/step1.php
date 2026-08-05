<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/dropdowns.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$user = [];

$stmt = mysqli_prepare($conn,"
SELECT

u.first_name,
u.last_name,
u.gender,
u.email,
u.mobile,

up.religion,
up.madhhab,
up.marital_status,
up.highest_education,
up.height_cm,
up.weight_kg,
up.complexion,
up.bio

FROM users u

LEFT JOIN user_profiles up
ON up.user_id=u.user_id

WHERE u.user_id=?
LIMIT 1
");

mysqli_stmt_bind_param($stmt,"i",$user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if($result && mysqli_num_rows($result)>0){
    $user=mysqli_fetch_assoc($result);
}

$error="";
$success="";

if(isset($_POST['save_step1'])){

$date_of_birth=clean_input($_POST['date_of_birth']);
$religion=clean_input($_POST['religion']);
$madhhab=clean_input($_POST['madhhab']);
$marital_status=clean_input($_POST['marital_status']);
$education = clean_input($_POST['education']);
$bio=clean_input($_POST['bio']);
$height_cm=clean_input($_POST['height_cm']);
$weight_kg=clean_input($_POST['weight_kg']);
$complexion=clean_input($_POST['complexion']);

$check=mysqli_prepare($conn,"
SELECT profile_id
FROM user_profiles
WHERE user_id=?
");

mysqli_stmt_bind_param($check,"i",$user_id);
mysqli_stmt_execute($check);

$res=mysqli_stmt_get_result($check);

if(mysqli_num_rows($res)>0){

$update=mysqli_prepare($conn,"
UPDATE user_profiles
SET
date_of_birth=?,
religion=?,
madhhab=?,
marital_status=?,
highest_education=?,
bio=?,
height_cm=?,
weight_kg=?,
complexion=?
WHERE user_id=?
");

mysqli_stmt_bind_param(

$update,

"ssssssddsi",

$date_of_birth,

$religion,

$madhhab,

$marital_status,

$education,

$bio,

$height_cm,

$weight_kg,

$complexion,

$user_id

);

if(mysqli_stmt_execute($update)){
header("Location: step2.php");
exit;
}

}else{

$insert=mysqli_prepare($conn,"
INSERT INTO user_profiles
(
user_id,
first_name,
last_name,
gender,
date_of_birth,
religion,
madhhab,
marital_status,
highest_education,
bio,
height_cm,
weight_kg,
complexion
)
VALUES
(
?,?,?,?,?,?,?,?,?,?,?,?,?
)
");

mysqli_stmt_bind_param(

$insert,

"isssssssssdds",

$user_id,

$user['first_name'],

$user['last_name'],

$user['gender'],

$date_of_birth,

$religion,

$madhhab,

$marital_status,

$education,

$bio,

$height_cm,

$weight_kg,

$complexion

);

if(mysqli_stmt_execute($insert)){
header("Location: step2.php");
exit;
}

}

}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container py-5">

<div class="row justify-content-center">

<div class="col-lg-8">

<div class="card shadow border-0 rounded-4">

<div class="card-body p-5">

<?php

$currentStep = 1;

$pageTitle = "Personal Information";

$pageDescription = "";

include '../includes/wizard_header.php';

?>

<form method="POST">

<div class="card border-0 shadow-sm bg-light mb-4">

    <div class="card-body">

        <h5 class="fw-bold text-success mb-4">

            <i class="bi bi-person-circle"></i>

            Basic Information

        </h5>

        <div class="row">

            <div class="col-md-6 mb-3">

                <small class="text-muted">Full Name</small>

                <h6><?= htmlspecialchars($user['first_name']." ".$user['last_name']); ?></h6>

            </div>

            <div class="col-md-3 mb-3">

                <small class="text-muted">Gender</small>

                <h6><?= htmlspecialchars($user['gender']); ?></h6>

            </div>

            <div class="col-md-3 mb-3">

                <small class="text-muted">Religion</small>

                <h6><?= $user['religion'] ?: '<span class="text-muted">Not Added Yet</span>'; ?></h6>

            </div>

            <div class="col-md-6 mb-3">

                <small class="text-muted">Email</small>

                <h6><?= htmlspecialchars($user['email']); ?></h6>

            </div>

            <div class="col-md-6 mb-3">

                <small class="text-muted">Mobile</small>

                <h6><?= htmlspecialchars($user['mobile']); ?></h6>

            </div>

        </div>

        <hr>

        <small class="text-danger">

            <i class="bi bi-lock-fill"></i>

            Name, Gender, Email and Mobile cannot be changed after registration.

        </small>

    </div>

</div>

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label">

Date of Birth

</label>

<input

type="date"

id="date_of_birth"

name="date_of_birth"

class="form-control"

required>

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Age

</label>

<input

type="text"

id="age"

class="form-control"

readonly>

</div>

</div>

<div class="col-md-12 mb-3">

<label class="form-label">
Religion
</label>

<select
name="religion"
class="form-select"
required>

<option value="">Select Religion</option>

<?php
$religions=[
"Islam",
"Hinduism",
"Christianity",
"Buddhism",
"Other"
];

foreach($religions as $religion){
?>

<option
value="<?= $religion ?>"
<?= (($user['religion'] ?? '')==$religion)?'selected':''; ?>>

<?= $religion ?>

</option>

<?php } ?>

</select>

</div>

<div class="row">

    <div class="col-md-12 mb-3">

        <label class="form-label">

            Madhhab

        </label>

        <select
class="form-select"
name="madhhab"
required>

<option value="">Select Madhhab</option>

<?php foreach($madhhabs as $madhhab){ ?>

<option
value="<?= $madhhab ?>"
<?= (($user['madhhab'] ?? '')==$madhhab)?'selected':''; ?>>

<?= $madhhab ?>

</option>

<?php } ?>

</select>

    </div>

</div>

<div class="row">

    <div class="col-md-6 mb-3">

        <label class="form-label">

            Marital Status

        </label>

        <select
            name="marital_status"
            class="form-select"
            required>

            <option value="">Select</option>

            <?php foreach($marital_statuses as $status){ ?>

                <option
                    value="<?= $status ?>"
                    <?= (($user['marital_status'] ?? '')==$status)?'selected':''; ?>>

                    <?= $status ?>

                </option>

            <?php } ?>

        </select>

    </div>

    <div class="col-md-6 mb-3">

        <label class="form-label">

            Education

        </label>

        <select
            name="education"
            class="form-select"
            required>

            <option value="">Select Education</option>

            <?php foreach($education_levels as $edu){ ?>

                <option
                    value="<?= $edu ?>">

                    <?= $edu ?>

                </option>

            <?php } ?>

        </select>

    </div>

</div>

<div class="row">

    <div class="col-md-6 mb-3">

        <label class="form-label">

            Height

        </label>

        <div class="row">

            <div class="col-6">

                <select
                    id="feet"
                    class="form-select">

                    <option value="">Feet</option>

                    <?php for($i=4;$i<=7;$i++){ ?>

                        <option><?= $i ?></option>

                    <?php } ?>

                </select>

            </div>

            <div class="col-6">

                <select
                    id="inch"
                    class="form-select">

                    <option value="">Inch</option>

                    <?php for($i=0;$i<=11;$i++){ ?>

                        <option><?= $i ?></option>

                    <?php } ?>

                </select>

            </div>

        </div>

        <input
            type="hidden"
            id="height_cm"
            name="height_cm">

    </div>

    <div class="col-md-6 mb-3">

        <label class="form-label">

            Weight (kg)

        </label>

        <input
            type="number"
            name="weight_kg"
            class="form-control"
            value="<?= htmlspecialchars($user['weight_kg'] ?? '') ?>"
            min="30"
            max="200"
            required>

    </div>

</div>

<div class="row">

    <div class="col-md-6 mb-3">

        <label class="form-label">

            Complexion

        </label>

        <select
            name="complexion"
            class="form-select"
            required>

            <option value="">Select</option>

            <?php foreach($complexions as $c){ ?>

                <option
                    value="<?= $c ?>"
                    <?= (($user['complexion'] ?? '')==$c)?'selected':''; ?>>

                    <?= $c ?>

                </option>

            <?php } ?>

        </select>

    </div>

    <div class="col-md-6 mb-3">

        <label class="form-label">

            Short Bio

        </label>

        <textarea
            name="bio"
            rows="4"
            maxlength="500"
            class="form-control"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>

    </div>

</div>

<div class="d-flex justify-content-between mt-4">

    <a
        href="create_profile.php"
        class="btn btn-outline-secondary btn-lg">

        <i class="bi bi-arrow-left-circle"></i>

        Back

    </a>

    <button
        type="submit"
        name="save_step1"
        class="btn btn-success btn-lg">

        Save & Continue

        <i class="bi bi-arrow-right-circle"></i>

    </button>

</div>

</form>

</div>

</div>

</div>

</div>

</div>

<script>

function convertHeight(){

let feet=document.getElementById('feet').value;

let inch=document.getElementById('inch').value;

if(feet=="" || inch=="") return;

let cm=((parseInt(feet)*12)+parseInt(inch))*2.54;

document.getElementById("height_cm").value=Math.round(cm);

}

document.getElementById("feet").addEventListener("change",convertHeight);

document.getElementById("inch").addEventListener("change",convertHeight);

document.getElementById("date_of_birth").addEventListener("change",function(){

let dob=new Date(this.value);

let today=new Date();

let age=today.getFullYear()-dob.getFullYear();

let month=today.getMonth()-dob.getMonth();

if(month<0 || (month===0 && today.getDate()<dob.getDate())){

age--;

}

document.getElementById("age").value=age+" Years";

});

</script>

<?php

include '../includes/footer.php';

?>