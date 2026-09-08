<?php
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function bookmark_response(bool $success, string $message, ?bool $bookmarked = null, int $status = 200): void {
    http_response_code($status);
    $data = ['success' => $success, 'message' => $message];
    if ($bookmarked !== null) {
        $data['bookmarked'] = $bookmarked;
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bookmark_response(false, 'Invalid request.', null, 405);
}

if (!isset($_SESSION['user_id'])) {
    bookmark_response(false, 'Please log in again.', null, 401);
}

$user_id = (int) $_SESSION['user_id'];
$target_id = (int) ($_POST['user_id'] ?? 0);
$action = $_POST['action'] ?? '';
$csrf = $_POST['csrf_token'] ?? '';

if ($user_id <= 0 || $target_id <= 0 || $target_id === $user_id) {
    bookmark_response(false, 'This profile cannot be bookmarked.', null, 400);
}

if (empty($_SESSION['matching_csrf'])) {
    $_SESSION['matching_csrf'] = bin2hex(random_bytes(32));
}

if (!is_string($csrf) || $csrf === '' || !hash_equals($_SESSION['matching_csrf'], $csrf)) {
    bookmark_response(false, 'Your session has expired. Please refresh the page and try again.', null, 403);
}

if (!in_array($action, ['bookmark_add', 'bookmark_remove'], true)) {
    bookmark_response(false, 'Invalid bookmark action.', null, 400);
}

/*
 * The dashboard login gate has already authenticated the session. We only
 * need to make sure the target is still a valid active matrimonial profile.
 * We intentionally do not re-check the viewer role here because the old
 * card endpoint failed when the session role and DB role were temporarily
 * out of sync, even though the dashboard itself was accessible.
 */
$target = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_id=? AND role='User' AND account_status='Active' LIMIT 1");
if (!$target) {
    bookmark_response(false, 'Bookmark service is temporarily unavailable.', null, 500);
}
mysqli_stmt_bind_param($target, 'i', $target_id);
mysqli_stmt_execute($target);
$target_exists = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($target));
mysqli_stmt_close($target);

if (!$target_exists) {
    bookmark_response(false, 'This profile is not available.', null, 404);
}

if ($action === 'bookmark_add') {
    /* Existing bookmarks are already the desired final state. */
    $check = mysqli_prepare($conn, "SELECT bookmark_id FROM bookmarks WHERE user_id=? AND bookmarked_user_id=? LIMIT 1");
    if (!$check) bookmark_response(false, 'Unable to update the bookmark right now.', null, 500);
    mysqli_stmt_bind_param($check, 'ii', $user_id, $target_id);
    mysqli_stmt_execute($check);
    $exists = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($check));
    mysqli_stmt_close($check);

    if (!$exists) {
        $insert = mysqli_prepare($conn, "INSERT INTO bookmarks (user_id, bookmarked_user_id) VALUES (?, ?)");
        if (!$insert) bookmark_response(false, 'Unable to update the bookmark right now.', null, 500);
        mysqli_stmt_bind_param($insert, 'ii', $user_id, $target_id);
        if (!mysqli_stmt_execute($insert)) {
            mysqli_stmt_close($insert);
            bookmark_response(false, 'Unable to update the bookmark right now.', null, 500);
        }
        mysqli_stmt_close($insert);
    }

    bookmark_response(true, 'Profile bookmarked.', true);
}

$delete = mysqli_prepare($conn, "DELETE FROM bookmarks WHERE user_id=? AND bookmarked_user_id=?");
if (!$delete) bookmark_response(false, 'Unable to update the bookmark right now.', null, 500);
mysqli_stmt_bind_param($delete, 'ii', $user_id, $target_id);
$ok = mysqli_stmt_execute($delete);
mysqli_stmt_close($delete);

if (!$ok) {
    bookmark_response(false, 'Unable to update the bookmark right now.', null, 500);
}

bookmark_response(true, 'Bookmark removed.', false);
