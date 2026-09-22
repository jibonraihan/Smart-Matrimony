<?php
$page_css = 'assets/css/dashboard.css';
require_once 'config/db.php';
require_once 'includes/functions.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$user_id = (int) $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT u.user_id, u.first_name, u.last_name, u.gender, u.email, u.mobile, u.role, up.photo FROM users u LEFT JOIN user_profiles up ON up.user_id=u.user_id WHERE u.user_id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$current_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$current_user) { session_destroy(); header('Location: login.php'); exit; }
$display_name = trim(($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? ''));
$display_name = $display_name !== '' ? $display_name : 'Member';
$own_profile_link = 'profile/view_profile.php?user_id=' . $user_id;

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
    $message_id=(int)($_POST['message_id']??0);
    $message_ids = isset($_POST['message_ids']) && is_array($_POST['message_ids']) ? array_values(array_unique(array_filter(array_map('intval', $_POST['message_ids']), fn($id) => $id > 0))) : [];
    $ok=false;
    if (!hash_equals($notification_csrf,$posted_csrf)) { $_SESSION['notification_flash']='Your session expired. Please refresh the page and try again.'; }
    elseif ($action==='mark_read' && $message_id>0) {
        $stmt_m=mysqli_prepare($conn,"UPDATE authenticator_messages SET status='Read', read_at=NOW() WHERE message_id=? AND user_id=? AND status='Unread'");
        if ($stmt_m) { mysqli_stmt_bind_param($stmt_m,'ii',$message_id,$user_id); $ok=mysqli_stmt_execute($stmt_m); mysqli_stmt_close($stmt_m); }
        $_SESSION['notification_flash']=$ok?'Notification marked as read.':'Unable to update the notification right now.';
    } elseif ($action==='delete_messages' && !empty($message_ids)) {
        mysqli_begin_transaction($conn);
        $delete_ok = true;
        $delete_stmt = mysqli_prepare($conn, "DELETE FROM authenticator_messages WHERE user_id=? AND message_id=?");
        if (!$delete_stmt) {
            $delete_ok = false;
        } else {
            foreach ($message_ids as $delete_id) {
                mysqli_stmt_bind_param($delete_stmt, 'ii', $user_id, $delete_id);
                if (!mysqli_stmt_execute($delete_stmt)) { $delete_ok = false; break; }
            }
            mysqli_stmt_close($delete_stmt);
        }
        if ($delete_ok) { mysqli_commit($conn); } else { mysqli_rollback($conn); }
        $ok = $delete_ok;
        $_SESSION['notification_flash']=$ok ? (count($message_ids) === 1 ? 'Notification deleted.' : count($message_ids).' notifications deleted.') : 'Unable to delete the selected notifications right now.';
    } elseif ($action==='mark_all_read') {
        $stmt_m=mysqli_prepare($conn,"UPDATE authenticator_messages SET status='Read', read_at=NOW() WHERE user_id=? AND status='Unread'");
        if ($stmt_m) { mysqli_stmt_bind_param($stmt_m,'i',$user_id); $ok=mysqli_stmt_execute($stmt_m); mysqli_stmt_close($stmt_m); }
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
include 'includes/header.php';
?>
<link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/notifications.css?v=1">
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
                <a href="<?= BASE_URL; ?>dashboard.php" class="notifications-back-button"><i class="fa-solid fa-arrow-left"></i><span>Back to Dashboard</span></a>
                <div class="notifications-hero">
                    <div class="notifications-hero-icon"><i class="fa-solid fa-bell"></i></div>
                    <div>
                        <span class="section-kicker">NOTIFICATION CENTER</span>
                        <h1>Notifications</h1>
                        <p>Messages and important notices from the Smart Matrimony team.</p>
                    </div>
                    <div class="notifications-hero-count">
                        <?php if ($authenticator_unread_count > 0): ?>
                            <strong><?= number_format($authenticator_unread_count); ?></strong><span>unread</span>
                        <?php else: ?>
                            <strong>0</strong><span>unread</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="notifications-toolbar">
                    <div class="notifications-filter-tabs" role="tablist" aria-label="Notification sources">
                        <button type="button" class="notification-filter is-active" data-notification-filter="all">All</button>
                        <button type="button" class="notification-filter" data-notification-filter="authenticator">Authenticator</button>
                    </div>
                    <div class="notifications-toolbar-actions">
                        <button type="button" class="notification-select-all" id="notification-select-all"><i class="fa-regular fa-square"></i> Select all</button>
                        <form method="post" class="notifications-inline-form" id="notification-delete-form">
                            <input type="hidden" name="notification_csrf" value="<?= htmlspecialchars($notification_csrf); ?>">
                            <input type="hidden" name="notification_action" value="delete_messages">
                            <div id="notification-delete-inputs"></div>
                            <button type="submit" class="notification-delete-selected" id="notification-delete-selected" disabled><i class="fa-regular fa-trash-can"></i> Delete selected</button>
                        </form>
                    <?php if ($authenticator_unread_count > 0): ?>
                        <form method="post" class="notifications-inline-form">
                            <input type="hidden" name="notification_csrf" value="<?= htmlspecialchars($notification_csrf); ?>">
                            <input type="hidden" name="notification_action" value="mark_all_read">
                            <button type="submit" class="notification-mark-all"><i class="fa-solid fa-check-double"></i> Mark all as read</button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ($notification_flash !== ''): ?>
                    <div class="notification-flash" role="status"><i class="fa-solid fa-circle-info"></i><span><?= htmlspecialchars($notification_flash); ?></span></div>
                <?php endif; ?>

                <section class="notification-source-section" data-notification-source="authenticator" aria-labelledby="authenticatorNotificationsTitle">
                    <div class="notification-source-heading">
                        <div>
                            <span class="notification-source-icon"><i class="fa-solid fa-user-check"></i></span>
                            <div><span class="section-kicker">VERIFICATION TEAM</span><h2 id="authenticatorNotificationsTitle">Authenticator</h2><p>Private messages and profile-verification notices sent by an Authenticator.</p></div>
                        </div>
                        <?php if ($authenticator_unread_count > 0): ?><span class="notification-source-badge"><?= number_format($authenticator_unread_count); ?> unread</span><?php endif; ?>
                    </div>

                    <?php if (!$authenticator_messages): ?>
                        <div class="notification-empty"><div class="notification-empty-icon"><i class="fa-regular fa-bell-slash"></i></div><div><strong>No notifications yet</strong><p>Important messages from an Authenticator will appear here when they are sent to you.</p></div></div>
                    <?php else: ?>
                        <div class="notification-list">
                            <?php foreach ($authenticator_messages as $auth_message): $message_unread = ($auth_message['status'] ?? '') === 'Unread'; $sender_name = trim(($auth_message['sender_first_name'] ?? '') . ' ' . ($auth_message['sender_last_name'] ?? '')) ?: 'Authenticator'; ?>
                                <article class="notification-card <?= $message_unread ? 'is-unread' : ''; ?>" data-notification-card="authenticator">
                                    <label class="notification-select-box" title="Select notification"><input type="checkbox" class="notification-checkbox" value="<?= (int) $auth_message['message_id']; ?>" data-notification-checkbox><span></span></label>
                                    <div class="notification-card-icon"><i class="fa-solid <?= $message_unread ? 'fa-envelope' : 'fa-envelope-open'; ?>"></i></div>
                                    <div class="notification-card-content">
                                        <div class="notification-card-topline"><div><strong><?= htmlspecialchars($sender_name); ?></strong><?php if ($message_unread): ?><span class="notification-new-pill">NEW</span><?php endif; ?></div><time><?= htmlspecialchars(date('d M Y, h:i A', strtotime($auth_message['created_at']))); ?></time></div>
                                        <p><?= nl2br(htmlspecialchars($auth_message['message'])); ?></p>
                                        <div class="notification-card-actions">
                                            <?php if ($message_unread): ?>
                                                <form method="post" class="notifications-inline-form"><input type="hidden" name="notification_csrf" value="<?= htmlspecialchars($notification_csrf); ?>"><input type="hidden" name="notification_action" value="mark_read"><input type="hidden" name="message_id" value="<?= (int) $auth_message['message_id']; ?>"><button type="submit" class="notification-mark-read"><i class="fa-solid fa-check"></i> Mark as read</button></form>
                                            <?php else: ?>
                                                <span class="notification-read-label"><i class="fa-solid fa-check-double"></i> Read</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
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
    const getCheckboxes = () => Array.from(document.querySelectorAll('[data-notification-checkbox]'));
    function syncSelection(){
        const boxes=getCheckboxes();
        const selected=boxes.filter(b=>b.checked);
        if(deleteButton) deleteButton.disabled=selected.length===0;
        if(selectAll){
            selectAll.disabled=boxes.length===0;
            selectAll.innerHTML = selected.length===boxes.length && boxes.length ? '<i class=\"fa-solid fa-check-double\"></i> Deselect all' : '<i class=\"fa-regular fa-square-check\"></i> Select all';
        }
    }
    document.addEventListener('change',function(e){ if(e.target.matches('[data-notification-checkbox]')) syncSelection(); });
    if(selectAll){ selectAll.addEventListener('click',function(){ const boxes=getCheckboxes(); const shouldCheck=boxes.some(b=>!b.checked); boxes.forEach(b=>b.checked=shouldCheck); syncSelection(); }); }
    if(deleteForm){ deleteForm.addEventListener('submit',function(e){
        const selected=getCheckboxes().filter(b=>b.checked);
        if(!selected.length){ e.preventDefault(); return; }
        deleteInputs.innerHTML='';
        selected.forEach(b=>{ const input=document.createElement('input'); input.type='hidden'; input.name='message_ids[]'; input.value=b.value; deleteInputs.appendChild(input); });
        if(!window.confirm(selected.length===1 ? 'Delete this notification?' : 'Delete the selected notifications?')) e.preventDefault();
    }); }
    syncSelection();
})();
</script>
<?php include 'includes/footer.php'; ?>
