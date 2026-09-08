<?php
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard.php');
    exit;
}

$action = $_POST['action'] ?? '';
$target_id = (int) ($_POST['user_id'] ?? 0);
$csrf = $_POST['csrf_token'] ?? '';
$return_to = $_POST['return_to'] ?? 'dashboard';
$submitted_return_url = trim((string) ($_POST['return_url'] ?? ''));

/*
 * Return URLs are accepted only for our own dashboard page. This lets a
 * bookmark action return to the exact search URL without creating an open
 * redirect. Fragments (#matching-profiles) are preserved as well.
 */
function safe_dashboard_return_url(string $url): string {
    if ($url === '') {
        return BASE_URL . 'dashboard.php#partner-search';
    }

    $parts = parse_url($url);
    if ($parts === false) {
        return BASE_URL . 'dashboard.php#partner-search';
    }

    $base = parse_url(BASE_URL);
    $scheme_ok = isset($parts['scheme'], $base['scheme'])
        ? strcasecmp($parts['scheme'], $base['scheme']) === 0 : false;
    $host_ok = isset($parts['host'], $base['host'])
        ? strcasecmp($parts['host'], $base['host']) === 0 : false;
    $port_ok = ($parts['port'] ?? null) === ($base['port'] ?? null);

    $base_path = rtrim($base['path'] ?? '/', '/');
    $path = $parts['path'] ?? '';
    $dashboard_path = $base_path . '/dashboard.php';

    if (!$scheme_ok || !$host_ok || !$port_ok || $path !== $dashboard_path) {
        return BASE_URL . 'dashboard.php#partner-search';
    }

    $safe = BASE_URL . 'dashboard.php';
    if (!empty($parts['query'])) {
        $safe .= '?' . $parts['query'];
    }
    if (isset($parts['fragment']) && $parts['fragment'] !== '') {
        $safe .= '#' . $parts['fragment'];
    }
    return $safe;
}

$return_dashboard_url = safe_dashboard_return_url($submitted_return_url);

$return_path = '../dashboard.php';
if ($return_to === 'bookmarks') {
    $return_path = '../matching/bookmarks.php';
} elseif ($return_to === 'profile') {
    $profile_return = urlencode($return_dashboard_url);
    $return_path = '../profile/view_profile.php?user_id=' . $target_id . '&return_url=' . $profile_return;
} elseif ($return_to === 'dashboard') {
    /* Convert the absolute validated URL into a redirect target. */
    $return_path = $return_dashboard_url;
}

$accept_header = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
$is_ajax = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) || strpos($accept_header, 'application/json') !== false;

function ajax_response($success, $message, $bookmarked = null) {
    header('Content-Type: application/json; charset=utf-8');
    $payload = [
        'success' => (bool) $success,
        'message' => (string) $message
    ];
    if ($bookmarked !== null) {
        $payload['bookmarked'] = (bool) $bookmarked;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function back($url, $msg, $type = 'error') {
    /* Append query parameters before the fragment so browser anchors still work. */
    $fragment = '';
    $hash_pos = strpos($url, '#');
    if ($hash_pos !== false) {
        $fragment = substr($url, $hash_pos);
        $url = substr($url, 0, $hash_pos);
    }

    $sep = strpos($url, '?') === false ? '?' : '&';
    header('Location: ' . $url . $sep . 'message=' . urlencode($msg) . '&type=' . urlencode($type) . $fragment);
    exit;
}

/* CSRF protection: session token + same-origin request verification. */
if (empty($_SESSION['matching_csrf'])) {
    $_SESSION['matching_csrf'] = bin2hex(random_bytes(32));
}

$token_valid = is_string($csrf) && $csrf !== '' && hash_equals($_SESSION['matching_csrf'], $csrf);
$origin_valid = false;
$origin = trim($_SERVER['HTTP_ORIGIN'] ?? '');
$referer = trim($_SERVER['HTTP_REFERER'] ?? '');
$expected_origin = rtrim((string) parse_url(BASE_URL, PHP_URL_SCHEME), ':/') . '://' . parse_url(BASE_URL, PHP_URL_HOST);
$base_port = parse_url(BASE_URL, PHP_URL_PORT);
if ($base_port) {
    $expected_origin .= ':' . $base_port;
}
if ($origin !== '') {
    $origin_valid = hash_equals(rtrim($expected_origin, '/'), rtrim($origin, '/'));
} elseif ($referer !== '') {
    $referer_origin = parse_url($referer, PHP_URL_SCHEME) . '://' . parse_url($referer, PHP_URL_HOST);
    $referer_port = parse_url($referer, PHP_URL_PORT);
    if ($referer_port) {
        $referer_origin .= ':' . $referer_port;
    }
    $origin_valid = hash_equals(rtrim($expected_origin, '/'), rtrim($referer_origin, '/'));
}

if (!$token_valid && !$origin_valid) {
    http_response_code(403);
    if ($is_ajax) {
        ajax_response(false, 'Your session has expired. Please refresh the page and try again.');
    }
    exit('Invalid request.');
}

/* Match-management actions operate on match_id, not a target profile id. */
$match_id = (int) ($_POST['match_id'] ?? 0);
$is_match_action = in_array($action, ['accept', 'reject', 'cancel', 'unmatch'], true);
$match_tab = $_POST['match_tab'] ?? 'all';
$allowed_match_tabs = ['all','received','sent','matches','history'];
if (!in_array($match_tab, $allowed_match_tabs, true)) $match_tab = 'all';

function back_to_matches(int $match_id, string $tab, string $msg, string $type = 'error'): void {
    $tab = in_array($tab, ['all','received','sent','matches','history'], true) ? $tab : 'all';
    $url = '../matching/my_matches.php?tab=' . urlencode($tab) . '#match-card-' . $match_id;
    back($url, $msg, $type);
}

/* Confirm the current session belongs to an active normal User account. */
$viewer_stmt = mysqli_prepare($conn, "SELECT gender FROM users WHERE user_id=? AND role='User' AND account_status='Active' LIMIT 1");
if (!$viewer_stmt) {
    if ($is_ajax) ajax_response(false, 'Unable to process the request right now.');
    back('../dashboard.php', 'Unable to process the request right now.');
}
mysqli_stmt_bind_param($viewer_stmt, 'i', $user_id);
mysqli_stmt_execute($viewer_stmt);
$viewer = mysqli_fetch_assoc(mysqli_stmt_get_result($viewer_stmt));
mysqli_stmt_close($viewer_stmt);

if (!$viewer) {
    if ($is_ajax) ajax_response(false, 'Your account is not available.');
    back('../dashboard.php', 'Your account is not available.');
}

/* Match actions are intentionally handled as normal POST/redirect requests.
 * This avoids JSON/fetch response problems and lets the browser restore the
 * exact My Matches location via a fragment + sessionStorage scroll position. */
if ($is_match_action) {
    if ($match_id <= 0) {
        back_to_matches(0, $match_tab, 'Invalid match request.');
    }

    if ($action === 'accept' || $action === 'reject') {
        $new_status = $action === 'accept' ? 'Accepted' : 'Rejected';
        $sql = "UPDATE matches SET status=?, responded_at=NOW(), matched_at=CASE WHEN ?='Accepted' THEN NOW() ELSE matched_at END, relationship_active=CASE WHEN ?='Accepted' THEN 1 ELSE 0 END, closed_reason=CASE WHEN ?='Rejected' THEN 'Rejected' ELSE NULL END WHERE match_id=? AND receiver_user_id=? AND status='Pending' AND relationship_active=1";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) back_to_matches($match_id, $match_tab, 'Request could not be updated.');
        mysqli_stmt_bind_param($stmt, 'ssssii', $new_status, $new_status, $new_status, $new_status, $match_id, $user_id);
        $executed = mysqli_stmt_execute($stmt);
        $changed = $executed ? mysqli_stmt_affected_rows($stmt) : 0;
        mysqli_stmt_close($stmt);
        $msg = $changed ? ($action === 'accept' ? 'Interest accepted.' : 'Interest rejected.') : 'Request could not be updated.';
        back_to_matches($match_id, $match_tab, $msg, $changed ? 'success' : 'error');
    }

    if ($action === 'cancel') {
        $stmt = mysqli_prepare($conn, "UPDATE matches SET status='Cancelled', relationship_active=0, responded_at=NOW(), closed_reason='Cancelled' WHERE match_id=? AND sender_user_id=? AND status='Pending' AND relationship_active=1");
        if (!$stmt) back_to_matches($match_id, $match_tab, 'Request could not be cancelled.');
        mysqli_stmt_bind_param($stmt, 'ii', $match_id, $user_id);
        $executed = mysqli_stmt_execute($stmt);
        $changed = $executed ? mysqli_stmt_affected_rows($stmt) : 0;
        mysqli_stmt_close($stmt);
        $msg = $changed ? 'Interest cancelled.' : 'Request could not be cancelled.';
        back_to_matches($match_id, $match_tab, $msg, $changed ? 'success' : 'error');
    }

    if ($action === 'unmatch') {
        $stmt = mysqli_prepare($conn, "UPDATE matches SET status='Unmatched', relationship_active=0, unmatched_at=NOW(), unmatched_by=?, closed_reason='Unmatched' WHERE match_id=? AND (sender_user_id=? OR receiver_user_id=?) AND status='Accepted' AND relationship_active=1");
        if (!$stmt) back_to_matches($match_id, $match_tab, 'Match could not be removed.');
        mysqli_stmt_bind_param($stmt, 'iiii', $user_id, $match_id, $user_id, $user_id);
        $executed = mysqli_stmt_execute($stmt);
        $changed = $executed ? mysqli_stmt_affected_rows($stmt) : 0;
        mysqli_stmt_close($stmt);
        $msg = $changed ? 'Match removed.' : 'Match could not be removed.';
        back_to_matches($match_id, $match_tab, $msg, $changed ? 'success' : 'error');
    }
}

/* All remaining actions target a profile and therefore require a valid target_id. */
if ($target_id <= 0 || $target_id === $user_id) {
    if ($is_ajax) ajax_response(false, 'This profile cannot be selected.');
    back('../dashboard.php', 'This profile cannot be selected.', 'info');
}

/* Confirm the target is an active normal User. */
$check = mysqli_prepare($conn, "SELECT u.user_id, u.gender, up.profile_visibility FROM users u LEFT JOIN user_profiles up ON up.user_id=u.user_id WHERE u.user_id=? AND u.role='User' AND u.account_status='Active' LIMIT 1");
if (!$check) {
    if ($is_ajax) ajax_response(false, 'Unable to process the request right now.');
    back($return_path, 'Unable to process the request right now.');
}
mysqli_stmt_bind_param($check, 'i', $target_id);
mysqli_stmt_execute($check);
$target = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
mysqli_stmt_close($check);

if (!$target) {
    if ($is_ajax) ajax_response(false, 'This profile is not available.');
    back($return_path, 'This profile is not available.');
}

/* ---------------- Bookmark ---------------- */
if ($action === 'bookmark_add') {
    $stmt = mysqli_prepare($conn, "INSERT INTO bookmarks (user_id, bookmarked_user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE bookmarked_user_id=VALUES(bookmarked_user_id)");
    if (!$stmt) {
        if ($is_ajax) ajax_response(false, 'Could not update bookmark.');
        back($return_path, 'Could not update bookmark.');
    }
    mysqli_stmt_bind_param($stmt, 'ii', $user_id, $target_id);
    $executed = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    /* A duplicate is already the desired final state, so verify the row rather than
       relying only on affected_rows / duplicate-key behavior. */
    $verify = mysqli_prepare($conn, "SELECT bookmark_id FROM bookmarks WHERE user_id=? AND bookmarked_user_id=? LIMIT 1");
    $verified = false;
    if ($verify) {
        mysqli_stmt_bind_param($verify, 'ii', $user_id, $target_id);
        mysqli_stmt_execute($verify);
        $verified = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($verify));
        mysqli_stmt_close($verify);
    }
    $ok = $executed && $verified;

    if ($is_ajax) {
        ajax_response($ok, $ok ? 'Profile bookmarked.' : 'Could not bookmark profile.', $ok);
    }
    back($return_path, $ok ? 'Profile bookmarked.' : 'Could not bookmark profile.', $ok ? 'success' : 'error');
}

if ($action === 'bookmark_remove') {
    $stmt = mysqli_prepare($conn, "DELETE FROM bookmarks WHERE user_id=? AND bookmarked_user_id=?");
    if (!$stmt) {
        if ($is_ajax) ajax_response(false, 'Could not update bookmark.');
        back($return_path, 'Could not update bookmark.');
    }
    mysqli_stmt_bind_param($stmt, 'ii', $user_id, $target_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($is_ajax) {
        ajax_response($ok, $ok ? 'Bookmark removed.' : 'Could not remove bookmark.', false);
    }
    back($return_path, $ok ? 'Bookmark removed.' : 'Could not remove bookmark.', $ok ? 'success' : 'error');
}

/* ---------------- Interest ---------------- */
if ($action === 'send_interest') {
    if (
        !in_array($viewer['gender'] ?? '', ['Male', 'Female'], true) ||
        !in_array($target['gender'] ?? '', ['Male', 'Female'], true) ||
        $viewer['gender'] === $target['gender']
    ) {
        back('../profile/view_profile.php?user_id=' . $target_id, 'Interest cannot be sent to a profile of the same gender.', 'info');
    }

    if (($target['profile_visibility'] ?? '') !== 'Public') {
        back('../profile/view_profile.php?user_id=' . $target_id, 'This profile is not available for interaction.', 'error');
    }

    $message = trim($_POST['interest_message'] ?? '');
    $message = $message !== '' ? mb_substr($message, 0, 300) : 'I would like to express my interest in your profile.';

    $stmt = mysqli_prepare($conn, "SELECT match_id,status,relationship_active FROM matches WHERE ((sender_user_id=? AND receiver_user_id=?) OR (sender_user_id=? AND receiver_user_id=?)) ORDER BY match_id DESC LIMIT 1");
    if (!$stmt) {
        back('../profile/view_profile.php?user_id=' . $target_id, 'Could not send interest.');
    }
    mysqli_stmt_bind_param($stmt, 'iiii', $user_id, $target_id, $target_id, $user_id);
    mysqli_stmt_execute($stmt);
    $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($existing && (int)$existing['relationship_active'] === 1 && in_array($existing['status'], ['Pending', 'Accepted'], true)) {
        back('../profile/view_profile.php?user_id=' . $target_id, 'An active interest or match already exists.', 'info');
    }

    if ($existing && $existing['status'] === 'Unmatched' && (int)$existing['relationship_active'] === 1) {
        back('../profile/view_profile.php?user_id=' . $target_id, 'This connection is unavailable right now.');
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO matches(sender_user_id,receiver_user_id,interest_message,status,relationship_active) VALUES(?,?,?,'Pending',1)");
    if (!$stmt) {
        back('../profile/view_profile.php?user_id=' . $target_id, 'Could not send interest.');
    }
    mysqli_stmt_bind_param($stmt, 'iis', $user_id, $target_id, $message);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    back('../profile/view_profile.php?user_id=' . $target_id, $ok ? 'Interest sent successfully.' : 'Could not send interest.', $ok ? 'success' : 'error');
}

if ($is_ajax) {
    ajax_response(false, 'Unknown action.');
}
back('../dashboard.php', 'Unknown action.');
