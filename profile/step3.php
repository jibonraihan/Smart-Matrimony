<?php

require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/dropdowns.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {

    header("Location: ../login.php");
    exit();

}

$user_id = $_SESSION['user_id'];

$error = "";
$success = "";

/*
|--------------------------------------------------------------------------
| Load Profile
|--------------------------------------------------------------------------
*/

$profileStmt = mysqli_prepare(

    $conn,

    "SELECT * FROM user_profiles WHERE user_id=? LIMIT 1"

);
$health=[];

$healthStmt=mysqli_prepare($conn,"
SELECT
blood_group,
disability_status,
medical_notes
FROM health_profiles
WHERE user_id=?
LIMIT 1
");
mysqli_stmt_bind_param($healthStmt,"i",$user_id);
mysqli_stmt_execute($healthStmt);

$healthResult = mysqli_stmt_get_result($healthStmt);

if(mysqli_num_rows($healthResult)>0){

    $health = mysqli_fetch_assoc($healthResult);

}

mysqli_stmt_bind_param($profileStmt,"i",$user_id);
mysqli_stmt_execute($profileStmt);

$result = mysqli_stmt_get_result($profileStmt);

$user = mysqli_fetch_assoc($result);

if(!$user){

    header("Location: step1.php");
    exit();

}

/*
|--------------------------------------------------------------------------
| Save Step 3
|--------------------------------------------------------------------------
*/

if(isset($_POST['save_step3'])){

    $smoking_status = clean_input($_POST['smoking_status']);

    $prayer_status = clean_input($_POST['prayer_status']);

    $beard_status = clean_input($_POST['beard_status'] ?? 'Not Applicable');

    $hijab_status = clean_input($_POST['hijab_status'] ?? 'Not Applicable');

    $hijab_details = clean_input($_POST['hijab_details'] ?? '');

    $mahram_maintained = clean_input($_POST['mahram_maintained'] ?? '');

    $blood_group = clean_input($_POST['blood_group'] ?? '');
$physical_disability = clean_input($_POST['physical_disability'] ?? '');

$health_notes = clean_input($_POST['health_notes'] ?? '');

    $update = mysqli_prepare(

        $conn,

        "UPDATE user_profiles

        SET

        smoking_status=?,

        prayer_status=?,

        beard_status=?,

        hijab_status=?,

        hijab_details=?,

        mahram_maintained=?

        WHERE user_id=?"

    );

    $stmt=mysqli_prepare($conn,"
SELECT user_id
FROM health_profiles
WHERE user_id=?
LIMIT 1
");

mysqli_stmt_bind_param($stmt,"i",$user_id);
mysqli_stmt_execute($stmt);

$result=mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result)>0){

    $stmt=mysqli_prepare($conn,"
 UPDATE health_profiles
SET
blood_group=?,
disability_status=?,
medical_notes=?
WHERE user_id=?
    ");

   mysqli_stmt_bind_param(

    $stmt,

    "sssi",

    $blood_group,
    $physical_disability,
    $health_notes,
    $user_id

);

}else{

    $stmt=mysqli_prepare($conn,"
    INSERT INTO health_profiles
(
user_id,
blood_group,
disability_status,
medical_notes
)
VALUES
(?,?,?,?)
    ");

mysqli_stmt_bind_param(

    $stmt,

    "isss",

    $user_id,
    $blood_group,
    $physical_disability,
    $health_notes

);

}

mysqli_stmt_execute($stmt);

    mysqli_stmt_bind_param(

        $update,

        "sssssii",

        $smoking_status,

        $prayer_status,

        $beard_status,

        $hijab_status,

        $hijab_details,

        $mahram_maintained,

        $user_id

    );

    if(mysqli_stmt_execute($update)){

        header("Location: step4.php");

        exit();

    }

    else{

        $error="Database update failed.";

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
$currentStep = 3;

$pageTitle = "Lifestyle & Health";

$pageDescription = "Share your Islamic lifestyle and basic health information to improve profile quality and matching accuracy.";

include '../includes/wizard_header.php';
?>
<?php if($error!=""){ ?>

<div class="alert alert-danger">

<?= $error ?>

</div>

<?php } ?>

<form method="POST">
<div class="row">

<div class="col-12">

<h5 class="fw-bold text-success">

<i class="bi bi-stars"></i>

Lifestyle

</h5>

</div>
<div class="col-md-6 mb-3">

    <label class="form-label">

        Smoking Status

    </label>

    <select
name="smoking_status"
class="form-select"
required>

<option value="">Select</option>

<?php foreach($smoking_statuses as $status){ ?>

<option
value="<?= $status ?>"
<?= (($user['smoking_status'] ?? '')==$status)?'selected':''; ?>>

<?= $status ?>

</option>

<?php } ?>

</select>

</div>

<div class="col-md-6 mb-3">

    <label class="form-label">

        Prayer Status

    </label>

   <select
name="prayer_status"
class="form-select"
required>

<option value="">Select</option>

<?php foreach($prayer_statuses as $status){ ?>

<option
value="<?= $status ?>"
<?= (($user['prayer_status'] ?? '')==$status)?'selected':''; ?>>

<?= $status ?>

</option>

<?php } ?>

</select>

</div>

<?php if($user['gender']=="Male"){ ?>

<div class="col-md-6 mb-3">

    <label class="form-label">

        Beard Status

    </label>

    <select
name="beard_status"
class="form-select">

<option value="">Select</option>

<?php foreach($beard_statuses as $status){ ?>

<option
value="<?= $status ?>"
<?= (($user['beard_status'] ?? '')==$status)?'selected':''; ?>>

<?= $status ?>

</option>

<?php } ?>

</select>

</div>

<?php } ?>

<?php if($user['gender']=="Female"){ ?>

<div class="col-md-6 mb-3">

    <label class="form-label">

        Hijab Status

    </label>

   <select
name="hijab_status"
class="form-select">

<option value="">Select</option>

<?php foreach($hijab_statuses as $status){ ?>

<option
value="<?= $status ?>"
<?= (($user['hijab_status'] ?? '')==$status)?'selected':''; ?>>

<?= $status ?>

</option>

<?php } ?>

</select>

</div>
<div class="col-12 mb-3">

    <label class="form-label">

        Hijab Details

    </label>

    <textarea
        name="hijab_details"
        rows="3"
        class="form-control"><?= htmlspecialchars($user['hijab_details'] ?? '') ?></textarea>

</div>

<?php } ?>

<div class="col-md-6 mb-3">

<label class="form-label">

Mahram Maintained

</label>

<select
name="mahram_maintained"
class="form-select">
<option value="">Select</option>
<?php foreach($mahram_options as $item){ ?>

<option
value="<?= $item ?>"
<?= (($user['mahram_maintained'] ?? '')==$item)?'selected':''; ?>>

<?= $item ?>

</option>

<?php } ?>

</select>

</div>

<div class="col-12">


<hr class="my-3">

<h5 class="fw-bold text-success mb-3">

<i class="bi bi-heart-pulse"></i>

Health Information

<h5 class="fw-bold text-success mb-3">

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Blood Group

</label>

<select
name="blood_group"
class="form-select">

<option value="">Select Blood Group</option>

<?php foreach($blood_groups as $blood){ ?>

<option
value="<?= $blood ?>"
<?= (($health['blood_group'] ?? '')==$blood)?'selected':''; ?>>

<?= $blood ?>

</option>

<?php } ?>

</select>

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Physical Disability

</label>

<select
name="physical_disability"
class="form-select">
<option value="">Select</option>
<?php foreach($physical_disabilities as $item){ ?>

<option
value="<?= $item ?>"
<?= (($health['disability_status'] ?? '')==$item)?'selected':''; ?>>

<?= $item ?>

</option>

<?php } ?>

</select>

</div>

</div>

<div class="col-12 mb-3">

<label class="form-label">

Medical Notes (Optional)

</label>

<textarea

name="health_notes"

rows="3"

maxlength="300"

class="form-control"

placeholder="Mention chronic diseases, allergies or important medical information."><?= htmlspecialchars($health['medical_notes'] ?? '') ?></textarea>

</div>

<div class="d-flex justify-content-between mt-4">

    <a
        href="step2.php"
        class="btn btn-outline-secondary btn-lg">

        <i class="bi bi-arrow-left-circle"></i>

        Back

    </a>

    <button
        type="submit"
        name="save_step3"
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

</div>

<?php

include '../includes/footer.php';

?>