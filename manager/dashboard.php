<?php
/*
 * Keep Event Manager authentication isolated from the regular customer
 * session so both can coexist in separate browser tabs.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_name('SMART_MANAGER_SESSION');
    ini_set('session.use_cookies', '0');
    ini_set('session.use_only_cookies', '0');
    ini_set('session.use_trans_sid', '0');

    $manager_sid = $_GET['manager_sid'] ?? $_POST['manager_sid'] ?? '';
    if (!preg_match('/^[a-f0-9]{64}$/', $manager_sid)) {
        header('Location: login.php');
        exit;
    }
    session_id($manager_sid);
    session_start();
}

require_once '../config/db.php';
require_once '../config/mail.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$manager_sid = session_id();
$manager_id = (int) $_SESSION['user_id'];

// Re-check the account on every Manager request. If Admin changes the role
// or suspends/deactivates the account while this session is still open,
// the existing Manager session is immediately invalidated.
$manager_stmt = mysqli_prepare($conn, 'SELECT role, account_status FROM users WHERE user_id = ? LIMIT 1');
mysqli_stmt_bind_param($manager_stmt, 'i', $manager_id);
mysqli_stmt_execute($manager_stmt);
$manager_account = mysqli_fetch_assoc(mysqli_stmt_get_result($manager_stmt));
mysqli_stmt_close($manager_stmt);

if (!$manager_account || $manager_account['role'] !== 'Manager' || $manager_account['account_status'] !== 'Active') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
    }
    session_destroy();
    header('Location: login.php?access=revoked');
    exit;
}

$page_css = 'assets/css/manager.css';

if (empty($_SESSION['manager_csrf'])) {
    $_SESSION['manager_csrf'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['manager_csrf'];
$manager_name = trim(($_SESSION['manager_name'] ?? '')) ?: 'Event Manager';
$manager_url = function(string $path = 'dashboard.php', array $params = [], string $fragment = '') use ($manager_sid): string {
    $params = array_merge(['manager_sid' => $manager_sid], $params);
    $url = $path . '?' . http_build_query($params);
    return $url . ($fragment !== '' ? '#' . ltrim($fragment, '#') : '');
};
$message = '';
$error = '';
$is_ajax_request = (($_POST['ajax'] ?? '') === '1') || (($_GET['ajax'] ?? '') === '1');

$upload_dir = dirname(__DIR__) . '/uploads/services/';
$upload_web = BASE_URL . 'uploads/services/';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0755, true);
}

function manager_clean_image_name(string $name): string {
    return preg_replace('/[^A-Za-z0-9._-]/', '_', basename($name));
}

function manager_remove_image(?string $filename, string $upload_dir): void {
    if (!$filename) return;
    $safe = basename($filename);
    $path = $upload_dir . $safe;
    if (is_file($path)) @unlink($path);
}

function manager_handle_image_upload(string $field, string $upload_dir): array {
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['name' => null, 'error' => null];
    }

    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['name' => null, 'error' => 'The image upload failed. Please try again.'];
    }
    if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
        return ['name' => null, 'error' => 'Image size must be 10 MB or less.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];
    if (!isset($allowed[$mime])) {
        return ['name' => null, 'error' => 'Only JPG, PNG or WebP images are allowed.'];
    }

    if (!is_dir($upload_dir) && !@mkdir($upload_dir, 0755, true)) {
        return ['name' => null, 'error' => 'The service upload folder could not be created.'];
    }

    $filename = 'svc_' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    $destination = $upload_dir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['name' => null, 'error' => 'The image could not be saved.'];
    }

    return ['name' => $filename, 'error' => null];
}

function manager_get_booking_data(mysqli $conn, int $booking_id): ?array {
    $stmt = mysqli_prepare($conn, "SELECT b.booking_id, b.total_price, b.booking_status, b.booking_date,
            b.cancellation_reason, b.confirmation_email_status, b.confirmation_email_sent_at,
            b.cancellation_email_status, b.cancellation_email_sent_at,
            u.first_name, u.last_name, u.email
        FROM bookings b
        INNER JOIN users u ON u.user_id=b.user_id
        WHERE b.booking_id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $booking_id);
    mysqli_stmt_execute($stmt);
    $booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$booking) return null;

    $items = [];
    $stmt = mysqli_prepare($conn, "SELECT s.service_name,
            COALESCE(sp.package_name, sp.provider_name) AS package_name,
            bd.quantity, bd.event_date, bd.unit_price,
            sp.provider_id, sp.manager_id,
            CONCAT(COALESCE(m.first_name,''), ' ', COALESCE(m.last_name,'')) AS manager_name
        FROM booking_details bd
        INNER JOIN service_providers sp ON sp.provider_id=bd.provider_id
        INNER JOIN services s ON s.service_id=sp.service_id
        LEFT JOIN users m ON m.user_id=sp.manager_id
        WHERE bd.booking_id=?
        ORDER BY bd.detail_id ASC");
    mysqli_stmt_bind_param($stmt, 'i', $booking_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) $items[] = $row;
    mysqli_stmt_close($stmt);
    $booking['items'] = $items;
    return $booking;
}

function manager_get_package_value_for_booking(mysqli $conn, int $booking_id, int $manager_id): float {
    $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(
            COALESCE(NULLIF(bd.unit_price,0),
                ROUND(sp.price * (1 - (LEAST(100,GREATEST(0,COALESCE(sp.discount_percent,0))) / 100)), 2)
            ) * bd.quantity
        ),0) AS value
        FROM booking_details bd
        INNER JOIN service_providers sp ON sp.provider_id=bd.provider_id
        WHERE bd.booking_id=? AND sp.manager_id=?");
    mysqli_stmt_bind_param($stmt, 'ii', $booking_id, $manager_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (float) ($row['value'] ?? 0);
}

function manager_log_activity(mysqli $conn, int $manager_id, string $manager_name, string $action, ?int $booking_id = null, ?int $provider_id = null, float $amount = 0.0, string $details = ''): void {
    $stmt = mysqli_prepare($conn, 'INSERT INTO manager_activity_log (manager_id, manager_name, action_type, booking_id, provider_id, amount, details) VALUES (?,?,?,?,?,?,?)');
    mysqli_stmt_bind_param($stmt, 'issiids', $manager_id, $manager_name, $action, $booking_id, $provider_id, $amount, $details);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}


function manager_json_response(bool $ok, string $message = '', array $extra = []): void {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code($ok ? 200 : 400);
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function manager_catalog_package_payload(array $p): array {
    return [
        'provider_id' => (int)($p['provider_id'] ?? 0),
        'service_id' => (int)($p['service_id'] ?? 0),
        'service_name' => (string)($p['service_name'] ?? ''),
        'provider_name' => (string)($p['provider_name'] ?? ''),
        'package_name' => (string)($p['package_name'] ?? ''),
        'package_details' => (string)($p['package_details'] ?? ''),
        'price' => (float)($p['price'] ?? 0),
        'discount_percent' => (float)($p['discount_percent'] ?? 0),
        'contact_number' => (string)($p['contact_number'] ?? ''),
        'location' => (string)($p['location'] ?? ''),
        'rating' => (float)($p['rating'] ?? 0),
        'review_count' => (int)($p['review_count'] ?? 0),
        'status' => (string)($p['status'] ?? 'Active'),
        'image' => (string)($p['image'] ?? ''),
        'manager_id' => (int)($p['manager_id'] ?? 0),
        'manager_name' => trim((string)($p['manager_name'] ?? '')),
    ];
}

function manager_render_catalog_card(array $p, int $manager_id, string $upload_web, string $manager_sid, string $csrf): string {
    $p_discount = max(0, min(100, (float)($p['discount_percent'] ?? 0)));
    $p_final = (float)$p['price'] * (1 - ($p_discount / 100));
    $is_mine = (int)($p['manager_id'] ?? 0) === $manager_id;
    $package_payload = manager_catalog_package_payload($p);
    $package_payload['image_url'] = !empty($p['image']) ? $upload_web . rawurlencode($p['image']) : '';
    $payload = htmlspecialchars(json_encode($package_payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
    $image = !empty($p['image'])
        ? '<img src="' . htmlspecialchars($upload_web . rawurlencode($p['image']), ENT_QUOTES, 'UTF-8') . '" alt="">'
        : '<i class="fa-solid fa-ring"></i>';
    $owner_name = trim((string)($p['manager_name'] ?? '')) ?: 'Manager';
    $owner = !empty($p['manager_id'])
        ? htmlspecialchars($owner_name) . ' <em>(SM-' . str_pad((string)((int)$p['manager_id']), 6, '0', STR_PAD_LEFT) . ')</em>'
        : '<span>Unassigned</span>';
    $old_price = $p_discount > 0 ? '<small class="manager-old-price">৳' . number_format((float)$p['price'], 0) . '</small>' : '';
    $discount = $p_discount > 0 ? '<span class="manager-discount-pill">' . rtrim(rtrim(number_format($p_discount, 2), '0'), '.') . '% OFF</span>' : '';
    $actions = '';
    if ($is_mine) {
        $actions = '<div class="package-actions">'
            . '<button type="button" class="icon-edit js-edit-package" data-package="' . $payload . '" title="Edit package"><i class="fa-solid fa-pen"></i></button>'
            . '<form method="post" action="dashboard.php?manager_sid=' . urlencode($manager_sid) . '"><input type="hidden" name="csrf" value="' . htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="provider_id" value="' . (int)$p['provider_id'] . '"><input type="hidden" name="new_status" value="' . ($p['status'] === 'Active' ? 'Inactive' : 'Active') . '"><button class="icon-status" type="submit" title="' . ($p['status'] === 'Active' ? 'Deactivate' : 'Activate') . '"><i class="fa-solid ' . ($p['status'] === 'Active' ? 'fa-eye-slash' : 'fa-eye') . '"></i></button></form>'
            . '<form method="post" action="dashboard.php?manager_sid=' . urlencode($manager_sid) . '" onsubmit="return confirm(\'Remove this package? If it has booking history, it will be kept and should be deactivated instead.\');"><input type="hidden" name="csrf" value="' . htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="action" value="delete_provider"><input type="hidden" name="provider_id" value="' . (int)$p['provider_id'] . '"><button class="icon-danger" type="submit" title="Remove package"><i class="fa-solid fa-trash-can"></i></button></form>'
            . '</div>';
    } else {
        $actions = '<span class="catalog-owner-note">View only</span>';
    }
    return '<article class="package-card" data-provider-id="' . (int)$p['provider_id'] . '">'
        . '<div class="package-card-main">'
        . '<div class="package-thumb">' . $image . '</div>'
        . '<div class="package-info">'
        . '<span>' . htmlspecialchars((string)$p['service_name']) . '</span>'
        . '<h3>' . htmlspecialchars((string)($p['package_name'] ?: $p['provider_name'])) . '</h3>'
        . '<p>' . htmlspecialchars((string)$p['provider_name']) . ($p['location'] ? ' · ' . htmlspecialchars((string)$p['location']) : '') . '</p>'
        . (!empty($p['package_details']) ? '<small class="package-details-preview">' . htmlspecialchars(mb_strimwidth(trim((string)$p['package_details']), 0, 180, '…', 'UTF-8')) . '</small>' : '')
        . '<small class="package-owner">' . $owner . '</small>'
        . '</div></div>'
        . '<div class="package-card-footer">'
        . '<div class="package-price-group">' . $old_price . '<strong>৳' . number_format($p_final, 0) . '</strong> ' . $discount . '</div>'
        . '<div class="package-card-right"><span class="status-pill ' . strtolower((string)$p['status']) . '">' . htmlspecialchars((string)$p['status']) . '</span>' . $actions . '</div>'
        . '</div></article>';
}

function manager_render_catalog_list(array $packages, int $manager_id, string $upload_web, string $manager_sid, string $csrf): string {
    if (!$packages) {
        return '<div class="manager-empty catalog-empty"><i class="fa-regular fa-folder-open"></i><h3>No packages found</h3><p>No packages are available in this view yet.</p></div>';
    }
    $html = '';
    foreach ($packages as $p) $html .= manager_render_catalog_card($p, $manager_id, $upload_web, $manager_sid, $csrf);
    return $html;
}

function manager_get_name(mysqli $conn, int $manager_id): string {
    $stmt = mysqli_prepare($conn, 'SELECT first_name, last_name FROM users WHERE user_id=? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $manager_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    return $name !== '' ? $name : 'Event Manager';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'save_provider') {
                $provider_id = (int) ($_POST['provider_id'] ?? 0);
                $service_id = (int) ($_POST['service_id'] ?? 0);
                $provider_name = trim($_POST['provider_name'] ?? '');
                $package_name = trim($_POST['package_name'] ?? '');
                $package_details = trim($_POST['package_details'] ?? '');
                $price = (float) ($_POST['price'] ?? 0);
                $discount_percent = max(0, min(100, (float) ($_POST['discount_percent'] ?? 0)));
                $contact_number = trim($_POST['contact_number'] ?? '');
                $location = trim($_POST['location'] ?? '');
                $rating = max(0, min(5, (float) ($_POST['rating'] ?? 0)));
                $review_count = max(0, (int) ($_POST['review_count'] ?? 0));
                $status = ($_POST['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';

                if ($service_id <= 0 || $provider_name === '' || $package_name === '' || $price < 0 || $discount_percent < 0 || $discount_percent > 100) {
                    throw new RuntimeException('Please complete the required package fields.');
                }

                $upload = manager_handle_image_upload('package_image', $upload_dir);
                if ($upload['error']) throw new RuntimeException($upload['error']);

                if ($provider_id > 0) {
                    $stmt = mysqli_prepare($conn, 'SELECT image FROM service_providers WHERE provider_id=? AND manager_id=? LIMIT 1');
                    mysqli_stmt_bind_param($stmt, 'ii', $provider_id, $manager_id);
                    mysqli_stmt_execute($stmt);
                    $owned = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                    mysqli_stmt_close($stmt);
                    if (!$owned) throw new RuntimeException('You can edit only your own packages.');

                    $image = $owned['image'];
                    if ($upload['name']) $image = $upload['name'];

                    $stmt = mysqli_prepare($conn, 'UPDATE service_providers SET service_id=?, provider_name=?, package_name=?, package_details=?, price=?, discount_percent=?, contact_number=?, location=?, rating=?, review_count=?, status=?, image=? WHERE provider_id=? AND manager_id=?');
                    mysqli_stmt_bind_param($stmt, 'isssddssdissii', $service_id, $provider_name, $package_name, $package_details, $price, $discount_percent, $contact_number, $location, $rating, $review_count, $status, $image, $provider_id, $manager_id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    if ($upload['name'] && $owned['image'] && $owned['image'] !== $upload['name']) manager_remove_image($owned['image'], $upload_dir);
                    manager_log_activity($conn, $manager_id, $manager_name, 'package_updated', null, $provider_id, 0, 'Package details updated.');
                    $message = 'Package updated successfully.';
                } else {
                    $image = $upload['name'] ?? null;
                    $stmt = mysqli_prepare($conn, 'INSERT INTO service_providers (service_id, provider_name, package_name, package_details, price, discount_percent, contact_number, location, rating, review_count, manager_id, status, image) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
                    mysqli_stmt_bind_param($stmt, 'isssddssdiiss', $service_id, $provider_name, $package_name, $package_details, $price, $discount_percent, $contact_number, $location, $rating, $review_count, $manager_id, $status, $image);
                    mysqli_stmt_execute($stmt);
                    $new_provider_id = mysqli_insert_id($conn);
                    mysqli_stmt_close($stmt);
                    manager_log_activity($conn, $manager_id, $manager_name, 'package_added', null, $new_provider_id, 0, 'Package added to the service catalog.');
                    $message = 'Package added successfully.';
                }
                if ($is_ajax_request) {
                    manager_json_response(true, $message, ['provider_id' => (int)($provider_id ?: $new_provider_id)]);
                }
                header('Location: dashboard.php?manager_sid=' . urlencode($manager_sid) . '&success=' . urlencode($message) . '&catalog_tab=my#catalog');
                exit;
            }

            if ($action === 'toggle_status') {
                $provider_id = (int) ($_POST['provider_id'] ?? 0);
                $new_status = ($_POST['new_status'] ?? '') === 'Inactive' ? 'Inactive' : 'Active';
                $stmt = mysqli_prepare($conn, 'UPDATE service_providers SET status=? WHERE provider_id=? AND manager_id=?');
                mysqli_stmt_bind_param($stmt, 'sii', $new_status, $provider_id, $manager_id);
                mysqli_stmt_execute($stmt);
                $changed = mysqli_stmt_affected_rows($stmt) === 1;
                mysqli_stmt_close($stmt);
                if (!$changed) throw new RuntimeException('You can change status only for your own package.');
                manager_log_activity($conn, $manager_id, $manager_name, 'package_status_changed', null, $provider_id, 0, 'Package status changed to ' . $new_status . '.');
                header('Location: dashboard.php?manager_sid=' . urlencode($manager_sid) . '&success=' . urlencode('Package status updated.') . '&catalog_tab=my#catalog');
                exit;
            }

            if ($action === 'delete_provider') {
                $provider_id = (int) ($_POST['provider_id'] ?? 0);
                $stmt = mysqli_prepare($conn, 'SELECT image FROM service_providers WHERE provider_id=? AND manager_id=? LIMIT 1');
                mysqli_stmt_bind_param($stmt, 'ii', $provider_id, $manager_id);
                mysqli_stmt_execute($stmt);
                $owned = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);
                if (!$owned) throw new RuntimeException('You can remove only your own package.');

                $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM booking_details WHERE provider_id=?');
                mysqli_stmt_bind_param($stmt, 'i', $provider_id);
                mysqli_stmt_execute($stmt);
                $booked = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0);
                mysqli_stmt_close($stmt);
                if ($booked > 0) throw new RuntimeException('This package has booking history. Deactivate it instead of deleting it so the booking history remains safe.');

                manager_log_activity($conn, $manager_id, $manager_name, 'package_deleted', null, $provider_id, 0, 'Package removed from the catalog.');
                $stmt = mysqli_prepare($conn, 'DELETE FROM service_providers WHERE provider_id=? AND manager_id=?');
                mysqli_stmt_bind_param($stmt, 'ii', $provider_id, $manager_id);
                mysqli_stmt_execute($stmt);
                $deleted = mysqli_stmt_affected_rows($stmt) === 1;
                mysqli_stmt_close($stmt);
                if (!$deleted) throw new RuntimeException('The package could not be removed.');
                manager_remove_image($owned['image'] ?? null, $upload_dir);
                header('Location: dashboard.php?manager_sid=' . urlencode($manager_sid) . '&success=' . urlencode('Package removed.') . '&catalog_tab=my#catalog');
                exit;
            }

            if (in_array($action, ['booking_confirm', 'booking_cancel', 'booking_complete', 'booking_delete', 'booking_resend_email'], true)) {
                $booking_id = (int) ($_POST['booking_id'] ?? 0);
                if ($booking_id <= 0) throw new RuntimeException('Invalid booking selected.');

                $booking = manager_get_booking_data($conn, $booking_id);
                if (!$booking) throw new RuntimeException('That booking could not be found.');

                $current_status = $booking['booking_status'];
                $manager_name = manager_get_name($conn, $manager_id);
                $manager_share = manager_get_package_value_for_booking($conn, $booking_id, $manager_id);

                if ($action === 'booking_confirm') {
                    if ($current_status !== 'Pending') throw new RuntimeException('Only pending bookings can be confirmed.');
                    $stmt = mysqli_prepare($conn, "UPDATE bookings SET booking_status='Confirmed', confirmation_email_status='Not Sent', confirmation_email_sent_at=NULL, confirmed_by_manager_id=?, confirmed_at=NOW() WHERE booking_id=? AND booking_status='Pending'");
                    mysqli_stmt_bind_param($stmt, 'ii', $manager_id, $booking_id);
                    mysqli_stmt_execute($stmt);
                    $changed = mysqli_stmt_affected_rows($stmt) === 1;
                    mysqli_stmt_close($stmt);
                    if (!$changed) throw new RuntimeException('The booking status changed before this action was completed. Please refresh and try again.');

                    manager_log_activity($conn, $manager_id, $manager_name, 'booking_confirmed', $booking_id, null, $manager_share, 'Booking confirmed by ' . $manager_name . '.');
                    $mail_sent = send_booking_status_email($booking['email'], $booking['first_name'], $booking, 'Confirmed', '', $manager_name);
                    if ($mail_sent) {
                        $stmt = mysqli_prepare($conn, "UPDATE bookings SET confirmation_email_status='Sent', confirmation_email_sent_at=NOW() WHERE booking_id=?");
                        mysqli_stmt_bind_param($stmt, 'i', $booking_id);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                        $message = 'Booking confirmed and confirmation email sent.';
                    } else {
                        $stmt = mysqli_prepare($conn, "UPDATE bookings SET confirmation_email_status='Failed' WHERE booking_id=?");
                        mysqli_stmt_bind_param($stmt, 'i', $booking_id);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                        $message = 'Booking confirmed, but the confirmation email could not be sent. You can retry it from the Confirmed tab.';
                    }
                    header('Location: dashboard.php?manager_sid=' . urlencode($manager_sid) . '&booking_tab=Confirmed&success=' . urlencode($message) . ($mail_sent ? '' : '&email_failed=1') . '#bookings');
                    exit;
                }

                if ($action === 'booking_cancel') {
                    if (!in_array($current_status, ['Pending', 'Confirmed'], true)) throw new RuntimeException('Only pending or confirmed bookings can be cancelled.');
                    $reason = trim($_POST['cancellation_reason'] ?? '');
                    if ($reason === '') throw new RuntimeException('Please provide a cancellation reason.');
                    if (mb_strlen($reason) < 5) throw new RuntimeException('The cancellation reason should be at least 5 characters.');
                    if (mb_strlen($reason) > 2000) throw new RuntimeException('The cancellation reason is too long.');

                    $stmt = mysqli_prepare($conn, "UPDATE bookings SET booking_status='Cancelled', cancellation_reason=?, cancelled_at=NOW(), cancelled_by='Manager', cancelled_by_manager_id=?, cancellation_email_status='Not Sent', cancellation_email_sent_at=NULL WHERE booking_id=? AND booking_status IN ('Pending','Confirmed')");
                    mysqli_stmt_bind_param($stmt, 'sii', $reason, $manager_id, $booking_id);
                    mysqli_stmt_execute($stmt);
                    $changed = mysqli_stmt_affected_rows($stmt) === 1;
                    mysqli_stmt_close($stmt);
                    if (!$changed) throw new RuntimeException('The booking status changed before this action was completed. Please refresh and try again.');

                    manager_log_activity($conn, $manager_id, $manager_name, 'booking_cancelled', $booking_id, null, $manager_share, 'Booking cancelled by ' . $manager_name . '. Reason: ' . $reason);
                    $mail_sent = send_booking_status_email($booking['email'], $booking['first_name'], $booking, 'Cancelled', $reason, $manager_name);
                    if ($mail_sent) {
                        $stmt = mysqli_prepare($conn, "UPDATE bookings SET cancellation_email_status='Sent', cancellation_email_sent_at=NOW() WHERE booking_id=?");
                        mysqli_stmt_bind_param($stmt, 'i', $booking_id);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                        $message = 'Booking cancelled and cancellation email sent.';
                    } else {
                        $stmt = mysqli_prepare($conn, "UPDATE bookings SET cancellation_email_status='Failed' WHERE booking_id=?");
                        mysqli_stmt_bind_param($stmt, 'i', $booking_id);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                        $message = 'Booking cancelled, but the cancellation email could not be sent. You can retry it from the Cancelled tab.';
                    }
                    header('Location: dashboard.php?manager_sid=' . urlencode($manager_sid) . '&booking_tab=Cancelled&success=' . urlencode($message) . ($mail_sent ? '' : '&email_failed=1') . '#bookings');
                    exit;
                }

                if ($action === 'booking_complete') {
                    if ($current_status !== 'Confirmed') throw new RuntimeException('Only confirmed bookings can be marked completed.');
                    $stmt = mysqli_prepare($conn, "UPDATE bookings SET booking_status='Completed', completed_by_manager_id=?, completed_at=NOW() WHERE booking_id=? AND booking_status='Confirmed'");
                    mysqli_stmt_bind_param($stmt, 'ii', $manager_id, $booking_id);
                    mysqli_stmt_execute($stmt);
                    $changed = mysqli_stmt_affected_rows($stmt) === 1;
                    mysqli_stmt_close($stmt);
                    if (!$changed) throw new RuntimeException('The booking status changed before this action was completed. Please refresh and try again.');
                    manager_log_activity($conn, $manager_id, $manager_name, 'booking_completed', $booking_id, null, $manager_share, 'Booking marked completed by ' . $manager_name . '.');
                    header('Location: dashboard.php?manager_sid=' . urlencode($manager_sid) . '&booking_tab=Completed&success=' . urlencode('Booking marked as completed.') . '#bookings');
                    exit;
                }

                if ($action === 'booking_delete') {
                    if (!in_array($current_status, ['Completed', 'Cancelled'], true)) throw new RuntimeException('Only completed or cancelled booking history can be deleted.');
                    manager_log_activity($conn, $manager_id, $manager_name, 'booking_deleted', $booking_id, null, $manager_share, 'Booking history deleted by ' . $manager_name . '.');
                    $stmt = mysqli_prepare($conn, "DELETE FROM bookings WHERE booking_id=? AND booking_status IN ('Completed','Cancelled')");
                    mysqli_stmt_bind_param($stmt, 'i', $booking_id);
                    mysqli_stmt_execute($stmt);
                    $deleted = mysqli_stmt_affected_rows($stmt) === 1;
                    mysqli_stmt_close($stmt);
                    if (!$deleted) throw new RuntimeException('The booking could not be deleted. Please refresh and try again.');
                    header('Location: dashboard.php?manager_sid=' . urlencode($manager_sid) . '&booking_tab=' . urlencode($current_status) . '&success=' . urlencode('Booking history deleted.') . '#bookings');
                    exit;
                }

                if ($action === 'booking_resend_email') {
                    $email_type = ($_POST['email_type'] ?? '') === 'cancelled' ? 'cancelled' : 'confirmed';
                    if ($email_type === 'confirmed') {
                        if ($current_status !== 'Confirmed' || $booking['confirmation_email_status'] !== 'Failed') {
                            throw new RuntimeException('There is no failed confirmation email to retry.');
                        }
                        $mail_sent = send_booking_status_email($booking['email'], $booking['first_name'], $booking, 'Confirmed', '', $manager_name);
                        if ($mail_sent) {
                            $stmt = mysqli_prepare($conn, "UPDATE bookings SET confirmation_email_status='Sent', confirmation_email_sent_at=NOW() WHERE booking_id=?");
                            mysqli_stmt_bind_param($stmt, 'i', $booking_id);
                            mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);
                            $message = 'Confirmation email sent successfully.';
                        } else {
                            $message = 'The confirmation email still could not be sent.';
                        }
                        header('Location: dashboard.php?manager_sid=' . urlencode($manager_sid) . '&booking_tab=Confirmed&success=' . urlencode($message) . ($mail_sent ? '' : '&email_failed=1') . '#bookings');
                        exit;
                    }

                    if ($current_status !== 'Cancelled' || $booking['cancellation_email_status'] !== 'Failed') {
                        throw new RuntimeException('There is no failed cancellation email to retry.');
                    }
                    $mail_sent = send_booking_status_email($booking['email'], $booking['first_name'], $booking, 'Cancelled', $booking['cancellation_reason'] ?? '', $manager_name);
                    if ($mail_sent) {
                        $stmt = mysqli_prepare($conn, "UPDATE bookings SET cancellation_email_status='Sent', cancellation_email_sent_at=NOW() WHERE booking_id=?");
                        mysqli_stmt_bind_param($stmt, 'i', $booking_id);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                        $message = 'Cancellation email sent successfully.';
                    } else {
                        $message = 'The cancellation email still could not be sent.';
                    }
                    header('Location: dashboard.php?manager_sid=' . urlencode($manager_sid) . '&booking_tab=Cancelled&success=' . urlencode($message) . ($mail_sent ? '' : '&email_failed=1') . '#bookings');
                    exit;
                }
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

if ($is_ajax_request && $error !== '') {
    manager_json_response(false, $error);
}

if (!empty($_GET['success'])) $message = trim($_GET['success']);
$email_failed_notice = !empty($_GET['email_failed']);

$manager_name = manager_get_name($conn, $manager_id);
$manager_public_id = 'SM-' . str_pad((string)$manager_id, 6, '0', STR_PAD_LEFT);

$services = [];
$res = mysqli_query($conn, 'SELECT service_id, service_name FROM services ORDER BY service_name');
while ($row = mysqli_fetch_assoc($res)) $services[] = $row;

$edit_package = null;
$edit_id = (int) ($_GET['edit'] ?? 0);
if ($edit_id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM service_providers WHERE provider_id=? AND manager_id=? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'ii', $edit_id, $manager_id);
    mysqli_stmt_execute($stmt);
    $edit_package = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$edit_package) $error = 'You can edit only your own package.';
}

$catalog_tab = ($_GET['catalog_tab'] ?? 'all') === 'my' ? 'my' : 'all';

$all_packages = [];
$stmt = mysqli_prepare($conn, "SELECT sp.*, s.service_name,
        CONCAT(COALESCE(m.first_name,''), ' ', COALESCE(m.last_name,'')) AS manager_name
    FROM service_providers sp
    INNER JOIN services s ON s.service_id=sp.service_id
    LEFT JOIN users m ON m.user_id=sp.manager_id
    ORDER BY sp.created_at DESC, sp.provider_id DESC");
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) $all_packages[] = $row;
mysqli_stmt_close($stmt);

if (($_GET['ajax'] ?? '') === '1' && ($_GET['action'] ?? '') === 'catalog_snapshot') {
    $tab = ($_GET['catalog_tab'] ?? $catalog_tab) === 'my' ? 'my' : 'all';
    $view_packages = $tab === 'my'
        ? array_values(array_filter($all_packages, fn($p) => (int)$p['manager_id'] === $manager_id))
        : $all_packages;
    $payload = array_map('manager_catalog_package_payload', $view_packages);
    $all_count = count($all_packages);
    $my_count = count(array_filter($all_packages, fn($p) => (int)$p['manager_id'] === $manager_id));
    manager_json_response(true, '', ['catalog_tab' => $tab, 'count' => count($view_packages), 'all_count' => $all_count, 'my_count' => $my_count, 'packages' => $payload, 'html' => manager_render_catalog_list($view_packages, $manager_id, $upload_web, $manager_sid, $csrf)]);
}

$packages = $catalog_tab === 'my'
    ? array_values(array_filter($all_packages, fn($p) => (int)$p['manager_id'] === $manager_id))
    : $all_packages;

$booking_tab = $_GET['booking_tab'] ?? 'Pending';
$allowed_booking_tabs = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
if (!in_array($booking_tab, $allowed_booking_tabs, true)) $booking_tab = 'Pending';

$booking_counts = ['Pending' => 0, 'Confirmed' => 0, 'Completed' => 0, 'Cancelled' => 0];
$res = mysqli_query($conn, "SELECT booking_status, COUNT(*) AS total FROM bookings GROUP BY booking_status");
while ($row = mysqli_fetch_assoc($res)) {
    if (isset($booking_counts[$row['booking_status']])) $booking_counts[$row['booking_status']] = (int)$row['total'];
}

$bookings = [];
$stmt = mysqli_prepare($conn, "SELECT b.booking_id, b.user_id, b.total_price, b.booking_status, b.booking_date,
        b.cancellation_reason, b.cancelled_at, b.cancelled_by,
        b.confirmation_email_status, b.confirmation_email_sent_at,
        b.cancellation_email_status, b.cancellation_email_sent_at,
        u.first_name, u.last_name, u.email,
        GROUP_CONCAT(
            CONCAT(
                s.service_name, ' — ', COALESCE(sp.package_name, sp.provider_name),
                ' × ', bd.quantity,
                ' · ', COALESCE(NULLIF(TRIM(CONCAT(m.first_name,' ',m.last_name)),''),'Unassigned')
            ) ORDER BY bd.detail_id SEPARATOR '||'
        ) AS items,
        MIN(bd.event_date) AS event_date
    FROM bookings b
    INNER JOIN users u ON u.user_id=b.user_id
    INNER JOIN booking_details bd ON bd.booking_id=b.booking_id
    INNER JOIN service_providers sp ON sp.provider_id=bd.provider_id
    INNER JOIN services s ON s.service_id=sp.service_id
    LEFT JOIN users m ON m.user_id=sp.manager_id
    WHERE b.booking_status=?
    GROUP BY b.booking_id, b.user_id, b.total_price, b.booking_status, b.booking_date,
        b.cancellation_reason, b.cancelled_at, b.cancelled_by,
        b.confirmation_email_status, b.confirmation_email_sent_at,
        b.cancellation_email_status, b.cancellation_email_sent_at,
        u.first_name, u.last_name, u.email
    ORDER BY b.booking_date DESC");
mysqli_stmt_bind_param($stmt, 's', $booking_tab);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) $bookings[] = $row;
mysqli_stmt_close($stmt);

$my_package_count = 0;
$active_my_packages = 0;
foreach ($all_packages as $p) {
    if ((int)$p['manager_id'] === $manager_id) {
        $my_package_count++;
        if ($p['status'] === 'Active') $active_my_packages++;
    }
}

$activity_counts = ['booking_confirmed'=>0,'booking_completed'=>0,'booking_cancelled'=>0];
$stmt = mysqli_prepare($conn, "SELECT action_type, COUNT(*) AS total
    FROM manager_activity_log
    WHERE manager_id=? AND action_type IN ('booking_confirmed','booking_completed','booking_cancelled')
    GROUP BY action_type");
mysqli_stmt_bind_param($stmt, 'i', $manager_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    if (isset($activity_counts[$row['action_type']])) $activity_counts[$row['action_type']] = (int)$row['total'];
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(amount),0) AS total
    FROM manager_activity_log
    WHERE manager_id=? AND action_type='booking_confirmed'");
mysqli_stmt_bind_param($stmt, 'i', $manager_id);
mysqli_stmt_execute($stmt);
$revenue = (float)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0);
mysqli_stmt_close($stmt);

include '../includes/header.php';
?>
<div class="manager-shell">
    <header class="manager-topbar">
        <div class="manager-container manager-topbar-inner">
            <a href="<?= BASE_URL; ?>dashboard.php" class="manager-brand-link" aria-label="Smart Matrimony Manager Dashboard">
                <span class="manager-brand-logo"><img src="<?= BASE_URL; ?>assets/images/logo/logo.png" alt="Smart Matrimony Logo"></span>
                <span class="manager-brand-copy">
                    <span class="manager-brand-title">
                        <img src="<?= BASE_URL; ?>assets/images/logo/matrimony_title.png" alt="Smart Matrimony">
                    </span>
                    <small class="manager-mobile-manager"><?= htmlspecialchars($manager_name); ?> <span>(<?= htmlspecialchars($manager_public_id); ?>)</span></small>
                </span>
            </a>
            <div class="manager-top-actions">
                <span class="manager-identity"><i class="fa-solid fa-user-tie"></i> <?= htmlspecialchars($manager_name); ?> <small>(<?= htmlspecialchars($manager_public_id); ?>)</small></span>
                <a href="<?= BASE_URL; ?>dashboard.php" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-left"></i> User Dashboard</a>
                <a href="<?= htmlspecialchars($manager_url('logout.php')); ?>" class="manager-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </div>
    </header>

    <main class="manager-container manager-main">
        <section class="manager-welcome">
            <div>
                <span class="manager-kicker">EVENT MANAGEMENT</span>
                <h1>Welcome, <?= htmlspecialchars($manager_name); ?></h1>
                <p>Manage the shared service catalog and customer bookings.</p>
            </div>
            <div class="manager-welcome-actions">
                <button type="button" class="manager-primary-btn js-open-package-modal"><i class="fa-solid fa-plus"></i> Add Package</button>
                <a href="#bookings" class="manager-secondary-btn js-bookings-jump"><i class="fa-solid fa-calendar-check"></i> Customer Bookings</a>
            </div>
        </section>

        <?php if ($message): ?><div class="manager-alert <?= $email_failed_notice ? 'warning' : 'success'; ?>"><i class="fa-solid <?= $email_failed_notice ? 'fa-triangle-exclamation' : 'fa-circle-check'; ?>"></i><?= htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="manager-alert error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($error); ?></div><?php endif; ?>

        <section class="manager-stats manager-stats-five">
            <article><span><i class="fa-solid fa-box"></i></span><div><strong><?= $my_package_count; ?></strong><small>My Packages</small></div></article>
            <article><span><i class="fa-solid fa-check"></i></span><div><strong><?= $activity_counts['booking_confirmed']; ?></strong><small>Confirmed by Me</small></div></article>
            <article><span><i class="fa-solid fa-circle-check"></i></span><div><strong><?= $activity_counts['booking_completed']; ?></strong><small>Completed by Me</small></div></article>
            <article><span><i class="fa-solid fa-ban"></i></span><div><strong><?= $activity_counts['booking_cancelled']; ?></strong><small>Cancelled by Me</small></div></article>
            <article><span><i class="fa-solid fa-bangladeshi-taka-sign"></i></span><div><strong>৳<?= number_format($revenue, 0); ?></strong><small>Confirmed Value (My Packages)</small></div></article>
        </section>

        <section class="manager-catalog-section" id="catalog">
            <div class="manager-panel catalog-panel" id="catalog-list">
                <div class="manager-panel-heading">
                    <div><span class="manager-kicker">SERVICE CATALOG</span><h2><?= $catalog_tab === 'my' ? 'My Packages' : 'All Packages'; ?></h2></div>
                    <span class="manager-count" id="catalogCount"><?= count($packages); ?></span>
                </div>
                <nav class="catalog-tabs" aria-label="Package catalog filters">
                    <a class="catalog-tab<?= $catalog_tab === 'all' ? ' is-active' : ''; ?>" href="<?= htmlspecialchars($manager_url('dashboard.php', ['catalog_tab'=>'all'], 'catalog')); ?>">All Packages <b><?= count($all_packages); ?></b></a>
                    <a class="catalog-tab<?= $catalog_tab === 'my' ? ' is-active' : ''; ?>" href="<?= htmlspecialchars($manager_url('dashboard.php', ['catalog_tab'=>'my'], 'catalog')); ?>">My Packages <b><?= $my_package_count; ?></b></a>
                </nav>
                <div class="package-catalog-scroll" id="packageCatalogList"><?= manager_render_catalog_list($packages, $manager_id, $upload_web, $manager_sid, $csrf); ?></div>
            </div>
        </section>

        <div class="package-modal" id="packageModal" aria-hidden="true">
            <div class="package-modal-backdrop js-close-package-modal"></div>
            <div class="package-modal-card" role="dialog" aria-modal="true" aria-labelledby="packageModalTitle">
                <button type="button" class="package-modal-close js-close-package-modal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
                <span class="manager-kicker">SERVICE CATALOG</span>
                <h2 id="packageModalTitle">Add a package</h2>
                <p class="package-modal-copy">Create or update a service package without leaving the dashboard.</p>
                <form method="post" action="<?= htmlspecialchars($manager_url('dashboard.php')); ?>" class="package-form package-modal-form" id="packageModalForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="save_provider">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="provider_id" id="packageProviderId" value="0">
                    <div class="form-grid">
                        <div><label>Service *</label><select name="service_id" id="packageService" required><option value="">Select service</option><?php foreach ($services as $s): ?><option value="<?= $s['service_id']; ?>"><?= htmlspecialchars($s['service_name']); ?></option><?php endforeach; ?></select></div>
                        <div><label>Provider / Business name *</label><input name="provider_name" id="packageProviderName" required maxlength="150" placeholder="e.g. Noor Photography"></div>
                        <div><label>Package name *</label><input name="package_name" id="packageName" required maxlength="150" placeholder="e.g. Premium Wedding Package"></div>
                        <div><label>Price (৳) *</label><input name="price" id="packagePrice" type="number" min="0" step="0.01" required placeholder="25000"></div>
                        <div><label>Discount (%)</label><input name="discount_percent" id="packageDiscount" type="number" min="0" max="100" step="0.01" value="0" placeholder="10"><small class="field-help">Set 0 for no discount. Maximum 100%.</small></div>
                        <div><label>Location</label><input name="location" id="packageLocation" maxlength="255" placeholder="Dhaka, Bangladesh"></div>
                        <div><label>Contact number</label><input name="contact_number" id="packageContact" maxlength="20" placeholder="01XXXXXXXXX"></div>
                        <div class="full-field"><label>Package image</label><input name="package_image" id="packageImage" type="file" accept="image/jpeg,image/png,image/webp"><small class="field-help" id="packageImageHelp">JPG, PNG or WebP · max 10 MB</small></div>
                        <div><label>Rating</label><input name="rating" id="packageRating" type="number" min="0" max="5" step="0.1" value="0"></div>
                        <div><label>Review count</label><input name="review_count" id="packageReviewCount" type="number" min="0" value="0"></div>
                        <div><label>Status</label><select name="status" id="packageStatus"><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
                    </div>
                    <div class="package-details-field"><label>Package details</label><textarea name="package_details" id="packageDetails" rows="4" maxlength="3000" placeholder="What's included, duration, terms, etc."></textarea></div>
                    <div class="package-current-image" id="packageCurrentImage" hidden></div>
                    <div class="package-modal-actions"><button type="button" class="booking-modal-secondary js-close-package-modal">Cancel</button><button class="manager-primary-btn" type="submit" id="packageSubmitBtn"><i class="fa-solid fa-plus"></i> Add Package</button></div>
                    <div class="package-modal-error" id="packageModalError" role="alert" hidden></div>
                </form>
            </div>
        </div>

        <section class="manager-panel booking-panel" id="bookings">
            <div class="manager-panel-heading booking-panel-heading">
                <div><span class="manager-kicker">BOOKING REQUESTS</span><h2>Customer bookings</h2></div>
                <span class="manager-count"><?= $booking_counts[$booking_tab]; ?></span>
            </div>
            <nav class="booking-tabs" aria-label="Booking status filters">
                <?php foreach ($allowed_booking_tabs as $tab): ?>
                    <a class="booking-tab<?= $booking_tab === $tab ? ' is-active' : ''; ?> <?= strtolower($tab); ?>" href="<?= htmlspecialchars($manager_url('dashboard.php', ['booking_tab' => $tab, 'catalog_tab'=>$catalog_tab], 'bookings')); ?>"><span><?= htmlspecialchars($tab); ?></span><b><?= $booking_counts[$tab]; ?></b></a>
                <?php endforeach; ?>
            </nav>

            <?php if (!$bookings): ?>
                <div class="manager-empty booking-empty"><i class="fa-regular fa-calendar"></i><h3>No <?= htmlspecialchars(strtolower($booking_tab)); ?> bookings</h3><p><?= $booking_tab === 'Pending' ? 'New customer booking requests will appear here.' : 'There are no bookings in this status right now.'; ?></p></div>
            <?php else: ?>
                <div class="booking-table-wrap">
                    <table class="booking-table">
                        <thead><tr><th>Booking</th><th>Customer</th><th>Packages</th><th>Event date</th><th>Total</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($bookings as $b): ?>
                            <tr>
                                <td data-label="Booking"><strong>#<?= (int)$b['booking_id']; ?></strong><small><?= date('d M Y', strtotime($b['booking_date'])); ?></small></td>
                                <td data-label="Customer"><strong><?= htmlspecialchars($b['first_name'].' '.$b['last_name']); ?></strong><small><?= htmlspecialchars($b['email']); ?></small></td>
                                <td data-label="Packages"><div class="booking-items"><?php foreach (explode('||', $b['items']) as $item): ?><?php $item_parts = explode(' · ', $item, 2); ?><span><?= htmlspecialchars($item_parts[0]); ?><?php if (isset($item_parts[1]) && trim($item_parts[1]) !== ''): ?> <span class="booking-manager-name" style="color:#0d7f78 !important;font-weight:800 !important;"><?= htmlspecialchars($item_parts[1]); ?></span><?php endif; ?></span><?php endforeach; ?></div></td>
                                <td data-label="Event date"><?= $b['event_date'] ? date('d M Y', strtotime($b['event_date'])) : '—'; ?></td>
                                <td data-label="Total"><strong>৳<?= number_format((float)$b['total_price'], 2); ?></strong></td>
                                <td data-label="Status">
                                    <span class="status-pill <?= strtolower($b['booking_status']); ?>"><?= htmlspecialchars($b['booking_status']); ?></span>
                                    <?php if ($booking_tab === 'Cancelled' && !empty($b['cancellation_reason'])): ?><div class="booking-reason"><strong>Reason</strong><span><?= nl2br(htmlspecialchars($b['cancellation_reason'])); ?></span></div><?php endif; ?>
                                    <?php if ($booking_tab === 'Confirmed' && ($b['confirmation_email_status'] ?? '') === 'Failed'): ?><small class="booking-email-state failed"><i class="fa-solid fa-triangle-exclamation"></i> Confirmation email failed</small><?php elseif ($booking_tab === 'Cancelled' && ($b['cancellation_email_status'] ?? '') === 'Failed'): ?><small class="booking-email-state failed"><i class="fa-solid fa-triangle-exclamation"></i> Cancellation email failed</small><?php endif; ?>
                                </td>
                                <td data-label="Action">
                                    <div class="booking-actions">
                                        <?php if ($booking_tab === 'Pending'): ?>
                                            <form method="post" action="<?= htmlspecialchars($manager_url('dashboard.php')); ?>" class="booking-action-form"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>"><input type="hidden" name="action" value="booking_confirm"><input type="hidden" name="booking_id" value="<?= (int)$b['booking_id']; ?>"><button class="booking-action-btn confirm" type="submit"><i class="fa-solid fa-check"></i> Confirm</button></form>
                                            <button type="button" class="booking-action-btn cancel js-open-cancel" data-booking-id="<?= (int)$b['booking_id']; ?>" data-customer="<?= htmlspecialchars($b['first_name'].' '.$b['last_name'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-ban"></i> Cancel</button>
                                        <?php elseif ($booking_tab === 'Confirmed'): ?>
                                            <form method="post" action="<?= htmlspecialchars($manager_url('dashboard.php')); ?>" class="booking-action-form"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>"><input type="hidden" name="action" value="booking_complete"><input type="hidden" name="booking_id" value="<?= (int)$b['booking_id']; ?>"><button class="booking-action-btn complete" type="submit"><i class="fa-solid fa-circle-check"></i> Completed</button></form>
                                            <button type="button" class="booking-action-btn cancel js-open-cancel" data-booking-id="<?= (int)$b['booking_id']; ?>" data-customer="<?= htmlspecialchars($b['first_name'].' '.$b['last_name'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-ban"></i> Cancel</button>
                                            <?php if (($b['confirmation_email_status'] ?? '') === 'Failed'): ?><form method="post" action="<?= htmlspecialchars($manager_url('dashboard.php')); ?>" class="booking-action-form"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>"><input type="hidden" name="action" value="booking_resend_email"><input type="hidden" name="email_type" value="confirmed"><input type="hidden" name="booking_id" value="<?= (int)$b['booking_id']; ?>"><button class="booking-action-btn email-retry" type="submit"><i class="fa-solid fa-envelope"></i> Retry email</button></form><?php endif; ?>
                                        <?php elseif ($booking_tab === 'Completed'): ?>
                                            <form method="post" action="<?= htmlspecialchars($manager_url('dashboard.php')); ?>" class="booking-action-form js-delete-booking-form"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>"><input type="hidden" name="action" value="booking_delete"><input type="hidden" name="booking_id" value="<?= (int)$b['booking_id']; ?>"><button class="booking-action-btn delete" type="submit"><i class="fa-solid fa-trash-can"></i> Delete</button></form>
                                        <?php else: ?>
                                            <?php if (($b['cancellation_email_status'] ?? '') === 'Failed'): ?><form method="post" action="<?= htmlspecialchars($manager_url('dashboard.php')); ?>" class="booking-action-form"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>"><input type="hidden" name="action" value="booking_resend_email"><input type="hidden" name="email_type" value="cancelled"><input type="hidden" name="booking_id" value="<?= (int)$b['booking_id']; ?>"><button class="booking-action-btn email-retry" type="submit"><i class="fa-solid fa-envelope"></i> Retry email</button></form><?php endif; ?>
                                            <form method="post" action="<?= htmlspecialchars($manager_url('dashboard.php')); ?>" class="booking-action-form js-delete-booking-form"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>"><input type="hidden" name="action" value="booking_delete"><input type="hidden" name="booking_id" value="<?= (int)$b['booking_id']; ?>"><button class="booking-action-btn delete" type="submit"><i class="fa-solid fa-trash-can"></i> Delete</button></form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="booking-cancel-modal" id="bookingCancelModal" aria-hidden="true">
                <div class="booking-modal-backdrop js-close-cancel"></div>
                <div class="booking-modal-card" role="dialog" aria-modal="true" aria-labelledby="bookingCancelTitle">
                    <button type="button" class="booking-modal-close js-close-cancel" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
                    <span class="manager-kicker">CANCEL BOOKING</span>
                    <h3 id="bookingCancelTitle">Cancel customer booking</h3>
                    <p class="booking-modal-customer">Booking <strong id="cancelBookingId">#0</strong> · <span id="cancelCustomerName">Customer</span></p>
                    <form method="post" action="<?= htmlspecialchars($manager_url('dashboard.php')); ?>" id="bookingCancelForm">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="action" value="booking_cancel">
                        <input type="hidden" name="booking_id" id="cancelBookingInput" value="">
                        <label for="cancellationReason">Reason for cancellation</label>
                        <textarea id="cancellationReason" name="cancellation_reason" rows="5" maxlength="2000" required placeholder="Write a clear reason for the customer..."></textarea>
                        <small class="field-help">This reason will be included in the cancellation email.</small>
                        <div class="booking-modal-actions"><button type="button" class="booking-modal-secondary js-close-cancel">Keep Booking</button><button type="submit" class="booking-action-btn cancel"><i class="fa-solid fa-ban"></i> Cancel Booking</button></div>
                    </form>
                </div>
            </div>
        </section>
    </main>
</div>
<script src="<?= BASE_URL; ?>assets/js/manager-bookings.js?v=2"></script>
<script src="<?= BASE_URL; ?>assets/js/manager-catalog.js?v=1"></script>
<?php include '../includes/footer.php'; ?>
