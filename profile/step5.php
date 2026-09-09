<?php

require_once '../config/db.php';
require_once '../includes/functions.php';
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
    SELECT photo, photo_visibility, profile_visibility
    FROM user_profiles
    WHERE user_id=?
    LIMIT 1
');
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result) ?: [];
mysqli_stmt_close($stmt);

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

    if (!in_array($profile_visibility_post, ['Public', 'Hidden'], true)) {
        $error = 'Please select a valid profile visibility option.';
    } elseif (!in_array($photo_visibility, $visibility_options, true)) {
        $error = 'Please select a valid photo visibility option.';

    } elseif (!in_array($voice_visibility_db, ['Everyone', 'Verified Users', 'Matched Users', 'Private'], true)) {
        $error = 'Please select a valid voice visibility option.';
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
                $error = 'Only JPG, JPEG and WEBP images are allowed.';
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
    1 => ['title' => 'Personal', 'icon' => 'bi-person-circle', 'url' => 'step1.php'],
    2 => ['title' => 'Family', 'icon' => 'bi-people', 'url' => 'step2.php'],
    3 => ['title' => 'Lifestyle', 'icon' => 'bi-heart', 'url' => 'step3.php'],
    4 => ['title' => 'Location', 'icon' => 'bi-geo-alt', 'url' => 'step4.php'],
    5 => ['title' => 'Privacy', 'icon' => 'bi-shield-lock', 'url' => 'step5.php'],
    6 => ['title' => 'PP & QnA', 'icon' => 'bi-patch-question', 'url' => 'step6.php']
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
                            <span class="step5-kicker">PROFILE BUILDER</span>
                            <h1>Step <?= $currentStep ?></h1>
                            <p>Profile Photo &amp; Privacy</p>
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
                        <div class="step5-progress-track" role="progressbar" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Profile completion">
                            <div class="step5-progress-fill" style="width: <?= $progress ?>%"></div>
                        </div>
                        <div class="step5-progress-meta">
                            <span>Step <?= $currentStep ?> of <?= count($steps) ?></span>
                            <strong><?= $progress ?>% complete</strong>
                        </div>
                    </div>
                </header>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger step5-alert" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php elseif (isset($_GET['removed'])): ?>
                    <div class="alert alert-success step5-alert step5-remove-alert" role="alert">
                        <i class="bi bi-check-circle me-2"></i>Profile photo removed successfully.
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="step5-form">
                    <section class="step5-section">
                        <div class="section-heading">
                            <span class="section-icon"><i class="bi bi-image"></i></span>
                            <div>
                                <h2>Profile Photo</h2>
                                <p>Add a clear photo so people can recognize your profile.</p>
                            </div>
                        </div>

                        <div class="photo-uploader">
                            <div class="photo-preview-wrap">
                                <?php if ($hasPhoto && $profile_media_id > 0): ?>
                                    <img src="<?= htmlspecialchars($preview) ?>" id="photoPreview" class="photo-preview" alt="Profile photo preview">
                                    <div id="photoPlaceholder" class="photo-placeholder d-none" aria-hidden="true">
                                        <i class="bi bi-person-fill"></i>
                                        <span>No photo</span>
                                    </div>
                                <?php else: ?>
                                    <div id="photoPlaceholder" class="photo-placeholder" aria-label="No profile photo">
                                        <i class="bi bi-person-fill"></i>
                                        <span>No photo</span>
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
                                        <i class="bi bi-camera me-1"></i><?= $hasPhoto ? 'Change Photo' : 'Upload Photo' ?>
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-outline-danger <?= $hasPhoto ? '' : 'd-none' ?>"
                                        id="removePhotoButton"
                                        data-has-saved-photo="<?= $hasPhoto ? '1' : '0' ?>"
                                    >
                                        <i class="bi bi-trash me-1"></i>Remove
                                    </button>
                                </div>
                                <div class="photo-status mt-2" id="photoStatus">
                                    <?= $hasPhoto ? 'Your current profile photo is shown above.' : 'No profile photo added yet.' ?>
                                </div>
                            </div>

                            <input type="file" name="photo" id="photoInput" class="d-none" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <div id="photoError" class="alert alert-danger py-2 px-3 d-none mb-0" role="alert"></div>
                        </div>
                    </section>

                    <section class="step5-section">
                        <div class="section-heading">
                            <span class="section-icon"><i class="bi bi-shield-lock"></i></span>
                            <div>
                                <h2>Photo Privacy &amp; Profile Visibility</h2>
                                <p>Control who can access your profile photo and discover your profile.</p>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label" for="photoVisibility">Photo Visibility</label>
                                <select name="photo_visibility" id="photoVisibility" class="form-select">
                                    <?php foreach ($visibility_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['photo_visibility'] ?? 'Verified Users') === $option) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($option) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="visibility-help" id="visibilityHelp">Only people allowed by this setting can access the protected media resource.</div>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-7">
                                <label class="form-label" for="profileVisibility">Profile Visibility</label>
                                <select name="profile_visibility" id="profileVisibility" class="form-select">
                                    <option value="Public" <?= $profile_visibility === 'Public' ? 'selected' : '' ?>>Public</option>
                                    <option value="Hidden" <?= $profile_visibility === 'Hidden' ? 'selected' : '' ?>>Hidden</option>
                                </select>
                                <div class="visibility-help" id="profileVisibilityHelp">Your profile can appear in eligible searches and recommendations.</div>
                            </div>
                        </div>
                    </section>

                    <section class="step5-section voice-section" id="voiceIntroductionSection">
                        <div class="section-heading">
                            <span class="section-icon"><i class="bi bi-mic"></i></span>
                            <div>
                                <h2>Voice Introduction</h2>
                                <p>Record a short introduction so others can hear your voice.</p>
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
                                        <i class="bi bi-mic-fill me-1"></i>Start Recording
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary d-none" id="stopVoice">
                                        <i class="bi bi-stop-fill me-1"></i>Stop
                                    </button>
                                    <button type="button" class="btn btn-outline-success d-none" id="recordAgainVoice">
                                        <i class="bi bi-arrow-repeat me-1"></i>Record Again
                                    </button>
                                    <?php if ($voice_media_id > 0): ?>
                                        <button type="button" class="btn btn-outline-danger" id="removeVoice">
                                            <i class="bi bi-trash me-1"></i>Remove
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <audio id="voicePlayer" class="voice-player <?= $voice_media_id > 0 ? '' : 'd-none' ?>" controls preload="metadata" <?= $voice_media_id > 0 ? 'src="media.php?id=' . $voice_media_id . '"' : '' ?>></audio>
                                <div class="voice-status" id="voiceStatus">
                                    <?= $voice_media_id > 0 ? 'Your current voice introduction is ready to play.' : 'Optional — record up to 30 seconds.' ?>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-7">
                                <label class="form-label media-visibility-label" for="voiceVisibility">Voice Visibility <span class="media-limit-inline">30 sec max</span></label>
                                <select name="voice_visibility" id="voiceVisibility" class="form-select">
                                    <?php foreach ($visibility_options as $option): ?>
                                        <?php $voice_option = ($option === 'Hidden') ? 'Private' : $option; ?>
                                        <option value="<?= htmlspecialchars($voice_option) ?>" <?= ($voice_visibility === $voice_option) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($option) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="visibility-help">Choose who can access your protected voice introduction.</div>
                            </div>
                        </div>
                        <div id="voiceError" class="alert alert-danger py-2 px-3 d-none mt-3 mb-0" role="alert"></div>
                    </section>

                    <section class="step5-section video-section" id="videoIntroductionSection">
                        <div class="section-heading">
                            <span class="section-icon"><i class="bi bi-camera-video"></i></span>
                            <div>
                                <h2>Video Introduction</h2>
                                <p>Record a short video introduction so others can see and hear you.</p>
                            </div>
                        </div>

                        <div class="video-card" id="videoCard" data-existing-media-id="<?= $video_media_id ?>">
                            <div class="video-preview-wrap">
                                <video id="videoPreview" class="video-preview <?= $video_media_id > 0 ? '' : 'd-none' ?>" controls playsinline preload="metadata" <?= $video_media_id > 0 ? 'src="media.php?id=' . $video_media_id . '"' : '' ?>></video>
                                <div id="videoPlaceholder" class="video-placeholder <?= $video_media_id > 0 ? 'd-none' : '' ?>">
                                    <i class="bi bi-camera-video-fill"></i>
                                    <span>No video yet</span>
                                </div>
                                <div id="videoRecordingBadge" class="video-recording-badge d-none"><span></span>Recording</div>
                            </div>

                            <div class="video-controls">
                                <div class="video-time" id="videoTimer">00:00 / 00:30</div>
                                <div class="video-buttons">
                                    <button type="button" class="btn btn-success" id="startVideo">
                                        <i class="bi bi-camera-video-fill me-1"></i>Start Recording
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary d-none" id="stopVideo">
                                        <i class="bi bi-stop-fill me-1"></i>Stop
                                    </button>
                                    <button type="button" class="btn btn-outline-success d-none" id="recordAgainVideo">
                                        <i class="bi bi-arrow-repeat me-1"></i>Record Again
                                    </button>
                                    <?php if ($video_media_id > 0): ?>
                                        <button type="button" class="btn btn-outline-danger" id="removeVideo">
                                            <i class="bi bi-trash me-1"></i>Remove
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div class="video-status" id="videoStatus">
                                    <?= $video_media_id > 0 ? 'Your current video introduction is ready to play.' : 'Optional — record up to 30 seconds.' ?>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-7">
                                <label class="form-label media-visibility-label" for="videoVisibility">Video Visibility <span class="media-limit-inline">30 sec max</span></label>
                                <select name="video_visibility" id="videoVisibility" class="form-select">
                                    <?php foreach ($visibility_options as $option): ?>
                                        <?php $video_option = ($option === 'Hidden') ? 'Private' : $option; ?>
                                        <option value="<?= htmlspecialchars($video_option) ?>" <?= ($video_visibility === $video_option) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($option) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="visibility-help">Choose who can access your protected video introduction.</div>
                            </div>
                        </div>
                        <div id="videoError" class="alert alert-danger py-2 px-3 d-none mt-3 mb-0" role="alert"></div>
                    </section>

                    <section class="step5-section privacy-summary-section">
                        <div class="section-heading">
                            <span class="section-icon"><i class="bi bi-eye"></i></span>
                            <div>
                                <h2>Privacy Summary</h2>
                                <p>A quick view of who can discover and access your profile media.</p>
                            </div>
                        </div>

                        <div class="privacy-summary-card" id="privacySummaryCard">
                            <div class="privacy-summary-item">
                                <div class="privacy-summary-icon"><i class="bi bi-person-check"></i></div>
                                <div>
                                    <span>Profile Visibility</span>
                                    <strong id="summaryProfileVisibility"><?= htmlspecialchars($profile_visibility) ?></strong>
                                </div>
                            </div>
                            <div class="privacy-summary-item">
                                <div class="privacy-summary-icon"><i class="bi bi-image"></i></div>
                                <div>
                                    <span>Profile Photo</span>
                                    <strong id="summaryPhotoVisibility"><?= htmlspecialchars($user['photo_visibility'] ?? 'Verified Users') ?></strong>
                                </div>
                            </div>
                            <div class="privacy-summary-item">
                                <div class="privacy-summary-icon"><i class="bi bi-mic"></i></div>
                                <div>
                                    <span>Voice Introduction</span>
                                    <strong id="summaryVoiceVisibility"><?= htmlspecialchars($voice_visibility === 'Private' ? 'Hidden' : $voice_visibility) ?></strong>
                                </div>
                            </div>
                            <div class="privacy-summary-item">
                                <div class="privacy-summary-icon"><i class="bi bi-camera-video"></i></div>
                                <div>
                                    <span>Video Introduction</span>
                                    <strong id="summaryVideoVisibility"><?= htmlspecialchars($video_visibility === 'Private' ? 'Hidden' : $video_visibility) ?></strong>
                                </div>
                            </div>
                        </div>

                    </section>

                    <section class="step5-section privacy-safety-section">
                        <div class="privacy-safety-panel">
                            <div class="privacy-safety-heading">
                                <span class="privacy-safety-icon"><i class="bi bi-shield-check"></i></span>
                                <div>
                                    <h2>Privacy &amp; Media Safety</h2>
                                    <p>Keep your profile information and introductions safe and respectful.</p>
                                </div>
                            </div>
                            <div class="privacy-safety-grid">
                                <div class="privacy-safety-item">
                                    <i class="bi bi-lock-fill"></i>
                                    <div><strong>Protected access</strong><span>Only people who satisfy the selected access rule can request protected media.</span></div>
                                </div>
                                <div class="privacy-safety-item">
                                    <i class="bi bi-shield-lock"></i>
                                    <div><strong>Secure media</strong><span>Voice and video use the permission-based media system; direct public access is not exposed.</span></div>
                                </div>
                                <div class="privacy-safety-item">
                                    <i class="bi bi-image"></i>
                                    <div><strong>Photo safety</strong><span>Use your own recent photo and avoid group photos, misleading images, or sensitive personal information.</span></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="step5-actions">
                        <a href="../profile/view_profile.php" class="btn step5-back">
                            <i class="bi bi-arrow-left-circle"></i> Back to Profile
                        </a>
                        <button type="submit" name="save_step5" value="1" class="btn step5-save">
                            Save &amp; Continue <i class="bi bi-arrow-right-circle"></i>
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
                    status.textContent = 'Your current profile photo is shown above.';
                } else {
                    showPlaceholder();
                    status.textContent = 'No profile photo added yet.';
                }
                updateRemoveButton();
                return;
            }

            // If the image is already saved, submit the existing server-side
            // removal action.
            if (removePhotoButton.dataset.hasSavedPhoto === '1') {
                if (!window.confirm('Remove your profile photo?')) return;
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
            errorBox.textContent = 'Maximum photo size is 5 MB.';
            errorBox.classList.remove('d-none');
            input.value = '';
            if (defaultPreview) showImage(defaultPreview); else showPlaceholder();
            updateRemoveButton();
            return;
        }
        if (!allowed.includes(file.type)) {
            errorBox.textContent = 'Only JPG, PNG and WEBP images are allowed.';
            errorBox.classList.remove('d-none');
            input.value = '';
            if (defaultPreview) showImage(defaultPreview); else showPlaceholder();
            updateRemoveButton();
            return;
        }
        showImage(URL.createObjectURL(file));
        status.textContent = 'New photo selected. Save & Continue to apply it.';
        updateRemoveButton();
    });

    const visibility = document.getElementById('photoVisibility');
    const help = document.getElementById('visibilityHelp');
    const helpText = {
        'Everyone': 'Anyone who can access your profile may view the photo.',
        'Verified Users': 'Only verified users may access the protected photo.',
        'Matched Users': 'Only users with an eligible match may access the photo.',
        'Hidden': 'The photo is hidden from other users until you change this setting.'
    };
    function updateHelp() { help.textContent = helpText[visibility.value] || ''; }
    visibility.addEventListener('change', updateHelp);
    updateHelp();
    updateRemoveButton();

    const summaryProfile = document.getElementById('summaryProfileVisibility');
    const profileVisibility = document.getElementById('profileVisibility');
    const profileVisibilityHelp = document.getElementById('profileVisibilityHelp');
    const summaryPhoto = document.getElementById('summaryPhotoVisibility');
    const summaryVoice = document.getElementById('summaryVoiceVisibility');
    const summaryVideo = document.getElementById('summaryVideoVisibility');
    function updatePrivacySummary() {
        if (summaryProfile && profileVisibility) summaryProfile.textContent = profileVisibility.value;
        if (summaryPhoto && visibility) summaryPhoto.textContent = visibility.value;
        const voiceSelect = document.getElementById('voiceVisibility');
        const videoSelect = document.getElementById('videoVisibility');
        if (summaryVoice && voiceSelect) summaryVoice.textContent = voiceSelect.value === 'Private' ? 'Hidden' : voiceSelect.value;
        if (summaryVideo && videoSelect) summaryVideo.textContent = videoSelect.value === 'Private' ? 'Hidden' : videoSelect.value;
    }
    if (visibility) visibility.addEventListener('change', updatePrivacySummary);
    if (profileVisibility) profileVisibility.addEventListener('change', function () {
        if (profileVisibilityHelp) profileVisibilityHelp.textContent = profileVisibility.value === 'Hidden'
            ? 'Your profile will not be discoverable by other users until you make it Public.'
            : 'Your profile can appear in eligible searches and recommendations.';
        updatePrivacySummary();
    });
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
