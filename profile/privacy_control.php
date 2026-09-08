<?php

require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in again.']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$csrf = (string) ($_POST['csrf_token'] ?? '');
$session_csrf = (string) ($_SESSION['privacy_csrf'] ?? '');

if ($csrf === '' || $session_csrf === '' || !hash_equals($session_csrf, $csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security check failed. Please refresh the page and try again.']);
    exit;
}

$profile_visibility = trim((string) ($_POST['profile_visibility'] ?? ''));
$photo_visibility = trim((string) ($_POST['photo_visibility'] ?? ''));
$voice_visibility = trim((string) ($_POST['voice_visibility'] ?? ''));
$video_visibility = trim((string) ($_POST['video_visibility'] ?? ''));

$valid_profile = ['Public', 'Hidden'];
$valid_visibility = ['Everyone', 'Verified Users', 'Matched Users', 'Hidden'];

if (!in_array($profile_visibility, $valid_profile, true)
    || !in_array($photo_visibility, $valid_visibility, true)
    || !in_array($voice_visibility, $valid_visibility, true)
    || !in_array($video_visibility, $valid_visibility, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please select valid privacy options.']);
    exit;
}

$voice_visibility_db = $voice_visibility === 'Hidden' ? 'Private' : $voice_visibility;
$video_visibility_db = $video_visibility === 'Hidden' ? 'Private' : $video_visibility;

mysqli_begin_transaction($conn);

try {
    $profile_stmt = mysqli_prepare($conn, '
        UPDATE user_profiles
        SET profile_visibility=?, photo_visibility=?
        WHERE user_id=?
        LIMIT 1
    ');
    if (!$profile_stmt) {
        throw new RuntimeException('Unable to prepare profile privacy update.');
    }
    mysqli_stmt_bind_param($profile_stmt, 'ssi', $profile_visibility, $photo_visibility, $user_id);
    if (!mysqli_stmt_execute($profile_stmt)) {
        mysqli_stmt_close($profile_stmt);
        throw new RuntimeException('Unable to save profile privacy settings.');
    }
    mysqli_stmt_close($profile_stmt);

    $photo_visibility_db = $photo_visibility === 'Hidden' ? 'Private' : $photo_visibility;
    $photo_stmt = mysqli_prepare($conn, '
        UPDATE profile_media
        SET visibility=?
        WHERE user_id=?
          AND media_type="Profile Photo"
          AND status="Active"
    ');
    if (!$photo_stmt) {
        throw new RuntimeException('Unable to prepare photo privacy update.');
    }
    mysqli_stmt_bind_param($photo_stmt, 'si', $photo_visibility_db, $user_id);
    if (!mysqli_stmt_execute($photo_stmt)) {
        mysqli_stmt_close($photo_stmt);
        throw new RuntimeException('Unable to save photo privacy settings.');
    }
    mysqli_stmt_close($photo_stmt);

    $voice_stmt = mysqli_prepare($conn, '
        UPDATE profile_media
        SET visibility=?
        WHERE user_id=?
          AND media_type="Voice Introduction"
          AND status="Active"
    ');
    if (!$voice_stmt) {
        throw new RuntimeException('Unable to prepare voice privacy update.');
    }
    mysqli_stmt_bind_param($voice_stmt, 'si', $voice_visibility_db, $user_id);
    if (!mysqli_stmt_execute($voice_stmt)) {
        mysqli_stmt_close($voice_stmt);
        throw new RuntimeException('Unable to save voice privacy settings.');
    }
    mysqli_stmt_close($voice_stmt);

    $video_stmt = mysqli_prepare($conn, '
        UPDATE profile_media
        SET visibility=?
        WHERE user_id=?
          AND media_type="Video Introduction"
          AND status="Active"
    ');
    if (!$video_stmt) {
        throw new RuntimeException('Unable to prepare video privacy update.');
    }
    mysqli_stmt_bind_param($video_stmt, 'si', $video_visibility_db, $user_id);
    if (!mysqli_stmt_execute($video_stmt)) {
        mysqli_stmt_close($video_stmt);
        throw new RuntimeException('Unable to save video privacy settings.');
    }
    mysqli_stmt_close($video_stmt);

    mysqli_commit($conn);
    echo json_encode(['success' => true, 'message' => 'Privacy settings saved successfully.']);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage() ?: 'Unable to save privacy settings.']);
}
