<?php

$page_css = 'assets/css/service-reviews.css';

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';
require_once '../includes/language.php';
require_once '../includes/service_reviews.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
    $review_error = sm_text('Reviews are temporarily unavailable. Please try again.', 'পর্যালোচনা সাময়িকভাবে পাওয়া যাচ্ছে না। অনুগ্রহ করে আবার চেষ্টা করুন।');
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
            <a href="<?= BASE_URL; ?>services/service_details.php?service_id=<?= (int) $provider['service_id']; ?>" class="service-page-brand" aria-label="<?= sm_text('Back to service details', 'সেবার বিস্তারিত তথ্যে ফিরুন'); ?>">
                <span class="service-page-logo">
                    <img src="<?= BASE_URL; ?>assets/images/logo/logo.png" alt="Smart Matrimony Logo">
                </span>
                <span class="service-page-title-logo">
                    <img src="<?= BASE_URL; ?>assets/images/logo/matrimony_title.png" alt="Smart Matrimony">
                </span>
            </a>
            <div class="service-page-actions">
                <a href="<?= BASE_URL; ?>services/service_details.php?service_id=<?= (int) $provider['service_id']; ?>" class="service-page-btn">
                    <i class="fa-solid fa-arrow-left"></i><span><?= sm_text('Service Details', 'সেবার বিস্তারিত'); ?></span>
                </a>
                <a href="<?= BASE_URL; ?>cart.php" class="service-page-btn">
                    <i class="fa-solid fa-cart-shopping"></i><span><?= sm_text('Cart', 'কার্ট'); ?></span>
                </a>
            </div>
        </div>
    </header>

    <main class="service-container service-reviews-main">
        <div class="service-reviews-crumb">
            <a href="<?= BASE_URL; ?>services/service_details.php?service_id=<?= (int) $provider['service_id']; ?>"><?= sm_text('Wedding Services', 'বিয়ের সেবা'); ?></a>
            <i class="fa-solid fa-chevron-right"></i>
            <span><?= sm_text('Customer Reviews', 'গ্রাহক পর্যালোচনা'); ?></span>
        </div>

        <section class="reviews-hero-card">
            <div class="reviews-hero-copy">
                <span class="service-kicker"><?= sm_text('VERIFIED CUSTOMER FEEDBACK', 'যাচাইকৃত গ্রাহক মতামত'); ?></span>
                <?php if (!empty($provider['package_code'])): ?><div class="package-code-badge"><?= htmlspecialchars($provider['package_code'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                <h1><?= htmlspecialchars($provider['package_name'] ?: $provider['provider_name']); ?></h1>
                <p><?= htmlspecialchars($provider['provider_name']); ?> · <?= htmlspecialchars($provider['service_name']); ?></p>
            </div>
            <div class="reviews-hero-rating">
                <strong><?= number_format((float) $summary['rating'], 1); ?></strong>
                <div class="reviews-hero-stars" aria-label="<?= number_format((float) $summary['rating'], 1); ?> <?= sm_text('out of 5 stars', 'এর মধ্যে ৫ তারার রেটিং'); ?>">
                    <?php for ($star = 1; $star <= 5; $star++): ?>
                        <i class="fa-solid fa-star<?= $star <= round((float) $summary['rating']) ? ' active' : ''; ?>"></i>
                    <?php endfor; ?>
                </div>
                <span><?= $rating_total; ?> <?= $rating_total === 1 ? sm_text('review', 'টি পর্যালোচনা') : sm_text('reviews', 'টি পর্যালোচনা'); ?></span>
            </div>
        </section>

        <section class="reviews-summary-grid">
            <div class="reviews-breakdown-card">
                <div class="reviews-card-heading">
                    <div>
                        <span class="service-kicker"><?= sm_text('RATING BREAKDOWN', 'রেটিংয়ের বিবরণ'); ?></span>
                        <h2><?= sm_text('What customers rated', 'গ্রাহকদের দেওয়া রেটিং'); ?></h2>
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
                        <span class="service-kicker"><?= sm_text('EXPLORE REVIEWS', 'পর্যালোচনা খুঁজুন'); ?></span>
                        <h2><?= sm_text('Find the feedback you need', 'প্রয়োজনীয় মতামত খুঁজুন'); ?></h2>
                    </div>
                </div>
                <form method="get" class="reviews-filter-form">
                    <input type="hidden" name="provider_id" value="<?= $provider_id; ?>">
                    <label>
                        <?= sm_text('Rating', 'রেটিং'); ?>
                        <select name="rating">
                            <option value="0"<?= $rating_filter === 0 ? ' selected' : ''; ?>><?= sm_text('All ratings', 'সব রেটিং'); ?></option>
                            <?php for ($star = 5; $star >= 1; $star--): ?>
                                <option value="<?= $star; ?>"<?= $rating_filter === $star ? ' selected' : ''; ?>><?= $star; ?> <?= sm_text('stars', 'তারা'); ?></option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    <label>
                        <?= sm_text('Sort by', 'সাজান'); ?>
                        <select name="sort">
                            <option value="newest"<?= $sort === 'newest' ? ' selected' : ''; ?>><?= sm_text('Newest first', 'নতুন আগে'); ?></option>
                            <option value="highest"<?= $sort === 'highest' ? ' selected' : ''; ?>><?= sm_text('Highest rated', 'সর্বোচ্চ রেটিং আগে'); ?></option>
                            <option value="lowest"<?= $sort === 'lowest' ? ' selected' : ''; ?>><?= sm_text('Lowest rated', 'সর্বনিম্ন রেটিং আগে'); ?></option>
                        </select>
                    </label>
                    <button type="submit"><i class="fa-solid fa-filter"></i> <?= sm_text('Apply filters', 'ফিল্টার প্রয়োগ করুন'); ?></button>
                </form>
            </div>
        </section>

        <section class="reviews-list-section">
            <div class="reviews-list-heading">
                <div>
                    <span class="service-kicker"><?= sm_text('CUSTOMER REVIEWS', 'গ্রাহক পর্যালোচনা'); ?></span>
                    <h2>
                        <?php if ($rating_filter > 0): ?>
                            <?= $rating_filter; ?> <?= sm_text('-star reviews', 'তারার পর্যালোচনা'); ?>
                        <?php else: ?>
                            <?= sm_text('All customer reviews', 'সব গ্রাহক পর্যালোচনা'); ?>
                        <?php endif; ?>
                    </h2>
                </div>
                <?php if ($review_page['total'] > 0): ?>
                    <span class="reviews-range"><?= sm_text('Showing', 'দেখানো হচ্ছে'); ?> <?= $start_number; ?>–<?= $end_number; ?> <?= sm_text('of', 'মোট'); ?> <?= $review_page['total']; ?></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($review_error)): ?>
                <div class="reviews-empty-card"><i class="fa-solid fa-circle-exclamation"></i><h3><?= sm_text('Reviews unavailable', 'পর্যালোচনা পাওয়া যাচ্ছে না'); ?></h3><p><?= htmlspecialchars($review_error); ?></p></div>
            <?php elseif (!$review_page['reviews']): ?>
                <div class="reviews-empty-card">
                    <i class="fa-regular fa-star"></i>
                    <h3><?= sm_text('No reviews found', 'কোনো পর্যালোচনা পাওয়া যায়নি'); ?></h3>
                    <p><?= $rating_filter > 0 ? sm_text('There are no published reviews with this rating yet.', 'এই রেটিংয়ের কোনো প্রকাশিত পর্যালোচনা এখনো নেই।') : sm_text('Customer reviews for this package will appear here after completed bookings.', 'বুকিং সম্পন্ন হলে এই প্যাকেজের গ্রাহক পর্যালোচনা এখানে দেখা যাবে।'); ?></p>
                    <?php if ($rating_filter > 0): ?><a href="<?= htmlspecialchars($build_url(['rating' => 0, 'page' => 1])); ?>"><?= sm_text('View all reviews', 'সব পর্যালোচনা দেখুন'); ?></a><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="review-full-list">
                    <?php foreach ($review_page['reviews'] as $review): ?>
                        <?php
                            $full_name = trim((string) (($review['first_name'] ?? '') . ' ' . ($review['last_name'] ?? '')));
                            if ($full_name === '') $full_name = sm_text('Verified customer', 'যাচাইকৃত গ্রাহক');
                            $review_date = '';
                            if (!empty($review['created_at'])) {
                                $review_timestamp = strtotime($review['created_at']);
                                if (($current_language ?? 'bn') === 'en') {
                                    $review_date = date('d M Y', $review_timestamp);
                                } else {
                                    $bn_months = [
                                        1 => 'জানুয়ারি', 2 => 'ফেব্রুয়ারি', 3 => 'মার্চ', 4 => 'এপ্রিল',
                                        5 => 'মে', 6 => 'জুন', 7 => 'জুলাই', 8 => 'আগস্ট',
                                        9 => 'সেপ্টেম্বর', 10 => 'অক্টোবর', 11 => 'নভেম্বর', 12 => 'ডিসেম্বর'
                                    ];
                                    $review_date = date('d', $review_timestamp) . ' ' . $bn_months[(int) date('n', $review_timestamp)] . ' ' . date('Y', $review_timestamp);
                                }
                            }
                            $review_rating = (int) $review['rating'];
                        ?>
                        <article class="full-review-card">
                            <div class="full-review-top">
                                <div class="full-review-customer">
                                    <div class="review-avatar"><?= htmlspecialchars(strtoupper(mb_substr($full_name, 0, 1))); ?></div>
                                    <div>
                                        <strong><?= htmlspecialchars($full_name); ?></strong>
                                        <span><i class="fa-solid fa-circle-check"></i> <?= sm_text('Verified booking', 'যাচাইকৃত বুকিং'); ?></span>
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
                                <p class="review-no-text"><?= sm_text('Customer left a rating without written feedback.', 'গ্রাহক লিখিত মতামত না দিয়ে শুধু রেটিং দিয়েছেন।'); ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($review_page['total_pages'] > 1): ?>
                    <nav class="reviews-pagination" aria-label="<?= sm_text('Review pages', 'পর্যালোচনার পৃষ্ঠা'); ?>">
                        <?php if ($review_page['page'] > 1): ?>
                            <a href="<?= htmlspecialchars($build_url(['page' => $review_page['page'] - 1])); ?>" aria-label="<?= sm_text('Previous page', 'আগের পৃষ্ঠা'); ?>"><i class="fa-solid fa-chevron-left"></i></a>
                        <?php endif; ?>
                        <?php
                            $start_page = max(1, $review_page['page'] - 2);
                            $end_page = min($review_page['total_pages'], $review_page['page'] + 2);
                            for ($page_number = $start_page; $page_number <= $end_page; $page_number++):
                        ?>
                            <a class="<?= $page_number === $review_page['page'] ? 'active' : ''; ?>" href="<?= htmlspecialchars($build_url(['page' => $page_number])); ?>"><?= $page_number; ?></a>
                        <?php endfor; ?>
                        <?php if ($review_page['page'] < $review_page['total_pages']): ?>
                            <a href="<?= htmlspecialchars($build_url(['page' => $review_page['page'] + 1])); ?>" aria-label="<?= sm_text('Next page', 'পরের পৃষ্ঠা'); ?>"><i class="fa-solid fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php include '../includes/footer.php'; ?>
