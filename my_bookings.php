<?php
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
    <header class="booking-topbar">
        <div class="booking-container booking-topbar-inner">
            <a href="<?= BASE_URL; ?>dashboard.php#wedding-services" class="booking-back"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
            <a href="<?= BASE_URL; ?>cart.php" class="booking-cart"><i class="fa-solid fa-cart-shopping"></i> My Cart</a>
        </div>
    </header>

    <main class="booking-container booking-main">
        <div class="booking-heading">
            <div>
                <span class="booking-kicker">YOUR WEDDING SERVICES</span>
                <h1>My Bookings</h1>
                <p>Review your service requests, event dates, selected packages and current booking status.</p>
            </div>
            <a href="<?= BASE_URL; ?>dashboard.php#wedding-services" class="booking-primary"><i class="fa-solid fa-plus"></i> Explore Services</a>
        </div>

        <?php if (!$bookings): ?>
            <section class="booking-empty">
                <div class="booking-empty-icon"><i class="fa-regular fa-calendar-check"></i></div>
                <h2>No bookings yet</h2>
                <p>Your confirmed and pending wedding service requests will appear here after you book a package.</p>
                <a href="<?= BASE_URL; ?>dashboard.php#wedding-services">Explore Wedding Services</a>
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
                            <div><small>Total</small><strong>৳<?= number_format((float) $booking['total_price'], 2); ?></strong></div>
                            <a href="<?= BASE_URL; ?>booking_details.php?id=<?= (int) $booking['booking_id']; ?>">View Details <i class="fa-solid fa-arrow-right"></i></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
