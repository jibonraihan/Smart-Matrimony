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

include '../includes/header.php';
include '../includes/navbar.php';
?>
<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-10">

            <div class="card border-0 shadow-lg rounded-4">

                <div class="card-body p-5">

                    <div class="text-center mb-5">

                        <h2 class="fw-bold text-success">

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

                        <div class="progress" style="height:10px;">

                            <div
                                class="progress-bar bg-success"
                                style="width:0%;">

                            </div>

                        </div>

                    </div>

                    <!-- Step Cards -->

                    <div class="row g-4">

                        <div class="col-md-4">

                            <div class="card h-100 border-success">

                                <div class="card-body text-center">

                                    <i class="bi bi-person-circle display-4 text-success"></i>

                                    <h5 class="mt-3">

                                        Step 1

                                    </h5>

                                    <h6>

                                        Personal Information

                                    </h6>

                                    <small class="text-muted">

                                        Basic personal details.

                                    </small>

                                </div>

                            </div>

                        </div>

                        <div class="col-md-4">

                            <div class="card h-100">

                                <div class="card-body text-center">

                                    <i class="bi bi-people display-4 text-secondary"></i>

                                    <h5 class="mt-3">

                                        Step 2

                                    </h5>

                                    <h6>

                                        Family Information

                                    </h6>

                                    <small class="text-muted">

                                        Parents & Guardian.

                                    </small>

                                </div>

                            </div>

                        </div>

                        <div class="col-md-4">

                            <div class="card h-100">

                                <div class="card-body text-center">

                                    <i class="bi bi-heart display-4 text-secondary"></i>

                                    <h5 class="mt-3">

                                        Step 3

                                    </h5>

                                    <h6>

                                        Lifestyle

                                    </h6>

                                    <small class="text-muted">

                                        Islamic lifestyle.

                                    </small>

                                </div>

                            </div>

                        </div>

                        <div class="col-md-4">

                            <div class="card h-100">

                                <div class="card-body text-center">

                                    <i class="bi bi-geo-alt display-4 text-secondary"></i>

                                    <h5 class="mt-3">

                                        Step 4

                                    </h5>

                                    <h6>

                                        Location

                                    </h6>

                                    <small class="text-muted">

                                        Address information.

                                    </small>

                                </div>

                            </div>

                        </div>

                        <div class="col-md-4">

                            <div class="card h-100">

                                <div class="card-body text-center">

                                    <i class="bi bi-shield-lock display-4 text-secondary"></i>

                                    <h5 class="mt-3">

                                        Step 5

                                    </h5>

                                    <h6>

                                        Privacy

                                    </h6>

                                    <small class="text-muted">

                                        Photo & Visibility.

                                    </small>

                                </div>

                            </div>

                        </div>

                        <div class="col-md-4">

                            <div class="card h-100">

                                <div class="card-body text-center">

                                    <i class="bi bi-patch-question display-4 text-secondary"></i>

                                    <h5 class="mt-3">

                                        Step 6

                                    </h5>

                                    <h6>

                                        Questions

                                    </h6>

                                    <small class="text-muted">

                                        Short personality questions.

                                    </small>

                                </div>

                            </div>

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