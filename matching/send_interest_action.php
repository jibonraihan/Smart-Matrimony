<?php
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$target_id = (int) ($_POST['user_id'] ?? 0);
$csrf = (string) ($_POST['csrf_token'] ?? '');
$message = trim((string) ($_POST['interest_message'] ?? ''));

function back_to_matches(string $tab, string $message, string $type = 'error'): void
{
    $allowed = ['all', 'received', 'sent', 'matches', 'history'];
    if (!in_array($tab, $allowed, true)) {
        $tab = 'all';
    }

    header(
        'Location: my_matches.php?tab=' . urlencode($tab)
        . '&message=' . urlencode($message)
        . '&type=' . urlencode($type)
    );
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $target_id <= 0 || $target_id === $user_id) {
    back_to_matches('all', 'Invalid interest request.');
}

if (
    empty($_SESSION['matching_csrf']) ||
    $csrf === '' ||
    !hash_equals((string) $_SESSION['matching_csrf'], $csrf)
) {
    back_to_matches('all', 'Your session expired. Please refresh the page and try again.');
}

/* Viewer must be an active normal user. */
$stmt = mysqli_prepare(
    $conn,
    "SELECT user_id, gender
     FROM users
     WHERE user_id=? AND role='User' AND account_status='Active'
     LIMIT 1"
);

if (!$stmt) {
    back_to_matches('all', 'Unable to send interest right now.');
}

mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$viewer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$viewer) {
    back_to_matches('all', 'Your account is not available.');
}

/* Target must be an active public normal user. */
$stmt = mysqli_prepare(
    $conn,
    "SELECT u.user_id, u.gender, up.profile_visibility
     FROM users u
     LEFT JOIN user_profiles up ON up.user_id=u.user_id
     WHERE u.user_id=?
       AND u.role='User'
       AND u.account_status='Active'
     LIMIT 1"
);

if (!$stmt) {
    back_to_matches('all', 'Unable to send interest right now.');
}

mysqli_stmt_bind_param($stmt, 'i', $target_id);
mysqli_stmt_execute($stmt);
$target = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$target || ($target['profile_visibility'] ?? '') !== 'Public') {
    back_to_matches('all', 'This profile is not available for interest requests.');
}

if (
    !in_array($viewer['gender'] ?? '', ['Male', 'Female'], true) ||
    !in_array($target['gender'] ?? '', ['Male', 'Female'], true) ||
    $viewer['gender'] === $target['gender']
) {
    back_to_matches('all', 'Interest can only be sent to the opposite gender.', 'info');
}

$message = $message !== ''
    ? mb_substr($message, 0, 300)
    : 'I would like to express my interest in your profile.';

/* Do not create a second active relationship between the same two users. */
$stmt = mysqli_prepare(
    $conn,
    "SELECT match_id, status, relationship_active
     FROM matches
     WHERE (sender_user_id=? AND receiver_user_id=?)
        OR (sender_user_id=? AND receiver_user_id=?)
     ORDER BY match_id DESC
     LIMIT 1"
);

if (!$stmt) {
    back_to_matches('all', 'Unable to check the existing interest.');
}

mysqli_stmt_bind_param($stmt, 'iiii', $user_id, $target_id, $target_id, $user_id);
mysqli_stmt_execute($stmt);
$existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (
    $existing &&
    (int) $existing['relationship_active'] === 1 &&
    in_array($existing['status'], ['Pending', 'Accepted'], true)
) {
    back_to_matches('sent', 'An active interest or match already exists.', 'info');
}

/* Insert the actual relationship request. */
mysqli_begin_transaction($conn);

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO matches
        (sender_user_id, receiver_user_id, interest_message, status, relationship_active)
     VALUES (?, ?, ?, 'Pending', 1)"
);

if (!$stmt) {
    mysqli_rollback($conn);
    back_to_matches('all', 'Could not create the interest request.');
}

mysqli_stmt_bind_param($stmt, 'iis', $user_id, $target_id, $message);
$inserted = mysqli_stmt_execute($stmt);
$new_match_id = $inserted ? mysqli_insert_id($conn) : 0;
$error = $inserted ? '' : mysqli_stmt_error($stmt);
mysqli_stmt_close($stmt);

if (!$inserted || $new_match_id <= 0) {
    mysqli_rollback($conn);
    back_to_matches('all', $error !== '' ? 'Interest could not be sent: ' . $error : 'Interest could not be sent.');
}

/* Verify the row before confirming success. */
$verify = mysqli_prepare(
    $conn,
    "SELECT match_id
     FROM matches
     WHERE match_id=?
       AND sender_user_id=?
       AND receiver_user_id=?
       AND status='Pending'
       AND relationship_active=1
     LIMIT 1"
);

$verified = false;
if ($verify) {
    mysqli_stmt_bind_param($verify, 'iii', $new_match_id, $user_id, $target_id);
    mysqli_stmt_execute($verify);
    $verified = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($verify));
    mysqli_stmt_close($verify);
}

if (!$verified) {
    mysqli_rollback($conn);
    back_to_matches('all', 'The interest request could not be verified.');
}

mysqli_commit($conn);

back_to_matches('sent', 'Interest sent successfully.', 'success');
