<?php
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function voice_response(bool $ok, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    voice_response(false, 'Authentication required.');
}

$user_id = (int) $_SESSION['user_id'];
$action = (string) ($_POST['action'] ?? 'save');

if ($action === 'remove') {
    $stmt = mysqli_prepare($conn, "UPDATE profile_media SET status='Deleted' WHERE user_id=? AND media_type='Voice Introduction' AND status='Active'");
    if (!$stmt) {
        http_response_code(500);
        voice_response(false, 'Unable to remove the voice introduction.');
    }
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    voice_response($ok, $ok ? 'Voice introduction removed.' : 'Unable to remove the voice introduction.');
}

$visibility = trim((string) ($_POST['visibility'] ?? 'Verified Users'));
$allowed_visibility = ['Everyone', 'Verified Users', 'Matched Users', 'Private'];
if (!in_array($visibility, $allowed_visibility, true)) {
    voice_response(false, 'Please select a valid voice visibility option.');
}

$duration = (int) ($_POST['duration_seconds'] ?? 0);
if ($duration < 1 || $duration > 30) {
    voice_response(false, 'Voice introduction must be between 1 and 30 seconds.');
}

if (!isset($_FILES['voice']) || $_FILES['voice']['error'] !== UPLOAD_ERR_OK) {
    voice_response(false, 'No voice recording was received.');
}

$file = $_FILES['voice'];
if ((int) $file['size'] > 6 * 1024 * 1024) {
    voice_response(false, 'Maximum voice recording size is 6 MB.');
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

/*
 * Browsers can label the same audio-only WebM container differently
 * (for example audio/webm or video/webm), and some servers report
 * Matroska/WebM recordings as application/octet-stream. Validate the
 * actual container signature as well as the detected MIME so legitimate
 * MediaRecorder output is not rejected.
 */
$extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
$signature = '';
$handle = @fopen($tmp, 'rb');
if ($handle) {
    $signature = (string) fread($handle, 16);
    fclose($handle);
}

$is_webm = substr($signature, 0, 4) === "\x1A\x45\xDF\xA3";
$is_ogg = substr($signature, 0, 4) === 'OggS';
$is_mp4 = strlen($signature) >= 8 && substr($signature, 4, 4) === 'ftyp';

if ($is_webm && in_array($mime, ['audio/webm', 'video/webm', 'application/octet-stream', 'application/x-matroska'], true)) {
    $extension = 'webm';
} elseif ($is_ogg && in_array($mime, ['audio/ogg', 'application/ogg', 'application/octet-stream'], true)) {
    $extension = 'ogg';
} elseif ($is_mp4 && in_array($mime, ['audio/mp4', 'video/mp4', 'application/octet-stream'], true)) {
    $extension = 'm4a';
} else {
    voice_response(false, 'Unsupported voice recording format. Please record again.');
}

$upload_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profile';
if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
    http_response_code(500);
    voice_response(false, 'Unable to prepare media storage.');
}

$filename = 'voice_' . $user_id . '_' . bin2hex(random_bytes(12)) . '.' . $extension;
$destination = $upload_dir . DIRECTORY_SEPARATOR . $filename;

if (!move_uploaded_file($tmp, $destination)) {
    http_response_code(500);
    voice_response(false, 'Unable to save the voice recording.');
}

$path_for_db = $filename;

/* Keep only the newest active introduction. Older records remain auditable as Deleted. */
$deactivate = mysqli_prepare($conn, "UPDATE profile_media SET status='Deleted' WHERE user_id=? AND media_type='Voice Introduction' AND status='Active'");
if ($deactivate) {
    mysqli_stmt_bind_param($deactivate, 'i', $user_id);
    mysqli_stmt_execute($deactivate);
    mysqli_stmt_close($deactivate);
}

$insert = mysqli_prepare($conn, "INSERT INTO profile_media (user_id, media_type, file_path, duration_seconds, visibility, status) VALUES (?, 'Voice Introduction', ?, ?, ?, 'Active')");
if (!$insert) {
    @unlink($destination);
    http_response_code(500);
    voice_response(false, 'Unable to save voice metadata.');
}

mysqli_stmt_bind_param($insert, 'isis', $user_id, $path_for_db, $duration, $visibility);
$ok = mysqli_stmt_execute($insert);
$new_id = mysqli_insert_id($conn);
mysqli_stmt_close($insert);

if (!$ok) {
    @unlink($destination);
    http_response_code(500);
    voice_response(false, 'Unable to save the voice introduction.');
}

voice_response(true, 'Voice introduction saved.', [
    'media_id' => (int) $new_id,
    'duration_seconds' => $duration
]);
