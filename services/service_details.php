<?php

$page_css = 'assets/css/service-details.css';

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';

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
    SELECT provider_id, provider_name, package_name, package_details, price,
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

$cart_count = 0;
$cart_stmt = mysqli_prepare($conn, 'SELECT COALESCE(SUM(quantity),0) AS total FROM service_cart_items WHERE user_id = ?');
mysqli_stmt_bind_param($cart_stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($cart_stmt);
$cart_count = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($cart_stmt))['total'] ?? 0);
mysqli_stmt_close($cart_stmt);

include '../includes/header.php';
?>

<div class="service-page">
    <header class="service-topbar">
        <div class="service-container service-topbar-inner">
            <a href="<?= BASE_URL; ?>dashboard.php#wedding-services" class="service-back">
                <i class="fa-solid fa-arrow-left"></i> Wedding Services
            </a>
            <a href="<?= BASE_URL; ?>cart.php" class="service-cart-link">
                <i class="fa-solid fa-cart-shopping"></i>
                Cart
                <span><?= $cart_count; ?></span>
            </a>
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
                                <span class="provider-rating"><i class="fa-solid fa-star"></i> <?= number_format((float) $provider['rating'], 1); ?> <small>(<?= (int) $provider['review_count']; ?>)</small></span>
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
                                <div class="provider-bottom">
                                    <div class="provider-price"><small>Starting from</small><strong>৳<?= number_format((float) $provider['price'], 2); ?></strong></div>
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
