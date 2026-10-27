<?php
require_once '../includes/language.php';

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

$stmt = mysqli_prepare($conn, 'SELECT service_id, service_name, service_name_bn, description, description_bn, is_active FROM services WHERE service_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $service_id);
mysqli_stmt_execute($stmt);
$service = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$service || (int)($service['is_active'] ?? 1) !== 1) {
    header('Location: ../dashboard.php#wedding-services');
    exit;
}

$providers = [];
$stmt = mysqli_prepare($conn, '
    SELECT provider_id, package_code, manager_id, provider_name, package_name, package_details, price, discount_percent,
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

// Contract Provider modal data is scoped to active packages in this service.
$contract_managers = [];
$contract_packages_by_manager = [];
$manager_ids_for_service = [];
foreach ($providers as $provider_row) {
    $mid = (int) ($provider_row['manager_id'] ?? 0);
    if ($mid > 0) {
        $manager_ids_for_service[$mid] = true;
        $contract_packages_by_manager[$mid][] = [
            'provider_id' => (int) $provider_row['provider_id'],
            'package_code' => (string) ($provider_row['package_code'] ?? ''),
            'package_name' => (string) ($provider_row['package_name'] ?: $provider_row['provider_name']),
            'provider_name' => (string) $provider_row['provider_name'],
        ];
    }
}
if ($manager_ids_for_service) {
    $manager_ids = array_keys($manager_ids_for_service);
    $placeholders = implode(',', array_fill(0, count($manager_ids), '?'));
    $manager_sql = "SELECT u.user_id, u.first_name, u.last_name, u.mobile, u.email, sp.profile_image
        FROM users u
        LEFT JOIN staff_profiles sp ON sp.user_id = u.user_id
        WHERE u.role = 'Manager' AND u.account_status = 'Active' AND u.user_id IN ($placeholders)
        ORDER BY u.first_name, u.last_name";
    $manager_stmt = mysqli_prepare($conn, $manager_sql);
    if ($manager_stmt) {
        $types = str_repeat('i', count($manager_ids));
        $bind_values = [$types];
        foreach ($manager_ids as $index => $manager_id_value) { $bind_values[] = &$manager_ids[$index]; }
        call_user_func_array('mysqli_stmt_bind_param', array_merge([$manager_stmt], $bind_values));
        mysqli_stmt_execute($manager_stmt);
        $manager_result = mysqli_stmt_get_result($manager_stmt);
        while ($manager_row = mysqli_fetch_assoc($manager_result)) {
            $mid = (int) $manager_row['user_id'];
            $image_name = trim((string) ($manager_row['profile_image'] ?? ''));
            $manager_row['image_url'] = ($image_name !== '' && is_file(dirname(__DIR__) . '/uploads/staff/' . basename($image_name)))
                ? BASE_URL . 'uploads/staff/' . rawurlencode(basename($image_name)) : '';
            $manager_row['package_count'] = count($contract_packages_by_manager[$mid] ?? []);
            $manager_row['packages'] = $contract_packages_by_manager[$mid] ?? [];
            $contract_managers[] = $manager_row;
        }
        mysqli_stmt_close($manager_stmt);
    }
}

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
                <a href="<?= BASE_URL; ?>dashboard.php#wedding-services" class="service-page-btn"><i class="fa-solid fa-arrow-left"></i><span><?= sm_text('Wedding Services', 'বিয়ের সেবা'); ?></span></a>
                <a href="<?= BASE_URL; ?>cart.php" class="service-page-btn"><i class="fa-solid fa-cart-shopping"></i><span><?= sm_text('Cart', 'কার্ট'); ?></span><b><?= $cart_count; ?></b></a>
            </div>
        </div>
    </header>

    <main>
        <section class="service-hero">
            <div class="service-container service-hero-layout">
                <div class="service-hero-copy">
                    <span class="service-kicker"><?= sm_text('WEDDING SERVICE', 'বিয়ের সেবা'); ?></span>
                    <h1><?= htmlspecialchars($service['service_name']); ?></h1>
                    <p><?= htmlspecialchars($service['description'] ?? 'Explore available packages and providers.'); ?></p>
                    <div class="service-hero-meta">
                        <span><i class="fa-solid fa-layer-group"></i> <?= count($providers); ?> <?= sm_text('available', 'উপলব্ধ'); ?> <?= count($providers) === 1 ? sm_text('option', 'টি বিকল্প') : sm_text('options', 'টি বিকল্প'); ?></span>
                        <span><i class="fa-solid fa-shield-heart"></i> <?= sm_text('Secure booking', 'নিরাপদ বুকিং'); ?></span>
                    </div>
                </div>
                <div class="service-hero-provider-action">
                    <button type="button" class="contract-provider-btn" id="openContractProvider" <?= !$contract_managers ? 'disabled' : ''; ?>>
                        <i class="fa-solid fa-handshake-angle" aria-hidden="true"></i>
                        <span><?= sm_text('Contract Provider', 'প্রোভাইডারের সাথে যোগাযোগ'); ?></span>
                        <i class="fa-solid fa-arrow-up-right-from-square contract-provider-arrow" aria-hidden="true"></i>
                    </button>
                    <small><?= sm_text('Explore managers and their packages', 'ম্যানেজার ও তাঁর প্যাকেজ দেখুন'); ?></small>
                </div>
            </div>
        </section>

        <section class="service-container service-options-section">
            <div class="service-section-heading">
                <div>
                    <span class="service-kicker"><?= sm_text('AVAILABLE OPTIONS', 'উপলব্ধ বিকল্প'); ?></span>
                    <h2><?= sm_text('Choose the package that fits your plan', 'আপনার পরিকল্পনার উপযোগী প্যাকেজ বেছে নিন'); ?></h2>
                </div>
                <a href="<?= BASE_URL; ?>cart.php" class="view-cart-btn"><i class="fa-solid fa-cart-shopping"></i> <?= sm_text('View Cart', 'কার্ট দেখুন'); ?></a>
            </div>

            <?php if (!$providers): ?>
                <div class="service-empty">
                    <div><i class="fa-regular fa-folder-open"></i></div>
                    <h3><?= sm_text('No packages available yet', 'এখনো কোনো প্যাকেজ নেই'); ?></h3>
                    <p><?= sm_text('This service category is ready, but an Event Manager has not added provider packages yet.', 'এই সেবার বিভাগটি প্রস্তুত, তবে ইভেন্ট ম্যানেজার এখনো কোনো প্যাকেজ যোগ করেননি।'); ?></p>
                    <a href="<?= BASE_URL; ?>dashboard.php#wedding-services"><?= sm_text('Back to services', 'সেবার তালিকায় ফিরুন'); ?></a>
                </div>
            <?php else: ?>
                <div class="provider-grid">
                    <?php foreach ($providers as $provider): ?>
                        <article class="provider-card" id="provider-card-<?= (int) $provider['provider_id']; ?>" data-provider-id="<?= (int) $provider['provider_id']; ?>">
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
                                        <?php if (!empty($provider['package_code'])): ?><span class="package-code-badge"><?= htmlspecialchars($provider['package_code']); ?></span><?php endif; ?>
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
                                    <span class="provider-rating provider-rating-empty"><i class="fa-regular fa-star"></i> <?= sm_text('No reviews yet', 'এখনো কোনো পর্যালোচনা নেই'); ?></span>
                                <?php endif; ?>
                                </div>
                                <?php $reviews = $provider_reviews[(int) $provider['provider_id']] ?? []; ?>
                                <div class="provider-reviews">
                                    <div class="provider-reviews-head">
                                        <div>
                                            <span class="provider-reviews-kicker"><i class="fa-regular fa-comments"></i> <?= sm_text('VERIFIED REVIEWS', 'যাচাইকৃত পর্যালোচনা'); ?></span>
                                            <strong><?= (int) $provider['review_count']; ?> <?= (int) $provider['review_count'] === 1 ? 'review' : 'reviews'; ?></strong>
                                        </div>
                                        <?php if ($reviews): ?><span><?= sm_text('Latest', 'সর্বশেষ'); ?> <?= count($reviews); ?></span><?php endif; ?>
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
                                                    <span class="provider-review-verified"><i class="fa-solid fa-circle-check"></i> <?= sm_text('Verified booking', 'যাচাইকৃত বুকিং'); ?></span>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="provider-reviews-empty"><i class="fa-regular fa-star"></i> <?= sm_text('No customer reviews yet. Be the first to share your experience after a completed booking.', 'এখনো কোনো গ্রাহক পর্যালোচনা নেই। বুকিং সম্পন্ন হলে প্রথমে আপনার অভিজ্ঞতা জানান।'); ?></p>
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
                                        <small><?= sm_text('Starting from', 'শুরুর মূল্য'); ?></small>
                                        <?php if ($provider_discount > 0): ?><del>৳<?= number_format((float)$provider['price'], 2); ?></del><?php endif; ?>
                                        <strong>৳<?= number_format($provider_final, 2); ?></strong>
                                        <?php if ($provider_discount > 0): ?><em><?= rtrim(rtrim(number_format($provider_discount, 2), '0'), '.'); ?>% OFF</em><?php endif; ?>
                                    </div>
                                    <form method="post" action="<?= BASE_URL; ?>cart.php">
                                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['cart_csrf']); ?>">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="provider_id" value="<?= (int) $provider['provider_id']; ?>">
                                        <input type="hidden" name="return_service_id" value="<?= $service_id; ?>">
                                        <button class="add-cart-btn" type="submit"><i class="fa-solid fa-cart-plus"></i> <?= sm_text('Add to Cart', 'কার্টে যোগ করুন'); ?></button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <div class="contract-modal" id="contractProviderModal" aria-hidden="true" hidden>
        <button type="button" class="contract-modal-backdrop" data-contract-close aria-label="<?= sm_text('Close window', 'উইন্ডো বন্ধ করুন'); ?>"></button>
        <section class="contract-modal-panel" role="dialog" aria-modal="true" aria-labelledby="contractModalTitle">
            <button type="button" class="contract-modal-close" data-contract-close aria-label="<?= sm_text('Close', 'বন্ধ করুন'); ?>"><i class="fa-solid fa-xmark"></i></button>
            <div class="contract-modal-heading">
                <span class="contract-modal-kicker"><i class="fa-solid fa-handshake-angle"></i> <?= sm_text('DIRECT CONNECTION', 'সরাসরি যোগাযোগ'); ?></span>
                <h2 id="contractModalTitle"><?= sm_text("Provider's Contract", 'প্রোভাইডারের সাথে চুক্তি'); ?></h2>
                <p><?= sm_text('Choose a manager and explore their packages for this service.', 'একজন ম্যানেজার বেছে নিয়ে এই সেবার তাঁর প্যাকেজগুলো দেখুন।'); ?></p>
            </div>
            <div class="contract-select-grid">
                <label class="contract-field"><span><?= sm_text('Select Manager', 'ম্যানেজার নির্বাচন করুন'); ?></span>
                    <select id="contractManagerSelect" <?= !$contract_managers ? 'disabled' : ''; ?>>
                        <option value=""><?= sm_text('Select manager first', 'আগে ম্যানেজার নির্বাচন করুন'); ?></option>
                        <?php foreach ($contract_managers as $manager): ?>
                            <option value="<?= (int) $manager['user_id']; ?>"><?= htmlspecialchars(trim($manager['first_name'] . ' ' . $manager['last_name'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="contract-field"><span><?= sm_text('Select Package', 'প্যাকেজ নির্বাচন করুন'); ?></span>
                    <select id="contractPackageSelect" disabled>
                        <option value=""><?= sm_text('Select a manager first', 'আগে একজন ম্যানেজার নির্বাচন করুন'); ?></option>
                    </select>
                </label>
            </div>
            <div class="contract-manager-profile" id="contractManagerProfile" aria-live="polite">
                <div class="contract-manager-avatar" id="contractManagerAvatar"><i class="fa-solid fa-user-tie"></i></div>
                <div class="contract-manager-info">
                    <span class="contract-manager-id" id="contractManagerId" hidden></span>
                    <h3 id="contractManagerName"><?= sm_text('Select Manager First', 'আগে ম্যানেজার নির্বাচন করুন'); ?></h3>
                    <div class="contract-manager-stats"><span><i class="fa-solid fa-box"></i> <b id="contractManagerPackageCount">—</b> <?= sm_text('packages in this service', 'টি প্যাকেজ'); ?></span></div>
                    <div class="contract-contact-list">
                        <div class="contract-contact-item">
                            <a href="#" id="contractManagerPhone" class="is-placeholder"><i class="fa-solid fa-phone"></i><span><?= sm_text('Select manager first', 'আগে ম্যানেজার নির্বাচন করুন'); ?></span></a>
                            <button type="button" class="contract-copy-btn" data-copy-contact="phone" aria-label="<?= sm_text('Copy mobile number', 'মোবাইল নম্বর কপি করুন'); ?>" title="<?= sm_text('Copy mobile number', 'মোবাইল নম্বর কপি করুন'); ?>" disabled><i class="fa-regular fa-copy"></i></button>
                        </div>
                        <div class="contract-contact-item">
                            <a href="#" id="contractManagerEmail" class="is-placeholder"><i class="fa-solid fa-envelope"></i><span><?= sm_text('Select manager first', 'আগে ম্যানেজার নির্বাচন করুন'); ?></span></a>
                            <button type="button" class="contract-copy-btn" data-copy-contact="email" aria-label="<?= sm_text('Copy email address', 'ইমেইল কপি করুন'); ?>" title="<?= sm_text('Copy email address', 'ইমেইল কপি করুন'); ?>" disabled><i class="fa-regular fa-copy"></i></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="contract-modal-footer"><span><i class="fa-solid fa-circle-info"></i> <?= sm_text('Selecting a package will take you to its card below.', 'প্যাকেজ নির্বাচন করলে নিচে সেই প্যাকেজ কার্ডে নিয়ে যাবে।'); ?></span></div>
        </section>
    </div>
    <script>
    (function () {
        const modal = document.getElementById('contractProviderModal');
        const openButton = document.getElementById('openContractProvider');
        const managerSelect = document.getElementById('contractManagerSelect');
        const packageSelect = document.getElementById('contractPackageSelect');
        const managers = <?= json_encode($contract_managers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        const isEnglish = <?= json_encode($current_language === 'en'); ?>;
        const text = (en, bn) => isEnglish ? en : bn;
        const managerName = document.getElementById('contractManagerName');
        const managerIdBadge = document.getElementById('contractManagerId');
        const managerCount = document.getElementById('contractManagerPackageCount');
        const managerPhone = document.getElementById('contractManagerPhone');
        const managerEmail = document.getElementById('contractManagerEmail');
        const copyPhoneButton = modal.querySelector('[data-copy-contact="phone"]');
        const copyEmailButton = modal.querySelector('[data-copy-contact="email"]');
        const avatar = document.getElementById('contractManagerAvatar');
        const fallbackManager = text('Select Manager First', 'আগে ম্যানেজার নির্বাচন করুন');
        const fallbackContact = text('Select manager first', 'আগে ম্যানেজার নির্বাচন করুন');
        let previousFocus = null;

        function setContact(element, value, kind) {
            const span = element.querySelector('span');
            element.classList.toggle('is-placeholder', !value);
            if (kind === 'phone') element.href = value ? 'tel:' + value.replace(/[^+\d]/g, '') : '#';
            else element.href = value ? 'mailto:' + value : '#';
            span.textContent = value || fallbackContact;
            element.dataset.copyValue = value || '';
            element.setAttribute('aria-disabled', value ? 'false' : 'true');
            const copyButton = kind === 'phone' ? copyPhoneButton : copyEmailButton;
            if (copyButton) copyButton.disabled = !value;
        }
        function renderManager(managerId) {
            const manager = managers.find(item => String(item.user_id) === String(managerId));
            packageSelect.innerHTML = '';
            if (!manager) {
                packageSelect.disabled = true;
                packageSelect.add(new Option(text('Select a manager first', 'আগে একজন ম্যানেজার নির্বাচন করুন'), ''));
                managerName.textContent = fallbackManager;
                managerIdBadge.hidden = true;
                managerIdBadge.textContent = '';
                managerCount.textContent = '—';
                setContact(managerPhone, '', 'phone');
                setContact(managerEmail, '', 'email');
                avatar.innerHTML = '<i class="fa-solid fa-user-tie"></i>';
                return;
            }
            const packages = Array.isArray(manager.packages) ? manager.packages : [];
            packageSelect.disabled = packages.length === 0;
            packageSelect.add(new Option(text('Select a package', 'প্যাকেজ নির্বাচন করুন'), ''));
            packages.forEach(item => {
                const label = item.package_code ? item.package_code + ' - ' + item.package_name : item.package_name;
                packageSelect.add(new Option(label, item.provider_id));
            });
            managerIdBadge.textContent = 'SM-' + String(manager.user_id).padStart(6, '0');
            managerIdBadge.hidden = false;
            managerName.textContent = [manager.first_name, manager.last_name].filter(Boolean).join(' ').trim() || text('Event Manager', 'ইভেন্ট ম্যানেজার');
            managerCount.textContent = String(manager.package_count || 0);
            setContact(managerPhone, manager.mobile || '', 'phone');
            setContact(managerEmail, manager.email || '', 'email');
            if (manager.image_url) {
                avatar.innerHTML = '';
                const img = document.createElement('img');
                img.src = manager.image_url;
                img.alt = managerName.textContent;
                img.onerror = function () { avatar.innerHTML = '<i class="fa-solid fa-user-tie"></i>'; };
                avatar.appendChild(img);
            } else avatar.innerHTML = '<i class="fa-solid fa-user-tie"></i>';
        }
        async function copyContact(kind) {
            const element = kind === 'phone' ? managerPhone : managerEmail;
            const value = element.dataset.copyValue || '';
            if (!value) return;
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(value);
                } else {
                    const temporary = document.createElement('textarea');
                    temporary.value = value;
                    temporary.setAttribute('readonly', '');
                    temporary.style.position = 'fixed';
                    temporary.style.opacity = '0';
                    document.body.appendChild(temporary);
                    temporary.select();
                    const copied = document.execCommand('copy');
                    temporary.remove();
                    if (!copied) throw new Error('Copy failed');
                }
                const button = kind === 'phone' ? copyPhoneButton : copyEmailButton;
                if (button) {
                    const icon = button.querySelector('i');
                    if (icon) icon.className = 'fa-solid fa-check';
                    button.title = text('Copied', 'কপি হয়েছে');
                    window.setTimeout(() => {
                        if (icon) icon.className = 'fa-regular fa-copy';
                        button.title = kind === 'phone' ? text('Copy mobile number', 'মোবাইল নম্বর কপি করুন') : text('Copy email address', 'ইমেইল কপি করুন');
                    }, 1300);
                }
            } catch (error) {
                // Leave the contact visible if clipboard access is blocked by the browser.
            }
        }
        function openModal() {
            if (!modal) return;
            previousFocus = document.activeElement;
            modal.hidden = false;
            requestAnimationFrame(() => modal.classList.add('is-open'));
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('contract-modal-open');
            managerSelect.focus();
        }
        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('contract-modal-open');
            window.setTimeout(() => { modal.hidden = true; }, 180);
            if (previousFocus) previousFocus.focus();
        }
        if (openButton && managers.length) openButton.addEventListener('click', openModal);
        modal.querySelectorAll('[data-contract-close]').forEach(button => button.addEventListener('click', closeModal));
        document.addEventListener('keydown', event => { if (event.key === 'Escape' && !modal.hidden) closeModal(); });
        managerSelect.addEventListener('change', () => renderManager(managerSelect.value));
        if (copyPhoneButton) copyPhoneButton.addEventListener('click', () => copyContact('phone'));
        if (copyEmailButton) copyEmailButton.addEventListener('click', () => copyContact('email'));
        packageSelect.addEventListener('change', () => {
            const id = packageSelect.value;
            if (!id) return;
            closeModal();
            const card = document.getElementById('provider-card-' + id);
            if (card) {
                history.replaceState(null, '', '#provider-card-' + id);
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                card.classList.remove('provider-card-highlight');
                void card.offsetWidth;
                card.classList.add('provider-card-highlight');
                window.setTimeout(() => card.classList.remove('provider-card-highlight'), 2400);
            }
        });
        document.querySelectorAll('.contract-contact-list a').forEach(link => link.addEventListener('click', event => {
            if (link.getAttribute('aria-disabled') === 'true') event.preventDefault();
        }));
        const hash = window.location.hash.match(/^#provider-card-(\d+)$/);
        if (hash) window.addEventListener('load', () => {
            const card = document.getElementById('provider-card-' + hash[1]);
            if (card) { card.scrollIntoView({ behavior: 'smooth', block: 'center' }); card.classList.add('provider-card-highlight'); }
        });
    })();
    </script>
</div>

<script src="<?= BASE_URL; ?>assets/js/package-image-preview.js?v=2"></script>
<?php include '../includes/footer.php'; ?>
