<?php
require_once 'includes/language.php';

$page_css = 'assets/css/service-details.css';

require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

if (empty($_SESSION['cart_csrf'])) {
    $_SESSION['cart_csrf'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['cart_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Invalid request token.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $provider_id = (int) ($_POST['provider_id'] ?? 0);
        $stmt = mysqli_prepare($conn, "INSERT INTO service_cart_items (user_id, provider_id, quantity)
            SELECT ?, provider_id, 1 FROM service_providers WHERE provider_id = ? AND status = 'Active'
            ON DUPLICATE KEY UPDATE quantity = quantity + 1");
        mysqli_stmt_bind_param($stmt, 'ii', $user_id, $provider_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $return_service_id = (int) ($_POST['return_service_id'] ?? 0);
        if ($return_service_id > 0) {
            header('Location: services/service_details.php?service_id=' . $return_service_id . '&added=1');
        } else {
            header('Location: cart.php');
        }
        exit;
    }

    if ($action === 'update') {
        $cart_item_id = (int) ($_POST['cart_item_id'] ?? 0);
        $quantity = max(1, min(20, (int) ($_POST['quantity'] ?? 1)));
        $stmt = mysqli_prepare($conn, 'UPDATE service_cart_items SET quantity = ? WHERE cart_item_id = ? AND user_id = ?');
        mysqli_stmt_bind_param($stmt, 'iii', $quantity, $cart_item_id, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header('Location: cart.php');
        exit;
    }

    if ($action === 'remove') {
        $cart_item_id = (int) ($_POST['cart_item_id'] ?? 0);
        $stmt = mysqli_prepare($conn, 'DELETE FROM service_cart_items WHERE cart_item_id = ? AND user_id = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $cart_item_id, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header('Location: cart.php');
        exit;
    }

    if ($action === 'book') {
        $event_date = trim($_POST['event_date'] ?? '');
        $special_instruction = trim($_POST['special_instruction'] ?? '');

        $date_parts = explode('-', $event_date);
        $valid_event_date = count($date_parts) === 3
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)
            && ctype_digit($date_parts[0])
            && ctype_digit($date_parts[1])
            && ctype_digit($date_parts[2])
            && checkdate((int)$date_parts[1], (int)$date_parts[2], (int)$date_parts[0])
            && $event_date >= date('Y-m-d');

        if (!$valid_event_date) {
            header('Location: cart.php?error=date');
            exit;
        }

        mysqli_begin_transaction($conn);
        try {
            $items = [];
            $stmt = mysqli_prepare($conn, "SELECT ci.provider_id, ci.quantity, sp.price, sp.discount_percent, sp.manager_id
                FROM service_cart_items ci
                INNER JOIN service_providers sp ON sp.provider_id = ci.provider_id
                WHERE ci.user_id = ? AND sp.status = 'Active' FOR UPDATE");
            mysqli_stmt_bind_param($stmt, 'i', $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) $items[] = $row;
            mysqli_stmt_close($stmt);

            if (!$items) throw new RuntimeException('Your cart is empty.');

            $total = 0.0;
            $manager_ids = [];
            foreach ($items as $item) {
                $discount = max(0, min(100, (float)($item['discount_percent'] ?? 0)));
                $final_price = round((float) $item['price'] * (1 - ($discount / 100)), 2);
                $total += ($final_price * (int) $item['quantity']);
                if (!empty($item['manager_id'])) $manager_ids[(int) $item['manager_id']] = true;
            }

            if (empty($manager_ids)) {
                throw new RuntimeException('One or more selected packages are not assigned to a manager. Please remove them and try again.');
            }

            // A shared Manager Dashboard handles the booking globally. Keep the legacy
            // bookings.manager_id populated only for a single-manager booking; multi-manager
            // bookings intentionally keep it NULL.
            $single_manager_id = count($manager_ids) === 1 ? (int) array_key_first($manager_ids) : null;

            $active_manager_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total
                FROM users
                WHERE user_id=? AND role='Manager' AND account_status='Active'");
            foreach (array_keys($manager_ids) as $booked_manager_id) {
                $booked_manager_id = (int) $booked_manager_id;
                mysqli_stmt_bind_param($active_manager_stmt, 'i', $booked_manager_id);
                mysqli_stmt_execute($active_manager_stmt);
                $active_manager = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($active_manager_stmt))['total'] ?? 0);
                if ($active_manager !== 1) {
                    mysqli_stmt_close($active_manager_stmt);
                    throw new RuntimeException('A selected package is currently assigned to an unavailable manager. Please try again after the package is reassigned.');
                }
            }
            mysqli_stmt_close($active_manager_stmt);

            if ($single_manager_id !== null) {
                $stmt = mysqli_prepare($conn, "INSERT INTO bookings (user_id, manager_id, total_price, booking_status) VALUES (?, ?, ?, 'Pending')");
                mysqli_stmt_bind_param($stmt, 'iid', $user_id, $single_manager_id, $total);
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO bookings (user_id, manager_id, total_price, booking_status) VALUES (?, NULL, ?, 'Pending')");
                mysqli_stmt_bind_param($stmt, 'id', $user_id, $total);
            }
            mysqli_stmt_execute($stmt);
            $booking_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, 'INSERT INTO booking_details (booking_id, provider_id, manager_id, quantity, unit_price, event_date, special_instruction) VALUES (?, ?, ?, ?, ?, ?, ?)');
            foreach ($items as $item) {
                $provider_id = (int) $item['provider_id'];
                $item_manager_id = (int) ($item['manager_id'] ?? 0);
                $quantity = (int) $item['quantity'];
                $discount = max(0, min(100, (float)($item['discount_percent'] ?? 0)));
                $unit_price = round((float) $item['price'] * (1 - ($discount / 100)), 2);
                mysqli_stmt_bind_param($stmt, 'iiiidss', $booking_id, $provider_id, $item_manager_id, $quantity, $unit_price, $event_date, $special_instruction);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, 'DELETE FROM service_cart_items WHERE user_id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
            header('Location: booking_details.php?id=' . $booking_id . '&booked=1');
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            header('Location: cart.php?error=booking');
            exit;
        }
    }
}

$items = [];
$total = 0.0;
$stmt = mysqli_prepare($conn, "SELECT ci.cart_item_id, ci.quantity,
        sp.provider_id, sp.package_code, sp.provider_name, sp.package_name, sp.price, sp.discount_percent, sp.location, sp.image,
        s.service_id, s.service_name
    FROM service_cart_items ci
    INNER JOIN service_providers sp ON sp.provider_id = ci.provider_id
    INNER JOIN services s ON s.service_id = sp.service_id
    WHERE ci.user_id = ? AND sp.status = 'Active'
    ORDER BY ci.created_at DESC");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $row['discount_percent'] = max(0, min(100, (float)($row['discount_percent'] ?? 0)));
    $row['final_price'] = round((float) $row['price'] * (1 - ($row['discount_percent'] / 100)), 2);
    $row['line_total'] = $row['final_price'] * (int) $row['quantity'];
    $total += $row['line_total'];
    $items[] = $row;
}
mysqli_stmt_close($stmt);

include 'includes/header.php';
?>

<div class="service-page">
    <header class="cart-topbar">
        <div class="service-container cart-topbar-inner">
            <a href="<?= BASE_URL; ?>dashboard.php" class="cart-brand" aria-label="Smart Matrimony Dashboard">
                <span class="cart-brand-logo">
                    <img src="<?= BASE_URL; ?>assets/images/logo/logo.png" alt="Smart Matrimony Logo">
                </span>
                <span class="cart-brand-title">
                    <img src="<?= BASE_URL; ?>assets/images/logo/matrimony_title.png" alt="Smart Matrimony">
                </span>
            </a>

            <nav class="cart-topbar-actions" aria-label="Cart navigation">
                <a href="<?= BASE_URL; ?>dashboard.php#wedding-services" class="cart-nav-link">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span><?= sm_text('Wedding Services', 'বিয়ের সেবা'); ?></span>
                </a>
                <a href="<?= BASE_URL; ?>my_bookings.php" class="cart-nav-link cart-nav-primary">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span><?= sm_text('My Bookings', 'আমার বুকিং'); ?></span>
                </a>
            </nav>
        </div>
    </header>

    <main class="service-container cart-page-main">
        <div class="service-section-heading">
            <div>
                <span class="service-kicker"><?= sm_text('YOUR SELECTION', 'আপনার নির্বাচন'); ?></span>
                <h1><?= sm_text('Review & Book', 'পর্যালোচনা ও বুকিং'); ?></h1>
                <p><?= sm_text('Choose your event date, review selected packages and submit your booking request.', 'অনুষ্ঠানের তারিখ নির্বাচন করুন, বাছাই করা প্যাকেজগুলো পর্যালোচনা করুন এবং বুকিংয়ের অনুরোধ পাঠান।'); ?></p>
            </div>
        </div>

        <?php if (isset($_GET['booked'])): ?>
            <div class="cart-alert success"><i class="fa-solid fa-circle-check"></i> <?= sm_text('Booking request', 'বুকিংয়ের অনুরোধ'); ?> #<?= (int) $_GET['booked']; ?> <?= sm_text('was created successfully.', 'সফলভাবে তৈরি হয়েছে।'); ?></div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="cart-alert error"><i class="fa-solid fa-circle-exclamation"></i> <?= sm_text('Please check your event date and try again.', 'অনুষ্ঠানের তারিখটি যাচাই করে আবার চেষ্টা করুন।'); ?></div>
        <?php endif; ?>

        <?php if (!$items): ?>
            <div class="service-empty cart-empty">
                <div><i class="fa-solid fa-cart-shopping"></i></div>
                <h3><?= sm_text('Your cart is empty', 'আপনার কার্ট খালি'); ?></h3>
                <p><?= sm_text('Add one or more wedding service packages before booking.', 'বুকিং করার আগে এক বা একাধিক বিয়ের সেবার প্যাকেজ যোগ করুন।'); ?></p>
                <a href="<?= BASE_URL; ?>dashboard.php#wedding-services"><?= sm_text('Explore Wedding Services', 'বিয়ের সেবাগুলো দেখুন'); ?></a>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                <section class="cart-items">
                    <?php foreach ($items as $item): ?>
                        <article class="cart-item">
                            <div class="cart-item-image">
                                <?php if (!empty($item['image'])): ?>
                                    <img src="<?= BASE_URL; ?>uploads/services/<?= htmlspecialchars($item['image']); ?>" alt="<?= htmlspecialchars($item['service_name']); ?>">
                                <?php else: ?>
                                    <i class="fa-solid fa-ring"></i>
                                <?php endif; ?>
                            </div>
                            <div class="cart-item-info">
                                <span><?= htmlspecialchars($item['service_name']); ?></span>
                                <?php if (!empty($item['package_code'])): ?><span class="package-code-badge"><?= htmlspecialchars($item['package_code']); ?></span><?php endif; ?>
                                <h3><?= htmlspecialchars($item['package_name'] ?: $item['provider_name']); ?></h3>
                                <p><?= htmlspecialchars($item['provider_name']); ?><?= !empty($item['location']) ? ' · ' . htmlspecialchars($item['location']) : ''; ?></p>
                            </div>
                            <div class="cart-item-price">
                                <?php if ((float)$item['discount_percent'] > 0): ?><del>৳<?= number_format((float)$item['price'], 2); ?></del><?php endif; ?>
                                <strong>৳<?= number_format((float)$item['final_price'], 2); ?></strong>
                                <?php if ((float)$item['discount_percent'] > 0): ?><small><?= rtrim(rtrim(number_format((float)$item['discount_percent'], 2), '0'), '.'); ?>% OFF</small><?php endif; ?>
                            </div>
                            <form method="post" class="cart-quantity-form">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="cart_item_id" value="<?= (int) $item['cart_item_id']; ?>">
                                <input type="number" name="quantity" min="1" max="20" value="<?= (int) $item['quantity']; ?>">
                                <button type="submit" title="<?= sm_text('Update quantity', 'পরিমাণ হালনাগাদ করুন'); ?>"><i class="fa-solid fa-rotate"></i></button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="cart_item_id" value="<?= (int) $item['cart_item_id']; ?>">
                                <button class="remove-cart-btn" type="submit" title="<?= sm_text('Remove', 'সরিয়ে ফেলুন'); ?>"><i class="fa-solid fa-trash-can"></i></button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </section>

                <aside class="checkout-card">
                    <div class="checkout-total"><span><?= sm_text('Cart Total', 'কার্টের মোট'); ?></span><strong>৳<?= number_format($total, 2); ?></strong></div>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="action" value="book">
                        <label for="event_date"><?= sm_text('Event date', 'অনুষ্ঠানের তারিখ'); ?></label>
                        <input id="event_date" type="date" name="event_date" min="<?= date('Y-m-d'); ?>" required>
                        <label for="special_instruction"><?= sm_text('Special instruction', 'বিশেষ নির্দেশনা'); ?> <span><?= sm_text('Optional', 'ঐচ্ছিক'); ?></span></label>
                        <textarea id="special_instruction" name="special_instruction" rows="4" maxlength="1000" placeholder="<?= sm_text('Tell the manager anything important about your booking...', 'আপনার বুকিং সম্পর্কে ম্যানেজারকে প্রয়োজনীয় কিছু জানাতে লিখুন...'); ?>"></textarea>
                        <button class="confirm-book-btn" type="submit"><i class="fa-solid fa-calendar-check"></i> <?= sm_text('Request Booking', 'বুকিংয়ের অনুরোধ করুন'); ?></button>
                    </form>
                    <p class="checkout-note"><i class="fa-solid fa-shield-heart"></i> <?= sm_text('Your request starts as', 'আপনার অনুরোধের প্রাথমিক অবস্থা'); ?> <strong><?= sm_text('Pending', 'অপেক্ষমাণ'); ?></strong><?= sm_text('. You can track its status from My Bookings.', '। আমার বুকিং পেজ থেকে এর অবস্থা দেখতে পারবেন।'); ?></p>
                </aside>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
