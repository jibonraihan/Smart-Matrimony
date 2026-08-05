<?php

require_once '../config/db.php';
require_once '../includes/functions.php';

if(session_status()===PHP_SESSION_NONE){
    session_start();
}

if(!isset($_SESSION['user_id'])){
    header("Location: ../login.php");
    exit;
}

$user_id=$_SESSION['user_id'];

$error="";
$success="";

$stmt=mysqli_prepare($conn,"
SELECT
country,
division_id,
district_id,
upazila_id,
area,
post_office,
postal_code
FROM user_profiles
WHERE user_id=?
LIMIT 1
");

mysqli_stmt_bind_param($stmt,"i",$user_id);
mysqli_stmt_execute($stmt);

$result=mysqli_stmt_get_result($stmt);

$user=mysqli_fetch_assoc($result);

if(isset($_POST['save_step4'])){

$country=clean_input($_POST['country']);
$division_id=(int)$_POST['division_id'];
$district_id=(int)$_POST['district_id'];
$upazila_id=(int)$_POST['upazila_id'];
$area=clean_input($_POST['area']);
$post_office=clean_input($_POST['post_office']);
$postal_code=clean_input($_POST['postal_code']);

$update=mysqli_prepare($conn,"
UPDATE user_profiles
SET
country=?,
division_id=?,
district_id=?,
upazila_id=?,
area=?,
post_office=?,
postal_code=?
WHERE user_id=?
");

mysqli_stmt_bind_param(

$update,

"siiisssi",

$country,
$division_id,
$district_id,
$upazila_id,
$area,
$post_office,
$postal_code,
$user_id

);

if(mysqli_stmt_execute($update)){

header("Location: step5.php");
exit;

}else{

$error="Address update failed.";

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

<h2 class="fw-bold text-success">

Step 4

</h2>

<p class="text-muted mb-4">

Address Information

</p>

<form method="POST">
    <div class="mb-4">

    <div class="d-flex justify-content-between mb-2">

        <span class="fw-bold">

            Step 4 of 6

        </span>

        <span class="text-success fw-bold">

            67%

        </span>

    </div>

    <div class="progress" style="height:10px;">

        <div
            class="progress-bar bg-success"
            style="width:67%;">

        </div>

    </div>

</div>

<?php

$divisions=mysqli_query($conn,"
SELECT *
FROM divisions
ORDER BY name_en
");

?>

<div class="mb-3">

<label class="form-label">

Country

</label>

<input
type="text"
class="form-control"
name="country"
value="Bangladesh"
readonly>

</div>

<div class="row">

<div class="col-md-4 mb-3">

<label class="form-label">

Division

</label>

<select
name="division_id"
id="division"
class="form-select"
required>

<option value="">

Select Division

</option>

<?php while($row=mysqli_fetch_assoc($divisions)){ ?>

<option
value="<?= $row['id'] ?>"

<?= (($user['division_id']??'')==$row['id'])?'selected':''; ?>

>

<?= htmlspecialchars($row['name_bn']) ?>

</option>

<?php } ?>

</select>

</div>

<div class="col-md-4 mb-3">

<label class="form-label">

District

</label>

<select
name="district_id"
id="district"
class="form-select"
required>

<option value="">

Select District

</option>

</select>

</div>

<div class="col-md-4 mb-3">

<label class="form-label">

Upazila

</label>

<select
name="upazila_id"
id="upazila"
class="form-select"
required>

<option value="">

Select Upazila

</option>

</select>

</div>

</div>

<div class="mb-3">

<label class="form-label">

Area / Village

</label>

<input
type="text"
class="form-control"
name="area"
value="<?= htmlspecialchars($user['area'] ?? '') ?>">

</div>

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label">

Post Office

</label>

<input
type="text"
class="form-control"
name="post_office"
value="<?= htmlspecialchars($user['post_office'] ?? '') ?>">

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Postal Code

</label>

<input
type="text"
class="form-control"
name="postal_code"
value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>">

</div>

</div>

<div class="d-flex justify-content-between mt-4">

    <a
        href="step3.php"
        class="btn btn-outline-secondary btn-lg">

        <i class="bi bi-arrow-left-circle"></i>

        Back

    </a>

    <button
        type="submit"
        name="save_step4"
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

window.onload=function(){

<?php if(!empty($user['division_id'])){ ?>

loadDistrict('<?= $user['district_id'] ?>');

<?php } ?>

setTimeout(function(){

<?php if(!empty($user['district_id'])){ ?>

loadUpazila('<?= $user['upazila_id'] ?>');

<?php } ?>

},400);

}

</script>

<?php

include '../includes/footer.php';

?>