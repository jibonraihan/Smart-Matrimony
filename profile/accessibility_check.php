<?php

require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

function accessibility_response(bool $success, string $message, array $extra = []): void
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra));
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    accessibility_response(false, 'Authentication required.');
}

$user_id = (int) $_SESSION['user_id'];
$check = trim((string) ($_POST['check'] ?? ''));
$answer = strtoupper(trim((string) ($_POST['answer'] ?? '')));

if (!in_array($check, ['hearing', 'vision'], true)) {
    accessibility_response(false, 'Invalid accessibility check.');
}

if ($answer === '') {
    accessibility_response(false, 'Please enter your answer.');
}

if ($check === 'hearing') {
    $expected = (string) ($_SESSION['step5_hearing_code'] ?? '');
    if ($expected === '' || !preg_match('/^\d{4}$/', $answer) || !hash_equals($expected, $answer)) {
        accessibility_response(false, 'That hearing code is not correct. Please play the audio and try again.');
    }

    $stmt = mysqli_prepare($conn, 'UPDATE user_profiles SET hearing_check_passed=1 WHERE user_id=? LIMIT 1');
    if (!$stmt) {
        accessibility_response(false, 'Unable to save the hearing check right now.');
    }
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        accessibility_response(false, 'Unable to save the hearing check right now.');
    }

    accessibility_response(true, 'Hearing check passed.', ['check' => 'hearing']);
}

$expected = (string) ($_SESSION['step5_vision_code'] ?? '');
if ($expected === '' || !preg_match('/^[A-Z0-9]{5}$/', $answer) || !hash_equals($expected, $answer)) {
    accessibility_response(false, 'That visual code is not correct. Please use the new image and try again.');
}

$stmt = mysqli_prepare($conn, 'UPDATE user_profiles SET vision_check_passed=1 WHERE user_id=? LIMIT 1');
if (!$stmt) {
    accessibility_response(false, 'Unable to save the visual check right now.');
}
mysqli_stmt_bind_param($stmt, 'i', $user_id);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if (!$ok) {
    accessibility_response(false, 'Unable to save the visual check right now.');
}

accessibility_response(true, 'Visual check passed.', ['check' => 'vision']);
