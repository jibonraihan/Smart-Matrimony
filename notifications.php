<?php
require_once 'includes/language.php';
$page_css = 'assets/css/dashboard.css';
require_once 'config/db.php';
require_once 'includes/functions.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = (int) $_SESSION['user_id'];

// Each account controls its own notification visibility.
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS user_notification_preferences (
    user_id INT(11) NOT NULL,
    notifications_enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_user_notification_preferences_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$notifications_enabled = true;
$notification_preference_stmt = mysqli_prepare($conn, 'SELECT notifications_enabled FROM user_notification_preferences WHERE user_id = ? LIMIT 1');
if ($notification_preference_stmt) {
    mysqli_stmt_bind_param($notification_preference_stmt, 'i', $user_id);
    mysqli_stmt_execute($notification_preference_stmt);
    $notification_preference_row = mysqli_fetch_assoc(mysqli_stmt_get_result($notification_preference_stmt));
    if ($notification_preference_row !== null) {
        $notifications_enabled = ((int) ($notification_preference_row['notifications_enabled'] ?? 1)) === 1;
    }
    mysqli_stmt_close($notification_preference_stmt);
}

$stmt = mysqli_prepare($conn, "SELECT u.user_id, u.first_name, u.last_name, u.gender, u.email, u.mobile, u.role, up.photo FROM users u LEFT JOIN user_profiles up ON up.user_id=u.user_id WHERE u.user_id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$current_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$current_user) { session_destroy(); header('Location: login.php'); exit; }
$display_name = trim(($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? ''));
$display_name = $display_name !== '' ? $display_name : 'Member';
$own_profile_link = 'profile/view_profile.php?user_id=' . $user_id;

// Keep notification interface feedback in the currently selected language.
function notification_localized_message(string $message): string {
    if (($GLOBALS['current_language'] ?? 'bn') === 'en') return $message;
    $translations = [
        'Your notifications are currently OFF. You can turn them back on in Settings.' => 'আপনার নোটিফিকেশন বর্তমানে বন্ধ। সেটিংস থেকে আবার চালু করতে পারবেন।',
        'Your session expired. Please refresh the page and try again.' => 'আপনার সেশনের মেয়াদ শেষ হয়েছে। পৃষ্ঠাটি রিফ্রেশ করে আবার চেষ্টা করুন।',
        'Notification marked as read.' => 'নোটিফিকেশনটি পঠিত হিসেবে চিহ্নিত করা হয়েছে।',
        'Unable to update the notification right now.' => 'এই মুহূর্তে নোটিফিকেশনটি হালনাগাদ করা যাচ্ছে না।',
        'Notification deleted.' => 'নোটিফিকেশন মুছে ফেলা হয়েছে।',
        'Unable to delete the selected notifications right now.' => 'নির্বাচিত নোটিফিকেশনগুলো এই মুহূর্তে মুছে ফেলা যাচ্ছে না।',
        'All notifications marked as read.' => 'সব নোটিফিকেশন পঠিত হিসেবে চিহ্নিত করা হয়েছে।',
        'Unable to update the notifications right now.' => 'এই মুহূর্তে নোটিফিকেশনগুলো হালনাগাদ করা যাচ্ছে না।',
        'This notification action is not available.' => 'এই নোটিফিকেশন কাজটি করা যাচ্ছে না.',
    ];
    if (isset($translations[$message])) return $translations[$message];
    if (preg_match('/^(\d+) notifications deleted\.$/', $message, $matches)) return $matches[1] . 'টি নোটিফিকেশন মুছে ফেলা হয়েছে।';
    return $message;
}
function notification_date(string $value): string {
    $timestamp = strtotime($value);
    if (!$timestamp) return $value;
    $formatted = date('d M Y, h:i A', $timestamp);
    if (($GLOBALS['current_language'] ?? 'bn') === 'bn') {
        $months = ['Jan'=>'জানু','Feb'=>'ফেব্রু','Mar'=>'মার্চ','Apr'=>'এপ্রি','May'=>'মে','Jun'=>'জুন','Jul'=>'জুল','Aug'=>'আগ','Sep'=>'সেপ্টে','Oct'=>'অক্টো','Nov'=>'নভে','Dec'=>'ডিসে'];
        $formatted = strtr($formatted, $months);
        $formatted = str_replace([' AM',' PM'], [' পূর্বাহ্ণ',' অপরাহ্ণ'], $formatted);
    }
    return $formatted;
}

$cart_count = 0;
$cart_stmt = mysqli_prepare($conn, 'SELECT COALESCE(SUM(quantity),0) AS total FROM service_cart_items WHERE user_id = ?');
if ($cart_stmt) { mysqli_stmt_bind_param($cart_stmt, 'i', $user_id); mysqli_stmt_execute($cart_stmt); $row=mysqli_fetch_assoc(mysqli_stmt_get_result($cart_stmt)); $cart_count=(int)($row['total']??0); mysqli_stmt_close($cart_stmt); }
$booking_count = 0;
$booking_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM bookings WHERE user_id = ? AND booking_status <> 'Cancelled'");
if ($booking_stmt) { mysqli_stmt_bind_param($booking_stmt, 'i', $user_id); mysqli_stmt_execute($booking_stmt); $row=mysqli_fetch_assoc(mysqli_stmt_get_result($booking_stmt)); $booking_count=(int)($row['total']??0); mysqli_stmt_close($booking_stmt); }

if (empty($_SESSION['notification_csrf'])) { $_SESSION['notification_csrf'] = bin2hex(random_bytes(32)); }
$notification_csrf = $_SESSION['notification_csrf'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_action'])) {
    $posted_csrf=(string)($_POST['notification_csrf']??'');
    $action=(string)($_POST['notification_action']??'');
    if (!$notifications_enabled) {
        $_SESSION['notification_flash']='Your notifications are currently OFF. You can turn them back on in Settings.';
        header('Location: '.BASE_URL.'notifications.php'); exit;
    }
    $message_id=(int)($_POST['message_id']??0);
    $message_ids = isset($_POST['message_ids']) && is_array($_POST['message_ids']) ? array_values(array_unique(array_filter(array_map('strval', $_POST['message_ids']), static fn($id) => preg_match('/^(admin|authenticator):\d+$/', $id) === 1))) : [];
    $ok=false;
    if (!hash_equals($notification_csrf,$posted_csrf)) { $_SESSION['notification_flash']='Your session expired. Please refresh the page and try again.'; }
    elseif ($action==='mark_read' && $message_id>0) {
        $source=(string)($_POST['message_source']??'authenticator');
        $table=$source==='admin'?'admin_messages':'authenticator_messages';
        $sender_condition=$source==='admin'?" AND admin_id IN (SELECT user_id FROM users WHERE role='Admin')":" AND authenticator_id IN (SELECT user_id FROM users WHERE role='Authenticator')";
        $stmt_m=mysqli_prepare($conn,"UPDATE {$table} SET status='Read', read_at=NOW() WHERE message_id=? AND user_id=? AND status='Unread'{$sender_condition}");
        if ($stmt_m) { mysqli_stmt_bind_param($stmt_m,'ii',$message_id,$user_id); $ok=mysqli_stmt_execute($stmt_m); mysqli_stmt_close($stmt_m); }
        $_SESSION['notification_flash']=$ok?'Notification marked as read.':'Unable to update the notification right now.';
    } elseif ($action==='delete_messages' && !empty($message_ids)) {
        mysqli_begin_transaction($conn);
        $delete_ok = true;
        foreach ($message_ids as $delete_value) {
            $parts=explode(':',(string)$delete_value,2); $source=$parts[0]??'authenticator'; $delete_id=(int)($parts[1]??$parts[0]??0);
            if($delete_id<=0){$delete_ok=false;break;}
            $table=$source==='admin'?'admin_messages':'authenticator_messages';
            $sender_condition=$source==='admin'?" AND admin_id IN (SELECT user_id FROM users WHERE role='Admin')":" AND authenticator_id IN (SELECT user_id FROM users WHERE role='Authenticator')";
            $delete_stmt=mysqli_prepare($conn,"DELETE FROM {$table} WHERE user_id=? AND message_id=?{$sender_condition}");
            if(!$delete_stmt){$delete_ok=false;break;}
            mysqli_stmt_bind_param($delete_stmt,'ii',$user_id,$delete_id);
            if(!mysqli_stmt_execute($delete_stmt)){mysqli_stmt_close($delete_stmt);$delete_ok=false;break;}
            mysqli_stmt_close($delete_stmt);
        }
        if ($delete_ok) { mysqli_commit($conn); } else { mysqli_rollback($conn); }
        $ok = $delete_ok;
        $_SESSION['notification_flash']=$ok ? (count($message_ids) === 1 ? 'Notification deleted.' : count($message_ids).' notifications deleted.') : 'Unable to delete the selected notifications right now.';
    } elseif ($action==='mark_all_read') {
        $ok=true;
        foreach(['authenticator_messages','admin_messages'] as $table){$stmt_m=mysqli_prepare($conn,"UPDATE {$table} SET status='Read', read_at=NOW() WHERE user_id=? AND status='Unread'");if(!$stmt_m){$ok=false;break;}mysqli_stmt_bind_param($stmt_m,'i',$user_id);if(!mysqli_stmt_execute($stmt_m))$ok=false;mysqli_stmt_close($stmt_m);}
        $_SESSION['notification_flash']=$ok?'All notifications marked as read.':'Unable to update the notifications right now.';
    } else { $_SESSION['notification_flash']='This notification action is not available.'; }
    header('Location: '.BASE_URL.'notifications.php'); exit;
}
$notification_flash=$_SESSION['notification_flash']??''; unset($_SESSION['notification_flash']);

$authenticator_messages=[];
$authenticator_unread_count=0;
$stmt_c=mysqli_prepare($conn,"SELECT COUNT(*) AS total FROM authenticator_messages WHERE user_id=? AND status='Unread'");
if ($stmt_c) { mysqli_stmt_bind_param($stmt_c,'i',$user_id); mysqli_stmt_execute($stmt_c); $row=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_c)); $authenticator_unread_count=(int)($row['total']??0); mysqli_stmt_close($stmt_c); }
$stmt_l=mysqli_prepare($conn,"SELECT am.message_id, am.message, am.status, am.created_at, am.read_at, u.first_name AS sender_first_name, u.last_name AS sender_last_name FROM authenticator_messages am INNER JOIN users u ON u.user_id=am.authenticator_id WHERE am.user_id=? AND u.role='Authenticator' ORDER BY am.created_at DESC, am.message_id DESC");
if ($stmt_l) { mysqli_stmt_bind_param($stmt_l,'i',$user_id); mysqli_stmt_execute($stmt_l); $res=mysqli_stmt_get_result($stmt_l); while($row=mysqli_fetch_assoc($res)) $authenticator_messages[]=$row; mysqli_stmt_close($stmt_l); }
$admin_messages=[]; $admin_unread_count=0;
$stmt_ac=mysqli_prepare($conn,"SELECT COUNT(*) AS total FROM admin_messages am INNER JOIN users a ON a.user_id=am.admin_id WHERE am.user_id=? AND am.status='Unread' AND a.role='Admin'");
if($stmt_ac){mysqli_stmt_bind_param($stmt_ac,'i',$user_id);mysqli_stmt_execute($stmt_ac);$row=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_ac));$admin_unread_count=(int)($row['total']??0);mysqli_stmt_close($stmt_ac);}
$stmt_al=mysqli_prepare($conn,"SELECT am.message_id, am.message, am.status, am.created_at, am.read_at, a.first_name AS sender_first_name, a.last_name AS sender_last_name FROM admin_messages am INNER JOIN users a ON a.user_id=am.admin_id WHERE am.user_id=? AND a.role='Admin' ORDER BY am.created_at DESC, am.message_id DESC");
if($stmt_al){mysqli_stmt_bind_param($stmt_al,'i',$user_id);mysqli_stmt_execute($stmt_al);$res=mysqli_stmt_get_result($stmt_al);while($row=mysqli_fetch_assoc($res))$admin_messages[]=$row;mysqli_stmt_close($stmt_al);}
$total_unread_count=$notifications_enabled ? ($authenticator_unread_count+$admin_unread_count) : 0;
include 'includes/header.php';
?>
<link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/notifications.css?v=2">
<div class="dashboard-page">

    <!-- ===================== TOP BAR ===================== -->
    <header class="dashboard-topbar">
        <div class="dashboard-container topbar-inner">

            <a href="<?= BASE_URL; ?>dashboard.php" class="dashboard-brand" aria-label="Smart Matrimony Dashboard">
                <span class="dashboard-brand-logo">
                    <img src="<?= BASE_URL; ?>assets/images/logo/logo.png" alt="Smart Matrimony Logo">
                </span>
                <span class="dashboard-brand-title">
                    <img src="<?= BASE_URL; ?>assets/images/logo/matrimony_title.png" alt="Smart Matrimony">
                </span>
            </a>

            <div class="topbar-actions notifications-topbar-empty" aria-hidden="true"></div>
        </div>
    </header>

    <main>
        <section class="notifications-page-section">
            <div class="dashboard-container notifications-content-container">
                <a href="<?= BASE_URL; ?>dashboard.php" class="notifications-back-button"><i class="fa-solid fa-arrow-left"></i><span><?= sm_text('Back to Dashboard', 'ড্যাশবোর্ডে ফিরুন') ?></span></a>
                <div class="notifications-hero">
                    <div class="notifications-hero-icon"><i class="fa-solid fa-bell"></i></div>
                    <div>
                        <span class="section-kicker"><?= sm_text('NOTIFICATION CENTER', 'নোটিফিকেশন কেন্দ্র') ?></span>
                        <h1><?= sm_text('Notifications', 'নোটিফিকেশন') ?></h1>
                        <p><?= sm_text('Messages and important notices from the Smart Matrimony team.', 'স্মার্ট ম্যাট্রিমনি টিমের বার্তা ও গুরুত্বপূর্ণ বিজ্ঞপ্তি।') ?></p>
                    </div>
                    <div class="notifications-hero-count">
                        <?php if ($total_unread_count > 0): ?>
                            <strong><?= number_format($total_unread_count); ?></strong><span><?= sm_text('unread', 'অপঠিত') ?></span>
                        <?php else: ?>
                            <strong>0</strong><span><?= sm_text('unread', 'অপঠিত') ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($notifications_enabled): ?>
                <div class="notifications-toolbar">
                    <div class="notifications-filter-tabs" role="tablist" aria-label="<?= sm_text('Notification sources', 'নোটিফিকেশনের ধরন') ?>">
                        <button type="button" class="notification-filter is-active" data-notification-filter="all"><?= sm_text('All', 'সব') ?></button>
                        <button type="button" class="notification-filter" data-notification-filter="authenticator"><?= sm_text('Authenticator', 'ভেরিফিকেশন কর্মকর্তা') ?></button>
                        <button type="button" class="notification-filter" data-notification-filter="admin"><?= sm_text('Admin', 'অ্যাডমিন') ?></button>
                    </div>
                    <div class="notifications-toolbar-actions">
                        <button type="button" class="notification-select-all" id="notification-select-all"><i class="fa-regular fa-square"></i> <?= sm_text('Select all', 'সব নির্বাচন করুন') ?></button>
                        <form method="post" class="notifications-inline-form" id="notification-delete-form">
                            <input type="hidden" name="notification_csrf" value="<?= htmlspecialchars($notification_csrf); ?>">
                            <input type="hidden" name="notification_action" value="delete_messages">
                            <div id="notification-delete-inputs"></div>
                            <button type="submit" class="notification-delete-selected" id="notification-delete-selected" disabled><i class="fa-regular fa-trash-can"></i> <?= sm_text('Delete selected', 'নির্বাচিতগুলো মুছুন') ?></button>
                        </form>
                    <?php if ($total_unread_count > 0): ?>
                        <form method="post" class="notifications-inline-form">
                            <input type="hidden" name="notification_csrf" value="<?= htmlspecialchars($notification_csrf); ?>">
                            <input type="hidden" name="notification_action" value="mark_all_read">
                            <button type="submit" class="notification-mark-all"><i class="fa-solid fa-check-double"></i> <?= sm_text('Mark all as read', 'সব পঠিত হিসেবে চিহ্নিত করুন') ?></button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ($notification_flash !== ''): ?>
                    <div class="notification-flash" role="status"><i class="fa-solid fa-circle-info"></i><span><?= htmlspecialchars(notification_localized_message($notification_flash), ENT_QUOTES, 'UTF-8'); ?></span></div>
                <?php endif; ?>

                <section class="notification-source-section" data-notification-source="authenticator" aria-labelledby="authenticatorNotificationsTitle">
                    <div class="notification-source-heading">
                        <div>
                            <span class="notification-source-icon"><i class="fa-solid fa-user-check"></i></span>
                            <div><span class="section-kicker"><?= sm_text('VERIFICATION TEAM', 'ভেরিফিকেশন টিম') ?></span><h2 id="authenticatorNotificationsTitle"><?= sm_text('Authenticator', 'ভেরিফিকেশন কর্মকর্তা') ?></h2><p><?= sm_text('Private messages and profile-verification notices sent by an Authenticator.', 'ভেরিফিকেশন কর্মকর্তার পাঠানো ব্যক্তিগত বার্তা ও প্রোফাইল যাচাইয়ের বিজ্ঞপ্তি।') ?></p></div>
                        </div>
                        <?php if ($authenticator_unread_count > 0): ?><span class="notification-source-badge"><?= number_format($authenticator_unread_count); ?> <?= sm_text('unread', 'অপঠিত') ?></span><?php endif; ?>
                    </div>

                    <?php if (!$authenticator_messages): ?>
                        <div class="notification-empty"><div class="notification-empty-icon"><i class="fa-regular fa-bell-slash"></i></div><div><strong><?= sm_text('No notifications yet', 'এখনো কোনো নোটিফিকেশন নেই') ?></strong><p><?= sm_text('Important messages from an Authenticator will appear here when they are sent to you.', 'ভেরিফিকেশন কর্মকর্তা আপনাকে বার্তা পাঠালে এখানে দেখা যাবে।') ?></p></div></div>
                    <?php else: ?>
                        <div class="notification-list">
                            <?php foreach ($authenticator_messages as $auth_message): $message_unread = ($auth_message['status'] ?? '') === 'Unread'; $sender_name = trim(($auth_message['sender_first_name'] ?? '') . ' ' . ($auth_message['sender_last_name'] ?? '')) ?: sm_text('Authenticator', 'ভেরিফিকেশন কর্মকর্তা'); ?>
                                <article class="notification-card <?= $message_unread ? 'is-unread' : ''; ?>" data-notification-card="authenticator">
                                    <label class="notification-select-box" title="<?= sm_text('Select notification', 'নোটিফিকেশন নির্বাচন করুন') ?>"><input type="checkbox" class="notification-checkbox" value="authenticator:<?= (int) $auth_message['message_id']; ?>" data-notification-checkbox><span></span></label>
                                    <div class="notification-card-icon"><i class="fa-solid <?= $message_unread ? 'fa-envelope' : 'fa-envelope-open'; ?>"></i></div>
                                    <div class="notification-card-content">
                                        <div class="notification-card-topline"><div><strong><?= htmlspecialchars($sender_name); ?></strong><?php if ($message_unread): ?><span class="notification-new-pill"><?= sm_text('NEW', 'নতুন') ?></span><?php endif; ?></div><time><?= htmlspecialchars(notification_date((string)$auth_message['created_at']), ENT_QUOTES, 'UTF-8'); ?></time></div>
                                        <p><?= nl2br(htmlspecialchars($auth_message['message'])); ?></p>
                                        <div class="notification-card-actions">
                                            <?php if ($message_unread): ?>
                                                <form method="post" class="notifications-inline-form"><input type="hidden" name="notification_csrf" value="<?= htmlspecialchars($notification_csrf); ?>"><input type="hidden" name="notification_action" value="mark_read"><input type="hidden" name="message_id" value="<?= (int) $auth_message['message_id']; ?>"><input type="hidden" name="message_source" value="authenticator"><button type="submit" class="notification-mark-read"><i class="fa-solid fa-check"></i> <?= sm_text('Mark as read', 'পঠিত হিসেবে চিহ্নিত করুন') ?></button></form>
                                            <?php else: ?>
                                                <span class="notification-read-label"><i class="fa-solid fa-check-double"></i> <?= sm_text('Read', 'পঠিত') ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="notification-source-section" data-notification-source="admin" aria-labelledby="adminNotificationsTitle">
                    <div class="notification-source-heading">
                        <div><span class="notification-source-icon"><i class="fa-solid fa-shield-halved"></i></span><div><span class="section-kicker"><?= sm_text('ADMINISTRATION', 'প্রশাসন') ?></span><h2 id="adminNotificationsTitle"><?= sm_text('Admin', 'অ্যাডমিন') ?></h2><p><?= sm_text('Private messages and important account notices sent by the Smart Matrimony Admin team.', 'স্মার্ট ম্যাট্রিমনি অ্যাডমিন টিমের ব্যক্তিগত বার্তা ও গুরুত্বপূর্ণ অ্যাকাউন্ট বিজ্ঞপ্তি।') ?></p></div></div>
                        <?php if ($admin_unread_count > 0): ?><span class="notification-source-badge"><?= number_format($admin_unread_count); ?> <?= sm_text('unread', 'অপঠিত') ?></span><?php endif; ?>
                    </div>
                    <?php if (!$admin_messages): ?>
                        <div class="notification-empty"><div class="notification-empty-icon"><i class="fa-regular fa-bell-slash"></i></div><div><strong><?= sm_text('No Admin messages yet', 'এখনো কোনো অ্যাডমিন বার্তা নেই') ?></strong><p><?= sm_text('Private messages from the Admin team will appear here when they are sent to you.', 'অ্যাডমিন টিম আপনাকে বার্তা পাঠালে এখানে দেখা যাবে।') ?></p></div></div>
                    <?php else: ?>
                        <div class="notification-list">
                            <?php foreach ($admin_messages as $admin_message): $message_unread=($admin_message['status']??'')==='Unread'; $sender_name=trim(($admin_message['sender_first_name']??'').' '.($admin_message['sender_last_name']??''))?:sm_text('Admin', 'অ্যাডমিন'); $selection_value='admin:'.(int)$admin_message['message_id']; ?>
                                <article class="notification-card <?= $message_unread?'is-unread':''; ?>" data-notification-card="admin">
                                    <label class="notification-select-box" title="<?= sm_text('Select notification', 'নোটিফিকেশন নির্বাচন করুন') ?>"><input type="checkbox" class="notification-checkbox" value="<?= htmlspecialchars($selection_value); ?>" data-notification-checkbox><span></span></label>
                                    <div class="notification-card-icon"><i class="fa-solid <?= $message_unread?'fa-envelope':'fa-envelope-open'; ?>"></i></div>
                                    <div class="notification-card-content"><div class="notification-card-topline"><div><strong><?= htmlspecialchars($sender_name); ?></strong><?php if($message_unread): ?><span class="notification-new-pill"><?= sm_text('NEW', 'নতুন') ?></span><?php endif; ?></div><time><?= htmlspecialchars(notification_date((string)$admin_message['created_at']), ENT_QUOTES, 'UTF-8'); ?></time></div><p><?= nl2br(htmlspecialchars($admin_message['message'])); ?></p><div class="notification-card-actions"><?php if($message_unread): ?><form method="post" class="notifications-inline-form"><input type="hidden" name="notification_csrf" value="<?= htmlspecialchars($notification_csrf); ?>"><input type="hidden" name="notification_action" value="mark_read"><input type="hidden" name="message_id" value="<?= (int)$admin_message['message_id']; ?>"><input type="hidden" name="message_source" value="admin"><button type="submit" class="notification-mark-read"><i class="fa-solid fa-check"></i> <?= sm_text('Mark as read', 'পঠিত হিসেবে চিহ্নিত করুন') ?></button></form><?php else: ?><span class="notification-read-label"><i class="fa-solid fa-check-double"></i> <?= sm_text('Read', 'পঠিত') ?></span><?php endif; ?></div></div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
                <?php else: ?>
                    <div class="notifications-disabled-notice" role="status">
                        <div class="notifications-disabled-icon"><i class="fa-solid fa-bell-slash"></i></div>
                        <div>
                            <strong><?= sm_text('Notifications are currently OFF', 'নোটিফিকেশন বর্তমানে বন্ধ') ?></strong>
                            <p><?= sm_text('The notification system is disabled for everyone right now. Existing notification records are kept and will be available again when notifications are turned ON.', 'এই মুহূর্তে সবার জন্য নোটিফিকেশন ব্যবস্থা বন্ধ। আগের নোটিফিকেশন সংরক্ষিত আছে এবং ব্যবস্থা চালু হলে আবার দেখা যাবে।') ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
<script>
(function(){
    const selectAll = document.getElementById('notification-select-all');
    const deleteButton = document.getElementById('notification-delete-selected');
    const deleteForm = document.getElementById('notification-delete-form');
    const deleteInputs = document.getElementById('notification-delete-inputs');
    const filterButtons = Array.from(document.querySelectorAll('[data-notification-filter]'));
    const sourceSections = Array.from(document.querySelectorAll('[data-notification-source]'));
    const getCheckboxes = () => Array.from(document.querySelectorAll('[data-notification-checkbox]'));

    // Filter notification sources without changing or removing their data.
    filterButtons.forEach(button => {
        button.addEventListener('click', function(){
            const filter = this.getAttribute('data-notification-filter') || 'all';
            filterButtons.forEach(tab => {
                const active = tab === this;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            sourceSections.forEach(section => {
                const show = filter === 'all' || section.getAttribute('data-notification-source') === filter;
                section.hidden = !show;
                // Enforce filtering even if site CSS overrides the browser's [hidden] rule.
                section.style.setProperty('display', show ? '' : 'none', 'important');
            });
        });
    });

    function syncSelection(){
        const boxes=getCheckboxes();
        const selected=boxes.filter(b=>b.checked);
        if(deleteButton) deleteButton.disabled=selected.length===0;
        if(selectAll){
            selectAll.disabled=boxes.length===0;
            selectAll.innerHTML = selected.length===boxes.length && boxes.length ? '<i class=\"fa-solid fa-check-double\"></i> <?= sm_text('Deselect all', 'নির্বাচন বাতিল করুন') ?>' : '<i class=\"fa-regular fa-square-check\"></i> <?= sm_text('Select all', 'সব নির্বাচন করুন') ?>';
        }
    }
    document.addEventListener('change',function(e){ if(e.target.matches('[data-notification-checkbox]')) syncSelection(); });
    if(selectAll){ selectAll.addEventListener('click',function(){ const boxes=getCheckboxes(); const shouldCheck=boxes.some(b=>!b.checked); boxes.forEach(b=>b.checked=shouldCheck); syncSelection(); }); }
    if(deleteForm){ deleteForm.addEventListener('submit',function(e){
        const selected=getCheckboxes().filter(b=>b.checked);
        if(!selected.length){ e.preventDefault(); return; }
        deleteInputs.innerHTML='';
        selected.forEach(b=>{ const input=document.createElement('input'); input.type='hidden'; input.name='message_ids[]'; input.value=b.value; deleteInputs.appendChild(input); });
        if(!window.confirm(selected.length===1 ? <?= json_encode(sm_text('Delete this notification?', 'এই নোটিফিকেশনটি মুছে ফেলবেন?'), JSON_UNESCAPED_UNICODE) ?> : <?= json_encode(sm_text('Delete the selected notifications?', 'নির্বাচিত নোটিফিকেশনগুলো মুছে ফেলবেন?'), JSON_UNESCAPED_UNICODE) ?>)) e.preventDefault();
    }); }
    syncSelection();
})();
</script>
<?php include 'includes/footer.php'; ?>
