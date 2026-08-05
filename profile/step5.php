<?php

require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/image_helper.php';

if(session_status()==PHP_SESSION_NONE){
    session_start();
}

if(!isset($_SESSION['user_id'])){
    header("Location: ../login.php");
    exit;
}

$user_id=$_SESSION['user_id'];

$stmt=mysqli_prepare($conn,"
SELECT
photo,
photo_visibility,
blur_photo
FROM user_profiles
WHERE user_id=?
LIMIT 1
");

mysqli_stmt_bind_param($stmt,"i",$user_id);

mysqli_stmt_execute($stmt);

$result=mysqli_stmt_get_result($stmt);

$user=mysqli_fetch_assoc($result);

$error="";
$success="";

include '../includes/header.php';
include '../includes/navbar.php';

if(isset($_POST['remove_photo'])){

    if(
        !empty($user['photo']) &&
        file_exists("../uploads/profile/".$user['photo'])
    ){

        unlink("../uploads/profile/".$user['photo']);

    }

    $stmt=mysqli_prepare($conn,"
    UPDATE user_profiles
    SET photo=NULL
    WHERE user_id=?
    ");

    mysqli_stmt_bind_param($stmt,"i",$user_id);

    mysqli_stmt_execute($stmt);

    header("Location: step5.php");

    exit;

}

if(isset($_POST['save_step5'])){

    $photo_visibility = clean_input($_POST['photo_visibility']);
    $blur_photo = clean_input($_POST['blur_photo']);

    $photo_name = $user['photo'] ?? '';

    if(isset($_FILES['photo']) && $_FILES['photo']['error']==0){

        $allowed = ['jpg','jpeg','png','webp'];
        $file_name = $_FILES['photo']['name'];
        $file_tmp  = $_FILES['photo']['tmp_name'];  
        $file_size = $_FILES['photo']['size'];
        $allowedMime = [

'image/jpeg',

'image/png',

'image/webp'

];

$mime = mime_content_type($file_tmp);

if(!in_array($mime,$allowedMime)){

    $error = "Invalid image format.";

}

        $extension = strtolower(pathinfo($file_name,PATHINFO_EXTENSION));

        if(!in_array($extension,$allowed)){

            $error = "Only JPG, JPEG, PNG and WEBP are allowed.";

        }

        elseif($file_size > 20*1024*1024){

            $error = "Maximum file size is 20 MB.";

        }

        else{

    $photo_name = resizeAndSaveImage($file_tmp,$user_id);

if($photo_name===false){

    $error="Failed to process image.";

}

        }

    }

    if(empty($error)){

        $update = mysqli_prepare($conn,"
        UPDATE user_profiles
        SET
        photo=?,
        photo_visibility=?,
        blur_photo=?
        WHERE user_id=?
        ");

        mysqli_stmt_bind_param(

            $update,

            "sssi",

            $photo_name,

            $photo_visibility,

            $blur_photo,

            $user_id

        );

        if(mysqli_stmt_execute($update)){

            header("Location: step6.php");
            exit;

        }else{

            $error="Failed to save.";

        }

    }

}
?>

<div class="container py-5">

<div class="row justify-content-center">

<div class="col-lg-8">

<div class="card shadow border-0 rounded-4">

<div class="card-body p-5">

<?php if(!empty($error)){ ?>

<div
id="serverError"
class="alert alert-danger">

<?= $error ?>

</div>

<?php } ?>

<?php if(!empty($success)){ ?>

<div class="alert alert-success">

<?= $success ?>

</div>

<?php } ?>
<h2 class="fw-bold text-success">

Step 5

</h2>

<p class="text-muted mb-4">

Profile Photo & Privacy

</p>

<div class="mb-4">

<div class="d-flex justify-content-between">

<span>

Step 5 of 6

</span>

<span class="fw-bold text-success">

83%

</span>

</div>

<div class="progress mt-2" style="height:10px;">

<div
class="progress-bar bg-success"
style="width:83%;">

</div>

</div>

</div>

<form
method="POST"
enctype="multipart/form-data">

<div
class="text-center mb-4 position-relative"
style="width:180px;margin:auto;">

<?php

$preview="../assets/images/default-avatar.png";

$hasPhoto=false;

if(
!empty($user['photo']) &&
file_exists("../uploads/profile/".$user['photo'])
){

$preview="../uploads/profile/".$user['photo'];

$hasPhoto=true;

}


?>

<img

src="<?= $preview ?>"

id="preview"

class="rounded-circle border border-3 border-success shadow"

style="

width:180px;

height:180px;

object-fit:cover;

cursor:pointer;

">

<div

id="cameraIcon"

class="position-absolute bottom-0 end-0 bg-success text-white rounded-circle shadow"

style="

width:45px;

height:45px;

line-height:45px;

text-align:center;

cursor:pointer;

border:3px solid #fff;

">

<i class="bi bi-camera-fill"></i>

</div>

</div>

<div class="mb-4">

<label class="form-label">

Profile Photo

<span class="text-danger">*</span>

</label>

<input

type="file"

name="photo"

id="photo"

class="d-none"

accept=".jpg,.jpeg,.png,.webp"

<?= $hasPhoto ? '' : 'required' ?>>
<div

id="photoError"

class="alert alert-danger mt-2 py-2 d-none">

</div>
<div class="text-center mt-3">

<button
type="button"
id="changePhoto"
class="btn btn-outline-success">

<i class="bi bi-camera-fill"></i>

<?= $hasPhoto ? 'Change Photo' : 'Upload Photo' ?>

</button>

</div>
<?php if($hasPhoto){ ?>

<div class="text-center mt-2">

<button

type="submit"

name="remove_photo"

class="btn btn-outline-danger btn-sm"

onclick="return confirm('Are you sure you want to remove your photo?')">

<i class="bi bi-trash"></i>

Remove Photo

</button>

</div>

<?php } ?>
</div>

<div class="mb-3">

<label class="form-label">

Photo Visibility

</label>

<select

name="photo_visibility"

class="form-select">

<option value="Everyone"
<?= (($user['photo_visibility']??'')=='Everyone')?'selected':''; ?>>

Everyone

</option>

<option value="Verified Users"
<?= (($user['photo_visibility']??'')=='Verified Users')?'selected':''; ?>>

Verified Users

</option>

<option value="Matched Users"
<?= (($user['photo_visibility']??'')=='Matched Users')?'selected':''; ?>>

Matched Users

</option>

<option value="Hidden"
<?= (($user['photo_visibility']??'')=='Hidden')?'selected':''; ?>>

Hidden

</option>

</select>

</div>

<div class="mb-4">

<label class="form-label">

Blur Profile Photo

</label>

<select

name="blur_photo"

class="form-select">

<option value="No"
<?= (($user['blur_photo']??'No')=='No')?'selected':''; ?>>

No

</option>

<option value="Yes"
<?= (($user['blur_photo']??'')=='Yes')?'selected':''; ?>>

Yes

</option>

</select>

</div>

<div class="form-check mb-4">

<input

class="form-check-input"

type="checkbox"

id="confirmPhoto"
name="confirm_photo"
required>

<label

class="form-check-label"

for="confirmPhoto">

আমি নিশ্চিত করছি যে আপলোড করা ছবিটি আমার নিজের এবং সাম্প্রতিক ছবি।

</label>

</div>

<div class="alert alert-info">

<b>Photo Guidelines</b>

<ul class="mb-0 mt-2">

<li>পরিষ্কার মুখ দেখা যায় এমন ছবি দিন।</li>

<li>গ্রুপ ছবি ব্যবহার করবেন না।</li>

<li>সানগ্লাস বা মাস্ক পরা ছবি এড়িয়ে চলুন।</li>

<li>সাম্প্রতিক ছবি ব্যবহার করুন।</li>

<li>JPG / PNG / WEBP (Maximum 20MB)</li>

</ul>

</div>

<div class="d-flex justify-content-between mt-4">

<a

href="step4.php"

class="btn btn-outline-secondary btn-lg">

<i class="bi bi-arrow-left-circle"></i>

Back

</a>

<button

type="submit"

name="save_step5"

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

const photoInput = document.getElementById("photo");
const preview = document.getElementById("preview");
const defaultImage = preview.src;

photoInput.addEventListener("change", function () {

    const serverError = document.getElementById("serverError");

    if(serverError){
        serverError.remove();
    }

    const errorBox = document.getElementById("photoError");

    errorBox.classList.add("d-none");
    errorBox.innerHTML="";

    const file = this.files[0];

    if(!file){
        preview.src = defaultImage;
        return;
    }

    if(file.size > 20 * 1024 * 1024){

        errorBox.classList.remove("d-none");
        errorBox.innerHTML = "Maximum file size is 20 MB.";

        this.value = "";
        preview.src = defaultImage;
        return;
    }

    const allowed = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    if(!allowed.includes(file.type)){

        errorBox.classList.remove("d-none");
        errorBox.innerHTML = "Only JPG, PNG and WEBP are allowed.";

        this.value = "";
        preview.src = defaultImage;
        return;
    }

    preview.src = URL.createObjectURL(file);

});

preview.onclick = function () {
    photoInput.click();
};

document.getElementById("changePhoto").onclick = function () {
    photoInput.click();
};

document.getElementById("cameraIcon").onclick = function () {
    photoInput.click();
};

</script>

<?php include '../includes/footer.php'; ?>