<?php
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_name('SMART_ADMIN_SESSION'); session_start(); }
if (empty($_SESSION['admin_user_id'])) { header('Location: login.php'); exit; }

$admin_id = (int)$_SESSION['admin_user_id'];
if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['admin_csrf'];

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function pid($id){ return 'SM-'.str_pad((string)(int)$id, 6, '0', STR_PAD_LEFT); }
function spid($id){ return 'SP-'.str_pad((string)(int)$id, 6, '0', STR_PAD_LEFT); }
function bkid($id){ return 'BK-'.str_pad((string)(int)$id, 6, '0', STR_PAD_LEFT); }

$uid = (int)($_GET['user_id'] ?? 0);
if ($uid <= 0 || $uid === $admin_id) { header('Location: managers.php'); exit; }

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_account') {
            $role = $_POST['role'] ?? 'Manager';
            $status = $_POST['account_status'] ?? 'Active';
            $allowed_roles = ['User','Manager','Authenticator'];
            $allowed_status = ['Active','Inactive','Suspended'];

            if (!in_array($role, $allowed_roles, true) || !in_array($status, $allowed_status, true)) {
                $error = 'Invalid account control value.';
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE users SET role=?, account_status=? WHERE user_id=? AND role<>'Admin'");
                mysqli_stmt_bind_param($stmt, 'ssi', $role, $status, $uid);
                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Manager account updated successfully.';
                } else {
                    $error = 'Unable to update the account right now.';
                }
                mysqli_stmt_close($stmt);
            }
        } elseif ($action === 'update_package') {
            $provider_id = (int)($_POST['provider_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            if ($provider_id <= 0 || !in_array($status, ['Active','Inactive'], true)) {
                $error = 'Invalid package update.';
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE service_providers SET status=? WHERE provider_id=? AND manager_id=?");
                mysqli_stmt_bind_param($stmt, 'sii', $status, $provider_id, $uid);
                $ok = mysqli_stmt_execute($stmt);
                $message = $ok ? 'Package status updated successfully.' : 'Unable to update package status.';
                if (!$ok) $error = $message; else $message = $message;
                mysqli_stmt_close($stmt);
            }
        } elseif ($action === 'update_booking') {
            $booking_id = (int)($_POST['booking_id'] ?? 0);
            $status = $_POST['booking_status'] ?? '';
            if ($booking_id <= 0 || !in_array($status, ['Pending','Confirmed','Completed','Cancelled'], true)) {
                $error = 'Invalid booking update.';
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE bookings SET booking_status=? WHERE booking_id=? AND manager_id=?");
                mysqli_stmt_bind_param($stmt, 'sii', $status, $booking_id, $uid);
                $ok = mysqli_stmt_execute($stmt);
                if ($ok) $message = 'Booking status updated successfully.';
                else $error = 'Unable to update booking status.';
                mysqli_stmt_close($stmt);
            }
        }
    }
}

$stmt = mysqli_prepare($conn, "SELECT user_id,first_name,last_name,gender,email,mobile,role,account_status,created_at FROM users WHERE user_id=? AND role='Manager' LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$manager = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$manager) { header('Location: managers.php'); exit; }

function count_for($conn, $sql, $uid) {
    $stmt=mysqli_prepare($conn,$sql); mysqli_stmt_bind_param($stmt,'i',$uid); mysqli_stmt_execute($stmt);
    $row=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt); return (int)($row['c']??0);
}
$package_total = count_for($conn, "SELECT COUNT(*) c FROM service_providers WHERE manager_id=?", $uid);
$active_packages = count_for($conn, "SELECT COUNT(*) c FROM service_providers WHERE manager_id=? AND status='Active'", $uid);
$pending_bookings = count_for($conn, "SELECT COUNT(*) c FROM bookings WHERE manager_id=? AND booking_status='Pending'", $uid);
$confirmed_bookings = count_for($conn, "SELECT COUNT(*) c FROM bookings WHERE manager_id=? AND booking_status='Confirmed'", $uid);

$stmt=mysqli_prepare($conn,"SELECT COALESCE(SUM(total_price),0) total FROM bookings WHERE manager_id=? AND booking_status<>'Cancelled'");
mysqli_stmt_bind_param($stmt,'i',$uid); mysqli_stmt_execute($stmt); $total_value=(float)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total']??0); mysqli_stmt_close($stmt);

$packages=[];
$stmt=mysqli_prepare($conn,"SELECT sp.provider_id,sp.package_name,sp.package_details,sp.provider_name,sp.price,sp.location,sp.rating,sp.review_count,sp.status,s.service_name FROM service_providers sp LEFT JOIN services s ON s.service_id=sp.service_id WHERE sp.manager_id=? ORDER BY sp.provider_id DESC LIMIT 100");
mysqli_stmt_bind_param($stmt,'i',$uid); mysqli_stmt_execute($stmt); $res=mysqli_stmt_get_result($stmt);
while($r=mysqli_fetch_assoc($res)) $packages[]=$r; mysqli_stmt_close($stmt);

$bookings=[];
$stmt=mysqli_prepare($conn,"SELECT b.booking_id,b.user_id,b.total_price,b.booking_status,b.booking_date,CONCAT(u.first_name,' ',u.last_name) customer_name,COUNT(bd.detail_id) item_count FROM bookings b LEFT JOIN users u ON u.user_id=b.user_id LEFT JOIN booking_details bd ON bd.booking_id=b.booking_id WHERE b.manager_id=? GROUP BY b.booking_id,b.user_id,b.total_price,b.booking_status,b.booking_date,u.first_name,u.last_name ORDER BY b.booking_id DESC LIMIT 100");
mysqli_stmt_bind_param($stmt,'i',$uid); mysqli_stmt_execute($stmt); $res=mysqli_stmt_get_result($stmt);
while($r=mysqli_fetch_assoc($res)) $bookings[]=$r; mysqli_stmt_close($stmt);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manager Details | Smart Matrimony</title>
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="../assets/css/admin-managers.css">
</head>
<body>
<header class="topbar"><div><span class="eyebrow">MANAGER PROFILE</span><h1><?=h($manager['first_name'].' '.$manager['last_name'])?></h1><p class="top-subtitle"><?=h(pid($manager['user_id']))?> · Manager oversight</p></div><div class="top-actions"><a href="managers.php">Manager Management</a><a href="dashboard.php">Admin Dashboard</a></div></header>

<main class="manager-wrap">
<?php if($message): ?><div class="manager-inline-message success"><?=h($message)?></div><?php endif; ?>
<?php if($error): ?><div class="manager-inline-message error"><?=h($error)?></div><?php endif; ?>

<section class="manager-detail-hero manager-detail-hero-color">
  <div class="hero-orb hero-orb-one"></div><div class="hero-orb hero-orb-two"></div>
  <div class="manager-hero-content">
    <span class="public-id large hero-id"><?=h(pid($manager['user_id']))?></span>
    <h2><?=h($manager['first_name'].' '.$manager['last_name'])?></h2>
    <p><?=h($manager['gender'])?> <span>•</span> <?=h($manager['email'])?> <span>•</span> <?=h($manager['mobile'])?></p>
    <div class="detail-badges hero-badges">
      <span>Role: <?=h($manager['role'])?></span><span>Status: <?=h($manager['account_status'])?></span>
      <span><?=number_format($package_total)?> Packages</span><span><?=number_format(count($bookings))?> Bookings</span>
    </div>
  </div>
</section>

<section class="manager-detail-grid">
  <div class="manager-stat"><span>Total Packages</span><strong><?=number_format($package_total)?></strong></div>
  <div class="manager-stat"><span>Active Packages</span><strong><?=number_format($active_packages)?></strong></div>
  <div class="manager-stat"><span>Pending Bookings</span><strong><?=number_format($pending_bookings)?></strong></div>
  <div class="manager-stat"><span>Confirmed Bookings</span><strong><?=number_format($confirmed_bookings)?></strong></div>
  <div class="manager-stat"><span>Total Booking Value</span><strong>৳ <?=number_format($total_value,2)?></strong></div>
</section>

<section class="manager-control">
  <h3>Account Control</h3>
  <form method="post">
    <input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="update_account">
    <label>Role<select name="role"><option <?= $manager['role']==='User'?'selected':'' ?>>User</option><option <?= $manager['role']==='Manager'?'selected':'' ?>>Manager</option><option <?= $manager['role']==='Authenticator'?'selected':'' ?>>Authenticator</option></select></label>
    <label>Status<select name="account_status"><option <?= $manager['account_status']==='Active'?'selected':'' ?>>Active</option><option <?= $manager['account_status']==='Inactive'?'selected':'' ?>>Inactive</option><option <?= $manager['account_status']==='Suspended'?'selected':'' ?>>Suspended</option></select></label>
    <button type="submit">Save Account</button>
  </form>
</section>

<section class="panel manager-detail-section"><div class="panel-head"><div><span class="eyebrow">SERVICE OVERSIGHT</span><h2>Manager Packages</h2></div></div>
<div class="manager-table-scroll"><table class="manager-table"><thead><tr><th>ID</th><th>PACKAGE</th><th>PROVIDER</th><th>PRICE</th><th>LOCATION</th><th>RATING</th><th>STATUS</th><th>UPDATE</th></tr></thead>
<tbody>
<?php foreach($packages as $r): ?><tr>
<td><?=h(spid($r['provider_id']))?></td><td><strong><?=h($r['package_name']?:'Unnamed package')?></strong><?php if($r['service_name']): ?><small><?=h($r['service_name'])?></small><?php endif; ?><?php if($r['package_details']): ?><small class="package-detail-preview"><?=h(mb_strimwidth($r['package_details'],0,90,'…'))?></small><?php endif; ?></td>
<td><?=h($r['provider_name'])?></td><td>৳ <?=number_format((float)$r['price'],2)?></td><td><?=h($r['location']?:'Not informed')?></td><td>★ <?=number_format((float)$r['rating'],1)?> (<?=number_format((int)$r['review_count'])?>)</td>
<td><?=h($r['status'])?></td><td><form method="post" class="manager-actions"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="update_package"><input type="hidden" name="provider_id" value="<?=h($r['provider_id'])?>"><select name="status" class="package-status-select"><option <?= $r['status']==='Active'?'selected':'' ?>>Active</option><option <?= $r['status']==='Inactive'?'selected':'' ?>>Inactive</option></select><button class="btn-save" type="submit">Save</button></form></td>
</tr><?php endforeach; ?>
<?php if(!$packages): ?><tr><td colspan="8" class="empty">No packages assigned to this manager.</td></tr><?php endif; ?>
</tbody></table></div></section>

<section class="panel manager-detail-section"><div class="panel-head"><div><span class="eyebrow">BOOKING OVERSIGHT</span><h2>Customer Bookings</h2></div></div>
<div class="manager-table-scroll"><table class="manager-table"><thead><tr><th>ID</th><th>CUSTOMER</th><th>ITEMS</th><th>TOTAL</th><th>BOOKED</th><th>STATUS</th><th>UPDATE</th></tr></thead><tbody>
<?php foreach($bookings as $b): ?><tr>
<td><?=h(bkid($b['booking_id']))?></td><td><strong><?=h($b['customer_name']?:'Unknown customer')?></strong><small><?=h(pid($b['user_id']))?></small></td><td><?=number_format((int)$b['item_count'])?></td><td>৳ <?=number_format((float)$b['total_price'],2)?></td><td><?=h(date('d M Y',strtotime($b['booking_date'])))?></td><td><?=h($b['booking_status'])?></td>
<td><form method="post" class="manager-actions"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="update_booking"><input type="hidden" name="booking_id" value="<?=h($b['booking_id'])?>"><select name="booking_status" class="booking-status-select"><option <?= $b['booking_status']==='Pending'?'selected':'' ?>>Pending</option><option <?= $b['booking_status']==='Confirmed'?'selected':'' ?>>Confirmed</option><option <?= $b['booking_status']==='Completed'?'selected':'' ?>>Completed</option><option <?= $b['booking_status']==='Cancelled'?'selected':'' ?>>Cancelled</option></select><button class="btn-save" type="submit">Save</button></form></td>
</tr><?php endforeach; ?>
<?php if(!$bookings): ?><tr><td colspan="7" class="empty">No bookings found for this manager.</td></tr><?php endif; ?>
</tbody></table></div></section>
</main>
</body></html>
