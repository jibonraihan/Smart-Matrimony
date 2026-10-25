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

if (empty($_SESSION['booking_csrf'])) {
    $_SESSION['booking_csrf'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['booking_csrf'];

$bookings = [];
$stmt = mysqli_prepare($conn, "
    SELECT
        b.booking_id,
        b.total_price,
        b.booking_status,
        b.booking_date,
        COUNT(bd.detail_id) AS item_count,
        MIN(bd.event_date) AS first_event_date,
        MAX(bd.event_date) AS last_event_date,
        GROUP_CONCAT(DISTINCT s.service_name ORDER BY s.service_name SEPARATOR '||') AS service_names
    FROM bookings b
    LEFT JOIN booking_details bd ON bd.booking_id = b.booking_id
    LEFT JOIN service_providers sp ON sp.provider_id = bd.provider_id
    LEFT JOIN services s ON s.service_id = sp.service_id
    WHERE b.user_id = ?
    GROUP BY b.booking_id, b.total_price, b.booking_status, b.booking_date
    ORDER BY b.booking_date DESC
");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $bookings[] = $row;
}
mysqli_stmt_close($stmt);

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
                <a href="<?= BASE_URL; ?>dashboard.php#wedding-services" class="site-page-btn"><i class="fa-solid fa-arrow-left"></i><span><?= sm_text('Dashboard', 'ড্যাশবোর্ড'); ?></span></a>
                <a href="<?= BASE_URL; ?>cart.php" class="site-page-btn"><i class="fa-solid fa-cart-shopping"></i><span><?= sm_text('My Cart', 'আমার কার্ট'); ?></span></a>
            </div>
        </div>
    </header>

    <main class="booking-container booking-main">
        <div class="booking-heading">
            <div>
                <span class="booking-kicker"><?= sm_text('YOUR WEDDING SERVICES', 'আপনার বিয়ের সেবা'); ?></span>
                <h1><?= sm_text('My Bookings', 'আমার বুকিং'); ?></h1>
                <p><?= sm_text('Review your service requests, event dates, selected packages and current booking status.', 'আপনার সেবার অনুরোধ, অনুষ্ঠানের তারিখ, বাছাই করা প্যাকেজ এবং বর্তমান বুকিংয়ের অবস্থা দেখুন।'); ?></p>
            </div>
            <a href="<?= BASE_URL; ?>dashboard.php#wedding-services" class="booking-primary"><i class="fa-solid fa-plus"></i> <?= sm_text('Explore Services', 'সেবাগুলো দেখুন'); ?></a>
        </div>

        <?php if (!$bookings): ?>
            <section class="booking-empty">
                <div class="booking-empty-icon"><i class="fa-regular fa-calendar-check"></i></div>
                <h2><?= sm_text('No bookings yet', 'এখনো কোনো বুকিং নেই'); ?></h2>
                <p><?= sm_text('Your confirmed and pending wedding service requests will appear here after you book a package.', 'প্যাকেজ বুক করার পর আপনার নিশ্চিত ও অপেক্ষমাণ বিয়ের সেবার অনুরোধগুলো এখানে দেখা যাবে।'); ?></p>
                <a href="<?= BASE_URL; ?>dashboard.php#wedding-services"><?= sm_text('Explore Wedding Services', 'বিয়ের সেবাগুলো দেখুন'); ?></a>
            </section>
        <?php else: ?>
            <section class="booking-list">
                <?php foreach ($bookings as $booking): ?>
                    <?php
                        $status = $booking['booking_status'];
                        $status_class = strtolower($status);
                        $date_text = '';
                        if (!empty($booking['first_event_date'])) {
                            $date_text = date('d M Y', strtotime($booking['first_event_date']));
                            if (!empty($booking['last_event_date']) && $booking['last_event_date'] !== $booking['first_event_date']) {
                                $date_text .= ' – ' . date('d M Y', strtotime($booking['last_event_date']));
                            }
                        }
                    ?>
                    <article class="booking-card">
                        <div class="booking-card-main">
                            <div class="booking-card-icon"><i class="fa-solid fa-calendar-days"></i></div>
                            <div class="booking-card-copy">
                                <div class="booking-card-topline">
                                    <span>Booking #<?= (int) $booking['booking_id']; ?></span>
                                    <span class="booking-status <?= htmlspecialchars($status_class); ?>"><?= htmlspecialchars($status); ?></span>
                                </div>
                                <h2><?= htmlspecialchars($date_text ?: 'Event date pending'); ?></h2>
                                <p class="booking-services">
                                    <?php foreach (array_filter(explode('||', $booking['service_names'] ?? '')) as $service_name): ?>
                                        <span><?= htmlspecialchars($service_name); ?></span>
                                    <?php endforeach; ?>
                                </p>
                                <small>Requested <?= date('d M Y, h:i A', strtotime($booking['booking_date'])); ?> · <?= (int) $booking['item_count']; ?> package<?= (int) $booking['item_count'] === 1 ? '' : 's'; ?></small>
                            </div>
                        </div>
                        <div class="booking-card-side">
                            <div><small><?= sm_text('Total', 'মোট'); ?></small><strong>৳<?= number_format((float) $booking['total_price'], 2); ?></strong></div>
                            <a href="<?= BASE_URL; ?>booking_details.php?id=<?= (int) $booking['booking_id']; ?>"><?= sm_text('View Details', 'বিস্তারিত দেখুন'); ?> <i class="fa-solid fa-arrow-right"></i></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
