<?php
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_name('SMART_ADMIN_SESSION'); session_start(); }
if (empty($_SESSION['admin_user_id'])) { header('Location: login.php'); exit; }
$admin_id = (int) $_SESSION['admin_user_id'];
if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['admin_csrf'];
$message = ''; $error = '';

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($v) { return number_format((float)$v, 2); }
function scalar_count(mysqli $conn, string $sql): int { $r=mysqli_query($conn,$sql); if(!$r) return 0; $row=mysqli_fetch_row($r); return (int)($row[0]??0); }
function public_id($id): string { return 'SM-'.str_pad((string)$id,6,'0',STR_PAD_LEFT); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'provider_status') {
            $provider_id=(int)($_POST['provider_id']??0); $status=$_POST['status']??'';
            if ($provider_id<=0 || !in_array($status,['Active','Inactive'],true)) $error='Invalid package status update.';
            else { $stmt=mysqli_prepare($conn,'UPDATE service_providers SET status=? WHERE provider_id=?'); mysqli_stmt_bind_param($stmt,'si',$status,$provider_id); $ok=mysqli_stmt_execute($stmt); $error=$ok?'':'Unable to update package status.'; $message=$ok?'Package status updated successfully.':''; mysqli_stmt_close($stmt); }
        } elseif ($action === 'booking_status') {
            $booking_id=(int)($_POST['booking_id']??0); $status=$_POST['booking_status']??'';
            if ($booking_id<=0 || !in_array($status,['Pending','Confirmed','Completed','Cancelled'],true)) $error='Invalid booking status update.';
            else { $stmt=mysqli_prepare($conn,'UPDATE bookings SET booking_status=? WHERE booking_id=?'); mysqli_stmt_bind_param($stmt,'si',$status,$booking_id); $ok=mysqli_stmt_execute($stmt); $error=$ok?'':'Unable to update booking status.'; $message=$ok?'Booking status updated successfully.':''; mysqli_stmt_close($stmt); }
        } elseif ($action === 'profile_moderation') {
            $uid=(int)($_POST['user_id']??0); $verification=$_POST['verification_status']??''; $visibility=$_POST['profile_visibility']??''; $photo=$_POST['photo_visibility']??'';
            $validV=['Pending','Verified','Rejected']; $validP=['Public','Hidden']; $validPhoto=['Everyone','Verified Users','Matched Users','Hidden'];
            if($uid<=0 || !in_array($verification,$validV,true) || !in_array($visibility,$validP,true) || !in_array($photo,$validPhoto,true)) $error='Invalid profile moderation data.';
            else { $stmt=mysqli_prepare($conn,'UPDATE user_profiles SET verification_status=?, profile_visibility=?, photo_visibility=? WHERE user_id=?'); mysqli_stmt_bind_param($stmt,'sssi',$verification,$visibility,$photo,$uid); $ok=mysqli_stmt_execute($stmt); $error=$ok?'':'Unable to update profile controls.'; $message=$ok?'Profile controls updated successfully.':''; mysqli_stmt_close($stmt); }
        }
    }
}

$stats=[
 'users'=>scalar_count($conn,"SELECT COUNT(*) FROM users WHERE role='User'"),
 'managers'=>scalar_count($conn,"SELECT COUNT(*) FROM users WHERE role='Manager'"),
 'authenticators'=>scalar_count($conn,"SELECT COUNT(*) FROM users WHERE role='Authenticator'"),
 'profiles'=>scalar_count($conn,'SELECT COUNT(*) FROM user_profiles'),
 'pending_verification'=>scalar_count($conn,"SELECT COUNT(*) FROM user_profiles WHERE verification_status='Pending'"),
 'active_matches'=>scalar_count($conn,"SELECT COUNT(*) FROM matches WHERE status='Accepted' AND relationship_active=1"),
 'bookings'=>scalar_count($conn,'SELECT COUNT(*) FROM bookings'),
 'chat_requests'=>scalar_count($conn,"SELECT COUNT(*) FROM chat_requests WHERE status='Pending'"),
];

$packages=[];
$sql="SELECT sp.provider_id,sp.provider_name,sp.package_name,sp.price,sp.location,sp.status,sp.created_at,
             s.service_name,u.user_id manager_id,CONCAT(u.first_name,' ',u.last_name) manager_name
      FROM service_providers sp
      LEFT JOIN services s ON s.service_id=sp.service_id
      LEFT JOIN users u ON u.user_id=sp.manager_id
      ORDER BY sp.provider_id DESC LIMIT 100";
if($res=mysqli_query($conn,$sql)){while($r=mysqli_fetch_assoc($res))$packages[]=$r;}

$bookings=[];
$sql="SELECT b.booking_id,b.user_id,b.manager_id,b.total_price,b.booking_status,b.booking_date,
             CONCAT(c.first_name,' ',c.last_name) customer_name,
             CONCAT(m.first_name,' ',m.last_name) manager_name,
             COUNT(bd.detail_id) item_count
      FROM bookings b
      LEFT JOIN users c ON c.user_id=b.user_id
      LEFT JOIN users m ON m.user_id=b.manager_id
      LEFT JOIN booking_details bd ON bd.booking_id=b.booking_id
      GROUP BY b.booking_id,b.user_id,b.manager_id,b.total_price,b.booking_status,b.booking_date,c.first_name,c.last_name,m.first_name,m.last_name
      ORDER BY b.booking_id DESC LIMIT 100";
if($res=mysqli_query($conn,$sql)){while($r=mysqli_fetch_assoc($res))$bookings[]=$r;}

$profiles=[];
$sql="SELECT up.user_id,up.first_name,up.last_name,up.gender,up.verification_status,up.profile_visibility,up.photo_visibility,
             u.role,u.account_status
      FROM user_profiles up JOIN users u ON u.user_id=up.user_id
      WHERE u.role<>'Admin' ORDER BY up.updated_at DESC LIMIT 100";
if($res=mysqli_query($conn,$sql)){while($r=mysqli_fetch_assoc($res))$profiles[]=$r;}

$matches=[];
$sql="SELECT ma.match_id,ma.sender_user_id,ma.receiver_user_id,ma.status,ma.relationship_active,ma.created_at,
             CONCAT(s.first_name,' ',s.last_name) sender_name, CONCAT(r.first_name,' ',r.last_name) receiver_name
      FROM matches ma LEFT JOIN users s ON s.user_id=ma.sender_user_id LEFT JOIN users r ON r.user_id=ma.receiver_user_id
      ORDER BY ma.match_id DESC LIMIT 100";
if($res=mysqli_query($conn,$sql)){while($r=mysqli_fetch_assoc($res))$matches[]=$r;}

$verifications=[];
$sql="SELECT vl.log_id,vl.authenticator_id,vl.profile_id,
             CONCAT(u.first_name,' ',u.last_name) user_name,
             CONCAT(a.first_name,' ',a.last_name) authenticator_name,
             vl.action,vl.remarks,vl.action_at
      FROM verification_logs vl
      LEFT JOIN user_profiles up ON up.profile_id=vl.profile_id
      LEFT JOIN users u ON u.user_id=up.user_id
      LEFT JOIN users a ON a.user_id=vl.authenticator_id
      ORDER BY vl.log_id DESC LIMIT 100";
if($res=mysqli_query($conn,$sql)){while($r=mysqli_fetch_assoc($res))$verifications[]=$r;}

$chat_requests=[];
$sql="SELECT cr.chat_request_id,cr.match_id,cr.sender_user_id,cr.receiver_user_id,cr.status,cr.chat_active,cr.created_at,
             CONCAT(s.first_name,' ',s.last_name) sender_name,CONCAT(r.first_name,' ',r.last_name) receiver_name
      FROM chat_requests cr LEFT JOIN users s ON s.user_id=cr.sender_user_id LEFT JOIN users r ON r.user_id=cr.receiver_user_id
      ORDER BY cr.chat_request_id DESC LIMIT 100";
if($res=mysqli_query($conn,$sql)){while($r=mysqli_fetch_assoc($res))$chat_requests[]=$r;}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Operations | Smart Matrimony</title><link rel="stylesheet" href="../assets/css/admin.css"></head><body>
<header class="topbar"><div class="brand-block"><span class="eyebrow">SMART MATRIMONY</span><h1>Admin Operations Center</h1><p class="subhead">System-wide oversight for profiles, matching and wedding services.</p><div class="admin-identity" aria-label="Administrator"><span class="admin-identity-label">ADMIN</span><strong><?=h($_SESSION['admin_name']??'Administrator')?></strong></div></div><div class="top-actions"><a href="dashboard.php">Admin Dashboard</a><a href="logout.php">Logout</a></div></header>
<main class="wrap">
<?php if($message):?><div class="alert success"><?=h($message)?></div><?php endif;?><?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?>
<section class="stats operations-stats"><?php foreach([['Users',$stats['users']],['Managers',$stats['managers']],['Authenticators',$stats['authenticators']],['Profiles',$stats['profiles']],['Pending Verification',$stats['pending_verification']],['Active Matches',$stats['active_matches']],['Bookings',$stats['bookings']],['Pending Chats',$stats['chat_requests']]] as $s):?><div class="stat"><span><?=h($s[0])?></span><strong><?=number_format($s[1])?></strong></div><?php endforeach;?></section>

<section class="panel"><div class="panel-head"><div><span class="eyebrow">PROFILE CONTROL</span><h2>Profile Moderation</h2><p class="muted">Verification and visibility controls. Detailed verification workflow will be handled by the Authenticator module.</p></div></div>
<div class="table-wrap"><table><thead><tr><th>User</th><th>Role</th><th>Account</th><th>Verification</th><th>Profile</th><th>Photo</th><th>Update</th></tr></thead><tbody>
<?php foreach($profiles as $p):?><tr><td><strong><?=h($p['first_name'].' '.$p['last_name'])?></strong><small><?=h(public_id($p['user_id']))?></small></td><td><?=h($p['role'])?></td><td><?=h($p['account_status'])?></td><td><form id="profile-form-<?=$p['user_id']?>" method="post" class="moderation-form"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="profile_moderation"><input type="hidden" name="user_id" value="<?=$p['user_id']?>"></form><select form="profile-form-<?=$p['user_id']?>" name="verification_status"><option value="Pending" <?=$p['verification_status']==='Pending'?'selected':''?>>Pending</option><option value="Verified" <?=$p['verification_status']==='Verified'?'selected':''?>>Verified</option><option value="Rejected" <?=$p['verification_status']==='Rejected'?'selected':''?>>Rejected</option></select></td><td><select form="profile-form-<?=$p['user_id']?>" name="profile_visibility"><option value="Public" <?=$p['profile_visibility']==='Public'?'selected':''?>>Public</option><option value="Hidden" <?=$p['profile_visibility']==='Hidden'?'selected':''?>>Hidden</option></select></td><td><select form="profile-form-<?=$p['user_id']?>" name="photo_visibility"><option value="Everyone" <?=$p['photo_visibility']==='Everyone'?'selected':''?>>Everyone</option><option value="Verified Users" <?=$p['photo_visibility']==='Verified Users'?'selected':''?>>Verified Users</option><option value="Matched Users" <?=$p['photo_visibility']==='Matched Users'?'selected':''?>>Matched Users</option><option value="Hidden" <?=$p['photo_visibility']==='Hidden'?'selected':''?>>Hidden</option></select></td><td><button class="small" type="submit" form="profile-form-<?=$p['user_id']?>">Save</button></td></tr><?php endforeach;?><?php if(!$profiles):?><tr><td colspan="7" class="empty">No profiles found.</td></tr><?php endif;?></tbody></table></div></section>

<section class="panel"><div class="panel-head"><div><span class="eyebrow">SERVICE OVERSIGHT</span><h2>Manager Packages</h2></div></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Package</th><th>Manager</th><th>Price</th><th>Location</th><th>Status</th><th>Update</th></tr></thead><tbody>
<?php foreach($packages as $p):?><tr><td>SP-<?=str_pad((string)$p['provider_id'],6,'0',STR_PAD_LEFT)?></td><td><strong><?=h($p['package_name']?:$p['provider_name'])?></strong><small><?=h($p['service_name']?:'Service')?></small></td><td><?=h($p['manager_name']?:'Unassigned')?></td><td>৳ <?=money($p['price'])?></td><td><?=h($p['location']?:'Not informed')?></td><td><span class="status-pill <?=strtolower($p['status'])?>"><?=h($p['status'])?></span></td><td><form method="post" class="row-form"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="provider_status"><input type="hidden" name="provider_id" value="<?=$p['provider_id']?>"><select name="status"><option value="Active" <?=$p['status']==='Active'?'selected':''?>>Active</option><option value="Inactive" <?=$p['status']==='Inactive'?'selected':''?>>Inactive</option></select><button class="small">Save</button></form></td></tr><?php endforeach;?><?php if(!$packages):?><tr><td colspan="7" class="empty">No packages found.</td></tr><?php endif;?></tbody></table></div></section>

<section class="panel"><div class="panel-head"><div><span class="eyebrow">BOOKING OVERSIGHT</span><h2>Customer Bookings</h2></div></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Customer</th><th>Manager</th><th>Items</th><th>Total</th><th>Booked</th><th>Status</th><th>Update</th></tr></thead><tbody>
<?php foreach($bookings as $b):?><tr><td>BK-<?=str_pad((string)$b['booking_id'],6,'0',STR_PAD_LEFT)?></td><td><strong><?=h($b['customer_name']?:'Unknown user')?></strong><small><?=h(public_id($b['user_id']))?></small></td><td><?=h($b['manager_name']?:'Unassigned')?></td><td><?=number_format((int)$b['item_count'])?></td><td>৳ <?=money($b['total_price'])?></td><td><?=h(date('d M Y',strtotime($b['booking_date'])))?></td><td><span class="status-pill <?=strtolower($b['booking_status'])?>"><?=h($b['booking_status'])?></span></td><td><form method="post" class="row-form"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="booking_status"><input type="hidden" name="booking_id" value="<?=$b['booking_id']?>"><select name="booking_status"><?php foreach(['Pending','Confirmed','Completed','Cancelled'] as $st):?><option <?=$b['booking_status']===$st?'selected':''?>><?=$st?></option><?php endforeach;?></select><button class="small">Save</button></form></td></tr><?php endforeach;?><?php if(!$bookings):?><tr><td colspan="8" class="empty">No bookings found.</td></tr><?php endif;?></tbody></table></div></section>

<section class="panel"><div class="panel-head"><div><span class="eyebrow">MATCHING OVERSIGHT</span><h2>Interests & Matches</h2></div></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Sender</th><th>Receiver</th><th>Status</th><th>Active</th><th>Created</th></tr></thead><tbody>
<?php foreach($matches as $m):?><tr><td>MT-<?=str_pad((string)$m['match_id'],6,'0',STR_PAD_LEFT)?></td><td><?=h($m['sender_name']?:'Unknown')?> <small><?=h(public_id($m['sender_user_id']))?></small></td><td><?=h($m['receiver_name']?:'Unknown')?> <small><?=h(public_id($m['receiver_user_id']))?></small></td><td><span class="status-pill <?=strtolower($m['status'])?>"><?=h($m['status'])?></span></td><td><?=((int)$m['relationship_active']===1?'Yes':'No')?></td><td><?=h(date('d M Y',strtotime($m['created_at'])))?></td></tr><?php endforeach;?><?php if(!$matches):?><tr><td colspan="6" class="empty">No match records found.</td></tr><?php endif;?></tbody></table></div></section>

<section class="panel"><div class="panel-head"><div><span class="eyebrow">VERIFICATION AUDIT</span><h2>Verification Logs</h2><p class="muted">Read-only audit trail. Authenticator actions will be managed in the next dedicated module.</p></div></div><div class="table-wrap"><table><thead><tr><th>Log</th><th>User</th><th>Authenticator</th><th>Action</th><th>Remarks</th><th>Date</th></tr></thead><tbody>
<?php foreach($verifications as $v):?><tr><td>VR-<?=str_pad((string)$v['log_id'],6,'0',STR_PAD_LEFT)?></td><td><?=h($v['user_name']?:'Unknown')?> <small>Profile #<?=h($v['profile_id'])?></small></td><td><?=h($v['authenticator_name']?:'Unknown')?></td><td><?=h($v['action'])?></td><td><?=h($v['remarks']?:'—')?></td><td><?=h(date('d M Y H:i',strtotime($v['action_at'])))?></td></tr><?php endforeach;?><?php if(!$verifications):?><tr><td colspan="6" class="empty">No verification logs found.</td></tr><?php endif;?></tbody></table></div></section>

<section class="panel"><div class="panel-head"><div><span class="eyebrow">CHAT OVERSIGHT</span><h2>Chat Requests</h2><p class="muted">Metadata only. Message contents are not exposed to Admin by default to preserve user privacy.</p></div></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Sender</th><th>Receiver</th><th>Status</th><th>Chat</th><th>Created</th></tr></thead><tbody>
<?php foreach($chat_requests as $c):?><tr><td>CR-<?=str_pad((string)$c['chat_request_id'],6,'0',STR_PAD_LEFT)?></td><td><?=h($c['sender_name']?:'Unknown')?> <small><?=h(public_id($c['sender_user_id']))?></small></td><td><?=h($c['receiver_name']?:'Unknown')?> <small><?=h(public_id($c['receiver_user_id']))?></small></td><td><?=h($c['status'])?></td><td><?=((int)$c['chat_active']===1?'Active':'Closed')?></td><td><?=h(date('d M Y',strtotime($c['created_at'])))?></td></tr><?php endforeach;?><?php if(!$chat_requests):?><tr><td colspan="6" class="empty">No chat requests found.</td></tr><?php endif;?></tbody></table></div></section>
</main></body></html>
