<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';
require_once '../includes/language.php';
require_once '../includes/profile_completion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {

    header("Location: ../login.php");
    exit();

}

$user_id = (int) $_SESSION['user_id'];
$completion = sm_get_profile_completion($conn, $user_id);
$progress = max(0, min(100, (int)($completion['percentage'] ?? 0)));
$completed_steps = $completion['steps'] ?? [];

$page_css = 'assets/css/create-profile.css';

include '../includes/header.php';
include '../includes/navbar.php';
?>
<div class="container py-5 create-profile-page">

    <div class="create-profile-back">
        <a href="view_profile.php?user_id=<?= (int) $user_id ?>" class="create-profile-back-link">
            <i class="bi bi-arrow-left"></i> <?= sm_text('Back to My Profile', 'আমার প্রোফাইলে ফিরে যান') ?>
        </a>
    </div>

    <div class="row justify-content-center">

        <div class="col-lg-10">

            <div class="card border-0 shadow-lg rounded-4">

                <div class="card-body p-5">

                    <div class="text-center mb-5">

                        <h2 class="fw-bold text-success create-profile-title">

                            <?= sm_text('Complete Your Matrimony Profile', 'আপনার বিবাহের প্রোফাইল সম্পূর্ণ করুন') ?>

                        </h2>

                        <p class="text-muted">

                            <?= sm_text('Complete your profile to receive accurate halal marriage matches.', 'সঠিক ও উপযুক্ত হালাল জীবনসঙ্গী খুঁজে পেতে আপনার প্রোফাইল সম্পূর্ণ করুন।') ?>

                        </p>

                    </div>

                    <!-- Progress -->

                    <div class="mb-5">

                        <div class="d-flex justify-content-between mb-2">

                            <span class="fw-semibold">

                                <?= sm_text('Profile Completion', 'প্রোফাইল সম্পন্ন') ?>

                            </span>

                            <span class="text-success fw-bold">

                                <?= $current_language === 'en' ? $progress . '% complete' : strtr((string)$progress, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']) . '% সম্পন্ন' ?>

                            </span>

                        </div>

                        <div class="create-profile-progress progress" role="progressbar" aria-label="Profile completion" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100">

                            <div class="progress-bar bg-success" style="width:<?= $progress ?>%;"></div>

                        </div>

                    </div>

                    <!-- Step Cards -->

                    <div class="row g-4">
                        <div class="col-md-4">

                            <a href="step1.php" class="create-step-card step-card-active" aria-label="<?= sm_text('Go to Step 1: Personal Information', 'ধাপ ১: ব্যক্তিগত তথ্য খুলুন') ?>">

                                <div class="create-step-card-body text-center">

                                    <span class="create-step-icon">
                                        <i class="bi bi-person-circle"></i>
                                    </span>

                                    <span class="create-step-number"><?= sm_text('Step 1', 'ধাপ ১') ?></span>

                                    <span class="create-step-title"><?= sm_text('Personal Information', 'ব্যক্তিগত তথ্য') ?></span>

                                    <span class="create-step-description"><?= sm_text('Basic personal details.', 'ব্যক্তিগত তথ্যের প্রাথমিক বিবরণ।') ?></span>

                                    <span class="create-step-link"><?= sm_text('Open Step', 'ধাপটি খুলুন') ?> <i class="bi bi-arrow-right"></i></span>

                                </div>

                            </a>

                        </div>
                        <div class="col-md-4">

                            <a href="step2.php" class="create-step-card " aria-label="<?= sm_text('Go to Step 2: Family Information', 'ধাপ ২: পারিবারিক তথ্য খুলুন') ?>">

                                <div class="create-step-card-body text-center">

                                    <span class="create-step-icon">
                                        <i class="bi bi-people"></i>
                                    </span>

                                    <span class="create-step-number"><?= sm_text('Step 2', 'ধাপ ২') ?></span>

                                    <span class="create-step-title"><?= sm_text('Family Information', 'পারিবারিক তথ্য') ?></span>

                                    <span class="create-step-description"><?= sm_text('Parents & Guardian.', 'বাবা-মা ও অভিভাবকের তথ্য।') ?></span>

                                    <span class="create-step-link"><?= sm_text('Open Step', 'ধাপটি খুলুন') ?> <i class="bi bi-arrow-right"></i></span>

                                </div>

                            </a>

                        </div>
                        <div class="col-md-4">

                            <a href="step3.php" class="create-step-card " aria-label="<?= sm_text('Go to Step 3: Lifestyle', 'ধাপ ৩: জীবনযাপন খুলুন') ?>">

                                <div class="create-step-card-body text-center">

                                    <span class="create-step-icon">
                                        <i class="bi bi-heart"></i>
                                    </span>

                                    <span class="create-step-number"><?= sm_text('Step 3', 'ধাপ ৩') ?></span>

                                    <span class="create-step-title"><?= sm_text('Lifestyle', 'জীবনযাপন') ?></span>

                                    <span class="create-step-description"><?= sm_text('Religious & Cultural lifestyle.', 'ধর্মীয় ও সাংস্কৃতিক জীবনযাপনের তথ্য।') ?></span>

                                    <span class="create-step-link"><?= sm_text('Open Step', 'ধাপটি খুলুন') ?> <i class="bi bi-arrow-right"></i></span>

                                </div>

                            </a>

                        </div>
                        <div class="col-md-4">

                            <a href="step4.php" class="create-step-card " aria-label="<?= sm_text('Go to Step 4: Location', 'ধাপ ৪: ঠিকানা খুলুন') ?>">

                                <div class="create-step-card-body text-center">

                                    <span class="create-step-icon">
                                        <i class="bi bi-geo-alt"></i>
                                    </span>

                                    <span class="create-step-number"><?= sm_text('Step 4', 'ধাপ ৪') ?></span>

                                    <span class="create-step-title"><?= sm_text('Location', 'ঠিকানা') ?></span>

                                    <span class="create-step-description"><?= sm_text('Address information.', 'বর্তমান ও স্থায়ী ঠিকানার তথ্য।') ?></span>

                                    <span class="create-step-link"><?= sm_text('Open Step', 'ধাপটি খুলুন') ?> <i class="bi bi-arrow-right"></i></span>

                                </div>

                            </a>

                        </div>
                        <div class="col-md-4">

                            <a href="step5.php" class="create-step-card " aria-label="<?= sm_text('Go to Step 5: Privacy', 'ধাপ ৫: গোপনীয়তা খুলুন') ?>">

                                <div class="create-step-card-body text-center">

                                    <span class="create-step-icon">
                                        <i class="bi bi-shield-lock"></i>
                                    </span>

                                    <span class="create-step-number"><?= sm_text('Step 5', 'ধাপ ৫') ?></span>

                                    <span class="create-step-title"><?= sm_text('Privacy', 'গোপনীয়তা') ?></span>

                                    <span class="create-step-description"><?= sm_text('Photo & Visibility.', 'ছবি ও প্রোফাইলের দৃশ্যমানতা।') ?></span>

                                    <span class="create-step-link"><?= sm_text('Open Step', 'ধাপটি খুলুন') ?> <i class="bi bi-arrow-right"></i></span>

                                </div>

                            </a>

                        </div>
                        <div class="col-md-4">

                            <a href="step6.php" class="create-step-card " aria-label="<?= sm_text('Go to Step 6: Questions', 'ধাপ ৬: প্রশ্নোত্তর খুলুন') ?>">

                                <div class="create-step-card-body text-center">

                                    <span class="create-step-icon">
                                        <i class="bi bi-patch-question"></i>
                                    </span>

                                    <span class="create-step-number"><?= sm_text('Step 6', 'ধাপ ৬') ?></span>

                                    <span class="create-step-title"><?= sm_text('Questions', 'প্রশ্নোত্তর') ?></span>

                                    <span class="create-step-description"><?= sm_text('Partner preferences & QnA.', 'জীবনসঙ্গীর পছন্দ ও প্রশ্নোত্তর।') ?></span>

                                    <span class="create-step-link"><?= sm_text('Open Step', 'ধাপটি খুলুন') ?> <i class="bi bi-arrow-right"></i></span>

                                </div>

                            </a>

                        </div>

                    </div>

                    <div class="text-center mt-5">

                        <a
                            href="step1.php"
                            class="btn btn-success btn-lg px-5">

                            <?= sm_text('Start Profile', 'প্রোফাইল শুরু করুন') ?>

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