<?php
require_once 'includes/language.php';
$page_css = 'assets/css/booking.css';
require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

require_once 'includes/service_reviews.php';

$booking_id = (int) ($_GET['id'] ?? 0);

if ($booking_id <= 0) {
    header('Location: my_bookings.php');
    exit;
}

if (empty($_SESSION['booking_csrf'])) {
    $_SESSION['booking_csrf'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['booking_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Invalid request token.');
    }

    if (($_POST['action'] ?? '') === 'cancel') {
        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "UPDATE bookings SET booking_status='Cancelled', cancellation_reason='Cancelled by customer.', cancelled_at=NOW(), cancelled_by='Customer' WHERE booking_id=? AND user_id=? AND booking_status='Pending'");
            mysqli_stmt_bind_param($stmt, 'ii', $booking_id, $user_id);
            mysqli_stmt_execute($stmt);
            $changed = mysqli_stmt_affected_rows($stmt) === 1;
            mysqli_stmt_close($stmt);

            if ($changed) {
                $stmt = mysqli_prepare($conn, "UPDATE booking_manager_requests SET status='Cancelled', cancellation_reason='Cancelled by customer.', cancelled_at=NOW(), cancelled_by='Customer' WHERE booking_id=? AND status IN ('Pending','Confirmed')");
                mysqli_stmt_bind_param($stmt, 'i', $booking_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $changed = false;
        }
        header('Location: booking_details.php?id=' . $booking_id . ($changed ? '&cancelled=1' : '&cancel_failed=1'));
        exit;
    }

}

$booking = null;
$stmt = mysqli_prepare($conn, "
    SELECT b.booking_id, b.total_price, b.booking_status, b.booking_date,
           u.first_name, u.last_name, u.email
    FROM bookings b
    INNER JOIN users u ON u.user_id = b.user_id
    WHERE b.booking_id = ? AND b.user_id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, 'ii', $booking_id, $user_id);
mysqli_stmt_execute($stmt);
$booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$booking) {
    header('Location: my_bookings.php');
    exit;
}

$items = [];
$stmt = mysqli_prepare($conn, "
    SELECT bd.detail_id, bd.quantity, bd.event_date, bd.special_instruction,
           sp.provider_id, sp.package_code, sp.provider_name, sp.package_name, sp.package_details, sp.price, bd.unit_price,
           sp.location, sp.image, s.service_name
    FROM booking_details bd
    INNER JOIN service_providers sp ON sp.provider_id = bd.provider_id
    INNER JOIN services s ON s.service_id = sp.service_id
    WHERE bd.booking_id = ?
    ORDER BY bd.detail_id ASC
");
mysqli_stmt_bind_param($stmt, 'i', $booking_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) $items[] = $row;
mysqli_stmt_close($stmt);

$reviews_by_detail = [];
if (($booking['booking_status'] ?? '') === 'Completed') {
    $reviews_by_detail = service_review_get_for_booking($conn, $user_id, $booking_id);
}

include 'includes/header.php';
?>

<div class="booking-page">
    <header class="booking-topbar site-page-header">
        <div class="booking-container booking-topbar-inner">
            <a href="<?= BASE_URL; ?>dashboard.php" class="site-page-brand" aria-label="Smart Matrimony Dashboard">
                <span class="site-page-logo">
                    <img src="<?= BASE_URL; ?>assets/images/logo/logo.png" alt="Smart Matrimony Logo">
                </span>
                <span class="site-page-title-logo">
                    <img src="<?= BASE_URL; ?>assets/images/logo/matrimony_title.png" alt="Smart Matrimony">
                </span>
            </a>
            <div class="site-page-actions">
                <a href="<?= BASE_URL; ?>my_bookings.php" class="site-page-btn"><i class="fa-solid fa-arrow-left"></i><span><?= sm_text('My Bookings', 'আমার বুকিং'); ?></span></a>
                <a href="<?= BASE_URL; ?>dashboard.php#wedding-services" class="site-page-btn"><i class="fa-solid fa-ring"></i><span><?= sm_text('Wedding Services', 'বিয়ের সেবা'); ?></span></a>
            </div>
        </div>
    </header>

    <main class="booking-container booking-detail-main">
        <?php if (isset($_GET['booked'])): ?>
            <div class="booking-alert"><i class="fa-solid fa-circle-check"></i> Booking #<?= $booking_id; ?> <?= sm_text('was submitted successfully and is now pending manager confirmation.', 'সফলভাবে জমা হয়েছে এবং এখন ম্যানেজারের নিশ্চিতকরণের অপেক্ষায় আছে।'); ?></div>
        <?php elseif (isset($_GET['cancelled'])): ?>
            <div class="booking-alert"><i class="fa-solid fa-circle-check"></i> Booking #<?= $booking_id; ?> <?= sm_text('has been cancelled.', 'বাতিল করা হয়েছে।'); ?></div>
        <?php elseif (isset($_GET['cancel_failed'])): ?>
            <div class="booking-alert"><i class="fa-solid fa-circle-exclamation"></i> <?= sm_text('This booking could not be cancelled. It may already have been updated.', 'এই বুকিংটি বাতিল করা যায়নি। সম্ভবত এর অবস্থা ইতিমধ্যে পরিবর্তিত হয়েছে।'); ?></div>
        <?php endif; ?>

        <section class="booking-detail-hero">
            <div>
                <span class="booking-kicker"><?= sm_text('BOOKING REQUEST', 'বুকিংয়ের অনুরোধ'); ?></span>
                <h1>Booking #<?= (int) $booking['booking_id']; ?></h1>
                <p><?= sm_text('Requested on', 'অনুরোধের তারিখ'); ?> <?= date('d M Y, h:i A', strtotime($booking['booking_date'])); ?></p>
            </div>
            <span class="booking-status large <?= strtolower($booking['booking_status']); ?>"><?= htmlspecialchars($booking['booking_status']); ?></span>
        </section>

        <section class="booking-summary-grid">
            <article><span><i class="fa-solid fa-calendar-days"></i></span><div><small>Event date<?= count($items) > 1 ? 's' : ''; ?></small><strong><?= !empty($items[0]['event_date']) ? date('d M Y', strtotime($items[0]['event_date'])) : 'Not set'; ?><?= count($items) > 1 ? ' +' . (count($items)-1) . ' more' : ''; ?></strong></div></article>
            <article><span><i class="fa-solid fa-layer-group"></i></span><div><small><?= sm_text('Packages', 'প্যাকেজ'); ?></small><strong><?= count($items); ?></strong></div></article>
            <article><span><i class="fa-solid fa-money-bill-wave"></i></span><div><small><?= sm_text('Booking total', 'বুকিংয়ের মোট'); ?></small><strong>৳<?= number_format((float) $booking['total_price'], 2); ?></strong></div></article>
        </section>

        <section class="booking-items-panel">
            <div class="booking-panel-heading"><div><span class="booking-kicker"><?= sm_text('SELECTED PACKAGES', 'নির্বাচিত প্যাকেজ'); ?></span><h2><?= sm_text('Your services', 'আপনার সেবাসমূহ'); ?></h2></div></div>
            <?php foreach ($items as $item): ?>
                <article class="detail-item">
                    <div class="detail-item-image">
                        <?php if (!empty($item['image'])): ?>
                            <img src="<?= BASE_URL; ?>uploads/services/<?= htmlspecialchars($item['image']); ?>" alt="<?= htmlspecialchars($item['package_name'] ?: $item['service_name']); ?>">
                        <?php else: ?><i class="fa-solid fa-ring"></i><?php endif; ?>
                    </div>
                    <div class="detail-item-copy">
                        <span><?= htmlspecialchars($item['service_name']); ?></span>
                        <?php if (!empty($item['package_code'])): ?><span class="package-code-badge"><?= htmlspecialchars($item['package_code']); ?></span><?php endif; ?>
                        <h3><?= htmlspecialchars($item['package_name'] ?: $item['provider_name']); ?></h3>
                        <p><?= htmlspecialchars($item['provider_name']); ?><?= !empty($item['location']) ? ' · ' . htmlspecialchars($item['location']) : ''; ?></p>
                        <?php if (!empty($item['package_details'])): ?><small><?= htmlspecialchars($item['package_details']); ?></small><?php endif; ?>
                    </div>
                    <div class="detail-item-side"><small><?= sm_text('Event date', 'অনুষ্ঠানের তারিখ'); ?></small><strong><?= date('d M Y', strtotime($item['event_date'])); ?></strong><span class="detail-qty"><?= sm_text('Qty:', 'পরিমাণ:'); ?> <?= (int)($item['quantity'] ?? 1); ?></span><b>৳<?= number_format((float)($item['unit_price'] ?? $item['price']) * (int)($item['quantity'] ?? 1), 2); ?></b></div>
                </article>
                <?php if (!empty($item['special_instruction'])): ?>
                    <div class="booking-instruction"><i class="fa-solid fa-note-sticky"></i><div><strong><?= sm_text('Special instruction', 'বিশেষ নির্দেশনা'); ?></strong><p><?= nl2br(htmlspecialchars($item['special_instruction'])); ?></p></div></div>
                <?php endif; ?>

                <?php if (($booking['booking_status'] ?? '') === 'Completed'): ?>
                    <?php $existing_review = $reviews_by_detail[(int) $item['detail_id']] ?? null; ?>
                    <section class="service-review-card<?= $existing_review ? ' is-reviewed' : ''; ?>" data-review-card data-booking-detail-id="<?= (int) $item['detail_id']; ?>">
                        <?php if ($existing_review): ?>
                            <div class="service-review-head">
                                <div>
                                    <span class="service-review-kicker"><i class="fa-solid fa-circle-check"></i> <?= sm_text('YOUR REVIEW', 'আপনার পর্যালোচনা'); ?></span>
                                    <h4><?= sm_text('Thanks for sharing your experience', 'আপনার অভিজ্ঞতা জানানোর জন্য ধন্যবাদ'); ?></h4>
                                </div>
                                <span class="service-review-published"><?= sm_text('Published', 'প্রকাশিত'); ?></span>
                            </div>
                            <div class="service-review-rating" aria-label="<?= (int) $existing_review['rating']; ?> out of 5 stars">
                                <?php for ($star = 1; $star <= 5; $star++): ?>
                                    <i class="fa-solid fa-star<?= $star <= (int) $existing_review['rating'] ? ' active' : ''; ?>"></i>
                                <?php endfor; ?>
                                <strong><?= (int) $existing_review['rating']; ?>/5</strong>
                            </div>
                            <?php if (trim((string) ($existing_review['review_text'] ?? '')) !== ''): ?>
                                <p class="service-review-text">“<?= nl2br(htmlspecialchars($existing_review['review_text'])); ?>”</p>
                            <?php endif; ?>
                            <small class="service-review-date">Submitted <?= date('d M Y', strtotime($existing_review['created_at'])); ?></small>
                        <?php else: ?>
                            <div class="service-review-head">
                                <div>
                                    <span class="service-review-kicker"><i class="fa-regular fa-star"></i> <?= sm_text('SERVICE REVIEW', 'সেবা পর্যালোচনা'); ?></span>
                                    <h4><?= sm_text('How was this service?', 'এই সেবাটি কেমন ছিল?'); ?></h4>
                                    <p><?= sm_text('Your completed booking gives you a verified opportunity to rate this package.', 'আপনার সম্পন্ন বুকিংয়ের ভিত্তিতে এই প্যাকেজের রেটিং দিতে পারবেন।'); ?></p>
                                </div>
                            </div>
                            <form class="service-review-form" data-review-form novalidate>
                                <div class="service-review-stars" role="radiogroup" aria-label="Choose a rating from 1 to 5 stars">
                                    <?php for ($star = 1; $star <= 5; $star++): ?>
                                        <button type="button" class="service-review-star" data-rating="<?= $star; ?>" role="radio" aria-checked="false" aria-label="<?= $star; ?> star<?= $star > 1 ? 's' : ''; ?>"><i class="fa-solid fa-star"></i></button>
                                    <?php endfor; ?>
                                    <span class="service-review-rating-label" data-rating-label><?= sm_text('Choose a rating', 'রেটিং নির্বাচন করুন'); ?></span>
                                </div>
                                <textarea name="review_text" maxlength="2000" placeholder="<?= sm_text('Tell us what you liked about the service (optional)', 'সেবাটির কোন বিষয়টি ভালো লেগেছে লিখুন (ঐচ্ছিক)'); ?>" data-review-text></textarea>
                                <div class="service-review-form-meta"><span data-review-count>0/2000</span><span><?= sm_text('Only one review per booked package.', 'প্রতি বুক করা প্যাকেজের জন্য একবারই পর্যালোচনা করা যাবে।'); ?></span></div>
                                <div class="service-review-feedback" data-review-feedback aria-live="polite"></div>
                                <button type="submit" class="service-review-submit"><i class="fa-solid fa-paper-plane"></i> <?= sm_text('Submit Review', 'পর্যালোচনা জমা দিন'); ?></button>
                            </form>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>

        <?php if ($booking['booking_status'] === 'Pending'): ?>
            <section class="booking-action-panel">
                <div><strong><?= sm_text('Need to cancel this request?', 'এই অনুরোধটি বাতিল করতে চান?'); ?></strong><p><?= sm_text('You can cancel a pending booking before the Event Manager confirms it.', 'ইভেন্ট ম্যানেজার নিশ্চিত করার আগে অপেক্ষমাণ বুকিং বাতিল করতে পারবেন।'); ?></p></div>
                <form method="post" onsubmit="return confirm('Cancel this pending booking?');">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit"><i class="fa-solid fa-ban"></i> <?= sm_text('Cancel Booking', 'বুকিং বাতিল করুন'); ?></button>
                </form>
            </section>
        <?php endif; ?>
    </main>
</div>

<script>window.SM_SERVICE_REVIEW = { csrf: <?= json_encode($csrf); ?>, endpoint: <?= json_encode(BASE_URL . 'ajax/service_review.php'); ?> };</script>
<script src="<?= BASE_URL; ?>assets/js/service-reviews.js?v=1"></script>

<?php include 'includes/footer.php'; ?>
