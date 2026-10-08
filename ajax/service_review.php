<?php
require_once '../config/db.php';
require_once '../includes/service_reviews.php';

header('Content-Type: application/json; charset=utf-8');

function service_review_response(bool $success, string $message, array $data = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(
        array_merge(['success' => $success, 'message' => $message], $data),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    service_review_response(false, 'Please log in again.', [], 401);
}

$user_id = (int) $_SESSION['user_id'];

$user_stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_id=? AND role='User' AND account_status='Active' LIMIT 1");
if (!$user_stmt) {
    service_review_response(false, 'The review service is temporarily unavailable.', [], 500);
}
mysqli_stmt_bind_param($user_stmt, 'i', $user_id);
mysqli_stmt_execute($user_stmt);
$valid_user = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));
mysqli_stmt_close($user_stmt);
if (!$valid_user) {
    service_review_response(false, 'Your account is not eligible to submit service reviews.', [], 403);
}

if (empty($_SESSION['booking_csrf'])) {
    $_SESSION['booking_csrf'] = bin2hex(random_bytes(24));
}

$action = (string) ($_REQUEST['action'] ?? '');

try {
    if ($action === 'eligibility') {
        $booking_detail_id = (int) ($_GET['booking_detail_id'] ?? 0);
        if ($booking_detail_id <= 0) {
            service_review_response(false, 'Invalid booking item.', [], 400);
        }

        $booking = service_review_get_eligible_booking_detail($conn, $user_id, $booking_detail_id);
        if (!$booking) {
            service_review_response(true, 'This booking item is not currently eligible for review.', [
                'eligible' => false,
                'already_reviewed' => false,
            ]);
        }

        $existing = service_review_get_existing_for_detail($conn, $user_id, $booking_detail_id);
        service_review_response(true, 'Review eligibility loaded.', [
            'eligible' => !$existing,
            'already_reviewed' => (bool) $existing,
            'booking' => [
                'booking_id' => (int) $booking['booking_id'],
                'booking_detail_id' => (int) $booking['booking_detail_id'],
                'provider_id' => (int) $booking['provider_id'],
                'service_name' => (string) $booking['service_name'],
                'package_name' => (string) $booking['package_name'],
                'provider_name' => (string) $booking['provider_name'],
            ],
            'review' => $existing ? [
                'review_id' => (int) $existing['review_id'],
                'rating' => (int) $existing['rating'],
                'review_text' => (string) ($existing['review_text'] ?? ''),
                'status' => (string) $existing['status'],
                'created_at' => (string) $existing['created_at'],
            ] : null,
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        service_review_response(false, 'Invalid request.', [], 405);
    }

    if ($action !== 'submit') {
        service_review_response(false, 'Invalid review action.', [], 400);
    }

    $csrf = (string) ($_POST['csrf'] ?? $_POST['csrf_token'] ?? '');
    if ($csrf === '' || !hash_equals($_SESSION['booking_csrf'], $csrf)) {
        service_review_response(false, 'Your session has expired. Please refresh the page and try again.', [], 403);
    }

    $booking_detail_id = (int) ($_POST['booking_detail_id'] ?? 0);
    $rating = (int) ($_POST['rating'] ?? 0);
    $review_text = (string) ($_POST['review_text'] ?? '');

    $result = service_review_submit(
        $conn,
        $user_id,
        $booking_detail_id,
        $rating,
        $review_text
    );

    service_review_response(true, 'Your review was submitted successfully.', [
        'review' => $result,
    ]);
} catch (Throwable $e) {
    $message = trim($e->getMessage());
    if ($message === '') {
        $message = 'Your review could not be processed. Please try again.';
    }

    $status = $e->getCode();
    if ($status < 400 || $status > 599) {
        $status = 400;
    }

    service_review_response(false, $message, [], $status);
}
