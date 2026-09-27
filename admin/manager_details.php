<?php
require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/manager_role_transition.php';
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function pid($id){ return 'SM-'.str_pad((string)(int)$id, 6, '0', STR_PAD_LEFT); }
function spid($id){ return 'SP-'.str_pad((string)(int)$id, 6, '0', STR_PAD_LEFT); }
function bkid($id){ return 'BK-'.str_pad((string)(int)$id, 6, '0', STR_PAD_LEFT); }

$uid = (int)($_GET['user_id'] ?? 0);
if ($uid <= 0 || $uid === $admin_id) { header('Location: managers.php'); exit; }

$message = '';
$error = '';
$created_replacement_id = 0;
$discount_sort = strtolower((string)($_GET['discount_sort'] ?? ''));
if (!in_array($discount_sort, ['asc','desc'], true)) $discount_sort = '';
$discount_order = $discount_sort === 'asc' ? 'ASC' : 'DESC';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create_replacement_manager') {
            $result = create_replacement_manager($conn, [
                'first_name' => $_POST['replacement_first_name'] ?? '',
                'last_name' => $_POST['replacement_last_name'] ?? '',
                'gender' => $_POST['replacement_gender'] ?? '',
                'mobile' => $_POST['replacement_mobile'] ?? '',
                'email' => $_POST['replacement_email'] ?? '',
                'password' => $_POST['replacement_password'] ?? ''
            ], $admin_id);
            if ($result['ok']) {
                $message = $result['message'];
                $created_replacement_id = (int)($result['user_id'] ?? 0);
            } else {
                $error = $result['message'];
            }
        } elseif ($action === 'update_account') {
            $role = $_POST['role'] ?? 'Manager';
            $status = $_POST['account_status'] ?? 'Active';
            $first_name = trim((string)($_POST['first_name'] ?? ''));
            $last_name = trim((string)($_POST['last_name'] ?? ''));
            $gender = $_POST['gender'] ?? '';
            $email = trim((string)($_POST['email'] ?? ''));
            $mobile = trim((string)($_POST['mobile'] ?? ''));
            $allowed_roles = ['User','Manager','Authenticator'];
            $allowed_status = ['Active','Inactive','Suspended'];
            if (!in_array($role, $allowed_roles, true) || !in_array($status, $allowed_status, true) || !in_array($gender, ['Male','Female'], true)) {
                $error = 'Invalid account control value.';
            } elseif ($first_name === '' || $last_name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $mobile === '') {
                $error = 'Please provide valid name, email and mobile information.';
            } else {
                $replacement_raw = $_POST['replacement_manager_id'] ?? '';
                $replacement = $replacement_raw !== '' ? (int)$replacement_raw : null;
                $profile_update = function(mysqli $db, int $account_id) use ($first_name, $last_name, $gender, $email, $mobile): void {
                    $check = mysqli_prepare($db, "SELECT user_id FROM users WHERE user_id<>? AND (email=? OR mobile=?) LIMIT 1");
                    mysqli_stmt_bind_param($check, 'iss', $account_id, $email, $mobile);
                    mysqli_stmt_execute($check);
                    $duplicate = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
                    mysqli_stmt_close($check);
                    if ($duplicate) throw new RuntimeException('That email or mobile number is already used by another account.');
                    $stmt = mysqli_prepare($db, "UPDATE users SET first_name=?, last_name=?, gender=?, email=?, mobile=? WHERE user_id=? AND role<>'Admin'");
                    mysqli_stmt_bind_param($stmt, 'sssssi', $first_name, $last_name, $gender, $email, $mobile, $account_id);
                    if (!mysqli_stmt_execute($stmt)) { $e=mysqli_stmt_error($stmt); mysqli_stmt_close($stmt); throw new RuntimeException($e ?: 'Unable to update profile information.'); }
                    mysqli_stmt_close($stmt);
                };
                $previous_role = '';
                $role_check = mysqli_prepare($conn, "SELECT role FROM users WHERE user_id=? LIMIT 1");
                if ($role_check) {
                    mysqli_stmt_bind_param($role_check, 'i', $uid);
                    mysqli_stmt_execute($role_check);
                    $role_row = mysqli_fetch_assoc(mysqli_stmt_get_result($role_check));
                    mysqli_stmt_close($role_check);
                    $previous_role = (string)($role_row['role'] ?? '');
                }

                $transition = transition_manager_role($conn, $uid, $role, $admin_id, $replacement, $status, $profile_update);
                if ($transition['ok']) {
                    $message = $transition['message'] ?: 'Manager account and profile information updated successfully.';
                } else {
                    $error = $transition['message'];
                }
            }
        } elseif ($action === 'reset_password') {
            $new_password = (string)($_POST['new_password'] ?? '');
            if (strlen($new_password) < 8) $error = 'New password must be at least 8 characters.';
            else {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "UPDATE users SET password=? WHERE user_id=? AND role<>'Admin'");
                mysqli_stmt_bind_param($stmt, 'si', $hash, $uid);
                if (mysqli_stmt_execute($stmt)) $message = 'Manager password reset successfully.'; else $error = 'Unable to reset the password right now.';
                mysqli_stmt_close($stmt);
            }
        } elseif ($action === 'delete_package') {
            $provider_id = (int)($_POST['provider_id'] ?? 0);
            if ($provider_id <= 0) {
                $error = 'Invalid package selected.';
            } else {
                $stmt = mysqli_prepare($conn, "DELETE FROM service_providers WHERE provider_id=? AND manager_id=?");
                mysqli_stmt_bind_param($stmt, 'ii', $provider_id, $uid);
                if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1) {
                    $message = 'Package removed successfully.';
                } else {
                    $error = 'Unable to remove the package right now.';
                }
                mysqli_stmt_close($stmt);
            }
        } elseif ($action === 'delete_completed_booking') {
            $booking_id = (int)($_POST['booking_id'] ?? 0);
            if ($booking_id <= 0) {
                $error = 'Invalid completed booking selected.';
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE bookings SET admin_removed=1, admin_removed_at=NOW(), admin_removed_by=? WHERE booking_id=? AND manager_id=? AND booking_status='Completed' AND admin_removed=0");
                mysqli_stmt_bind_param($stmt, 'iii', $admin_id, $booking_id, $uid);
                if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1) {
                    $message = 'Completed booking removed from the active booking list. Historical completion statistics are preserved.';
                } else {
                    $error = 'Unable to remove the completed booking right now.';
                }
                mysqli_stmt_close($stmt);
            }
        } elseif ($action === 'update_package') {
            $provider_id = (int)($_POST['provider_id'] ?? 0);
            $service_id = (int)($_POST['service_id'] ?? 0);
            $provider_name = trim((string)($_POST['provider_name'] ?? ''));
            $package_name = trim((string)($_POST['package_name'] ?? ''));
            $package_details = trim((string)($_POST['package_details'] ?? ''));
            $price = (float)($_POST['price'] ?? -1);
            $discount_percent = (float)($_POST['discount_percent'] ?? -1);
            $contact_number = trim((string)($_POST['contact_number'] ?? ''));
            $location = trim((string)($_POST['location'] ?? ''));
            $status = $_POST['status'] ?? '';
            if ($provider_id <= 0 || $service_id <= 0 || $provider_name === '' || $package_name === '' || $price < 0 || $discount_percent < 0 || $discount_percent > 100 || !in_array($status, ['Active','Inactive'], true)) $error = 'Please provide valid package information.';
            else {
                $stmt = mysqli_prepare($conn, "UPDATE service_providers SET service_id=?, provider_name=?, package_name=?, package_details=?, price=?, discount_percent=?, contact_number=?, location=?, status=? WHERE provider_id=? AND manager_id=?");
                mysqli_stmt_bind_param($stmt, 'isssddsssii', $service_id, $provider_name, $package_name, $package_details, $price, $discount_percent, $contact_number, $location, $status, $provider_id, $uid);
                if (mysqli_stmt_execute($stmt)) $message = 'Package updated successfully.'; else $error = 'Unable to update package.';
                mysqli_stmt_close($stmt);
            }
        } elseif ($action === 'update_booking') {
            $booking_id = (int)($_POST['booking_id'] ?? 0);
            $status = $_POST['booking_status'] ?? '';
            $cancellation_reason = trim((string)($_POST['cancellation_reason'] ?? ''));
            if ($booking_id <= 0 || !in_array($status, ['Pending','Confirmed','Completed','Cancelled'], true)) $error = 'Invalid booking update.';
            elseif ($status === 'Cancelled' && $cancellation_reason === '') $error = 'A cancellation reason is required when cancelling a booking.';
            else {
                if ($status === 'Cancelled') {
                    $stmt = mysqli_prepare($conn, "UPDATE bookings SET booking_status=?, cancellation_reason=?, cancelled_at=NOW(), cancelled_by='Admin', cancelled_by_manager_id=NULL WHERE booking_id=? AND manager_id=?");
                    mysqli_stmt_bind_param($stmt, 'ssii', $status, $cancellation_reason, $booking_id, $uid);
                } else {
                    $stmt = mysqli_prepare($conn, "UPDATE bookings SET booking_status=?, cancellation_reason=NULL, cancelled_at=NULL, cancelled_by=NULL, cancelled_by_manager_id=NULL WHERE booking_id=? AND manager_id=?");
                    mysqli_stmt_bind_param($stmt, 'sii', $status, $booking_id, $uid);
                }
                if (mysqli_stmt_execute($stmt)) $message = 'Booking status updated successfully.'; else $error = 'Unable to update booking status.';
                mysqli_stmt_close($stmt);
            }
        }
    }
}

$stmt = mysqli_prepare($conn, "SELECT u.user_id,u.first_name,u.last_name,u.gender,u.email,u.mobile,u.role,u.account_status,u.created_at,COALESCE(sp.profile_image,'') AS profile_image FROM users u LEFT JOIN staff_profiles sp ON sp.user_id=u.user_id WHERE u.user_id=? AND u.role='Manager' LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$manager = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$manager) { header('Location: managers.php'); exit; }

$services = [];
$service_result = mysqli_query($conn, "SELECT service_id, service_name FROM services ORDER BY service_name ASC");
if ($service_result) { while ($service_row = mysqli_fetch_assoc($service_result)) { $services[] = $service_row; } }

function count_for($conn, $sql, $uid) {
    $stmt=mysqli_prepare($conn,$sql); mysqli_stmt_bind_param($stmt,'i',$uid); mysqli_stmt_execute($stmt);
    $row=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt); return (int)($row['c']??0);
}
$package_total = count_for($conn, "SELECT COUNT(*) c FROM service_providers WHERE manager_id=?", $uid);
$active_packages = count_for($conn, "SELECT COUNT(*) c FROM service_providers WHERE manager_id=? AND status='Active'", $uid);
$inactive_packages = count_for($conn, "SELECT COUNT(*) c FROM service_providers WHERE manager_id=? AND status='Inactive'", $uid);
$total_bookings = count_for($conn, "SELECT COUNT(*) c FROM bookings WHERE manager_id=?", $uid);
$pending_bookings = count_for($conn, "SELECT COUNT(*) c FROM bookings WHERE manager_id=? AND booking_status='Pending'", $uid);
$confirmed_bookings = count_for($conn, "SELECT COUNT(*) c FROM bookings WHERE manager_id=? AND booking_status='Confirmed'", $uid);
$completed_bookings = count_for($conn, "SELECT COUNT(*) c FROM bookings WHERE manager_id=? AND booking_status='Completed'", $uid);
$cancelled_bookings = count_for($conn, "SELECT COUNT(*) c FROM bookings WHERE manager_id=? AND booking_status='Cancelled'", $uid);
$available_replacements = manager_available_replacements($conn, $uid);

$stmt=mysqli_prepare($conn,"SELECT COALESCE(SUM(total_price),0) total FROM bookings WHERE manager_id=? AND booking_status<>'Cancelled'");
mysqli_stmt_bind_param($stmt,'i',$uid); mysqli_stmt_execute($stmt); $total_value=(float)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total']??0); mysqli_stmt_close($stmt);

$profile_file = basename((string)($manager['profile_image'] ?? ''));
$profile_url = $profile_file !== '' ? '../uploads/staff/'.rawurlencode($profile_file) : '';
$has_profile_image = $profile_file !== '' && is_file(__DIR__.'/../uploads/staff/'.$profile_file);
$manager_name = trim($manager['first_name'].' '.$manager['last_name']);
$initial = strtoupper(substr(trim((string)$manager['first_name']),0,1)) ?: 'M';
$staff_key = 'Manager:'.$uid;

$packages=[];
$stmt=mysqli_prepare($conn,"SELECT sp.provider_id,sp.service_id,sp.package_name,sp.package_details,sp.provider_name,sp.price,sp.discount_percent,sp.contact_number,sp.location,sp.rating,sp.review_count,sp.status,s.service_name FROM service_providers sp LEFT JOIN services s ON s.service_id=sp.service_id WHERE sp.manager_id=? ORDER BY sp.discount_percent $discount_order, sp.provider_id DESC LIMIT 100");
mysqli_stmt_bind_param($stmt,'i',$uid); mysqli_stmt_execute($stmt); $res=mysqli_stmt_get_result($stmt);
while($r=mysqli_fetch_assoc($res)) $packages[]=$r; mysqli_stmt_close($stmt);

$bookings=[];
$stmt=mysqli_prepare($conn,"SELECT b.booking_id,b.user_id,b.total_price,b.booking_status,b.cancellation_reason,b.cancelled_at,b.cancelled_by,b.confirmation_email_status,b.cancellation_email_status,b.booking_date,CONCAT(u.first_name,' ',u.last_name) customer_name,COUNT(bd.detail_id) item_count FROM bookings b LEFT JOIN users u ON u.user_id=b.user_id LEFT JOIN booking_details bd ON bd.booking_id=b.booking_id WHERE b.manager_id=? AND b.admin_removed=0 GROUP BY b.booking_id,b.user_id,b.total_price,b.booking_status,b.booking_date,u.first_name,u.last_name ORDER BY b.booking_id DESC LIMIT 100");
mysqli_stmt_bind_param($stmt,'i',$uid); mysqli_stmt_execute($stmt); $res=mysqli_stmt_get_result($stmt);
while($r=mysqli_fetch_assoc($res)) $bookings[]=$r; mysqli_stmt_close($stmt);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($manager_name)?> | Manager Details | Smart Matrimony</title>
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="../assets/css/admin-managers.css">
<link rel="stylesheet" href="../assets/css/manager-details.css">
</head>
<body>
<header class="manager-topbar manager-details-topbar">
  <div class="manager-brand">
    <img src="../assets/images/logo/logo.png" alt="Smart Matrimony" class="manager-brand-logo">
    <img src="../assets/images/logo/matrimony_title.png" alt="Smart Matrimony" class="manager-brand-title">
  </div>
  <a class="manager-topbar-logout" href="logout.php">↪ <span>Logout</span></a>
</header>

<main class="manager-wrap manager-details-wrap">
  <a class="manager-back-dashboard manager-back-managers" href="managers.php">&#8592; Back to Managers</a>
  <?php if($message): ?><div class="manager-inline-message success"><?=h($message)?></div><?php endif; ?>
  <?php if($error): ?><div class="manager-inline-message error"><?=h($error)?></div><?php endif; ?>

  <section class="manager-detail-hero manager-detail-hero-v2">
    <div class="manager-detail-photo">
      <?php if($has_profile_image): ?>
        <img src="<?=h($profile_url)?>" alt="<?=h($manager_name)?> profile photo">
      <?php else: ?>
        <span><?=h($initial)?></span>
      <?php endif; ?>
    </div>
    <div class="manager-detail-hero-main">
      <span class="detail-eyebrow">MANAGER PROFILE</span>
      <div class="manager-detail-title-row">
        <div>
          <h1><?=h($manager_name)?></h1>
          <span class="public-id large"><?=h(pid($manager['user_id']))?></span>
        </div>
        <span class="manager-detail-status status-<?=strtolower(h($manager['account_status']))?>"><?=h($manager['account_status'])?></span>
      </div>
      <div class="manager-detail-contact">
        <span><?=h($manager['gender'])?></span>
        <span><?=h($manager['email'])?></span>
        <span><?=h($manager['mobile'])?></span>
        <span>Joined <?=h(date('d M Y', strtotime($manager['created_at'])))?></span>
      </div>
      <div class="manager-detail-hero-actions">
        <a class="manager-message-btn" href="staff_messages.php?filter=Manager&amp;staff=<?=rawurlencode($staff_key)?>">✉ Message Manager</a>
        <a class="btn-light" href="managers.php">Manager Directory</a>
      </div>
    </div>
  </section>

  <section class="manager-detail-stats-section">
    <div class="manager-detail-section-heading"><span class="eyebrow">LIVE OVERVIEW</span><h2>Manager Statistics</h2></div>
    <div class="manager-detail-stats-grid">
      <article><span class="detail-stat-icon">▣</span><div><strong><?=number_format($package_total)?></strong><small>Total Packages</small></div></article>
      <article><span class="detail-stat-icon">✓</span><div><strong><?=number_format($active_packages)?></strong><small>Active Packages</small></div></article>
      <article><span class="detail-stat-icon">Ⅱ</span><div><strong><?=number_format($inactive_packages)?></strong><small>Inactive Packages</small></div></article>
      <article><span class="detail-stat-icon">#</span><div><strong><?=number_format($total_bookings)?></strong><small>Total Bookings</small></div></article>
      <article><span class="detail-stat-icon">…</span><div><strong><?=number_format($pending_bookings)?></strong><small>Pending</small></div></article>
      <article><span class="detail-stat-icon">✓</span><div><strong><?=number_format($confirmed_bookings)?></strong><small>Confirmed</small></div></article>
      <article><span class="detail-stat-icon">✓✓</span><div><strong><?=number_format($completed_bookings)?></strong><small>Completed</small></div></article>
      <article><span class="detail-stat-icon">×</span><div><strong><?=number_format($cancelled_bookings)?></strong><small>Cancelled</small></div></article>
      <article class="detail-stat-wide"><span class="detail-stat-icon">৳</span><div><strong>৳ <?=number_format($total_value,2)?></strong><small>Booking Value (excluding cancelled)</small></div></article>
    </div>
  </section>

  <section class="manager-control manager-detail-section-card manager-detail-panel">
    <div class="manager-detail-panel-head"><div><span class="eyebrow">ACCOUNT &amp; ACCESS</span><h2>Manager Account Control</h2><p>Manage this manager's identity, role and account access.</p></div></div>
    <form method="post" class="manager-account-form manager-account-form-grid">
      <input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="update_account">
      <label>First Name<input type="text" name="first_name" value="<?=h($manager['first_name'])?>" required></label>
      <label>Last Name<input type="text" name="last_name" value="<?=h($manager['last_name'])?>" required></label>
      <label>Gender<select name="gender"><option <?= $manager['gender']==='Male'?'selected':'' ?>>Male</option><option <?= $manager['gender']==='Female'?'selected':'' ?>>Female</option></select></label>
      <label>Email<input type="email" name="email" value="<?=h($manager['email'])?>" required></label>
      <label>Mobile<input type="text" name="mobile" value="<?=h($manager['mobile'])?>" required></label>
      <label>Role<select name="role"><option <?= $manager['role']==='User'?'selected':'' ?>>User</option><option <?= $manager['role']==='Manager'?'selected':'' ?>>Manager</option><option <?= $manager['role']==='Authenticator'?'selected':'' ?>>Authenticator</option></select></label>
      <label>Status<select name="account_status"><option <?= $manager['account_status']==='Active'?'selected':'' ?>>Active</option><option <?= $manager['account_status']==='Inactive'?'selected':'' ?>>Inactive</option><option <?= $manager['account_status']==='Suspended'?'selected':'' ?>>Suspended</option></select></label>
      <label>Package Replacement Manager<select name="replacement_manager_id">
        <option value="">Select only when changing role away from Manager</option>
        <?php foreach($available_replacements as $replacement): ?>
          <option value="<?=h($replacement['user_id'])?>" <?=((int)$replacement['user_id']===$created_replacement_id)?'selected':''?>><?=h($replacement['first_name'].' '.$replacement['last_name'])?> · <?=h(pid($replacement['user_id']))?></option>
        <?php endforeach; ?>
      </select></label>
      <div class="manager-form-action"><button type="submit">Save Account Changes</button></div>
    </form>
    <?php if($package_total > 0): ?>
      <div class="manager-role-transition-box">
        <div><strong>Package reassignment required for role change</strong><span>This Manager currently owns <?=number_format($package_total)?> package(s). If the role changes to User or Authenticator, every package must be transferred to another active Manager first. Historical booking ownership is not changed.</span></div>
        <?php if($available_replacements): ?><span class="manager-replacement-ready"><?=number_format(count($available_replacements))?> active replacement Manager(s) available</span><?php else: ?><button type="button" class="btn-outline-small" data-modal-open="create-replacement-manager">Create Replacement Manager</button><?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if($package_total > 0 && !$available_replacements): ?>
    <div class="control-modal" id="create-replacement-manager" hidden><div class="control-modal-backdrop" data-modal-close></div><div class="control-modal-card" role="dialog" aria-modal="true"><button type="button" class="control-modal-close" data-modal-close>×</button><span class="eyebrow">REPLACEMENT MANAGER</span><h3>Create Manager ID</h3><p class="section-note">Create a separate active Manager account first. Its email and mobile must be unique. The current Manager keeps the same user ID when later returning to the Manager role.</p><form method="post" class="control-form-grid"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="create_replacement_manager"><label>First Name<input name="replacement_first_name" required></label><label>Last Name<input name="replacement_last_name" required></label><label>Gender<select name="replacement_gender"><option>Male</option><option>Female</option></select></label><label>Mobile<input name="replacement_mobile" required></label><label class="span-2">Email<input type="email" name="replacement_email" required></label><label class="span-2">Password<input type="password" name="replacement_password" minlength="8" required></label><div class="modal-form-actions"><button type="button" class="btn-light" data-modal-close>Cancel</button><button type="submit" class="btn-save">Create Manager</button></div></form></div></div>
    <?php endif; ?>
    <div class="security-reset-box"><div><strong>Reset Password</strong><span>Set a new password for this manager. Minimum 8 characters.</span></div><form method="post" class="reset-password-form" onsubmit="return confirm('Reset this manager password?');"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="reset_password"><input type="password" name="new_password" minlength="8" placeholder="New password" required><button type="submit" class="btn-danger-soft">Reset Password</button></form></div>
  </section>

  <section class="manager-detail-section panel manager-detail-section-card">
    <div class="panel-head"><div><span class="eyebrow">SERVICE OVERSIGHT</span><h2>Manager Packages</h2><p class="section-note">Review and fully control packages owned by this manager.</p></div><span class="detail-section-count"><?=number_format($package_total)?> Packages</span></div>
    <div class="manager-table-scroll package-table-scroll"><table class="manager-table"><thead><tr><th>ID</th><th>PACKAGE</th><th>PROVIDER</th><th>PRICE</th><th><a class="discount-sort-link" href="?user_id=<?=h($uid)?>&amp;discount_sort=<?= $discount_sort==='asc' ? 'desc' : 'asc' ?>">DISCOUNT <?= $discount_sort==='asc' ? '↑' : ($discount_sort==='desc' ? '↓' : '↕') ?></a></th><th>STATUS</th><th>CONTROL</th></tr></thead><tbody>
    <?php foreach($packages as $r): ?><tr><td><?=h(spid($r['provider_id']))?></td><td><strong><?=h($r['package_name']?:'Unnamed package')?></strong><?php if($r['service_name']): ?><small><?=h($r['service_name'])?></small><?php endif; ?></td><td><?=h($r['provider_name'])?><small><?=h($r['location']?:'No location')?></small></td><td>৳ <?=number_format((float)$r['price'],2)?></td><td><?=number_format((float)$r['discount_percent'],2)?>%</td><td><span class="mini-status mini-status-<?=strtolower(h($r['status']))?>"><?=h($r['status'])?></span></td><td><div class="table-action-group"><button type="button" class="btn-outline-small" data-modal-open="package-<?=h($r['provider_id'])?>">Edit Package</button><form method="post" class="inline-action-form" onsubmit="return confirm('Remove this package permanently? Existing booking records will remain, but booking items linked to this package will be removed by the existing database relationship.');"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="delete_package"><input type="hidden" name="provider_id" value="<?=h($r['provider_id'])?>"><button type="submit" class="btn-danger-small">Remove</button></form></div></td></tr><?php endforeach; ?>
    <?php if(!$packages): ?><tr><td colspan="7" class="empty">No packages assigned to this manager.</td></tr><?php endif; ?></tbody></table></div>
  </section>
  <?php foreach($packages as $r): ?><div class="control-modal" id="package-<?=h($r['provider_id'])?>" hidden><div class="control-modal-backdrop" data-modal-close></div><div class="control-modal-card" role="dialog" aria-modal="true"><button type="button" class="control-modal-close" data-modal-close>×</button><span class="eyebrow">PACKAGE CONTROL</span><h3><?=h($r['package_name']?:'Unnamed package')?></h3><form method="post" class="control-form-grid"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="update_package"><input type="hidden" name="provider_id" value="<?=h($r['provider_id'])?>"><label>Service<select name="service_id" required><?php foreach($services as $svc): ?><option value="<?=h($svc['service_id'])?>" <?=((int)$svc['service_id']===(int)$r['service_id'])?'selected':''?>><?=h($svc['service_name'])?></option><?php endforeach; ?></select></label><label>Provider Name<input name="provider_name" value="<?=h($r['provider_name'])?>" required></label><label>Package Name<input name="package_name" value="<?=h($r['package_name'])?>" required></label><label>Price<input type="number" name="price" min="0" step="0.01" value="<?=h($r['price'])?>" required></label><label>Discount %<input type="number" name="discount_percent" min="0" max="100" step="0.01" value="<?=h($r['discount_percent'])?>" required></label><label>Contact<input name="contact_number" value="<?=h($r['contact_number'])?>"></label><label class="span-2">Location<input name="location" value="<?=h($r['location'])?>"></label><label class="span-2">Package Details<textarea name="package_details" rows="4"><?=h($r['package_details'])?></textarea></label><label>Status<select name="status"><option <?= $r['status']==='Active'?'selected':'' ?>>Active</option><option <?= $r['status']==='Inactive'?'selected':'' ?>>Inactive</option></select></label><div class="modal-form-actions"><button type="button" class="btn-light" data-modal-close>Cancel</button><button type="submit" class="btn-save">Save Package</button></div></form></div></div><?php endforeach; ?>

  <section class="manager-detail-section panel manager-detail-section-card">
    <div class="panel-head"><div><span class="eyebrow">BOOKING OVERSIGHT</span><h2>Customer Bookings</h2><p class="section-note">Review booking details, customer information and control the current booking status.</p></div><span class="detail-section-count"><?=number_format($total_bookings)?> Bookings</span></div>
    <div class="manager-table-scroll booking-table-scroll"><table class="manager-table"><thead><tr><th>ID</th><th>CUSTOMER</th><th>ITEMS</th><th>TOTAL</th><th>BOOKED</th><th>STATUS</th><th>CONTROL</th></tr></thead><tbody>
    <?php foreach($bookings as $b): ?><tr><td><?=h(bkid($b['booking_id']))?></td><td><strong><?=h($b['customer_name']?:'Unknown customer')?></strong><small><?=h(pid($b['user_id']))?></small></td><td><?=number_format((int)$b['item_count'])?></td><td>৳ <?=number_format((float)$b['total_price'],2)?></td><td><?=h(date('d M Y',strtotime($b['booking_date'])))?></td><td><span class="mini-status mini-status-<?=strtolower(h($b['booking_status']))?>"><?=h($b['booking_status'])?></span></td><td><div class="table-action-group"><button type="button" class="btn-outline-small" data-modal-open="booking-<?=h($b['booking_id'])?>">View / Control</button><?php if($b['booking_status']==='Completed'): ?><form method="post" class="inline-action-form" onsubmit="return confirm('Remove this completed booking from the active list? The completed statistics will be preserved.');"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="delete_completed_booking"><input type="hidden" name="booking_id" value="<?=h($b['booking_id'])?>"><button type="submit" class="btn-danger-small">Delete</button></form><?php endif; ?></div></td></tr><?php endforeach; ?>
    <?php if(!$bookings): ?><tr><td colspan="7" class="empty">No bookings found for this manager.</td></tr><?php endif; ?></tbody></table></div>
  </section>
  <?php foreach($bookings as $b): ?><?php $details=[]; $bd=mysqli_prepare($conn,"SELECT bd.quantity,bd.unit_price,bd.event_date,bd.special_instruction,sp.package_name,sp.provider_name FROM booking_details bd LEFT JOIN service_providers sp ON sp.provider_id=bd.provider_id WHERE bd.booking_id=? AND bd.manager_id=? ORDER BY bd.detail_id ASC"); mysqli_stmt_bind_param($bd,'ii',$b['booking_id'],$uid); mysqli_stmt_execute($bd); $dr=mysqli_stmt_get_result($bd); while($d=mysqli_fetch_assoc($dr)) $details[]=$d; mysqli_stmt_close($bd); ?>
  <div class="control-modal" id="booking-<?=h($b['booking_id'])?>" hidden><div class="control-modal-backdrop" data-modal-close></div><div class="control-modal-card wide" role="dialog" aria-modal="true"><button type="button" class="control-modal-close" data-modal-close>×</button><span class="eyebrow">BOOKING CONTROL</span><h3><?=h(bkid($b['booking_id']))?> · <?=h($b['customer_name']?:'Unknown customer')?></h3><div class="booking-meta-grid"><div><small>Customer ID</small><strong><?=h(pid($b['user_id']))?></strong></div><div><small>Total</small><strong>৳ <?=number_format((float)$b['total_price'],2)?></strong></div><div><small>Booked</small><strong><?=h(date('d M Y, h:i A',strtotime($b['booking_date'])))?></strong></div><div><small>Confirmation email</small><strong><?=h($b['confirmation_email_status'])?></strong></div><div><small>Cancellation email</small><strong><?=h($b['cancellation_email_status'])?></strong></div><div><small>Cancelled by</small><strong><?=h($b['cancelled_by']?:'—')?></strong></div></div><div class="booking-detail-list"><h4>Booking Items</h4><?php if($details): foreach($details as $d): ?><div class="booking-detail-item"><div><strong><?=h($d['package_name']?:'Package')?></strong><small><?=h($d['provider_name']?:'Provider')?> · Qty <?=number_format((int)$d['quantity'])?></small></div><div><strong>৳ <?=number_format((float)$d['unit_price'],2)?></strong><small>Event <?=h(date('d M Y',strtotime($d['event_date'])))?></small></div><?php if(trim((string)$d['special_instruction'])!==''): ?><p><?=h($d['special_instruction'])?></p><?php endif; ?></div><?php endforeach; else: ?><p class="empty">No booking detail items found.</p><?php endif; ?></div><form method="post" class="booking-control-form"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="update_booking"><input type="hidden" name="booking_id" value="<?=h($b['booking_id'])?>"><label>Status<select name="booking_status" data-cancel-toggle="<?=h($b['booking_id'])?>"><option <?= $b['booking_status']==='Pending'?'selected':'' ?>>Pending</option><option <?= $b['booking_status']==='Confirmed'?'selected':'' ?>>Confirmed</option><option <?= $b['booking_status']==='Completed'?'selected':'' ?>>Completed</option><option <?= $b['booking_status']==='Cancelled'?'selected':'' ?>>Cancelled</option></select></label><label class="cancel-reason-field" data-cancel-field="<?=h($b['booking_id'])?>">Cancellation Reason<textarea name="cancellation_reason" rows="3" placeholder="Required when cancelled"><?=h($b['cancellation_reason'])?></textarea></label><div class="modal-form-actions"><button type="button" class="btn-light" data-modal-close>Cancel</button><button type="submit" class="btn-save">Save Booking Control</button></div></form></div></div><?php endforeach; ?>

  <section class="manager-detail-communication">
    <div><span class="eyebrow">COMMUNICATION</span><h2>Manager Communication</h2><p>Open the existing Admin ↔ Manager conversation for this manager.</p></div>
    <a class="manager-message-btn" href="staff_messages.php?filter=Manager&amp;staff=<?=rawurlencode($staff_key)?>">✉ Open Conversation</a>
  </section>
</main>
<script>
(function(){
  var scrollKey = 'managerDetailsScroll:' + window.location.pathname + window.location.search.split('&action=')[0];
  var saved = sessionStorage.getItem(scrollKey);
  if (saved !== null) {
    sessionStorage.removeItem(scrollKey);
    window.requestAnimationFrame(function(){ window.scrollTo(0, parseInt(saved,10) || 0); });
  }
  document.querySelectorAll('form[method="post"]').forEach(function(form){
    form.addEventListener('submit', function(){ sessionStorage.setItem(scrollKey, String(window.scrollY || window.pageYOffset || 0)); });
  });
  document.querySelectorAll('.discount-sort-link').forEach(function(link){
    link.addEventListener('click', function(){ sessionStorage.setItem(scrollKey, String(window.scrollY || window.pageYOffset || 0)); });
  });
})();
var bookingTableScroll = document.querySelector('.booking-table-scroll');
if (bookingTableScroll) {
  bookingTableScroll.addEventListener('wheel', function(e){
    if (e.deltaY === 0) return;
    var atTop = bookingTableScroll.scrollTop <= 0;
    var atBottom = bookingTableScroll.scrollTop + bookingTableScroll.clientHeight >= bookingTableScroll.scrollHeight - 1;
    if ((e.deltaY > 0 && atBottom) || (e.deltaY < 0 && atTop)) {
      e.preventDefault();
      window.scrollBy(0, e.deltaY);
    }
  }, {passive:false});
}
document.querySelectorAll('[data-modal-open]').forEach(function(btn){btn.addEventListener('click',function(){var m=document.getElementById(btn.getAttribute('data-modal-open'));if(m){m.hidden=false;document.body.classList.add('modal-open');}});});
document.querySelectorAll('[data-modal-close]').forEach(function(btn){btn.addEventListener('click',function(){var m=btn.closest('.control-modal');if(m){m.hidden=true;document.body.classList.remove('modal-open');}});});
document.addEventListener('keydown',function(e){if(e.key==='Escape'){document.querySelectorAll('.control-modal:not([hidden])').forEach(function(m){m.hidden=true;});document.body.classList.remove('modal-open');}});
document.querySelectorAll('[data-cancel-toggle]').forEach(function(sel){function sync(){var id=sel.getAttribute('data-cancel-toggle'),field=document.querySelector('[data-cancel-field="'+id+'"]');if(field)field.classList.toggle('is-required',sel.value==='Cancelled');}sel.addEventListener('change',sync);sync();});
</script>
</body>
</html>
