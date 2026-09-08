<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {

    header("Location: ../login.php");
    exit();

}

$user_id = $_SESSION['user_id'];

$page_css = 'assets/css/create-profile.css';

include '../includes/header.php';
include '../includes/navbar.php';
?>
<div class="container py-5 create-profile-page">

    <div class="create-profile-back">
        <a href="view_profile.php?user_id=<?= (int) $user_id ?>" class="create-profile-back-link">
            <i class="bi bi-arrow-left"></i> Back to My Profile
        </a>
    </div>

    <div class="row justify-content-center">

        <div class="col-lg-10">

            <div class="card border-0 shadow-lg rounded-4">

                <div class="card-body p-5">

                    <div class="text-center mb-5">

                        <h2 class="fw-bold text-success create-profile-title">

                            Complete Your Matrimony Profile

                        </h2>

                        <p class="text-muted">

                            Complete your profile to receive accurate halal marriage matches.

                        </p>

                    </div>

                    <!-- Progress -->

                    <div class="mb-5">

                        <div class="d-flex justify-content-between mb-2">

                            <span class="fw-semibold">

                                Profile Completion

                            </span>

                            <span class="text-success fw-bold">

                                0%

                            </span>

                        </div>

                        <div class="create-profile-progress progress" role="progressbar" aria-label="Profile completion" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">

                            <div class="progress-bar bg-success" style="width:0%;"></div>

                        </div>

                    </div>

                    <!-- Step Cards -->

                    <div class="row g-4">
                        <div class="col-md-4">

                            <a href="step1.php" class="create-step-card step-card-active" aria-label="Go to Step 1: Personal Information">

                                <div class="create-step-card-body text-center">

                                    <span class="create-step-icon">
                                        <i class="bi bi-person-circle"></i>
                                    </span>

                                    <span class="create-step-number">Step 1</span>

                                    <span class="create-step-title">Personal Information</span>

                                    <span class="create-step-description">Basic personal details.</span>

                                    <span class="create-step-link">Open Step <i class="bi bi-arrow-right"></i></span>

                                </div>

                            </a>

                        </div>
                        <div class="col-md-4">

                            <a href="step2.php" class="create-step-card " aria-label="Go to Step 2: Family Information">

                                <div class="create-step-card-body text-center">

                                    <span class="create-step-icon">
                                        <i class="bi bi-people"></i>
                                    </span>

                                    <span class="create-step-number">Step 2</span>

                                    <span class="create-step-title">Family Information</span>

                                    <span class="create-step-description">Parents & Guardian.</span>

                                    <span class="create-step-link">Open Step <i class="bi bi-arrow-right"></i></span>

                                </div>

                            </a>

                        </div>
                        <div class="col-md-4">

                            <a href="step3.php" class="create-step-card " aria-label="Go to Step 3: Lifestyle">

                                <div class="create-step-card-body text-center">

                                    <span class="create-step-icon">
                                        <i class="bi bi-heart"></i>
                                    </span>

                                    <span class="create-step-number">Step 3</span>

                                    <span class="create-step-title">Lifestyle</span>

                                    <span class="create-step-description">Religious & Cultural lifestyle.</span>

                                    <span class="create-step-link">Open Step <i class="bi bi-arrow-right"></i></span>

                                </div>

                            </a>

                        </div>
                        <div class="col-md-4">

                            <a href="step4.php" class="create-step-card " aria-label="Go to Step 4: Location">

                                <div class="create-step-card-body text-center">

                                    <span class="create-step-icon">
                                        <i class="bi bi-geo-alt"></i>
                                    </span>

                                    <span class="create-step-number">Step 4</span>

                                    <span class="create-step-title">Location</span>

                                    <span class="create-step-description">Address information.</span>

                                    <span class="create-step-link">Open Step <i class="bi bi-arrow-right"></i></span>

                                </div>

                            </a>

                        </div>
                        <div class="col-md-4">

                            <a href="step5.php" class="create-step-card " aria-label="Go to Step 5: Privacy">

                                <div class="create-step-card-body text-center">

                                    <span class="create-step-icon">
                                        <i class="bi bi-shield-lock"></i>
                                    </span>

                                    <span class="create-step-number">Step 5</span>

                                    <span class="create-step-title">Privacy</span>

                                    <span class="create-step-description">Photo & Visibility.</span>

                                    <span class="create-step-link">Open Step <i class="bi bi-arrow-right"></i></span>

                                </div>

                            </a>

                        </div>
                        <div class="col-md-4">

                            <a href="step6.php" class="create-step-card " aria-label="Go to Step 6: Questions">

                                <div class="create-step-card-body text-center">

                                    <span class="create-step-icon">
                                        <i class="bi bi-patch-question"></i>
                                    </span>

                                    <span class="create-step-number">Step 6</span>

                                    <span class="create-step-title">Questions</span>

                                    <span class="create-step-description">Partner preferences & QnA.</span>

                                    <span class="create-step-link">Open Step <i class="bi bi-arrow-right"></i></span>

                                </div>

                            </a>

                        </div>

                    </div>

                    <div class="text-center mt-5">

                        <a
                            href="step1.php"
                            class="btn btn-success btn-lg px-5">

                            Start Profile

                            <i class="bi bi-arrow-right-circle ms-2"></i>

                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<?php

include '../includes/footer.php';

?>