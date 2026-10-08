<?php

$page_css = 'assets/css/service-details.css';

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';
require_once '../includes/service_reviews.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (empty($_SESSION['cart_csrf'])) {
    $_SESSION['cart_csrf'] = bin2hex(random_bytes(24));
}

$service_id = (int) ($_GET['service_id'] ?? 0);
if ($service_id <= 0) {
    header('Location: ../dashboard.php#wedding-services');
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT service_id, service_name, description FROM services WHERE service_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $service_id);
mysqli_stmt_execute($stmt);
$service = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$service) {
    header('Location: ../dashboard.php#wedding-services');
    exit;
}

$providers = [];
$stmt = mysqli_prepare($conn, '
    SELECT provider_id, provider_name, package_name, package_details, price, discount_percent,
           contact_number, location, rating, review_count, image
    FROM service_providers
    WHERE service_id = ? AND status = \'Active\'
    ORDER BY rating DESC, review_count DESC, provider_id DESC
');
mysqli_stmt_bind_param($stmt, 'i', $service_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $providers[] = $row;
}
mysqli_stmt_close($stmt);

$provider_ids = array_map(static fn($row) => (int) $row['provider_id'], $providers);
$provider_reviews = service_review_get_published_for_providers($conn, $provider_ids, 3);
$provider_rating_breakdowns = service_review_get_rating_breakdown($conn, $provider_ids);

$cart_count = 0;
$cart_stmt = mysqli_prepare($conn, 'SELECT COALESCE(SUM(quantity),0) AS total FROM service_cart_items WHERE user_id = ?');
mysqli_stmt_bind_param($cart_stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($cart_stmt);
$cart_count = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($cart_stmt))['total'] ?? 0);
mysqli_stmt_close($cart_stmt);

include '../includes/header.php';
?>

<div class="service-page">
    <header class="service-topbar site-page-header">
        <div class="service-container service-topbar-inner">
            <a href="<?= BASE_URL; ?>dashboard.php" class="service-page-brand" aria-label="Smart Matrimony Dashboard">
                <span class="service-page-logo">
                    <img src="<?= BASE_URL; ?>assets/images/logo/logo.png" alt="Smart Matrimony Logo">
                </span>
                <span class="service-page-title-logo">
                    <img src="<?= BASE_URL; ?>assets/images/logo/matrimony_title.png" alt="Smart Matrimony">
                </span>
            </a>
            <div class="service-page-actions">
                <a href="<?= BASE_URL; ?>dashboard.php#wedding-services" class="service-page-btn"><i class="fa-solid fa-arrow-left"></i><span>Wedding Services</span></a>
                <a href="<?= BASE_URL; ?>cart.php" class="service-page-btn"><i class="fa-solid fa-cart-shopping"></i><span>Cart</span><b><?= $cart_count; ?></b></a>
            </div>
        </div>
    </header>

    <main>
        <section class="service-hero">
            <div class="service-container">
                <span class="service-kicker">WEDDING SERVICE</span>
                <h1><?= htmlspecialchars($service['service_name']); ?></h1>
                <p><?= htmlspecialchars($service['description'] ?? 'Explore available packages and providers.'); ?></p>
                <div class="service-hero-meta">
                    <span><i class="fa-solid fa-layer-group"></i> <?= count($providers); ?> available <?= count($providers) === 1 ? 'option' : 'options'; ?></span>
                    <span><i class="fa-solid fa-shield-heart"></i> Secure booking</span>
                </div>
            </div>
        </section>

        <section class="service-container service-options-section">
            <div class="service-section-heading">
                <div>
                    <span class="service-kicker">AVAILABLE OPTIONS</span>
                    <h2>Choose the package that fits your plan</h2>
                </div>
                <a href="<?= BASE_URL; ?>cart.php" class="view-cart-btn"><i class="fa-solid fa-cart-shopping"></i> View Cart</a>
            </div>

            <?php if (!$providers): ?>
                <div class="service-empty">
                    <div><i class="fa-regular fa-folder-open"></i></div>
                    <h3>No packages available yet</h3>
                    <p>This service category is ready, but an Event Manager has not added provider packages yet.</p>
                    <a href="<?= BASE_URL; ?>dashboard.php#wedding-services">Back to services</a>
                </div>
            <?php else: ?>
                <div class="provider-grid">
                    <?php foreach ($providers as $provider): ?>
                        <article class="provider-card">
                            <div class="provider-image">
                                <?php if (!empty($provider['image'])): ?>
                                    <img src="<?= BASE_URL; ?>uploads/services/<?= htmlspecialchars($provider['image']); ?>" alt="<?= htmlspecialchars($provider['provider_name']); ?>">
                                <?php else: ?>
                                    <div class="provider-image-fallback"><i class="fa-solid fa-store"></i></div>
                                <?php endif; ?>

                            </div>
                            <div class="provider-body">
                                <div class="provider-heading">
                                    <div>
                                        <h3><?= htmlspecialchars($provider['package_name'] ?: $provider['provider_name']); ?></h3>
                                        <p><?= htmlspecialchars($provider['provider_name']); ?></p>
                                    </div>
                                </div>
                                <div class="provider-facts">
                                    <?php if (!empty($provider['location'])): ?><span><i class="fa-solid fa-location-dot"></i><?= htmlspecialchars($provider['location']); ?></span><?php endif; ?>
                                    <?php if (!empty($provider['package_details'])): ?><span><i class="fa-solid fa-list-check"></i><?= htmlspecialchars($provider['package_details']); ?></span><?php endif; ?>
                                </div>
                                <div class="provider-rating-summary">
                                <?php $provider_id = (int) $provider['provider_id']; $rating_total = (int) $provider['review_count']; $rating_breakdown = $provider_rating_breakdowns[$provider_id] ?? []; ?>
                                <?php if ($rating_total > 0): ?>
                                    <details class="provider-rating-breakdown">
                                        <summary aria-label="View rating breakdown">
                                            <span><i class="fa-solid fa-star"></i> <?= number_format((float) $provider['rating'], 1); ?></span>
                                            <small><?= $rating_total; ?> <?= $rating_total === 1 ? 'review' : 'reviews'; ?></small>
                                            <i class="fa-solid fa-chevron-down provider-rating-chevron" aria-hidden="true"></i>
                                        </summary>
                                        <div class="provider-rating-breakdown-panel">
                                            <?php for ($rating_star = 5; $rating_star >= 1; $rating_star--): ?>
                                                <?php $rating_count = (int) ($rating_breakdown[$rating_star] ?? 0); $rating_percent = $rating_total > 0 ? round(($rating_count / $rating_total) * 100, 1) : 0; ?>
                                                <div class="rating-breakdown-row">
                                                    <span><?= $rating_star; ?> <i class="fa-solid fa-star"></i></span>
                                                    <span class="rating-breakdown-track"><span style="width: <?= $rating_percent; ?>%;"></span></span>
                                                    <b><?= $rating_count; ?></b>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                    </details>
                                <?php else: ?>
                                    <span class="provider-rating provider-rating-empty"><i class="fa-regular fa-star"></i> No reviews yet</span>
                                <?php endif; ?>
                                </div>
                                <?php $reviews = $provider_reviews[(int) $provider['provider_id']] ?? []; ?>
                                <div class="provider-reviews">
                                    <div class="provider-reviews-head">
                                        <div>
                                            <span class="provider-reviews-kicker"><i class="fa-regular fa-comments"></i> VERIFIED REVIEWS</span>
                                            <strong><?= (int) $provider['review_count']; ?> <?= (int) $provider['review_count'] === 1 ? 'review' : 'reviews'; ?></strong>
                                        </div>
                                        <?php if ($reviews): ?><span>Latest <?= count($reviews); ?></span><?php endif; ?>
                                    </div>
                                    <?php if ($reviews): ?>
                                        <div class="provider-review-list">
                                            <?php foreach ($reviews as $review): ?>
                                                <?php
                                                    $reviewer_first = trim((string) ($review['first_name'] ?? ''));
                                                    $reviewer_last = trim((string) ($review['last_name'] ?? ''));
                                                    $reviewer_label = trim($reviewer_first . ' ' . $reviewer_last);
                                                    if ($reviewer_label === '') $reviewer_label = 'Verified Customer';
                                                ?>
                                                <article class="provider-review-item">
                                                    <div class="provider-review-top">
                                                        <strong><?= htmlspecialchars($reviewer_label); ?></strong>
                                                        <time datetime="<?= htmlspecialchars(date('c', strtotime($review['created_at']))); ?>"><?= htmlspecialchars(date('d M Y', strtotime($review['created_at']))); ?></time>
                                                    </div>
                                                    <div class="provider-review-stars" aria-label="<?= (int) $review['rating']; ?> out of 5 stars">
                                                        <?php for ($star = 1; $star <= 5; $star++): ?><i class="fa-solid fa-star<?= $star <= (int) $review['rating'] ? ' active' : ''; ?>"></i><?php endfor; ?>
                                                    </div>
                                                    <?php if (trim((string) ($review['review_text'] ?? '')) !== ''): ?>
                                                        <p><?= nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                                    <?php endif; ?>
                                                    <span class="provider-review-verified"><i class="fa-solid fa-circle-check"></i> Verified booking</span>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="provider-reviews-empty"><i class="fa-regular fa-star"></i> No customer reviews yet. Be the first to share your experience after a completed booking.</p>
                                    <?php endif; ?>
                                    <?php if ((int) $provider['review_count'] > 0): ?>
                                        <a class="provider-view-all-reviews" href="<?= BASE_URL; ?>services/service_reviews.php?provider_id=<?= (int) $provider['provider_id']; ?>">
                                            View all <?= (int) $provider['review_count']; ?> <?= (int) $provider['review_count'] === 1 ? 'review' : 'reviews'; ?>
                                            <i class="fa-solid fa-arrow-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <div class="provider-bottom">
                                    <div class="provider-price">
                                        <?php $provider_discount = max(0, min(100, (float)($provider['discount_percent'] ?? 0))); $provider_final = (float)$provider['price'] * (1 - ($provider_discount / 100)); ?>
                                        <small>Starting from</small>
                                        <?php if ($provider_discount > 0): ?><del>৳<?= number_format((float)$provider['price'], 2); ?></del><?php endif; ?>
                                        <strong>৳<?= number_format($provider_final, 2); ?></strong>
                                        <?php if ($provider_discount > 0): ?><em><?= rtrim(rtrim(number_format($provider_discount, 2), '0'), '.'); ?>% OFF</em><?php endif; ?>
                                    </div>
                                    <form method="post" action="<?= BASE_URL; ?>cart.php">
                                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['cart_csrf']); ?>">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="provider_id" value="<?= (int) $provider['provider_id']; ?>">
                                        <input type="hidden" name="return_service_id" value="<?= $service_id; ?>">
                                        <button class="add-cart-btn" type="submit"><i class="fa-solid fa-cart-plus"></i> Add to Cart</button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php include '../includes/footer.php'; ?>
