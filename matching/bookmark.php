<?php
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function bookmark_json(bool $success, string $message, ?bool $bookmarked = null): void {
    $payload = ['success' => $success, 'message' => $message];
    if ($bookmarked !== null) {
        $payload['bookmarked'] = $bookmarked;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    bookmark_json(false, 'Invalid request method.');
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    bookmark_json(false, 'Please log in again.');
}

$user_id = (int) $_SESSION['user_id'];
$target_id = (int) ($_POST['user_id'] ?? 0);
$action = $_POST['action'] ?? '';
$csrf = $_POST['csrf_token'] ?? '';

if (empty($_SESSION['matching_csrf'])) {
    $_SESSION['matching_csrf'] = bin2hex(random_bytes(32));
}

if (!is_string($csrf) || $csrf === '' || !hash_equals($_SESSION['matching_csrf'], $csrf)) {
    http_response_code(403);
    bookmark_json(false, 'Your session has expired. Please refresh the page and try again.');
}

if ($user_id <= 0 || $target_id <= 0 || $target_id === $user_id) {
    bookmark_json(false, 'This profile cannot be bookmarked.');
}

/* The bookmark owner may be any active authenticated account. The target remains an active User profile. */
$stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_id=? AND account_status='Active' LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    bookmark_json(false, 'Bookmark service is temporarily unavailable.');
}
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$viewer_exists = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$viewer_exists) {
    http_response_code(403);
    bookmark_json(false, 'Your account is not available.');
}

$stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_id=? AND role='User' AND account_status='Active' LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    bookmark_json(false, 'Bookmark service is temporarily unavailable.');
}
mysqli_stmt_bind_param($stmt, 'i', $target_id);
mysqli_stmt_execute($stmt);
$target_exists = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$target_exists) {
    bookmark_json(false, 'This profile is not available.');
}

if ($action === 'bookmark_add') {
    /* Check first, then insert. This works with or without the optional unique index. */
    $check = mysqli_prepare($conn, "SELECT bookmark_id FROM bookmarks WHERE user_id=? AND bookmarked_user_id=? LIMIT 1");
    if (!$check) {
        http_response_code(500);
        bookmark_json(false, 'Unable to save the bookmark right now.');
    }
    mysqli_stmt_bind_param($check, 'ii', $user_id, $target_id);
    mysqli_stmt_execute($check);
    $already = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($check));
    mysqli_stmt_close($check);

    if (!$already) {
        $insert = mysqli_prepare($conn, "INSERT INTO bookmarks (user_id, bookmarked_user_id) VALUES (?, ?)");
        if (!$insert) {
            http_response_code(500);
            bookmark_json(false, 'Unable to save the bookmark right now.');
        }
        mysqli_stmt_bind_param($insert, 'ii', $user_id, $target_id);
        $ok = mysqli_stmt_execute($insert);
        mysqli_stmt_close($insert);
        if (!$ok) {
            /* A concurrent duplicate still means the final state is bookmarked. */
            $verify = mysqli_prepare($conn, "SELECT bookmark_id FROM bookmarks WHERE user_id=? AND bookmarked_user_id=? LIMIT 1");
            $exists_after_error = false;
            if ($verify) {
                mysqli_stmt_bind_param($verify, 'ii', $user_id, $target_id);
                mysqli_stmt_execute($verify);
                $exists_after_error = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($verify));
                mysqli_stmt_close($verify);
            }
            if (!$exists_after_error) {
                http_response_code(500);
                bookmark_json(false, 'Unable to save the bookmark right now.');
            }
        }
    }

    bookmark_json(true, 'Profile bookmarked.', true);
}

if ($action === 'bookmark_remove') {
    $delete = mysqli_prepare($conn, "DELETE FROM bookmarks WHERE user_id=? AND bookmarked_user_id=?");
    if (!$delete) {
        http_response_code(500);
        bookmark_json(false, 'Unable to remove the bookmark right now.');
    }
    mysqli_stmt_bind_param($delete, 'ii', $user_id, $target_id);
    $ok = mysqli_stmt_execute($delete);
    mysqli_stmt_close($delete);

    if (!$ok) {
        http_response_code(500);
        bookmark_json(false, 'Unable to remove the bookmark right now.');
    }

    bookmark_json(true, 'Bookmark removed.', false);
}

http_response_code(400);
bookmark_json(false, 'Invalid bookmark action.');
