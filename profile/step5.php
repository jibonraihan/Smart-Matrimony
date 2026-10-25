<?php

require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';
require_once '../includes/dropdowns.php';
require_once '../includes/image_helper.php';
require_once '../includes/profile_completion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$error = '';

$stmt = mysqli_prepare($conn, '
    SELECT photo, photo_visibility, profile_visibility, hearing_check_passed, vision_check_passed
    FROM user_profiles
    WHERE user_id=?
    LIMIT 1
');
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result) ?: [];
mysqli_stmt_close($stmt);

$hearing_check_passed = !empty($user['hearing_check_passed']);
$vision_check_passed = !empty($user['vision_check_passed']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['step5_hearing_code'])) {
    $_SESSION['step5_hearing_code'] = (string) random_int(1, 9) . (string) random_int(1, 9) . (string) random_int(1, 9) . (string) random_int(1, 9);
}
$step5_hearing_code = (string) $_SESSION['step5_hearing_code'];

$profile_media_id = 0;
if (!empty($user['photo'])) {
    $media_stmt = mysqli_prepare($conn, '
        SELECT media_id
        FROM profile_media
        WHERE user_id=?
          AND media_type="Profile Photo"
          AND status="Active"
        ORDER BY media_id DESC
        LIMIT 1
    ');
    if ($media_stmt) {
        mysqli_stmt_bind_param($media_stmt, 'i', $user_id);
        mysqli_stmt_execute($media_stmt);
        $media_row = mysqli_fetch_assoc(mysqli_stmt_get_result($media_stmt));
        mysqli_stmt_close($media_stmt);
        $profile_media_id = (int) ($media_row['media_id'] ?? 0);
    }
}

$voice_media_id = 0;
$voice_visibility = 'Verified Users';
$voice_duration = 0;
$voice_status = 'none';

$voice_stmt = mysqli_prepare($conn, '
    SELECT media_id, visibility, duration_seconds, status
    FROM profile_media
    WHERE user_id=?
      AND media_type="Voice Introduction"
      AND status="Active"
    ORDER BY media_id DESC
    LIMIT 1
');
if ($voice_stmt) {
    mysqli_stmt_bind_param($voice_stmt, 'i', $user_id);
    mysqli_stmt_execute($voice_stmt);
    $voice_row = mysqli_fetch_assoc(mysqli_stmt_get_result($voice_stmt));
    mysqli_stmt_close($voice_stmt);
    if ($voice_row) {
        $voice_media_id = (int) ($voice_row['media_id'] ?? 0);
        $voice_visibility = (string) ($voice_row['visibility'] ?? 'Verified Users');
        $voice_duration = (int) ($voice_row['duration_seconds'] ?? 0);
        $voice_status = 'active';
    }
}

$video_media_id = 0;
$video_visibility = 'Verified Users';
$video_duration = 0;

$video_stmt = mysqli_prepare($conn, '
    SELECT media_id, visibility, duration_seconds
    FROM profile_media
    WHERE user_id=?
      AND media_type="Video Introduction"
      AND status="Active"
    ORDER BY media_id DESC
    LIMIT 1
');
if ($video_stmt) {
    mysqli_stmt_bind_param($video_stmt, 'i', $user_id);
    mysqli_stmt_execute($video_stmt);
    $video_row = mysqli_fetch_assoc(mysqli_stmt_get_result($video_stmt));
    mysqli_stmt_close($video_stmt);
    if ($video_row) {
        $video_media_id = (int) ($video_row['media_id'] ?? 0);
        $video_visibility = (string) ($video_row['visibility'] ?? 'Verified Users');
        $video_duration = (int) ($video_row['duration_seconds'] ?? 0);
    }
}

$visibility_options = ['Everyone', 'Verified Users', 'Matched Users', 'Hidden'];
$profile_visibility = (string) ($user['profile_visibility'] ?? 'Public');

// Step 5 display-only labels; stored option values remain unchanged.
if (!function_exists('sm_step5_visibility_label')) {
    function sm_step5_visibility_label(string $value): string
    {
        $labels = [
            'Public' => ['Public', 'সবার জন্য উন্মুক্ত'],
            'Hidden' => ['Hidden', 'গোপন'],
            'Everyone' => ['Everyone', 'সবাই'],
            'Verified Users' => ['Verified Users', 'যাচাইকৃত ব্যবহারকারী'],
            'Matched Users' => ['Matched Users', 'ম্যাচ হওয়া ব্যবহারকারী'],
            'Private' => ['Private', 'গোপন'],
        ];
        $pair = $labels[$value] ?? [$value, $value];
        return sm_text($pair[0], $pair[1]);
    }
}

// Translate permanent Step 5 feedback messages according to the selected language.
if (!function_exists('sm_step5_feedback_message')) {
    function sm_step5_feedback_message(string $message): string
    {
        $translations = [
            'Please select a valid profile visibility option.' => 'প্রোফাইলের দৃশ্যমানতার জন্য সঠিক অপশন নির্বাচন করুন।',
            'Please select a valid photo visibility option.' => 'ছবির দৃশ্যমানতার জন্য সঠিক অপশন নির্বাচন করুন।',
            'Please select a valid voice visibility option.' => 'কণ্ঠ পরিচিতির দৃশ্যমানতার জন্য সঠিক অপশন নির্বাচন করুন।',
            'Please select a valid video visibility option.' => 'ভিডিও পরিচিতির দৃশ্যমানতার জন্য সঠিক অপশন নির্বাচন করুন।',
            'Please complete both the Hearing Check and Visual Check before continuing.' => 'এগিয়ে যাওয়ার আগে শ্রবণ ও দৃষ্টিশক্তি—দুটি পরীক্ষাই সম্পন্ন করুন।',
            'The photo upload failed. Please try again.' => 'ছবি আপলোড করা যায়নি। অনুগ্রহ করে আবার চেষ্টা করুন।',
            'Only JPG, JPEG, PNG and WEBP images are allowed.' => 'শুধু JPG, JPEG, PNG ও WEBP ছবি ব্যবহার করা যাবে।',
            'Maximum photo size is 20 MB.' => 'ছবির সর্বোচ্চ আকার ২০ এমবি।',
            'Unable to process the photo. Please try another image.' => 'ছবিটি প্রক্রিয়া করা যায়নি। অনুগ্রহ করে অন্য ছবি ব্যবহার করুন।',
            'Unable to prepare the profile update.' => 'প্রোফাইল আপডেট প্রস্তুত করা যায়নি।',
            'Unable to save Step 5 right now. Please try again.' => 'এখন ধাপ ৫ সংরক্ষণ করা যাচ্ছে না। অনুগ্রহ করে আবার চেষ্টা করুন।',
        ];
        return isset($translations[$message]) ? sm_text($message, $translations[$message]) : $message;
    }
}

function sync_profile_media(mysqli $conn, int $user_id, string $file_path, string $visibility, string $status = 'Active'): void
{
    $media_visibility = ($visibility === 'Hidden') ? 'Private' : $visibility;

    $find = mysqli_prepare($conn, '
        SELECT media_id
        FROM profile_media
        WHERE user_id=? AND media_type="Profile Photo"
        ORDER BY media_id DESC
        LIMIT 1
    ');
    if (!$find) {
        return;
    }

    mysqli_stmt_bind_param($find, 'i', $user_id);
    mysqli_stmt_execute($find);
    $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($find));
    mysqli_stmt_close($find);

    if ($existing) {
        $update = mysqli_prepare($conn, '
            UPDATE profile_media
            SET file_path=?, visibility=?, status=?
            WHERE media_id=?
        ');
        if ($update) {
            $media_id = (int) $existing['media_id'];
            mysqli_stmt_bind_param($update, 'sssi', $file_path, $media_visibility, $status, $media_id);
            mysqli_stmt_execute($update);
            mysqli_stmt_close($update);
        }
        return;
    }

    $insert = mysqli_prepare($conn, '
        INSERT INTO profile_media (user_id, media_type, file_path, visibility, status)
        VALUES (?, "Profile Photo", ?, ?, ?)
    ');
    if ($insert) {
        mysqli_stmt_bind_param($insert, 'isss', $user_id, $file_path, $media_visibility, $status);
        mysqli_stmt_execute($insert);
        mysqli_stmt_close($insert);
    }
}

if (isset($_POST['remove_photo'])) {
    $old_photo = trim((string) ($user['photo'] ?? ''));

    if ($old_photo !== '') {
        $old_path = '../uploads/profile/' . basename($old_photo);
        if (is_file($old_path)) {
            @unlink($old_path);
        }
    }

    $remove = mysqli_prepare($conn, 'UPDATE user_profiles SET photo=NULL WHERE user_id=?');
    if ($remove) {
        mysqli_stmt_bind_param($remove, 'i', $user_id);
        mysqli_stmt_execute($remove);
        mysqli_stmt_close($remove);
    }

    $media = mysqli_prepare($conn, '
        UPDATE profile_media
        SET status="Deleted"
        WHERE user_id=? AND media_type="Profile Photo" AND status="Active"
    ');
    if ($media) {
        mysqli_stmt_bind_param($media, 'i', $user_id);
        mysqli_stmt_execute($media);
        mysqli_stmt_close($media);
    }

    header('Location: step5.php?removed=1');
    exit;
}

if (isset($_POST['save_step5'])) {
    $photo_visibility = clean_input($_POST['photo_visibility'] ?? 'Verified Users');
    $profile_visibility_post = clean_input($_POST['profile_visibility'] ?? 'Public');
    $voice_visibility = clean_input($_POST['voice_visibility'] ?? 'Verified Users');
    $voice_visibility_db = ($voice_visibility === 'Hidden') ? 'Private' : $voice_visibility;
    $video_visibility = clean_input($_POST['video_visibility'] ?? 'Verified Users');
    $video_visibility_db = ($video_visibility === 'Hidden') ? 'Private' : $video_visibility;

    if (!in_array($profile_visibility_post, ['Public', 'Hidden'], true)) {
        $error = 'Please select a valid profile visibility option.';
    } elseif (!in_array($photo_visibility, $visibility_options, true)) {
        $error = 'Please select a valid photo visibility option.';
    } elseif (!in_array($voice_visibility_db, ['Everyone', 'Verified Users', 'Matched Users', 'Private'], true)) {
        $error = 'Please select a valid voice visibility option.';
    } elseif (!in_array($video_visibility_db, ['Everyone', 'Verified Users', 'Matched Users', 'Private'], true)) {
        $error = 'Please select a valid video visibility option.';
    } elseif (!$hearing_check_passed || !$vision_check_passed) {
        $error = 'Please complete both the Hearing Check and Visual Check before continuing.';
    }

    $photo_name = trim((string) ($user['photo'] ?? ''));
    $new_photo_uploaded = false;

    if ($error === '' && isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $error = 'The photo upload failed. Please try again.';
        } else {
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
            $allowed_mime = ['image/jpeg', 'image/png', 'image/webp'];
            $file_tmp = $_FILES['photo']['tmp_name'];
            $file_size = (int) $_FILES['photo']['size'];
            $extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $mime = function_exists('mime_content_type') ? mime_content_type($file_tmp) : '';

            if (!in_array($extension, $allowed_extensions, true) || !in_array($mime, $allowed_mime, true)) {
                $error = 'Only JPG, JPEG, PNG and WEBP images are allowed.';
            } elseif ($file_size > 20 * 1024 * 1024) {
                $error = 'Maximum photo size is 20 MB.';
            } else {
                $saved = resizeAndSaveImage($file_tmp, $user_id);
                if ($saved === false) {
                    $error = 'Unable to process the photo. Please try another image.';
                } else {
                    $photo_name = (string) $saved;
                    $new_photo_uploaded = true;
                }
            }
        }
    }

    if ($error === '') {
        $update = mysqli_prepare($conn, '
            UPDATE user_profiles
            SET photo=?, photo_visibility=?, profile_visibility=?
            WHERE user_id=?
        ');

        if (!$update) {
            $error = 'Unable to prepare the profile update.';
        } else {
            mysqli_stmt_bind_param($update, 'sssi', $photo_name, $photo_visibility, $profile_visibility_post, $user_id);
            $ok = mysqli_stmt_execute($update);
            mysqli_stmt_close($update);

            if (!$ok) {
                $error = 'Unable to save Step 5 right now. Please try again.';
            } elseif ($photo_name !== '') {
                sync_profile_media($conn, $user_id, $photo_name, $photo_visibility, 'Active');
                $voice_visibility_stmt = mysqli_prepare($conn, '
                    UPDATE profile_media
                    SET visibility=?
                    WHERE user_id=?
                      AND media_type="Voice Introduction"
                      AND status="Active"
                ');
                if ($voice_visibility_stmt) {
                    mysqli_stmt_bind_param($voice_visibility_stmt, 'si', $voice_visibility_db, $user_id);
                    mysqli_stmt_execute($voice_visibility_stmt);
                    mysqli_stmt_close($voice_visibility_stmt);
                }
                $media_lookup = mysqli_prepare($conn, '
                    SELECT media_id FROM profile_media
                    WHERE user_id=? AND media_type="Profile Photo" AND status="Active"
                    ORDER BY media_id DESC LIMIT 1
                ');
                if ($media_lookup) {
                    mysqli_stmt_bind_param($media_lookup, 'i', $user_id);
                    mysqli_stmt_execute($media_lookup);
                    $media_saved = mysqli_fetch_assoc(mysqli_stmt_get_result($media_lookup));
                    mysqli_stmt_close($media_lookup);
                    $profile_media_id = (int) ($media_saved['media_id'] ?? 0);
                }
            }
        }
    }

    if ($error === '') {
        /* Keep the existing voice introduction's visibility in sync even when no new recording is uploaded. */
        $voice_visibility_stmt = mysqli_prepare($conn, '
            UPDATE profile_media
            SET visibility=?
            WHERE user_id=?
              AND media_type="Voice Introduction"
              AND status="Active"
        ');
        if ($voice_visibility_stmt) {
            mysqli_stmt_bind_param($voice_visibility_stmt, 'si', $voice_visibility_db, $user_id);
            mysqli_stmt_execute($voice_visibility_stmt);
            mysqli_stmt_close($voice_visibility_stmt);
        }
    }

    if ($error === '') {
        /* Keep the existing video introduction's visibility in sync even when no new recording is uploaded. */
        $video_visibility_stmt = mysqli_prepare($conn, '
            UPDATE profile_media
            SET visibility=?
            WHERE user_id=?
              AND media_type="Video Introduction"
              AND status="Active"
        ');
        if ($video_visibility_stmt) {
            mysqli_stmt_bind_param($video_visibility_stmt, 'si', $video_visibility_db, $user_id);
            mysqli_stmt_execute($video_visibility_stmt);
            mysqli_stmt_close($video_visibility_stmt);
        }
    }

    if ($error === '') {
        header('Location: step6.php');
        exit;
    }

    if ($new_photo_uploaded && $photo_name !== ($user['photo'] ?? '')) {
        /* Keep the preview available after validation/save errors. */
        $user['photo'] = $photo_name;
    }
    $user['photo_visibility'] = $photo_visibility;
    $user['profile_visibility'] = $profile_visibility_post;
    $profile_visibility = $profile_visibility_post;
}

$page_css = 'assets/css/profile-step5.css';
include '../includes/header.php';
include '../includes/navbar.php';

$currentStep = 5;
$steps = [
    1 => ['title' => sm_text('Personal', 'ব্যক্তিগত তথ্য'), 'icon' => 'bi-person-circle', 'url' => 'step1.php'],
    2 => ['title' => sm_text('Family', 'পারিবারিক তথ্য'), 'icon' => 'bi-people', 'url' => 'step2.php'],
    3 => ['title' => sm_text('Lifestyle', 'জীবনযাপন ও স্বাস্থ্য'), 'icon' => 'bi-heart', 'url' => 'step3.php'],
    4 => ['title' => sm_text('Location', 'ঠিকানা'), 'icon' => 'bi-geo-alt', 'url' => 'step4.php'],
    5 => ['title' => sm_text('Photo & Checks', 'ছবি ও যাচাইকরণ'), 'icon' => 'bi-shield-lock', 'url' => 'step5.php'],
    6 => ['title' => sm_text('PP & QnA', 'জীবনসঙ্গীর পছন্দ ও প্রশ্নোত্তর'), 'icon' => 'bi-patch-question', 'url' => 'step6.php']
];
$completion = sm_get_profile_completion($conn, $user_id);
                $progress = $completion['percentage'];
$hasPhoto = !empty($user['photo']) && is_file('../uploads/profile/' . basename($user['photo']));
$preview = ($hasPhoto && $profile_media_id > 0) ? 'media.php?id=' . $profile_media_id : '../assets/images/default-avatar.png';
?>

<div class="container py-4 py-lg-5 profile-step5-page">
    <div class="row justify-content-center">
        <div class="col-12 col-xxl-10">
            <div class="step5-shell">
                <header class="step5-wizard-head">
                    <div class="step5-heading-row">
                        <div>
                            <span class="step5-kicker"><?= sm_text('PROFILE BUILDER', 'প্রোফাইল তৈরি') ?></span>
                            <h1><?= sm_text('Step', 'ধাপ') ?> <?= (($GLOBALS['current_language'] ?? 'bn') === 'en' ? $currentStep : strtr((string)$currentStep, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯'])) ?></h1>
                        </div>
                        <a class="step5-logo" href="../dashboard.php" aria-label="Back to dashboard">
                            <img src="<?= BASE_URL ?>assets/images/logo/logo.png" alt="Smart Matrimony">
                        </a>
                    </div>

                    <nav class="step5-step-nav" aria-label="Profile steps">
                        <?php foreach ($steps as $stepNo => $step): ?>
                            <a class="step5-step <?= $stepNo === $currentStep ? 'is-current' : '' ?> <?= !empty($completion['steps'][$stepNo]) ? 'is-completed' : '' ?>"
                               href="<?= htmlspecialchars($step['url']) ?>"
                               aria-current="<?= $stepNo === $currentStep ? 'step' : 'false' ?>">
                                <span class="step5-step-icon"><i class="bi <?= $step['icon'] ?>"></i></span>
                                <span class="step5-step-label"><?= htmlspecialchars($step['title']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>

                    <div class="step5-progress-wrap">
                        <div class="step5-progress-track" role="progressbar" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= sm_text('Profile completion', 'প্রোফাইল সম্পন্নের অগ্রগতি') ?>">
                            <div class="step5-progress-fill" style="width: <?= $progress ?>%"></div>
                        </div>
                        <div class="step5-progress-meta">
                            <span><?= sm_text('Step', 'ধাপ') ?> <?= (($GLOBALS['current_language'] ?? 'bn') === 'en' ? $currentStep : strtr((string)$currentStep, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯'])) ?> / <?= count($steps) ?></span>
                            <strong><?= $progress ?><?= sm_text('% complete', '% সম্পন্ন') ?></strong>
                        </div>
                    </div>
                </header>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger step5-alert" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars(sm_step5_feedback_message($error), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php elseif (isset($_GET['removed'])): ?>
                    <div class="alert alert-success step5-alert step5-remove-alert" role="alert">
                        <i class="bi bi-check-circle me-2"></i><?= sm_text("Profile photo removed successfully.", "প্রোফাইলের ছবি সফলভাবে সরানো হয়েছে।") ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="step5-form">
                    <section class="step5-section">
                        <div class="section-heading">
                            <span class="section-icon"><i class="bi bi-image"></i></span>
                            <div>
                                <h2><?= sm_text('Profile Photo', 'প্রোফাইল ছবি') ?></h2>
                            </div>
                        </div>

                        <div class="photo-uploader">
                            <div class="photo-preview-wrap">
                                <?php if ($hasPhoto && $profile_media_id > 0): ?>
                                    <img src="<?= htmlspecialchars($preview) ?>" id="photoPreview" class="photo-preview" alt="Profile photo preview">
                                    <div id="photoPlaceholder" class="photo-placeholder d-none" aria-hidden="true">
                                        <i class="bi bi-person-fill"></i>
                                        <span><?= sm_text('No photo', 'কোনো ছবি নেই') ?></span>
                                    </div>
                                <?php else: ?>
                                    <div id="photoPlaceholder" class="photo-placeholder" aria-label="No profile photo">
                                        <i class="bi bi-person-fill"></i>
                                        <span><?= sm_text('No photo', 'কোনো ছবি নেই') ?></span>
                                    </div>
                                    <img src="" id="photoPreview" class="photo-preview d-none" alt="Profile photo preview">
                                <?php endif; ?>
                                <button type="button" class="photo-camera" id="cameraButton" aria-label="Choose profile photo">
                                    <i class="bi bi-camera-fill"></i>
                                </button>
                            </div>

                            <div>
                                <div class="photo-actions">
                                    <button type="button" class="btn btn-outline-success" id="choosePhoto">
                                        <i class="bi bi-camera me-1"></i><?= $hasPhoto ? sm_text('Change Photo', 'ছবি পরিবর্তন করুন') : sm_text('Upload Photo', 'ছবি আপলোড করুন') ?>
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-outline-danger <?= $hasPhoto ? '' : 'd-none' ?>"
                                        id="removePhotoButton"
                                        data-has-saved-photo="<?= $hasPhoto ? '1' : '0' ?>"
                                    >
                                        <i class="bi bi-trash me-1"></i><?= sm_text('Remove', 'সরিয়ে ফেলুন') ?>
                                    </button>
                                </div>
                                <div class="photo-status mt-2" id="photoStatus">
                                    <?= $hasPhoto ? sm_text('Your current profile photo is shown above.', 'আপনার বর্তমান প্রোফাইল ছবি উপরে দেখানো হয়েছে।') : sm_text('No profile photo added yet.', 'এখনো কোনো প্রোফাইল ছবি যোগ করা হয়নি।') ?>
                                </div>
                            </div>

                            <input type="file" name="photo" id="photoInput" class="d-none" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <div id="photoError" class="alert alert-danger py-2 px-3 d-none mb-0" role="alert"></div>
                        </div>
                    </section>

                    <section class="step5-section accessibility-checks-section" id="accessibilityChecksSection">
                        <div class="section-heading">
                            <span class="section-icon"><i class="bi bi-universal-access"></i></span>
                            <div>
                                <h2><?= sm_text('Accessibility Checks', 'যাচাইকরণ পরীক্ষা') ?></h2>
                            </div>
                        </div>

                        <div class="accessibility-check-grid">
                            <article class="accessibility-check-card <?= $hearing_check_passed ? 'is-passed' : '' ?>" id="hearingCheckCard">
                                <div class="accessibility-check-head">
                                    <div class="accessibility-check-icon"><i class="bi bi-volume-up-fill"></i></div>
                                    <div>
                                        <h3><?= sm_text('Hearing Check', 'শ্রবণ পরীক্ষা') ?> <span class="required-badge"><?= sm_text('Required', 'আবশ্যক') ?></span></h3>
                                    </div>
                                </div>
                                <div class="hearing-check-body">
                                    <button type="button" class="btn btn-outline-success accessibility-play-btn" id="playHearingCheck">
                                        <i class="bi bi-play-fill"></i> <?= sm_text('Play Audio', 'অডিও শুনুন') ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary d-none" id="replayHearingCheck">
                                        <i class="bi bi-arrow-repeat"></i> <?= sm_text('Play Again', 'আবার শুনুন') ?>
                                    </button>
                                    <div class="accessibility-code-hint" id="hearingCheckPrompt" data-code="<?= htmlspecialchars($step5_hearing_code) ?>"><?= sm_text('Listen carefully to the spoken four-digit code.', 'শোনানো চার অঙ্কের কোডটি মনোযোগ দিয়ে শুনুন।') ?></div>
                                    <div class="accessibility-answer-row">
                                        <label for="hearingCheckAnswer"><?= sm_text('Your answer', 'আপনার উত্তর') ?></label>
                                        <div class="accessibility-answer-controls">
                                            <input type="text" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" id="hearingCheckAnswer" class="form-control" autocomplete="off" placeholder="<?= sm_text('4 digits', '৪টি অঙ্ক') ?>">
                                            <button type="button" class="btn btn-success" id="verifyHearingCheck"><?= sm_text('Verify', 'যাচাই করুন') ?></button>
                                        </div>
                                    </div>
                                    <div class="accessibility-check-status <?= $hearing_check_passed ? 'is-success' : '' ?>" id="hearingCheckStatus" role="status">
                                        <?= $hearing_check_passed ? sm_text('✓ Hearing check passed.', '✓ শ্রবণ পরীক্ষা সফল হয়েছে।') : sm_text('Not completed yet.', 'এখনো সম্পন্ন হয়নি।') ?>
                                    </div>
                                </div>
                            </article>

                            <article class="accessibility-check-card <?= $vision_check_passed ? 'is-passed' : '' ?>" id="visionCheckCard">
                                <div class="accessibility-check-head">
                                    <div class="accessibility-check-icon"><i class="bi bi-eye-fill"></i></div>
                                    <div>
                                        <h3><?= sm_text('Visual Check', 'দৃষ্টিশক্তি পরীক্ষা') ?> <span class="required-badge"><?= sm_text('Required', 'আবশ্যক') ?></span></h3>
                                    </div>
                                </div>
                                <div class="visual-captcha-wrap">
                                    <img src="accessibility_captcha.php?<?= time() ?>" id="visualCaptchaImage" class="visual-captcha-image" alt="<?= sm_text('Visual verification code', 'দৃশ্যমান যাচাইকরণ কোড') ?>">
                                    <button type="button" class="btn btn-outline-secondary accessibility-refresh-btn" id="refreshVisualCaptcha" aria-label="<?= sm_text('Refresh visual verification code', 'যাচাইকরণ কোড নতুন করে নিন') ?>">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </div>
                                <div class="accessibility-answer-row">
                                    <label for="visionCheckAnswer"><?= sm_text('Your answer', 'আপনার উত্তর') ?></label>
                                    <div class="accessibility-answer-controls">
                                        <input type="text" maxlength="5" id="visionCheckAnswer" class="form-control" autocomplete="off" placeholder="<?= sm_text('Enter the code', 'কোড লিখুন') ?>">
                                        <button type="button" class="btn btn-success" id="verifyVisionCheck"><?= sm_text('Verify', 'যাচাই করুন') ?></button>
                                    </div>
                                </div>
                                <div class="accessibility-check-status <?= $vision_check_passed ? 'is-success' : '' ?>" id="visionCheckStatus" role="status">
                                    <?= $vision_check_passed ? sm_text('✓ Visual check passed.', '✓ দৃষ্টিশক্তি পরীক্ষা সফল হয়েছে।') : sm_text('Not completed yet.', 'এখনো সম্পন্ন হয়নি।') ?>
                                </div>
                            </article>
                        </div>
                        <div class="accessibility-required-note"><i class="bi bi-info-circle"></i> <?= sm_text('Both checks are required before Step 5 can be completed.', 'ধাপ ৫ সম্পন্ন করতে দুটি পরীক্ষাই পাস করতে হবে।') ?></div>
                    </section>

                    

                    <section class="step5-section step5-media-section">
                        <div class="section-heading">
                            <span class="section-icon"><i class="bi bi-soundwave"></i></span>
                            <div>
                                <h2><?= sm_text('Voice &amp; Video Introductions', 'কণ্ঠ ও ভিডিও পরিচিতি') ?></h2>
                            </div>
                        </div>

                        <div class="step5-media-grid">
                        <div class="step5-media-panel" id="videoIntroductionSection">
                        <div class="section-heading">
                            <span class="section-icon"><i class="bi bi-camera-video"></i></span>
                            <div>
                                <h2><?= sm_text('Video Introduction', 'ভিডিও পরিচিতি') ?></h2>
                            </div>
                        </div>

                        <div class="video-card" id="videoCard" data-existing-media-id="<?= $video_media_id ?>">
                            <div class="video-preview-wrap">
                                <video id="videoPreview" class="video-preview <?= $video_media_id > 0 ? '' : 'd-none' ?>" controls playsinline preload="metadata" <?= $video_media_id > 0 ? 'src="media.php?id=' . $video_media_id . '"' : '' ?>></video>
                                <div id="videoPlaceholder" class="video-placeholder <?= $video_media_id > 0 ? 'd-none' : '' ?>">
                                    <i class="bi bi-camera-video-fill"></i>
                                    <span><?= sm_text('No video yet', 'এখনো কোনো ভিডিও নেই') ?></span>
                                </div>
                                <div id="videoRecordingBadge" class="video-recording-badge d-none"><span></span><?= sm_text('Recording', 'রেকর্ড হচ্ছে') ?></div>
                            </div>

                            <div class="video-controls">
                                <div class="video-time" id="videoTimer">00:00 / 00:30</div>
                                <div class="video-buttons">
                                    <button type="button" class="btn btn-success" id="startVideo">
                                        <i class="bi bi-camera-video-fill me-1"></i><?= sm_text('Start Recording', 'রেকর্ডিং শুরু করুন') ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary d-none" id="stopVideo">
                                        <i class="bi bi-stop-fill me-1"></i><?= sm_text('Stop', 'থামান') ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-success d-none" id="recordAgainVideo">
                                        <i class="bi bi-arrow-repeat me-1"></i><?= sm_text('Record Again', 'আবার রেকর্ড করুন') ?>
                                    </button>
                                    <?php if ($video_media_id > 0): ?>
                                        <button type="button" class="btn btn-outline-danger" id="removeVideo">
                                            <i class="bi bi-trash me-1"></i><?= sm_text('Remove', 'সরিয়ে ফেলুন') ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div class="video-error alert alert-danger d-none" id="videoError" role="alert"></div>
                                <div class="video-status" id="videoStatus">
                                    <?= $video_media_id > 0 ? sm_text('Your current video introduction is ready to play.', 'আপনার বর্তমান ভিডিও পরিচিতি চালানোর জন্য প্রস্তুত।') : sm_text('Optional — record up to 30 seconds.', 'ঐচ্ছিক — সর্বোচ্চ ৩০ সেকেন্ড রেকর্ড করুন।') ?>
                                </div>
                            </div>
                        </div>
                     </div>

                      <div class="step5-media-panel" id="voiceIntroductionSection">
                        <div class="section-heading">
                            <span class="section-icon"><i class="bi bi-mic"></i></span>
                            <div>
                                <h2><?= sm_text('Voice Introduction', 'কণ্ঠে পরিচিতি') ?></h2>
                            </div>
                        </div>

                        <div class="voice-card" id="voiceCard" data-existing-media-id="<?= $voice_media_id ?>">
                            <div class="voice-visual">
                                <div class="voice-mic-circle" id="voiceMicCircle"><i class="bi bi-mic-fill"></i></div>
                                <div class="voice-wave" id="voiceWave" aria-hidden="true">
                                    <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                                </div>
                            </div>

                            <div class="voice-controls">
                                <div class="voice-time" id="voiceTimer">00:00 / 00:30</div>
                                <div class="voice-buttons">
                                    <button type="button" class="btn btn-success" id="startVoice">
                                        <i class="bi bi-mic-fill me-1"></i><?= sm_text('Start Recording', 'রেকর্ডিং শুরু করুন') ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary d-none" id="stopVoice">
                                        <i class="bi bi-stop-fill me-1"></i><?= sm_text('Stop', 'থামান') ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-success d-none" id="recordAgainVoice">
                                        <i class="bi bi-arrow-repeat me-1"></i><?= sm_text('Record Again', 'আবার রেকর্ড করুন') ?>
                                    </button>
                                    <?php if ($voice_media_id > 0): ?>
                                        <button type="button" class="btn btn-outline-danger" id="removeVoice">
                                            <i class="bi bi-trash me-1"></i><?= sm_text('Remove', 'সরিয়ে ফেলুন') ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <audio id="voicePlayer" class="voice-player <?= $voice_media_id > 0 ? '' : 'd-none' ?>" controls preload="metadata" <?= $voice_media_id > 0 ? 'src="media.php?id=' . $voice_media_id . '"' : '' ?>></audio>
                                <div class="voice-error alert alert-danger d-none" id="voiceError" role="alert"></div>
                                <div class="voice-status" id="voiceStatus">
                                    <?= $voice_media_id > 0 ? sm_text('Your current voice introduction is ready to play.', 'আপনার বর্তমান কণ্ঠ পরিচিতি চালানোর জন্য প্রস্তুত।') : sm_text('Optional — record up to 30 seconds.', 'ঐচ্ছিক — সর্বোচ্চ ৩০ সেকেন্ড রেকর্ড করুন।') ?>
                                </div>
                            </div>
                        </div>
                        </div>
                        </div>
                    </section>
                    
                    <section class="step5-section step5-visibility-section">
                        <div class="section-heading">
                            <span class="section-icon"><i class="bi bi-shield-lock"></i></span>
                            <div>
                                <h2><?= sm_text('Privacy &amp; Visibility', 'গোপনীয়তা ও দৃশ্যমানতা') ?></h2>
                            </div>
                        </div>

                        <div class="step5-visibility-grid">
                            <div class="step5-visibility-field">
                                <label class="form-label" for="profileVisibility"><?= sm_text('Profile Visibility', 'প্রোফাইলের দৃশ্যমানতা') ?></label>
                                <select name="profile_visibility" id="profileVisibility" class="form-select">
                                    <option value="Public" <?= $profile_visibility === 'Public' ? 'selected' : '' ?>><?= sm_text('Public', 'সবার জন্য উন্মুক্ত') ?></option>
                                    <option value="Hidden" <?= $profile_visibility === 'Hidden' ? 'selected' : '' ?>><?= sm_text('Hidden', 'গোপন') ?></option>
                                </select>
                            </div>

                            <div class="step5-visibility-field">
                                <label class="form-label" for="photoVisibility"><?= sm_text('Photo Visibility', 'ছবির দৃশ্যমানতা') ?></label>
                                <select name="photo_visibility" id="photoVisibility" class="form-select">
                                    <?php foreach ($visibility_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['photo_visibility'] ?? 'Verified Users') === $option) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(sm_step5_visibility_label($option)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="step5-visibility-field">
                                <label class="form-label" for="voiceVisibility"><?= sm_text('Voice Visibility', 'কণ্ঠ পরিচিতির দৃশ্যমানতা') ?></label>
                                <select name="voice_visibility" id="voiceVisibility" class="form-select">
                                    <?php foreach ($visibility_options as $option): ?>
                                        <?php $voice_option = ($option === 'Hidden') ? 'Private' : $option; ?>
                                        <option value="<?= htmlspecialchars($voice_option) ?>" <?= ($voice_visibility === $voice_option) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(sm_step5_visibility_label($option)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="step5-visibility-field">
                                <label class="form-label" for="videoVisibility"><?= sm_text('Video Visibility', 'ভিডিও পরিচিতির দৃশ্যমানতা') ?></label>
                                <select name="video_visibility" id="videoVisibility" class="form-select">
                                    <?php foreach ($visibility_options as $option): ?>
                                        <?php $video_option = ($option === 'Hidden') ? 'Private' : $option; ?>
                                        <option value="<?= htmlspecialchars($video_option) ?>" <?= ($video_visibility === $video_option) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(sm_step5_visibility_label($option)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </section>

                    <section class="step5-section privacy-summary-section">
                        <div class="section-heading">
                            <span class="section-icon"><i class="bi bi-eye"></i></span>
                            <div>
                                <h2><?= sm_text('Privacy Summary', 'গোপনীয়তার সারসংক্ষেপ') ?></h2>
                            </div>
                        </div>

                        <div class="privacy-summary-card" id="privacySummaryCard">
                            <div class="privacy-summary-item">
                                <div class="privacy-summary-icon"><i class="bi bi-person-check"></i></div>
                                <div>
                                    <span><?= sm_text('Profile Visibility', 'প্রোফাইলের দৃশ্যমানতা') ?></span>
                                    <strong id="summaryProfileVisibility"><?= htmlspecialchars(sm_step5_visibility_label($profile_visibility)) ?></strong>
                                </div>
                            </div>
                            <div class="privacy-summary-item">
                                <div class="privacy-summary-icon"><i class="bi bi-image"></i></div>
                                <div>
                                    <span><?= sm_text('Profile Photo', 'প্রোফাইল ছবি') ?></span>
                                    <strong id="summaryPhotoVisibility"><?= htmlspecialchars(sm_step5_visibility_label((string) ($user['photo_visibility'] ?? 'Verified Users'))) ?></strong>
                                </div>
                            </div>
                            <div class="privacy-summary-item">
                                <div class="privacy-summary-icon"><i class="bi bi-mic"></i></div>
                                <div>
                                    <span><?= sm_text('Voice Introduction', 'কণ্ঠে পরিচিতি') ?></span>
                                    <strong id="summaryVoiceVisibility"><?= htmlspecialchars(sm_step5_visibility_label($voice_visibility === 'Private' ? 'Hidden' : $voice_visibility)) ?></strong>
                                </div>
                            </div>
                            <div class="privacy-summary-item">
                                <div class="privacy-summary-icon"><i class="bi bi-camera-video"></i></div>
                                <div>
                                    <span><?= sm_text('Video Introduction', 'ভিডিও পরিচিতি') ?></span>
                                    <strong id="summaryVideoVisibility"><?= htmlspecialchars(sm_step5_visibility_label($video_visibility === 'Private' ? 'Hidden' : $video_visibility)) ?></strong>
                                </div>
                            </div>
                        </div>

                    </section>


                    <div class="step5-actions">
                        <a href="view_profile.php?user_id=<?= (int)$user_id ?>" class="btn step5-back">
                            <i class="bi bi-arrow-left-circle"></i> <?= sm_text('Back to Profile', 'প্রোফাইলে ফিরে যান') ?>
                        </a>
                        <button type="submit" name="save_step5" value="1" class="btn step5-save">
                            <?= sm_text('Save &amp; Continue', 'সংরক্ষণ করে এগিয়ে যান') ?> <i class="bi bi-arrow-right-circle"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/profile-step5-media.js"></script>
<script>
(function () {
    const input = document.getElementById('photoInput');
    const preview = document.getElementById('photoPreview');
    const placeholder = document.getElementById('photoPlaceholder');
    const choose = document.getElementById('choosePhoto');
    const camera = document.getElementById('cameraButton');
    const errorBox = document.getElementById('photoError');
    const status = document.getElementById('photoStatus');
    const removePhotoButton = document.getElementById('removePhotoButton');
    const defaultPreview = preview.getAttribute('src') || '';
    const maxSize = 20 * 1024 * 1024;
    const allowed = ['image/jpeg', 'image/png', 'image/webp'];

    function openPicker() { input.click(); }
    function showPlaceholder() {
        preview.src = '';
        preview.classList.add('d-none');
        placeholder.classList.remove('d-none');
    }
    function showImage(src) {
        preview.src = src;
        preview.classList.remove('d-none');
        placeholder.classList.add('d-none');
    }

    function updateRemoveButton() {
        if (!removePhotoButton) return;
        const hasPendingPhoto = !!(input.files && input.files.length);
        const hasSavedPhoto = removePhotoButton.dataset.hasSavedPhoto === '1';
        removePhotoButton.classList.toggle('d-none', !hasPendingPhoto && !hasSavedPhoto);
    }

    choose.addEventListener('click', openPicker);
    camera.addEventListener('click', openPicker);
    preview.addEventListener('click', openPicker);
    placeholder.addEventListener('click', openPicker);

    if (removePhotoButton) {
        removePhotoButton.addEventListener('click', function () {
            const hasPendingPhoto = !!(input.files && input.files.length);

            // If a new image is only selected (not saved yet), Remove means
            // discard the pending selection and keep the saved photo intact.
            if (hasPendingPhoto) {
                input.value = '';
                if (defaultPreview) {
                    showImage(defaultPreview);
                    status.textContent = <?= json_encode(sm_text('Your current profile photo is shown above.', 'আপনার বর্তমান প্রোফাইল ছবি উপরে দেখানো হয়েছে।'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
                } else {
                    showPlaceholder();
                    status.textContent = <?= json_encode(sm_text('No profile photo added yet.', 'এখনো কোনো প্রোফাইল ছবি যোগ করা হয়নি।'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
                }
                updateRemoveButton();
                return;
            }

            // If the image is already saved, submit the existing server-side
            // removal action.
            if (removePhotoButton.dataset.hasSavedPhoto === '1') {
                if (!window.confirm(<?= json_encode(sm_text('Remove your profile photo?', 'আপনি কি প্রোফাইলের ছবি সরাতে চান?'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)) return;
                const removeInput = document.createElement('input');
                removeInput.type = 'hidden';
                removeInput.name = 'remove_photo';
                removeInput.value = '1';
                removePhotoButton.form.appendChild(removeInput);
                removePhotoButton.form.submit();
            }
        });
    }

    input.addEventListener('change', function () {
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
        const file = input.files[0];
        if (!file) {
            if (defaultPreview) showImage(defaultPreview); else showPlaceholder();
            updateRemoveButton();
            return;
        }
        if (file.size > maxSize) {
            errorBox.textContent = <?= json_encode(sm_text('Maximum photo size is 20 MB.', 'ছবির সর্বোচ্চ আকার ২০ এমবি।'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            errorBox.classList.remove('d-none');
            input.value = '';
            if (defaultPreview) showImage(defaultPreview); else showPlaceholder();
            updateRemoveButton();
            return;
        }
        if (!allowed.includes(file.type)) {
            errorBox.textContent = <?= json_encode(sm_text('Only JPG, JPEG, PNG and WEBP images are allowed.', 'শুধু JPG, JPEG, PNG ও WEBP ছবি ব্যবহার করা যাবে।'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            errorBox.classList.remove('d-none');
            input.value = '';
            if (defaultPreview) showImage(defaultPreview); else showPlaceholder();
            updateRemoveButton();
            return;
        }
        showImage(URL.createObjectURL(file));
        status.textContent = <?= json_encode(sm_text('New photo selected. Save & Continue to apply it.', 'নতুন ছবি নির্বাচন করা হয়েছে। কার্যকর করতে “সংরক্ষণ করে এগিয়ে যান” চাপুন।'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        updateRemoveButton();
    });

    window.smDropdownLabels = <?= json_encode([
        'Public' => sm_text('Public', 'সবার জন্য উন্মুক্ত'),
        'Hidden' => sm_text('Hidden', 'গোপন'),
        'Everyone' => sm_text('Everyone', 'সবাই'),
        'Verified Users' => sm_text('Verified Users', 'যাচাইকৃত ব্যবহারকারী'),
        'Matched Users' => sm_text('Matched Users', 'ম্যাচ হওয়া ব্যবহারকারী'),
        'Private' => sm_text('Private', 'ব্যক্তিগত'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const visibility = document.getElementById('photoVisibility');
    updateRemoveButton();

    const summaryProfile = document.getElementById('summaryProfileVisibility');
    const profileVisibility = document.getElementById('profileVisibility');
    const summaryPhoto = document.getElementById('summaryPhotoVisibility');
    const summaryVoice = document.getElementById('summaryVoiceVisibility');
    const summaryVideo = document.getElementById('summaryVideoVisibility');
    function updatePrivacySummary() {
        if (summaryProfile && profileVisibility) summaryProfile.textContent = (window.smDropdownLabels && window.smDropdownLabels[profileVisibility.value]) || profileVisibility.value;
        if (summaryPhoto && visibility) summaryPhoto.textContent = (window.smDropdownLabels && window.smDropdownLabels[visibility.value]) || visibility.value;
        const voiceSelect = document.getElementById('voiceVisibility');
        const videoSelect = document.getElementById('videoVisibility');
        if (summaryVoice && voiceSelect) { const v = voiceSelect.value === 'Private' ? 'Hidden' : voiceSelect.value; summaryVoice.textContent = (window.smDropdownLabels && window.smDropdownLabels[v]) || v; }
        if (summaryVideo && videoSelect) { const v = videoSelect.value === 'Private' ? 'Hidden' : videoSelect.value; summaryVideo.textContent = (window.smDropdownLabels && window.smDropdownLabels[v]) || v; }
    }
    if (visibility) visibility.addEventListener('change', updatePrivacySummary);
    if (profileVisibility) profileVisibility.addEventListener('change', updatePrivacySummary);
    if (document.getElementById('voiceVisibility')) document.getElementById('voiceVisibility').addEventListener('change', updatePrivacySummary);
    if (document.getElementById('videoVisibility')) document.getElementById('videoVisibility').addEventListener('change', updatePrivacySummary);
    updatePrivacySummary();

    // The remove-success message is a one-time flash: hide it on the next
    // interaction and remove the query string so it cannot return on refresh.
    const removeAlert = document.querySelector('.step5-alert.step5-remove-alert');
    if (removeAlert) {
        if (window.history && window.history.replaceState) {
            const cleanUrl = new URL(window.location.href);
            cleanUrl.searchParams.delete('removed');
            window.history.replaceState({}, document.title, cleanUrl.pathname + (cleanUrl.search || '') + (cleanUrl.hash || ''));
        }
        const dismissRemoveAlert = function () {
            removeAlert.remove();
            document.removeEventListener('click', dismissRemoveAlert, true);
            document.removeEventListener('keydown', dismissRemoveAlert, true);
            document.removeEventListener('change', dismissRemoveAlert, true);
        };
        document.addEventListener('click', dismissRemoveAlert, true);
        document.addEventListener('keydown', dismissRemoveAlert, true);
        document.addEventListener('change', dismissRemoveAlert, true);
    }
})();
</script>

<?php include '../includes/footer.php'; ?>
