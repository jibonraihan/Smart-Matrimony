<?php

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
        $event_date = $_POST['event_date'] ?? '';
        $special_instruction = trim($_POST['special_instruction'] ?? '');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date) || $event_date < date('Y-m-d')) {
            header('Location: cart.php?error=date');
            exit;
        }

        mysqli_begin_transaction($conn);
        try {
            $items = [];
            $stmt = mysqli_prepare($conn, "SELECT ci.provider_id, ci.quantity, sp.price, sp.manager_id
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
                $total += ((float) $item['price'] * (int) $item['quantity']);
                if (!empty($item['manager_id'])) $manager_ids[(int) $item['manager_id']] = true;
            }
            $manager_id = count($manager_ids) === 1 ? (int) array_key_first($manager_ids) : null;

            if ($manager_id !== null) {
                $stmt = mysqli_prepare($conn, "INSERT INTO bookings (user_id, manager_id, total_price, booking_status) VALUES (?, ?, ?, 'Pending')");
                mysqli_stmt_bind_param($stmt, 'iid', $user_id, $manager_id, $total);
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO bookings (user_id, total_price, booking_status) VALUES (?, ?, 'Pending')");
                mysqli_stmt_bind_param($stmt, 'id', $user_id, $total);
            }
            mysqli_stmt_execute($stmt);
            $booking_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, 'INSERT INTO booking_details (booking_id, provider_id, event_date, special_instruction) VALUES (?, ?, ?, ?)');
            foreach ($items as $item) {
                $provider_id = (int) $item['provider_id'];
                mysqli_stmt_bind_param($stmt, 'iiss', $booking_id, $provider_id, $event_date, $special_instruction);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, 'DELETE FROM service_cart_items WHERE user_id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
            header('Location: cart.php?booked=' . $booking_id);
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
        sp.provider_id, sp.provider_name, sp.package_name, sp.price, sp.location, sp.image,
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
    $row['line_total'] = (float) $row['price'] * (int) $row['quantity'];
    $total += $row['line_total'];
    $items[] = $row;
}
mysqli_stmt_close($stmt);

include 'includes/header.php';
?>

<div class="service-page">
    <header class="service-topbar">
        <div class="service-container service-topbar-inner">
            <a href="<?= BASE_URL; ?>dashboard.php#wedding-services" class="service-back"><i class="fa-solid fa-arrow-left"></i> Wedding Services</a>
            <strong class="cart-title"><i class="fa-solid fa-cart-shopping"></i> My Cart</strong>
        </div>
    </header>

    <main class="service-container cart-page-main">
        <div class="service-section-heading">
            <div>
                <span class="service-kicker">YOUR SELECTION</span>
                <h1>Review & Book</h1>
                <p>Choose your event date, review selected packages and submit your booking request.</p>
            </div>
        </div>

        <?php if (isset($_GET['booked'])): ?>
            <div class="cart-alert success"><i class="fa-solid fa-circle-check"></i> Booking request #<?= (int) $_GET['booked']; ?> was created successfully.</div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="cart-alert error"><i class="fa-solid fa-circle-exclamation"></i> Please check your event date and try again.</div>
        <?php endif; ?>

        <?php if (!$items): ?>
            <div class="service-empty cart-empty">
                <div><i class="fa-solid fa-cart-shopping"></i></div>
                <h3>Your cart is empty</h3>
                <p>Add one or more wedding service packages before booking.</p>
                <a href="<?= BASE_URL; ?>dashboard.php#wedding-services">Explore Wedding Services</a>
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
                                <h3><?= htmlspecialchars($item['package_name'] ?: $item['provider_name']); ?></h3>
                                <p><?= htmlspecialchars($item['provider_name']); ?><?= !empty($item['location']) ? ' · ' . htmlspecialchars($item['location']) : ''; ?></p>
                            </div>
                            <div class="cart-item-price">৳<?= number_format((float) $item['price'], 2); ?></div>
                            <form method="post" class="cart-quantity-form">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="cart_item_id" value="<?= (int) $item['cart_item_id']; ?>">
                                <input type="number" name="quantity" min="1" max="20" value="<?= (int) $item['quantity']; ?>">
                                <button type="submit" title="Update quantity"><i class="fa-solid fa-rotate"></i></button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="cart_item_id" value="<?= (int) $item['cart_item_id']; ?>">
                                <button class="remove-cart-btn" type="submit" title="Remove"><i class="fa-solid fa-trash-can"></i></button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </section>

                <aside class="checkout-card">
                    <div class="checkout-total"><span>Cart Total</span><strong>৳<?= number_format($total, 2); ?></strong></div>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="action" value="book">
                        <label for="event_date">Event date</label>
                        <input id="event_date" type="date" name="event_date" min="<?= date('Y-m-d'); ?>" required>
                        <label for="special_instruction">Special instruction <span>Optional</span></label>
                        <textarea id="special_instruction" name="special_instruction" rows="4" maxlength="1000" placeholder="Tell the manager anything important about your booking...\"></textarea>
                        <button class="confirm-book-btn" type="submit"><i class="fa-solid fa-calendar-check"></i> Request Booking</button>
                    </form>
                    <p class="checkout-note"><i class="fa-solid fa-shield-heart"></i> Your booking starts as <strong>Pending</strong> and can be confirmed by the Event Manager.</p>
                </aside>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
