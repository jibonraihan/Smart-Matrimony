<?php
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_name('SMART_ADMIN_SESSION'); session_start(); }
if (empty($_SESSION['admin_user_id'])) { header('Location: login.php'); exit; }
$admin_id=(int)$_SESSION['admin_user_id'];
if(empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['admin_csrf']; $message=''; $error='';
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function pid($id){return 'SM-'.str_pad((string)(int)$id,6,'0',STR_PAD_LEFT);}
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($csrf,(string)($_POST['csrf']??''))){$error='Security check failed. Please refresh and try again.';}
 else{
  $uid=(int)($_POST['user_id']??0); $action=$_POST['action']??'';
  if($uid<=0||$uid===$admin_id){$error='Invalid manager account.';}
  elseif($action==='update_manager'){
   $status=$_POST['account_status']??''; $role=$_POST['role']??'';
   if(!in_array($status,['Active','Inactive','Suspended'],true)||!in_array($role,['Manager','User','Authenticator'],true)){$error='Invalid role or status.';}
   else{
    $st=mysqli_prepare($conn,"UPDATE users SET role=?, account_status=? WHERE user_id=? AND role IN ('Manager','User','Authenticator')"); mysqli_stmt_bind_param($st,'ssi',$role,$status,$uid);
    if(mysqli_stmt_execute($st)){$message='Manager account updated successfully.';}else{$error='Unable to update manager account.';} mysqli_stmt_close($st);
   }
  }
 }
}
$q=trim($_GET['q']??''); $status=$_GET['status']??'All';
$where=["u.role='Manager'"]; $params=[]; $types='';
if($q!==''){$like='%'.$q.'%'; $where[]='(CAST(u.user_id AS CHAR) LIKE ? OR CONCAT("SM-",LPAD(u.user_id,6,"0")) LIKE ? OR CONCAT(u.first_name," ",u.last_name) LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)';$params=[$like,$like,$like,$like,$like];$types='sssss';}
if($status!=='All'&&in_array($status,['Active','Inactive','Suspended'],true)){$where[]='u.account_status=?';$params[]=$status;$types.='s';}
$sql='SELECT u.user_id,u.first_name,u.last_name,u.gender,u.email,u.mobile,u.account_status,u.created_at,(SELECT COUNT(*) FROM service_providers sp WHERE sp.manager_id=u.user_id) package_count,(SELECT COUNT(*) FROM bookings b WHERE b.manager_id=u.user_id) booking_count FROM users u WHERE '.implode(' AND ',$where).' ORDER BY u.user_id DESC LIMIT 100';
$st=mysqli_prepare($conn,$sql); if($params) mysqli_stmt_bind_param($st,$types,...$params); mysqli_stmt_execute($st); $res=mysqli_stmt_get_result($st); $managers=[]; while($r=mysqli_fetch_assoc($res))$managers[]=$r; mysqli_stmt_close($st);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Manager Management | Smart Matrimony</title><link rel="stylesheet" href="../assets/css/admin.css"><link rel="stylesheet" href="../assets/css/admin-managers.css"></head><body>
<header class="topbar"><div><span class="eyebrow">ADMIN CONTROL</span><h1>Manager Management</h1><p class="top-subtitle">Manage manager accounts, access status and service activity.</p></div><div class="top-actions"><a href="dashboard.php">Admin Dashboard</a><a href="operations.php">Operations</a><a href="logout.php">Logout</a></div></header>
<main class="manager-wrap">
<?php if($message):?><div class="manager-toast success"><?=h($message)?></div><?php endif;?><?php if($error):?><div class="manager-toast error"><?=h($error)?></div><?php endif;?>
<section class="manager-summary"><div><span>Managers</span><strong><?=number_format(count($managers))?></strong></div><div><span>Active in list</span><strong><?=number_format(count(array_filter($managers,fn($m)=>$m['account_status']==='Active')))?></strong></div><div><span>Packages</span><strong><?=number_format(array_sum(array_column($managers,'package_count')))?></strong></div><div><span>Bookings</span><strong><?=number_format(array_sum(array_column($managers,'booking_count')))?></strong></div></section>
<section class="panel"><div class="panel-head"><div><span class="eyebrow">MANAGER DIRECTORY</span><h2>All Managers</h2></div></div>
<form class="manager-filters" method="get"><div><label>Search</label><input name="q" value="<?=h($q)?>" placeholder="Search ID, name, email or mobile"></div><div><label>Status</label><select name="status"><option>All</option><?php foreach(['Active','Inactive','Suspended'] as $s):?><option <?=$status===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></div><button>Filter</button><?php if($q!==''||$status!=='All'):?><a class="clear-btn" href="managers.php">Clear</a><?php endif;?></form>
<div class="manager-table-scroll"><table class="manager-table"><thead><tr><th>ID</th><th>Manager</th><th>Contact</th><th>Account</th><th>Packages</th><th>Bookings</th><th>Actions</th></tr></thead><tbody>
<?php foreach($managers as $m):?><tr><td><span class="public-id"><?=h(pid($m['user_id']))?></span></td><td><strong><?=h($m['first_name'].' '.$m['last_name'])?></strong><small><?=h($m['gender'])?></small></td><td><?=h($m['email'])?><small><?=h($m['mobile'])?></small></td><td><form method="post" class="manager-row-form"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="update_manager"><input type="hidden" name="user_id" value="<?=$m['user_id']?>"><select name="role"><option selected>Manager</option><option>User</option><option>Authenticator</option></select><select name="account_status"><?php foreach(['Active','Inactive','Suspended'] as $s):?><option <?=$m['account_status']===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></td><td><?=number_format((int)$m['package_count'])?></td><td><?=number_format((int)$m['booking_count'])?></td><td class="manager-actions"><a class="btn-light" href="manager_details.php?user_id=<?=$m['user_id']?>">View</a><button class="btn-save">Save</button></form></td></tr><?php endforeach;?><?php if(!$managers):?><tr><td colspan="7" class="empty">No managers found.</td></tr><?php endif;?></tbody></table></div></section>
</main></body></html>
