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
$manager_url = function(string $path = 'dashboard.php', array $params = [], string $fragment = '') use ($manager_sid): string {
    $params = array_merge(['manager_sid' => $manager_sid], $params);
    $url = $path . '?' . http_build_query($params);
    return $url . ($fragment !== '' ? '#' . ltrim($fragment, '#') : '');
};
$message = '';
$error = '';

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
                $contact_number = trim($_POST['contact_number'] ?? '');
                $location = trim($_POST['location'] ?? '');
                $rating = max(0, min(5, (float) ($_POST['rating'] ?? 0)));
                $review_count = max(0, (int) ($_POST['review_count'] ?? 0));
                $status = ($_POST['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';

                if ($service_id <= 0 || $provider_name === '' || $package_name === '' || $price < 0) {
                    throw new RuntimeException('Please complete the required package fields.');
                }

                $upload = manager_handle_image_upload('package_image', $upload_dir);
                if ($upload['error']) throw new RuntimeException($upload['error']);

                if ($provider_id > 0) {
                    $stmt = mysqli_prepare($conn, 'SELECT image FROM service_providers WHERE provider_id = ? AND manager_id = ? LIMIT 1');
                    mysqli_stmt_bind_param($stmt, 'ii', $provider_id, $manager_id);
                    mysqli_stmt_execute($stmt);
                    $owned = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                    mysqli_stmt_close($stmt);
                    if (!$owned) throw new RuntimeException('That package could not be found.');

                    $image = $owned['image'];
                    if ($upload['name']) {
                        $image = $upload['name'];
                    }

                    $stmt = mysqli_prepare($conn, 'UPDATE service_providers SET service_id=?, provider_name=?, package_name=?, package_details=?, price=?, contact_number=?, location=?, rating=?, review_count=?, status=?, image=? WHERE provider_id=? AND manager_id=?');
                    mysqli_stmt_bind_param($stmt, 'isssdssdiisii', $service_id, $provider_name, $package_name, $package_details, $price, $contact_number, $location, $rating, $review_count, $status, $image, $provider_id, $manager_id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    if ($upload['name'] && $owned['image'] && $owned['image'] !== $upload['name']) {
                        manager_remove_image($owned['image'], $upload_dir);
                    }
                    $message = 'Package updated successfully.';
                } else {
                    $image = $upload['name'] ?? null;
                    $stmt = mysqli_prepare($conn, 'INSERT INTO service_providers (service_id, provider_name, package_name, package_details, price, contact_number, location, rating, review_count, manager_id, status, image) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
                    mysqli_stmt_bind_param($stmt, 'isssdssdiiss', $service_id, $provider_name, $package_name, $package_details, $price, $contact_number, $location, $rating, $review_count, $manager_id, $status, $image);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    $message = 'Package added successfully.';
                }
                header('Location: dashboard.php?manager_sid=' . urlencode($manager_sid) . '&success=' . urlencode($message) . '#catalog');
                exit;
            }

            if ($action === 'toggle_status') {
                $provider_id = (int) ($_POST['provider_id'] ?? 0);
                $new_status = ($_POST['new_status'] ?? '') === 'Inactive' ? 'Inactive' : 'Active';
                $stmt = mysqli_prepare($conn, 'UPDATE service_providers SET status=? WHERE provider_id=? AND manager_id=?');
                mysqli_stmt_bind_param($stmt, 'sii', $new_status, $provider_id, $manager_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                header('Location: dashboard.php?manager_sid=' . urlencode($manager_sid) . '&success=' . urlencode('Package status updated.') . '#catalog');
                exit;
            }

            if ($action === 'delete_provider') {
                $provider_id = (int) ($_POST['provider_id'] ?? 0);
                $stmt = mysqli_prepare($conn, 'SELECT image FROM service_providers WHERE provider_id=? AND manager_id=? LIMIT 1');
                mysqli_stmt_bind_param($stmt, 'ii', $provider_id, $manager_id);
                mysqli_stmt_execute($stmt);
                $owned = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);
                if (!$owned) throw new RuntimeException('That package could not be found.');

                $stmt = mysqli_prepare($conn, 'DELETE FROM service_providers WHERE provider_id=? AND manager_id=?');
                mysqli_stmt_bind_param($stmt, 'ii', $provider_id, $manager_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                manager_remove_image($owned['image'] ?? null, $upload_dir);
                header('Location: dashboard.php?manager_sid=' . urlencode($manager_sid) . '&success=' . urlencode('Package removed.') . '#catalog');
                exit;
            }

            if ($action === 'booking_status') {
                $booking_id = (int) ($_POST['booking_id'] ?? 0);
                $status = $_POST['booking_status'] ?? 'Pending';
                $allowed = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
                if (!in_array($status, $allowed, true)) throw new RuntimeException('Invalid booking status.');
                $stmt = mysqli_prepare($conn, 'UPDATE bookings SET booking_status=? WHERE booking_id=? AND manager_id=?');
                mysqli_stmt_bind_param($stmt, 'sii', $status, $booking_id, $manager_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                header('Location: dashboard.php?manager_sid=' . urlencode($manager_sid) . '&success=' . urlencode('Booking status updated.') . '#bookings');
                exit;
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

if (!empty($_GET['success'])) $message = trim($_GET['success']);

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
    if (!$edit_package) $error = 'The selected package could not be found.';
}

$packages = [];
$stmt = mysqli_prepare($conn, 'SELECT sp.*, s.service_name FROM service_providers sp INNER JOIN services s ON s.service_id=sp.service_id WHERE sp.manager_id=? ORDER BY sp.created_at DESC');
mysqli_stmt_bind_param($stmt, 'i', $manager_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) $packages[] = $row;
mysqli_stmt_close($stmt);

$bookings = [];
$stmt = mysqli_prepare($conn, "SELECT b.booking_id, b.user_id, b.total_price, b.booking_status, b.booking_date, u.first_name, u.last_name, u.email,
    GROUP_CONCAT(CONCAT(s.service_name, ' — ', COALESCE(sp.package_name, sp.provider_name), ' × ', bd.quantity) ORDER BY s.service_name SEPARATOR '||') AS items,
    MIN(bd.event_date) AS event_date
    FROM bookings b
    INNER JOIN users u ON u.user_id=b.user_id
    INNER JOIN booking_details bd ON bd.booking_id=b.booking_id
    INNER JOIN service_providers sp ON sp.provider_id=bd.provider_id
    INNER JOIN services s ON s.service_id=sp.service_id
    WHERE b.manager_id=?
    GROUP BY b.booking_id, b.user_id, b.total_price, b.booking_status, b.booking_date, u.first_name, u.last_name, u.email
    ORDER BY b.booking_date DESC");
mysqli_stmt_bind_param($stmt, 'i', $manager_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) $bookings[] = $row;
mysqli_stmt_close($stmt);

$active_packages = count(array_filter($packages, fn($p) => $p['status'] === 'Active'));
$pending = count(array_filter($bookings, fn($b) => $b['booking_status'] === 'Pending'));
$confirmed = count(array_filter($bookings, fn($b) => $b['booking_status'] === 'Confirmed'));
$revenue = 0.0;
foreach ($bookings as $b) if ($b['booking_status'] !== 'Cancelled') $revenue += (float) $b['total_price'];

include '../includes/header.php';
?>
<div class="manager-shell">
    <header class="manager-topbar">
        <div class="manager-container manager-topbar-inner">
            <a href="<?= BASE_URL; ?>dashboard.php" class="manager-brand-link"><i class="fa-solid fa-heart"></i><span>Smart Matrimony</span><small>Event Manager</small></a>
            <div class="manager-top-actions">
                <a href="<?= BASE_URL; ?>dashboard.php" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-left"></i> User Dashboard</a>
                <a href="<?= htmlspecialchars($manager_url('logout.php')); ?>" class="manager-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </div>
    </header>

    <main class="manager-container manager-main">
        <section class="manager-welcome">
            <div>
                <span class="manager-kicker">EVENT MANAGEMENT</span>
                <h1>Welcome, <?= htmlspecialchars($_SESSION['manager_name'] ?? 'Manager'); ?></h1>
                <p>Manage your wedding service packages and respond to customer booking requests.</p>
            </div>
            <a href="#catalog" class="manager-primary-btn"><i class="fa-solid fa-plus"></i> Add Package</a>
        </section>

        <?php if ($message): ?><div class="manager-alert success"><i class="fa-solid fa-circle-check"></i><?= htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="manager-alert error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($error); ?></div><?php endif; ?>

        <section class="manager-stats">
            <article><span><i class="fa-solid fa-box"></i></span><div><strong><?= $active_packages; ?></strong><small>Active Packages</small></div></article>
            <article><span><i class="fa-solid fa-clock"></i></span><div><strong><?= $pending; ?></strong><small>Pending Requests</small></div></article>
            <article><span><i class="fa-solid fa-calendar-check"></i></span><div><strong><?= $confirmed; ?></strong><small>Confirmed Bookings</small></div></article>
            <article><span><i class="fa-solid fa-bangladeshi-taka-sign"></i></span><div><strong>৳<?= number_format($revenue, 0); ?></strong><small>Booking Value</small></div></article>
        </section>

        <section class="manager-grid" id="catalog">
            <div class="manager-panel" id="package-form">
                <div class="manager-panel-heading">
                    <div><span class="manager-kicker">SERVICE CATALOG</span><h2><?= $edit_package ? 'Edit package' : 'Add a package'; ?></h2></div>
                    <?php if ($edit_package): ?><a class="manager-cancel-edit" href="<?= htmlspecialchars($manager_url('dashboard.php', [], 'catalog')); ?>">Cancel</a><?php endif; ?>
                </div>
                <form method="post"
                              action="dashboard.php?manager_sid=<?= urlencode($manager_sid); ?>" class="package-form" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="save_provider">
                    <input type="hidden" name="provider_id" value="<?= (int) ($edit_package['provider_id'] ?? 0); ?>">
                    <div class="form-grid">
                        <div><label>Service *</label><select name="service_id" required><option value="">Select service</option><?php foreach ($services as $s): ?><option value="<?= $s['service_id']; ?>" <?= ((int)($edit_package['service_id'] ?? 0) === (int)$s['service_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($s['service_name']); ?></option><?php endforeach; ?></select></div>
                        <div><label>Provider / Business name *</label><input name="provider_name" required maxlength="150" value="<?= htmlspecialchars($edit_package['provider_name'] ?? ''); ?>" placeholder="e.g. Noor Photography"></div>
                        <div><label>Package name *</label><input name="package_name" required maxlength="150" value="<?= htmlspecialchars($edit_package['package_name'] ?? ''); ?>" placeholder="e.g. Premium Wedding Package"></div>
                        <div><label>Price (৳) *</label><input name="price" type="number" min="0" step="0.01" required value="<?= htmlspecialchars($edit_package['price'] ?? ''); ?>" placeholder="25000"></div>
                        <div><label>Location</label><input name="location" maxlength="255" value="<?= htmlspecialchars($edit_package['location'] ?? ''); ?>" placeholder="Dhaka, Bangladesh"></div>
                        <div><label>Contact number</label><input name="contact_number" maxlength="20" value="<?= htmlspecialchars($edit_package['contact_number'] ?? ''); ?>" placeholder="01XXXXXXXXX"></div>
                        <div class="full-field"><label>Package image</label><input name="package_image" type="file" accept="image/jpeg,image/png,image/webp"><small class="field-help">JPG, PNG or WebP · max 10 MB<?= !empty($edit_package['image']) ? ' · current image will remain if no new image is selected' : ''; ?></small></div>
                        <div><label>Rating</label><input name="rating" type="number" min="0" max="5" step="0.1" value="<?= htmlspecialchars($edit_package['rating'] ?? '0'); ?>"></div>
                        <div><label>Review count</label><input name="review_count" type="number" min="0" value="<?= htmlspecialchars($edit_package['review_count'] ?? '0'); ?>"></div>
                        <div><label>Status</label><select name="status"><option value="Active" <?= (($edit_package['status'] ?? 'Active') === 'Active') ? 'selected' : ''; ?>>Active</option><option value="Inactive" <?= (($edit_package['status'] ?? '') === 'Inactive') ? 'selected' : ''; ?>>Inactive</option></select></div>
                    </div>
                    <div><label>Package details</label><textarea name="package_details" rows="4" maxlength="3000" placeholder="What's included, duration, terms, etc."><?= htmlspecialchars($edit_package['package_details'] ?? ''); ?></textarea></div>
                    <?php if (!empty($edit_package['image'])): ?><div class="current-image-row"><img src="<?= $upload_web . rawurlencode($edit_package['image']); ?>" alt="Current package image"><span>Current package image</span></div><?php endif; ?>
                    <button class="manager-primary-btn" type="submit"><i class="fa-solid <?= $edit_package ? 'fa-floppy-disk' : 'fa-plus'; ?>"></i> <?= $edit_package ? 'Save Changes' : 'Add Package'; ?></button>
                </form>
            </div>

            <div class="manager-panel">
                <div class="manager-panel-heading"><div><span class="manager-kicker">MY CATALOG</span><h2>Packages you manage</h2></div><span class="manager-count"><?= count($packages); ?></span></div>
                <?php if (!$packages): ?>
                    <div class="manager-empty"><i class="fa-regular fa-folder-open"></i><h3>No packages yet</h3><p>Add your first service package to make it available to customers.</p></div>
                <?php else: ?>
                    <div class="package-list">
                        <?php foreach ($packages as $p): ?>
                            <article class="package-row package-row-rich">
                                <div class="package-thumb"><?php if (!empty($p['image'])): ?><img src="<?= $upload_web . rawurlencode($p['image']); ?>" alt=""><?php else: ?><i class="fa-solid fa-ring"></i><?php endif; ?></div>
                                <div class="package-info"><span><?= htmlspecialchars($p['service_name']); ?></span><h3><?= htmlspecialchars($p['package_name'] ?: $p['provider_name']); ?></h3><p><?= htmlspecialchars($p['provider_name']); ?><?= $p['location'] ? ' · '.htmlspecialchars($p['location']) : ''; ?></p></div>
                                <div class="package-meta"><strong>৳<?= number_format((float)$p['price'], 0); ?></strong><span class="status-pill <?= strtolower($p['status']); ?>"><?= htmlspecialchars($p['status']); ?></span></div>
                                <div class="package-actions">
                                    <a class="icon-edit" href="<?= htmlspecialchars($manager_url('dashboard.php', ['edit' => (int)$p['provider_id']], 'package-form')); ?>" title="Edit package"><i class="fa-solid fa-pen"></i></a>
                                    <form method="post"
                              action="dashboard.php?manager_sid=<?= urlencode($manager_sid); ?>"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="provider_id" value="<?= (int)$p['provider_id']; ?>"><input type="hidden" name="new_status" value="<?= $p['status'] === 'Active' ? 'Inactive' : 'Active'; ?>"><button class="icon-status" title="<?= $p['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?>"><i class="fa-solid <?= $p['status'] === 'Active' ? 'fa-eye-slash' : 'fa-eye'; ?>"></i></button></form>
                                    <form method="post"
                              action="dashboard.php?manager_sid=<?= urlencode($manager_sid); ?>" onsubmit="return confirm('Remove this package permanently?');"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>"><input type="hidden" name="action" value="delete_provider"><input type="hidden" name="provider_id" value="<?= (int)$p['provider_id']; ?>"><button class="icon-danger" title="Remove"><i class="fa-solid fa-trash-can"></i></button></form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="manager-panel booking-panel" id="bookings">
            <div class="manager-panel-heading"><div><span class="manager-kicker">BOOKING REQUESTS</span><h2>Customer bookings</h2></div><span class="manager-count"><?= count($bookings); ?></span></div>
            <?php if (!$bookings): ?>
                <div class="manager-empty"><i class="fa-regular fa-calendar"></i><h3>No booking requests yet</h3><p>When customers book one of your packages, requests will appear here.</p></div>
            <?php else: ?>
                <div class="booking-table-wrap"><table class="booking-table"><thead><tr><th>Booking</th><th>Customer</th><th>Packages</th><th>Event date</th><th>Total</th><th>Status</th><th>Action</th></tr></thead><tbody>
                <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td><strong>#<?= $b['booking_id']; ?></strong><small><?= date('d M Y', strtotime($b['booking_date'])); ?></small></td>
                        <td><strong><?= htmlspecialchars($b['first_name'].' '.$b['last_name']); ?></strong><small><?= htmlspecialchars($b['email']); ?></small></td>
                        <td><div class="booking-items"><?php foreach (explode('||', $b['items']) as $item): ?><span><?= htmlspecialchars($item); ?></span><?php endforeach; ?></div></td>
                        <td><?= $b['event_date'] ? date('d M Y', strtotime($b['event_date'])) : '—'; ?></td>
                        <td><strong>৳<?= number_format((float)$b['total_price'], 2); ?></strong></td>
                        <td><span class="status-pill <?= strtolower($b['booking_status']); ?>"><?= htmlspecialchars($b['booking_status']); ?></span></td>
                        <td><form method="post"
                              action="dashboard.php?manager_sid=<?= urlencode($manager_sid); ?>" class="status-form"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>"><input type="hidden" name="action" value="booking_status"><input type="hidden" name="booking_id" value="<?= $b['booking_id']; ?>"><select name="booking_status"><option <?= $b['booking_status']==='Pending'?'selected':''; ?>>Pending</option><option <?= $b['booking_status']==='Confirmed'?'selected':''; ?>>Confirmed</option><option <?= $b['booking_status']==='Completed'?'selected':''; ?>>Completed</option><option <?= $b['booking_status']==='Cancelled'?'selected':''; ?>>Cancelled</option></select><button type="submit" title="Update status"><i class="fa-solid fa-check"></i></button></form></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php include '../includes/footer.php'; ?>
