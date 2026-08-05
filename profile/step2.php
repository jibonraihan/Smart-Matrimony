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

$success = "";
$error = "";

/*
|--------------------------------------------------------------------------
| Save Step 2
|--------------------------------------------------------------------------
*/

if (isset($_POST['save_step2'])) {

    $father_name        = clean_input($_POST['father_name']);
    $father_profession  = clean_input($_POST['father_profession']);

    $mother_name        = clean_input($_POST['mother_name']);
    $mother_profession  = clean_input($_POST['mother_profession']);

    $guardian_name      = clean_input($_POST['guardian_name']);
    $guardian_relation  = clean_input($_POST['guardian_relation']);
    $guardian_contact   = clean_input($_POST['guardian_contact']);

    $brothers           = (int)$_POST['brothers'];
    $sisters            = (int)$_POST['sisters'];

    $family_type        = clean_input($_POST['family_type']);
    $family_status      = clean_input($_POST['family_status']);

    $siblings = $brothers + $sisters;

    if (
        empty($father_name) ||
        empty($mother_name) ||
        empty($guardian_name) ||
        empty($guardian_relation) ||
        empty($guardian_contact) ||
        empty($family_type) ||
        empty($family_status)
    ) {

        $error = "Please fill all required fields.";

    } else {

        $sql = "UPDATE user_profiles
                SET
                    father_name=?,
                    father_profession=?,
                    mother_name=?,
                    mother_profession=?,
                    guardian_name=?,
                    guardian_relation=?,
                    guardian_contact=?,
                    family_type=?,
                    family_status=?,
                    siblings=?
                WHERE user_id=?";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(

            $stmt,

            "sssssssssii",

            $father_name,
            $father_profession,
            $mother_name,
            $mother_profession,
            $guardian_name,
            $guardian_relation,
            $guardian_contact,
            $family_type,
            $family_status,
            $siblings,
            $user_id

        );

        if (mysqli_stmt_execute($stmt)) {

            header("Location: step3.php");
            exit();

        } else {

            $error = "Database update failed.";

        }

    }

}

/*
|--------------------------------------------------------------------------
| Load Existing Data
|--------------------------------------------------------------------------
*/

$stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM user_profiles WHERE user_id=? LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $user_id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$user = mysqli_fetch_assoc($result);

include '../includes/header.php';
include '../includes/navbar.php';

?>

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-9">

            <div class="card shadow border-0 rounded-4">

                <div class="card-body p-5">

<?php

$currentStep = 2;
$pageTitle = "Family Information";
$pageDescription = "";

include '../includes/wizard_header.php';

?>

                    <?php if($error!=""){ ?>

                        <div class="alert alert-danger">

                            <?= $error; ?>

                        </div>

                    <?php } ?>

                    <form method="POST">

                        <div class="row">

                            <div class="col-md-6 mb-3">

                                <label class="form-label">

                                    Father's Name

                                </label>

                                <input
                                    type="text"
                                    name="father_name"
                                    class="form-control"
                                    value="<?= htmlspecialchars($user['father_name'] ?? '') ?>"
                                    required>

                            </div>

                            <div class="col-md-6 mb-3">

                                <label class="form-label">

                                    Father's Profession

                                </label>

                                <select
    name="father_profession"
    class="form-select">

    <option value="">Select Profession</option>

    <?php foreach($father_professions as $profession){ ?>

        <option
            value="<?= $profession; ?>"
            <?= (($user['father_profession'] ?? '')==$profession)?'selected':''; ?>>

            <?= $profession; ?>

        </option>

    <?php } ?>

</select>

                            </div>

                        </div>

                        <div class="row">

                            <div class="col-md-6 mb-3">

                                <label class="form-label">

                                    Mother's Name

                                </label>

                                <input
                                    type="text"
                                    name="mother_name"
                                    class="form-control"
                                    value="<?= htmlspecialchars($user['mother_name'] ?? '') ?>"
                                    required>

                            </div>

                            <div class="col-md-6 mb-3">

                                <label class="form-label">

                                    Mother's Profession

                                </label>

                                <select
    name="mother_profession"
    class="form-select">

    <option value="">Select Profession</option>

    <?php foreach($mother_professions as $profession){ ?>

        <option
            value="<?= $profession; ?>"
            <?= (($user['mother_profession'] ?? '')==$profession)?'selected':''; ?>>

            <?= $profession; ?>

        </option>

    <?php } ?>

</select>

                            </div>

                        </div>

                        <hr class="my-4">

                        <h5 class="fw-bold">

                           Marriage Guardian (Wali)

                        </h5>

                        <div class="row">

                            <div class="col-md-4 mb-3">

                                <label class="form-label">

                                    Guardian Name

                                </label>

                                <input
                                    type="text"
                                    name="guardian_name"
                                    class="form-control"
                                    value="<?= htmlspecialchars($user['guardian_name'] ?? '') ?>"
                                    required>

                            </div>

                            <div class="col-md-4 mb-3">

                                <label class="form-label">

                                    Relationship

                                </label>

                                <select
                                    class="form-select"
                                    name="guardian_relation"
                                    required>

                                    <option value="">Select</option>

                                    <?php foreach($guardian_relations as $relation){ ?>

<option
value="<?= $relation; ?>"
<?= (($user['guardian_relation'] ?? '')==$relation)?'selected':''; ?>>

<?= $relation; ?>

</option>

<?php } ?>

                                </select>

                            </div>

                            <div class="col-md-4 mb-3">

                                <label class="form-label">

                                    Guardian Mobile

                                </label>

                                <input
                                    type="text"
                                    name="guardian_contact"
                                    maxlength="11"
                                    class="form-control"
                                    value="<?= htmlspecialchars($user['guardian_contact'] ?? '') ?>"
                                    placeholder="01XXXXXXXXX"
                                    required>

                            </div>

                        </div>

                        <hr class="my-4">

                        <h5 class="fw-bold">

                            Family Details

                        </h5>

                        <div class="row">

                            <div class="col-md-3 mb-3">

                                <label class="form-label">

                                    Brothers

                                </label>

                                <input
                                    type="number"
                                    name="brothers"
                                    min="0"
                                    value="0"
                                    class="form-control">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label class="form-label">

                                    Sisters

                                </label>

                                <input
                                    type="number"
                                    name="sisters"
                                    min="0"
                                    value="0"
                                    class="form-control">

                            </div>

                            <div class="col-md-3 mb-3">

                                <label class="form-label">

                                    Family Type

                                </label>

                                <select
                                    class="form-select"
                                    name="family_type"
                                    required>

                                    <option value="">Select</option>

                                    <?php foreach($family_types as $type){ ?>

<option
value="<?= $type; ?>"
<?= (($user['family_type'] ?? '')==$type)?'selected':''; ?>>

<?= $type; ?>

</option>

<?php } ?>

                                </select>

                            </div>

                            <div class="col-md-3 mb-3">

                                <label class="form-label">

                                    Family Status

                                </label>

                                <select
    class="form-select"
    name="family_status"
    required>

    <option value="">Select</option>

    <?php foreach($family_statuses as $status){ ?>

        <option
            value="<?= $status; ?>"
            <?= (($user['family_status'] ?? '')==$status)?'selected':''; ?>>

            <?= $status; ?>

        </option>

    <?php } ?>

</select>

                            </div>

                        </div>
                        <div class="d-flex justify-content-between mt-5">

    <a
        href="step1.php"
        class="btn btn-outline-secondary btn-lg">

        <i class="bi bi-arrow-left-circle"></i>

        Back

    </a>

    <button
        type="submit"
        name="save_step2"
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

<?php

include '../includes/footer.php';

?>