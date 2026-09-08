<?php
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_name('SMART_ADMIN_SESSION'); session_start(); }
if (empty($_SESSION['admin_user_id'])) { header('Location: login.php'); exit; }
$admin_id=(int)$_SESSION['admin_user_id'];
if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['admin_csrf']; $message=''; $error='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!hash_equals($csrf, (string)($_POST['csrf']??''))) { $error='Security check failed. Please refresh and try again.'; }
  else {
    $action=$_POST['action']??'';
    if ($action==='create_manager') {
      $first=trim($_POST['first_name']??''); $last=trim($_POST['last_name']??''); $gender=$_POST['gender']??''; $mobile=trim($_POST['mobile']??''); $email=trim($_POST['email']??''); $pass=(string)($_POST['password']??'');
      if ($first===''||$last===''||!in_array($gender,['Male','Female'],true)||$mobile===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pass)<8) $error='Please complete all manager fields. Password must be at least 8 characters.';
      else { $hash=password_hash($pass,PASSWORD_DEFAULT); $stmt=mysqli_prepare($conn,"INSERT INTO users(first_name,last_name,gender,mobile,email,password,role,account_status) VALUES(?,?,?,?,?,?, 'Manager','Active')"); mysqli_stmt_bind_param($stmt,'ssssss',$first,$last,$gender,$mobile,$email,$hash); if(mysqli_stmt_execute($stmt)) $message='Manager account created successfully.'; else $error=(mysqli_errno($conn)===1062?'Email or mobile already exists.':'Unable to create manager account.'); mysqli_stmt_close($stmt); }
    } elseif ($action==='update_user') {
      $uid=(int)($_POST['user_id']??0); $role=$_POST['role']??'User'; $status=$_POST['account_status']??'Active';
      $allowed_roles=['User','Manager','Authenticator']; $allowed_status=['Active','Inactive','Suspended'];
      if($uid<=0||$uid===$admin_id||!in_array($role,$allowed_roles,true)||!in_array($status,$allowed_status,true)) $error='Invalid user update.';
      else { $stmt=mysqli_prepare($conn,"UPDATE users SET role=?, account_status=? WHERE user_id=? AND role<>'Admin'"); mysqli_stmt_bind_param($stmt,'ssi',$role,$status,$uid); if(mysqli_stmt_execute($stmt)&&mysqli_stmt_affected_rows($stmt)>=0) $message='User account updated successfully.'; else $error='Unable to update user.'; mysqli_stmt_close($stmt); }
    }
  }
}

function scalar($conn,$sql){$r=mysqli_query($conn,$sql);$x=mysqli_fetch_row($r);return (int)($x[0]??0);}
$stats=['users'=>scalar($conn,"SELECT COUNT(*) FROM users WHERE role='User'"),'managers'=>scalar($conn,"SELECT COUNT(*) FROM users WHERE role='Manager'"),'authenticators'=>scalar($conn,"SELECT COUNT(*) FROM users WHERE role='Authenticator'"),'active'=>scalar($conn,"SELECT COUNT(*) FROM users WHERE account_status='Active'"),'suspended'=>scalar($conn,"SELECT COUNT(*) FROM users WHERE account_status='Suspended'"),'interests'=>scalar($conn,"SELECT COUNT(*) FROM matches WHERE status='Pending' AND relationship_active=1"),'pending_profiles'=>scalar($conn,"SELECT COUNT(*) FROM user_profiles up INNER JOIN users u ON u.user_id=up.user_id WHERE u.role='User' AND up.verification_status='Pending'"),'verified_profiles'=>scalar($conn,"SELECT COUNT(*) FROM user_profiles WHERE verification_status='Verified'"),'matched_profiles'=>scalar($conn,"SELECT COUNT(*) FROM (SELECT sender_user_id AS user_id FROM matches WHERE status='Accepted' AND relationship_active=1 UNION SELECT receiver_user_id AS user_id FROM matches WHERE status='Accepted' AND relationship_active=1) AS matched_users")];
$q=trim($_GET['q']??''); $role=$_GET['role']??''; $status=$_GET['status']??'';
$where=['role<>' . "'Admin'"]; $params=[];$types='';
if($q!==''){
  $search_conditions=["CAST(user_id AS CHAR) LIKE ?","CONCAT('SM-', LPAD(CAST(user_id AS CHAR), 6, '0')) LIKE ?","first_name LIKE ?","last_name LIKE ?","email LIKE ?","mobile LIKE ?"];
  $like="%$q%"; array_push($params,$like,$like,$like,$like,$like,$like); $types.='ssssss';

  // Public IDs are displayed as SM-000001. Also accept the numeric portion
  // alone, including leading zeros (e.g. 000006 or 6), without affecting
  // normal name/email/mobile searches.
  $normalized_id = null;
  if (preg_match('/^SM-\s*0*(\d+)$/i', $q, $id_match)) {
    $normalized_id = (int)$id_match[1];
  } elseif (preg_match('/^0*\d+$/', $q)) {
    $normalized_id = (int)$q;
  }
  if ($normalized_id !== null && $normalized_id > 0) {
    $search_conditions[]='user_id=?';
    $params[]=$normalized_id;
    $types.='i';
  }
  $where[]='('.implode(' OR ',$search_conditions).')';
}
if(in_array($role,['User','Manager','Authenticator'],true)){ $where[]='role=?';$params[]=$role;$types.='s'; }
if(in_array($status,['Active','Inactive','Suspended'],true)){ $where[]='account_status=?';$params[]=$status;$types.='s'; }
$sql='SELECT user_id,first_name,last_name,gender,mobile,email,role,account_status,created_at FROM users WHERE '.implode(' AND ',$where).' ORDER BY user_id DESC LIMIT 100';
$stmt=mysqli_prepare($conn,$sql); if($params) mysqli_stmt_bind_param($stmt,$types,...$params); mysqli_stmt_execute($stmt); $res=mysqli_stmt_get_result($stmt); $users=[];while($r=mysqli_fetch_assoc($res))$users[]=$r;mysqli_stmt_close($stmt);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Dashboard | Smart Matrimony</title><link rel="stylesheet" href="../assets/css/admin.css"></head><body>
<header class="topbar"><div class="brand-block"><span class="eyebrow">SMART MATRIMONY</span><h1>Admin Control Center</h1><div class="admin-identity" aria-label="Administrator"><span class="admin-identity-label">ADMIN</span><strong><?=htmlspecialchars($_SESSION['admin_name']??'Administrator')?></strong></div></div><div class="top-actions"><a href="users.php">User Management</a><a href="operations.php">Operations</a><a href="managers.php">Manager Management</a><a href="authenticators.php">Authenticator Management</a><a href="logout.php" class="logout-btn">Logout</a></div></header>
<main class="wrap">
<?php if($message):?><div class="alert success"><?=htmlspecialchars($message)?></div><?php endif;?><?php if($error):?><div class="alert error"><?=htmlspecialchars($error)?></div><?php endif;?>
<section class="stats"><?php foreach([['Users',$stats['users']],['Managers',$stats['managers']],['Authenticators',$stats['authenticators']],['Active Accounts',$stats['active']],['Suspended',$stats['suspended']],['Pending Interests',$stats['interests']],['Pending Profiles',$stats['pending_profiles']],['Verified Profiles',$stats['verified_profiles']],['Matched Profiles',$stats['matched_profiles']]] as $s):?><div class="stat"><span><?=htmlspecialchars($s[0])?></span><strong><?=number_format($s[1])?></strong></div><?php endforeach;?></section>
<section class="panel"><div class="panel-head"><div><span class="eyebrow">MANAGER MANAGEMENT</span><h2>Create Manager Account</h2></div></div><form method="post" class="manager-form"><input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="action" value="create_manager"><input name="first_name" placeholder="First name" required><input name="last_name" placeholder="Last name" required><select name="gender" required><option value="">Gender</option><option>Male</option><option>Female</option></select><input name="mobile" placeholder="Mobile" required><input type="email" name="email" placeholder="Email" required><input type="password" name="password" placeholder="Password (8+ chars)" minlength="8" required><button>Create Manager</button></form></section>
<section class="panel"><div class="panel-head"><div><span class="eyebrow">ACCOUNT MANAGEMENT</span><h2>Users Managers & Authenticator</h2></div></div><form class="filters" method="get"><input name="q" value="<?=htmlspecialchars($q)?>" placeholder="Search ID, name, email or mobile"><div class="filter-field"><label for="role-filter">Role</label><select id="role-filter" name="role" aria-label="Role"><option value="" disabled <?= $role==='' ? 'selected' : '' ?>>Select role</option><option value="All" <?= $role==='All' ? 'selected' : '' ?>>All</option><?php foreach(['User','Manager','Authenticator'] as $r):?><option <?=$role===$r?'selected':''?>><?=$r?></option><?php endforeach;?></select></div><div class="filter-field"><label for="status-filter">Status</label><select id="status-filter" name="status" aria-label="Status"><option value="" disabled <?= $status==='' ? 'selected' : '' ?>>Select status</option><option value="All" <?= $status==='All' ? 'selected' : '' ?>>All</option><?php foreach(['Active','Inactive','Suspended'] as $st):?><option <?=$status===$st?'selected':''?>><?=$st?></option><?php endforeach;?></select></div><button>Filter</button></form>
<div class="table-wrap"><table><thead><tr><th>ID</th><th>Name</th><th>Contact</th><th>Role</th><th>Status</th><th>Created</th><th>Update</th></tr></thead><tbody><?php foreach($users as $u):?><tr><td>SM-<?=str_pad((string)$u['user_id'],6,'0',STR_PAD_LEFT)?></td><td><strong><?=htmlspecialchars($u['first_name'].' '.$u['last_name'])?></strong><small><?=htmlspecialchars($u['gender'])?></small></td><td><?=htmlspecialchars($u['email'])?><small><?=htmlspecialchars($u['mobile'])?></small></td><td><form method="post" class="row-form"><input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="action" value="update_user"><input type="hidden" name="user_id" value="<?=$u['user_id']?>"><select name="role"><?php foreach(['User','Manager','Authenticator'] as $r):?><option <?=$u['role']===$r?'selected':''?>><?=$r?></option><?php endforeach;?></select></td><td><select name="account_status"><?php foreach(['Active','Inactive','Suspended'] as $st):?><option <?=$u['account_status']===$st?'selected':''?>><?=$st?></option><?php endforeach;?></select></td><td><?=htmlspecialchars(date('d M Y',strtotime($u['created_at'])))?></td><td><button class="small">Save</button></form></td></tr><?php endforeach;?><?php if(!$users):?><tr><td colspan="7" class="empty">No accounts found.</td></tr><?php endif;?></tbody></table></div></section>
</main></body></html>
