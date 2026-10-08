<?php

$page_css = 'assets/css/service-reviews.css';

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

$provider_id = (int) ($_GET['provider_id'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));
$rating_filter = (int) ($_GET['rating'] ?? 0);
$sort = (string) ($_GET['sort'] ?? 'newest');

try {
    $review_page = service_review_get_provider_review_page(
        $conn,
        $provider_id,
        $page,
        10,
        $rating_filter,
        $sort
    );
} catch (Throwable $e) {
    $review_page = [
        'provider' => null,
        'reviews' => [],
        'total' => 0,
        'page' => 1,
        'per_page' => 10,
        'total_pages' => 0,
        'rating_filter' => 0,
        'sort' => 'newest',
    ];
    $review_error = 'Reviews are temporarily unavailable. Please try again.';
}

$provider = $review_page['provider'];
if (!$provider) {
    header('Location: ../dashboard.php#wedding-services');
    exit;
}

$summary = service_review_get_provider_summary($conn, $provider_id);
$breakdown = service_review_get_rating_breakdown($conn, [$provider_id])[$provider_id] ?? [];
$rating_total = (int) $summary['review_count'];

$build_url = static function (array $overrides = []) use ($provider_id, $review_page): string {
    $params = [
        'provider_id' => $provider_id,
        'page' => $review_page['page'],
        'rating' => $review_page['rating_filter'],
        'sort' => $review_page['sort'],
    ];
    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }
    if ((int) $params['page'] <= 1) {
        unset($params['page']);
    }
    if ((int) $params['rating'] <= 0) {
        unset($params['rating']);
    }
    if ($params['sort'] === 'newest') {
        unset($params['sort']);
    }
    return 'service_reviews.php?' . http_build_query($params);
};

$start_number = $review_page['total'] > 0
    ? (($review_page['page'] - 1) * $review_page['per_page']) + 1
    : 0;
$end_number = min(
    $review_page['total'],
    $review_page['page'] * $review_page['per_page']
);

include '../includes/header.php';
?>

<div class="service-reviews-page">
    <header class="service-reviews-topbar site-page-header">
        <div class="service-container service-reviews-topbar-inner">
            <a href="<?= BASE_URL; ?>services/service_details.php?service_id=<?= (int) $provider['service_id']; ?>" class="service-page-brand" aria-label="Back to service details">
                <span class="service-page-logo">
                    <img src="<?= BASE_URL; ?>assets/images/logo/logo.png" alt="Smart Matrimony Logo">
                </span>
                <span class="service-page-title-logo">
                    <img src="<?= BASE_URL; ?>assets/images/logo/matrimony_title.png" alt="Smart Matrimony">
                </span>
            </a>
            <div class="service-page-actions">
                <a href="<?= BASE_URL; ?>services/service_details.php?service_id=<?= (int) $provider['service_id']; ?>" class="service-page-btn">
                    <i class="fa-solid fa-arrow-left"></i><span>Service Details</span>
                </a>
                <a href="<?= BASE_URL; ?>cart.php" class="service-page-btn">
                    <i class="fa-solid fa-cart-shopping"></i><span>Cart</span>
                </a>
            </div>
        </div>
    </header>

    <main class="service-container service-reviews-main">
        <div class="service-reviews-crumb">
            <a href="<?= BASE_URL; ?>services/service_details.php?service_id=<?= (int) $provider['service_id']; ?>">Wedding Services</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>Customer Reviews</span>
        </div>

        <section class="reviews-hero-card">
            <div class="reviews-hero-copy">
                <span class="service-kicker">VERIFIED CUSTOMER FEEDBACK</span>
                <h1><?= htmlspecialchars($provider['package_name'] ?: $provider['provider_name']); ?></h1>
                <p><?= htmlspecialchars($provider['provider_name']); ?> · <?= htmlspecialchars($provider['service_name']); ?></p>
            </div>
            <div class="reviews-hero-rating">
                <strong><?= number_format((float) $summary['rating'], 1); ?></strong>
                <div class="reviews-hero-stars" aria-label="<?= number_format((float) $summary['rating'], 1); ?> out of 5 stars">
                    <?php for ($star = 1; $star <= 5; $star++): ?>
                        <i class="fa-solid fa-star<?= $star <= round((float) $summary['rating']) ? ' active' : ''; ?>"></i>
                    <?php endfor; ?>
                </div>
                <span><?= $rating_total; ?> <?= $rating_total === 1 ? 'review' : 'reviews'; ?></span>
            </div>
        </section>

        <section class="reviews-summary-grid">
            <div class="reviews-breakdown-card">
                <div class="reviews-card-heading">
                    <div>
                        <span class="service-kicker">RATING BREAKDOWN</span>
                        <h2>What customers rated</h2>
                    </div>
                </div>
                <?php for ($star = 5; $star >= 1; $star--): ?>
                    <?php $count = (int) ($breakdown[$star] ?? 0); $percent = $rating_total > 0 ? round(($count / $rating_total) * 100, 1) : 0; ?>
                    <div class="review-breakdown-row">
                        <span class="review-breakdown-label"><?= $star; ?> <i class="fa-solid fa-star"></i></span>
                        <span class="review-breakdown-track"><span style="width: <?= $percent; ?>%;"></span></span>
                        <b><?= $count; ?></b>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="reviews-filter-card">
                <div class="reviews-card-heading">
                    <div>
                        <span class="service-kicker">EXPLORE REVIEWS</span>
                        <h2>Find the feedback you need</h2>
                    </div>
                </div>
                <form method="get" class="reviews-filter-form">
                    <input type="hidden" name="provider_id" value="<?= $provider_id; ?>">
                    <label>
                        Rating
                        <select name="rating">
                            <option value="0"<?= $rating_filter === 0 ? ' selected' : ''; ?>>All ratings</option>
                            <?php for ($star = 5; $star >= 1; $star--): ?>
                                <option value="<?= $star; ?>"<?= $rating_filter === $star ? ' selected' : ''; ?>><?= $star; ?> stars</option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    <label>
                        Sort by
                        <select name="sort">
                            <option value="newest"<?= $sort === 'newest' ? ' selected' : ''; ?>>Newest first</option>
                            <option value="highest"<?= $sort === 'highest' ? ' selected' : ''; ?>>Highest rated</option>
                            <option value="lowest"<?= $sort === 'lowest' ? ' selected' : ''; ?>>Lowest rated</option>
                        </select>
                    </label>
                    <button type="submit"><i class="fa-solid fa-filter"></i> Apply filters</button>
                </form>
            </div>
        </section>

        <section class="reviews-list-section">
            <div class="reviews-list-heading">
                <div>
                    <span class="service-kicker">CUSTOMER REVIEWS</span>
                    <h2>
                        <?php if ($rating_filter > 0): ?>
                            <?= $rating_filter; ?>-star reviews
                        <?php else: ?>
                            All customer reviews
                        <?php endif; ?>
                    </h2>
                </div>
                <?php if ($review_page['total'] > 0): ?>
                    <span class="reviews-range">Showing <?= $start_number; ?>–<?= $end_number; ?> of <?= $review_page['total']; ?></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($review_error)): ?>
                <div class="reviews-empty-card"><i class="fa-solid fa-circle-exclamation"></i><h3>Reviews unavailable</h3><p><?= htmlspecialchars($review_error); ?></p></div>
            <?php elseif (!$review_page['reviews']): ?>
                <div class="reviews-empty-card">
                    <i class="fa-regular fa-star"></i>
                    <h3>No reviews found</h3>
                    <p><?= $rating_filter > 0 ? 'There are no published reviews with this rating yet.' : 'Customer reviews for this package will appear here after completed bookings.'; ?></p>
                    <?php if ($rating_filter > 0): ?><a href="<?= htmlspecialchars($build_url(['rating' => 0, 'page' => 1])); ?>">View all reviews</a><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="review-full-list">
                    <?php foreach ($review_page['reviews'] as $review): ?>
                        <?php
                            $full_name = trim((string) (($review['first_name'] ?? '') . ' ' . ($review['last_name'] ?? '')));
                            if ($full_name === '') $full_name = 'Verified customer';
                            $review_date = !empty($review['created_at']) ? date('d M Y', strtotime($review['created_at'])) : '';
                            $review_rating = (int) $review['rating'];
                        ?>
                        <article class="full-review-card">
                            <div class="full-review-top">
                                <div class="full-review-customer">
                                    <div class="review-avatar"><?= htmlspecialchars(strtoupper(mb_substr($full_name, 0, 1))); ?></div>
                                    <div>
                                        <strong><?= htmlspecialchars($full_name); ?></strong>
                                        <span><i class="fa-solid fa-circle-check"></i> Verified booking</span>
                                    </div>
                                </div>
                                <time datetime="<?= htmlspecialchars((string) $review['created_at']); ?>"><?= htmlspecialchars($review_date); ?></time>
                            </div>
                            <div class="full-review-stars">
                                <?php for ($star = 1; $star <= 5; $star++): ?><i class="fa-solid fa-star<?= $star <= $review_rating ? ' active' : ''; ?>"></i><?php endfor; ?>
                                <b><?= $review_rating; ?>/5</b>
                            </div>
                            <?php if (trim((string) $review['review_text']) !== ''): ?>
                                <p><?= nl2br(htmlspecialchars($review['review_text'])); ?></p>
                            <?php else: ?>
                                <p class="review-no-text">Customer left a rating without written feedback.</p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($review_page['total_pages'] > 1): ?>
                    <nav class="reviews-pagination" aria-label="Review pages">
                        <?php if ($review_page['page'] > 1): ?>
                            <a href="<?= htmlspecialchars($build_url(['page' => $review_page['page'] - 1])); ?>" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>
                        <?php endif; ?>
                        <?php
                            $start_page = max(1, $review_page['page'] - 2);
                            $end_page = min($review_page['total_pages'], $review_page['page'] + 2);
                            for ($page_number = $start_page; $page_number <= $end_page; $page_number++):
                        ?>
                            <a class="<?= $page_number === $review_page['page'] ? 'active' : ''; ?>" href="<?= htmlspecialchars($build_url(['page' => $page_number])); ?>"><?= $page_number; ?></a>
                        <?php endfor; ?>
                        <?php if ($review_page['page'] < $review_page['total_pages']): ?>
                            <a href="<?= htmlspecialchars($build_url(['page' => $review_page['page'] + 1])); ?>" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php include '../includes/footer.php'; ?>
