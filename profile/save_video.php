<?php
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function video_response(bool $ok, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    video_response(false, 'Authentication required.');
}

$user_id = (int) $_SESSION['user_id'];
$action = (string) ($_POST['action'] ?? 'save');

if ($action === 'remove') {
    $stmt = mysqli_prepare($conn, 'UPDATE profile_media SET status="Deleted" WHERE user_id=? AND media_type="Video Introduction" AND status="Active"');
    if (!$stmt) {
        http_response_code(500);
        video_response(false, 'Unable to remove the video introduction.');
    }
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    video_response($ok, $ok ? 'Video introduction removed.' : 'Unable to remove the video introduction.');
}

$visibility = trim((string) ($_POST['visibility'] ?? 'Verified Users'));
if (!in_array($visibility, ['Everyone', 'Verified Users', 'Matched Users', 'Private'], true)) {
    video_response(false, 'Please select a valid video visibility option.');
}

$duration = (int) ($_POST['duration_seconds'] ?? 0);
if ($duration < 1 || $duration > 30) {
    video_response(false, 'Video introduction must be between 1 and 30 seconds.');
}

if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
    video_response(false, 'No video recording was received.');
}

$file = $_FILES['video'];
if ((int) $file['size'] > 25 * 1024 * 1024) {
    video_response(false, 'Maximum video recording size is 25 MB.');
}

$tmp = (string) $file['tmp_name'];
$mime = '';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mime = (string) finfo_file($finfo, $tmp);
        finfo_close($finfo);
    }
}

$allowed_mime = [
    'video/webm' => 'webm',
    'video/mp4' => 'mp4',
    'video/ogg' => 'ogv'
];
if (!isset($allowed_mime[$mime])) {
    video_response(false, 'Unsupported video recording format. Please record again.');
}

$upload_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profile';
if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
    http_response_code(500);
    video_response(false, 'Unable to prepare media storage.');
}

$extension = $allowed_mime[$mime];
$filename = 'video_' . $user_id . '_' . bin2hex(random_bytes(12)) . '.' . $extension;
$destination = $upload_dir . DIRECTORY_SEPARATOR . $filename;

if (!move_uploaded_file($tmp, $destination)) {
    http_response_code(500);
    video_response(false, 'Unable to save the video recording.');
}

$deactivate = mysqli_prepare($conn, 'UPDATE profile_media SET status="Deleted" WHERE user_id=? AND media_type="Video Introduction" AND status="Active"');
if ($deactivate) {
    mysqli_stmt_bind_param($deactivate, 'i', $user_id);
    mysqli_stmt_execute($deactivate);
    mysqli_stmt_close($deactivate);
}

$insert = mysqli_prepare($conn, 'INSERT INTO profile_media (user_id, media_type, file_path, duration_seconds, visibility, status) VALUES (?, "Video Introduction", ?, ?, ?, "Active")');
if (!$insert) {
    @unlink($destination);
    http_response_code(500);
    video_response(false, 'Unable to save video metadata.');
}

mysqli_stmt_bind_param($insert, 'isis', $user_id, $filename, $duration, $visibility);
$ok = mysqli_stmt_execute($insert);
$new_id = mysqli_insert_id($conn);
mysqli_stmt_close($insert);

if (!$ok) {
    @unlink($destination);
    http_response_code(500);
    video_response(false, 'Unable to save the video introduction.');
}

video_response(true, 'Video introduction saved.', [
    'media_id' => (int) $new_id,
    'duration_seconds' => $duration
]);
