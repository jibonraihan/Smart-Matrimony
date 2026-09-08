<?php

require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
 * Protected media endpoint.
 *
 * The endpoint accepts only a profile_media.media_id. It never exposes the
 * stored file path to the viewer and checks the current user's permission
 * before streaming the file.
 */

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Authentication required.');
}

$viewer_id = (int) $_SESSION['user_id'];
$media_id = (int) ($_GET['id'] ?? 0);

if ($media_id <= 0) {
    http_response_code(400);
    exit('Invalid media request.');
}

$stmt = mysqli_prepare($conn, '
    SELECT
        pm.media_id,
        pm.user_id AS owner_id,
        pm.media_type,
        pm.file_path,
        pm.visibility,
        pm.status,
        up.verification_status AS owner_verification,
        up.profile_visibility AS owner_profile_visibility
    FROM profile_media pm
    INNER JOIN user_profiles up ON up.user_id = pm.user_id
    WHERE pm.media_id=?
      AND pm.status="Active"
    LIMIT 1
');

if (!$stmt) {
    http_response_code(500);
    exit('Unable to process media request.');
}

mysqli_stmt_bind_param($stmt, 'i', $media_id);
mysqli_stmt_execute($stmt);
$media = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$media) {
    http_response_code(404);
    exit('Media not found.');
}

$owner_id = (int) $media['owner_id'];
$allowed = false;

/* Owner can always access their own active media. */
if ($viewer_id === $owner_id) {
    $allowed = true;
} elseif (($media['owner_profile_visibility'] ?? '') !== 'Public') {
    /* A hidden owner profile is not available to other viewers. */
    $allowed = false;
} else {
    $visibility = (string) ($media['visibility'] ?? 'Private');

    if ($visibility === 'Everyone') {
        $allowed = true;
    } elseif ($visibility === 'Verified Users') {
        $verify_stmt = mysqli_prepare($conn, '
            SELECT up.verification_status
            FROM users u
            INNER JOIN user_profiles up ON up.user_id=u.user_id
            WHERE u.user_id=?
              AND u.role="User"
              AND u.account_status="Active"
            LIMIT 1
        ');

        if ($verify_stmt) {
            mysqli_stmt_bind_param($verify_stmt, 'i', $viewer_id);
            mysqli_stmt_execute($verify_stmt);
            $viewer = mysqli_fetch_assoc(mysqli_stmt_get_result($verify_stmt));
            mysqli_stmt_close($verify_stmt);
            $allowed = ($viewer && ($viewer['verification_status'] ?? '') === 'Verified');
        }
    } elseif ($visibility === 'Matched Users') {
        $match_stmt = mysqli_prepare($conn, '
            SELECT match_id
            FROM matches
            WHERE status="Accepted"
              AND relationship_active=1
              AND (
                    (sender_user_id=? AND receiver_user_id=?)
                 OR (sender_user_id=? AND receiver_user_id=?)
              )
            LIMIT 1
        ');

        if ($match_stmt) {
            mysqli_stmt_bind_param($match_stmt, 'iiii', $viewer_id, $owner_id, $owner_id, $viewer_id);
            mysqli_stmt_execute($match_stmt);
            $match = mysqli_fetch_assoc(mysqli_stmt_get_result($match_stmt));
            mysqli_stmt_close($match_stmt);
            $allowed = (bool) $match;
        }
    } elseif ($visibility === 'Private') {
        $allowed = false;
    }
}

if (!$allowed) {
    http_response_code(403);
    exit('You do not have permission to access this media.');
}

$file_name = basename((string) $media['file_path']);
if ($file_name === '' || $file_name === '.' || $file_name === '..') {
    http_response_code(404);
    exit('Media file unavailable.');
}

$file_path = realpath('../uploads/profile/' . $file_name);
$upload_root = realpath('../uploads/profile');

/* Prevent path traversal and make sure the resolved file remains in the media directory. */
if ($file_path === false || $upload_root === false || !is_file($file_path) || !is_readable($file_path)) {
    http_response_code(404);
    exit('Media file unavailable.');
}

$root_prefix = rtrim($upload_root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (strpos($file_path, $root_prefix) !== 0) {
    http_response_code(403);
    exit('Invalid media path.');
}

$mime = 'application/octet-stream';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $detected = finfo_file($finfo, $file_path);
        finfo_close($finfo);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
        }
    }
}

$allowed_mime = [
    'Profile Photo' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ],
    'Voice Introduction' => [
        'audio/webm' => 'webm',
        'video/webm' => 'webm',
        'audio/ogg' => 'ogg',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a'
    ],
    'Video Introduction' => [
        'video/webm' => 'webm',
        'video/mp4' => 'mp4',
        'video/ogg' => 'ogv'
    ]
];

$type = (string) $media['media_type'];
$type_allowed = $allowed_mime[$type] ?? [];

/*
 * Some servers identify an audio-only MediaRecorder WebM file as video/webm.
 * save_voice.php already accepts that legitimate WebM container, so serve it
 * as audio/webm when the stored media type is Voice Introduction.
 */
if ($type === 'Voice Introduction' && $mime === 'video/webm') {
    $mime = 'audio/webm';
}

if (!isset($type_allowed[$mime]) && !($type === 'Voice Introduction' && $mime === 'audio/webm' && isset($type_allowed['video/webm']))) {
    http_response_code(415);
    exit('Unsupported media type.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($file_path));
header('Content-Disposition: inline; filename="media.' . $type_allowed[$mime] . '"');
if ($type === 'Voice Introduction' || $type === 'Video Introduction') {
    header('Accept-Ranges: bytes');
}
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');

readfile($file_path);
exit;
