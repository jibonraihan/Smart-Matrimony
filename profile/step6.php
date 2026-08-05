<?php

require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/dropdowns.php';

if(session_status()==PHP_SESSION_NONE){
    session_start();
}

if(!isset($_SESSION['user_id'])){
    header("Location: ../login.php");
    exit;
}

$user_id=$_SESSION['user_id'];

$error="";
$success="";

include '../includes/header.php';
include '../includes/navbar.php';
$user_id = $_SESSION['user_id'];

if(isset($_POST['save_step6'])){

    $preferred_gender = trim($_POST['preferred_gender']);

    $min_age = !empty($_POST['min_age']) ? (int)$_POST['min_age'] : NULL;
    $max_age = !empty($_POST['max_age']) ? (int)$_POST['max_age'] : NULL;

    $min_height_cm = !empty($_POST['min_height_cm']) ? $_POST['min_height_cm'] : NULL;
    $max_height_cm = !empty($_POST['max_height_cm']) ? $_POST['max_height_cm'] : NULL;

    $religion = !empty($_POST['religion']) ? trim($_POST['religion']) : NULL;

    $marital_status = !empty($_POST['marital_status']) ? trim($_POST['marital_status']) : NULL;

    $education = !empty($_POST['education']) ? trim($_POST['education']) : NULL;

    $profession = !empty($_POST['profession']) ? trim($_POST['profession']) : NULL;

    $min_monthly_income = !empty($_POST['min_monthly_income'])
        ? $_POST['min_monthly_income']
        : NULL;

    $division_id = !empty($_POST['division'])
        ? (int)$_POST['division']
        : NULL;

    $district_id = !empty($_POST['district'])
        ? (int)$_POST['district']
        : NULL;

    $upazila_id = !empty($_POST['upazila'])
        ? (int)$_POST['upazila']
        : NULL;

$stmt = mysqli_prepare($conn,"
SELECT preference_id
FROM search_preferences
WHERE user_id=?
LIMIT 1
");

mysqli_stmt_bind_param($stmt,"i",$user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result)>0){

    $sql="UPDATE search_preferences SET

    preferred_gender=?,
    min_age=?,
    max_age=?,
    min_height_cm=?,
    max_height_cm=?,
    religion=?,
    marital_status=?,
    education=?,
    profession=?,
    min_monthly_income=?,
    division_id=?,
    district_id=?,
    upazila_id=?

    WHERE user_id=?";

}else{

    $sql="INSERT INTO search_preferences(

    user_id,
    preferred_gender,
    min_age,
    max_age,
    min_height_cm,
    max_height_cm,
    religion,
    marital_status,
    education,
    profession,
    min_monthly_income,
    division_id,
    district_id,
    upazila_id

    )

    VALUES(

    ?,?,?,?,?,?,?,?,?,?,?,?,?,?

    )";

}
$stmt = mysqli_prepare($conn,$sql);

if(mysqli_num_rows($result)>0){

    mysqli_stmt_bind_param(

        $stmt,

        "siiddssssdiiii",

        $preferred_gender,
        $min_age,
        $max_age,
        $min_height_cm,
        $max_height_cm,
        $religion,
        $marital_status,
        $education,
        $profession,
        $min_monthly_income,
        $division_id,
        $district_id,
        $upazila_id,
        $user_id

    );

}else{

    mysqli_stmt_bind_param(

        $stmt,

        "isiiddssssdiii",

        $user_id,
        $preferred_gender,
        $min_age,
        $max_age,
        $min_height_cm,
        $max_height_cm,
        $religion,
        $marital_status,
        $education,
        $profession,
        $min_monthly_income,
        $division_id,
        $district_id,
        $upazila_id

    );

}

mysqli_stmt_execute($stmt);

header("Location: ../dashboard.php");
exit;

}
?>
<div class="container py-5">

<div class="row justify-content-center">

<div class="col-lg-8">

<div class="card shadow border-0 rounded-4">

<div class="card-body p-5">
$currentStep = 6;

$pageTitle = "Initial Partner Preferences";

$pageDescription = "These are your initial partner preferences. You can update them anytime later from Dashboard.";

include '../includes/wizard_header.php';
<div class="mb-2">

<span>

Step 6 of 6

</span>

<span class="float-end fw-bold text-success">

100%

</span>

</div>

<div class="progress mb-4">

<div

class="progress-bar bg-success"

style="width:100%">

</div>

</div>

<form

method="POST"

autocomplete="off">

<div class="row">

<div class="col-md-4 mb-3">

<label class="form-label">

Preferred Gender

</label>

<select

name="preferred_gender"

class="form-select"

required>

<option value="">

Select

</option>

<option value="Male">

Male

</option>

<option value="Female">

Female

</option>

</select>

</div>

<div class="col-md-4 mb-3">

<label class="form-label">

Minimum Age

</label>

<input

type="number"

name="min_age"

class="form-control"

min="16"

max="50"

required>

</div>

<div class="col-md-4 mb-3">

<label class="form-label">

Maximum Age

</label>

<input

type="number"

name="max_age"

class="form-control"

min="18"

max="80"

required>

</div>

</div>

<div class="row">

    <div class="col-md-6 mb-3">

        <label class="form-label">
            Minimum Height (cm)
        </label>

        <input
            type="number"
            name="min_height_cm"
            class="form-control"
            min="120"
            max="250"
            placeholder="e.g. 160">

    </div>

    <div class="col-md-6 mb-3">

        <label class="form-label">
            Maximum Height (cm)
        </label>

        <input
            type="number"
            name="max_height_cm"
            class="form-control"
            min="120"
            max="250"
            placeholder="e.g. 180">

    </div>

</div>

<div class="row">

    <div class="col-md-4 mb-3">

        <label class="form-label">
            Religion
        </label>

        <select
            name="religion"
            class="form-select">

            <option value="">Any</option>
            <option value="Islam">Islam</option>
            <option value="Hinduism">Hinduism</option>
            <option value="Christianity">Christianity</option>
            <option value="Buddhism">Buddhism</option>
            <option value="Other">Other</option>

        </select>

    </div>

    <div class="col-md-4 mb-3">

        <label class="form-label">
            Marital Status
        </label>

        <select
name="marital_status"
class="form-select">

<option value="">Any</option>

<?php foreach($marital_statuses as $status){ ?>

<option value="<?= $status ?>">

<?= htmlspecialchars($status) ?>

</option>

<?php } ?>

</select>

    </div>

    <div class="col-md-4 mb-3">

        <label class="form-label">
            Education
        </label>

        <select
name="education"
class="form-select">

<option value="">Any</option>

<?php foreach($education_levels as $education){ ?>

<option value="<?= $education ?>">

<?= htmlspecialchars($education) ?>

</option>

<?php } ?>

</select>

    </div>

</div>

<div class="row">

    <div class="col-md-6 mb-3">

        <label class="form-label">
            Profession
        </label>

        <select
name="profession"
class="form-select">

<option value="">Any</option>

<?php foreach($user_professions as $profession){ ?>

<option value="<?= $profession ?>">

<?= htmlspecialchars($profession) ?>

</option>

<?php } ?>

</select>

    </div>

    <div class="col-md-6 mb-3">

        <label class="form-label">
            Minimum Monthly Income
        </label>

        <input
            type="number"
            name="min_monthly_income"
            class="form-control"
            min="0"
            step="1000"
            placeholder="Optional">

    </div>

</div>

<?php

$divisions=mysqli_query($conn,"
SELECT *
FROM divisions
ORDER BY name_bn
");

?>

<div class="row">

    <div class="col-md-4 mb-3">

        <label class="form-label">

            Preferred Division

        </label>

        <select
        name="division"
        id="division"
        class="form-select">

            <option value="">

                Any Division

            </option>

            <?php while($row=mysqli_fetch_assoc($divisions)){ ?>

                <option
                value="<?= $row['id'] ?>">

                    <?= htmlspecialchars($row['name_bn']) ?>

                </option>

            <?php } ?>

        </select>

    </div>

    <div class="col-md-4 mb-3">

        <label class="form-label">

            Preferred District

        </label>

        <select
        name="district"
        id="district"
        class="form-select">

            <option value="">

                Any District

            </option>

        </select>

    </div>

    <div class="col-md-4 mb-3">

        <label class="form-label">

            Preferred Upazila

        </label>

        <select
        name="upazila"
        id="upazila"
        class="form-select">

            <option value="">

                Any Upazila

            </option>

        </select>

    </div>

</div>

<div class="text-end mt-4">

    <button
        type="submit"
        name="save_step6"
        class="btn btn-success px-5">

        Finish Profile

    </button>

</div>
</form>
</div>

</div>

</div>

</div>

</div>

<script>

function loadDistrict(selected=''){

let division=document.getElementById('division').value;

if(division=='') return;

fetch('../ajax/get_districts.php?division_id='+division)

.then(res=>res.text())

.then(data=>{

document.getElementById('district').innerHTML=data;

if(selected!=''){

document.getElementById('district').value=selected;

}

});

}

function loadUpazila(selected=''){

let district=document.getElementById('district').value;

if(district=='') return;

fetch('../ajax/get_upazilas.php?district_id='+district)

.then(res=>res.text())

.then(data=>{

document.getElementById('upazila').innerHTML=data;

if(selected!=''){

document.getElementById('upazila').value=selected;

}

});

}

document.getElementById('division').addEventListener('change',function(){

loadDistrict();

document.getElementById('upazila').innerHTML='<option value="">Select Upazila</option>';

});

document.getElementById('district').addEventListener('change',function(){

loadUpazila();

});


</script>

<?php include '../includes/footer.php'; ?>