<?php
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_name('SMART_ADMIN_SESSION'); session_start(); }
if (empty($_SESSION['admin_user_id'])) { header('Location: login.php'); exit; }
$admin_id=(int)$_SESSION['admin_user_id'];
if(empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['admin_csrf']; $message=''; $error='';

function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function pid($id){return 'SM-'.str_pad((string)$id,6,'0',STR_PAD_LEFT);}

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!hash_equals($csrf,(string)($_POST['csrf']??''))){$error='Security check failed. Please refresh and try again.';}
  else{
    $action=$_POST['action']??'';
    if($action==='create_authenticator'){
      $first=trim($_POST['first_name']??''); $last=trim($_POST['last_name']??'');
      $gender=$_POST['gender']??''; $mobile=trim($_POST['mobile']??'');
      $email=trim($_POST['email']??''); $pass=(string)($_POST['password']??'');
      if($first===''||$last===''||!in_array($gender,['Male','Female'],true)||$mobile===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pass)<8){
        $error='Please complete all fields. Password must be at least 8 characters.';
      }else{
        // Prevent duplicate credentials before INSERT so the admin sees a friendly message instead of a database exception.
        $dup=mysqli_prepare($conn,"SELECT user_id,first_name,last_name,email,mobile FROM users WHERE email=? OR mobile=? LIMIT 1");
        mysqli_stmt_bind_param($dup,'ss',$email,$mobile);
        mysqli_stmt_execute($dup);
        $dup_res=mysqli_stmt_get_result($dup);
        $existing=mysqli_fetch_assoc($dup_res);
        mysqli_stmt_close($dup);

        if($existing){
          if(strcasecmp((string)$existing['email'],$email)===0 && (string)$existing['mobile']===$mobile){
            $error="This email and mobile number already belong to an existing account. Use User Management to change that account's role to Authenticator.";
          }elseif(strcasecmp((string)$existing['email'],$email)===0){
            $error='This email is already registered. Use the existing account or choose a different email.';
          }else{
            $error='This mobile number is already registered. Use the existing account or choose a different mobile number.';
          }
        }else{
          $hash=password_hash($pass,PASSWORD_DEFAULT);
          try {
            $st=mysqli_prepare($conn,"INSERT INTO users(first_name,last_name,gender,mobile,email,password,role,account_status) VALUES(?,?,?,?,?,?, 'Authenticator','Active')");
            mysqli_stmt_bind_param($st,'ssssss',$first,$last,$gender,$mobile,$email,$hash);
            if(mysqli_stmt_execute($st)) $message='Authenticator account created successfully.';
            else $error='Unable to create authenticator account right now.';
            mysqli_stmt_close($st);
          } catch (mysqli_sql_exception $e) {
            $error=(mysqli_errno($conn)===1062?'Email or mobile already exists. Please use the existing account or choose different credentials.':'Unable to create authenticator account right now.');
          }
        }
      }
    }elseif($action==='update_authenticator'){
      $uid=(int)($_POST['user_id']??0); $role=$_POST['role']??'Authenticator'; $status=$_POST['account_status']??'Active';
      $roles=['User','Manager','Authenticator']; $statuses=['Active','Inactive','Suspended'];
      if($uid<=0||$uid===$admin_id||!in_array($role,$roles,true)||!in_array($status,$statuses,true)) $error='Invalid account update.';
      else{
        $st=mysqli_prepare($conn,"UPDATE users SET role=?,account_status=? WHERE user_id=? AND role<>'Admin'");
        mysqli_stmt_bind_param($st,'ssi',$role,$status,$uid);
        if(mysqli_stmt_execute($st)) $message='Authenticator account updated successfully.';
        else $error='Unable to update authenticator account.';
        mysqli_stmt_close($st);
      }
    }
  }
}
$q=trim($_GET['q']??''); $status=$_GET['status']??''; $page=max(1,(int)($_GET['page']??1)); $per=30; $offset=($page-1)*$per;
$where=["u.role='Authenticator'"]; $types=''; $params=[];
if($q!==''){
  if(preg_match('/^SM-(\d+)$/i',$q,$m)) $q=(string)((int)$m[1]);
  elseif(ctype_digit($q)) $q=(string)((int)$q);
  $where[]="(u.user_id=? OR u.first_name LIKE CONCAT('%',?,'%') OR u.last_name LIKE CONCAT('%',?,'%') OR u.email LIKE CONCAT('%',?,'%') OR u.mobile LIKE CONCAT('%',?,'%'))";
  $types.='issss'; $params[]=(int)$q; $params[]=$q; $params[]=$q; $params[]=$q; $params[]=$q;
}
if(in_array($status,['Active','Inactive','Suspended'],true)){ $where[]='u.account_status=?'; $types.='s'; $params[]=$status; }
$w=implode(' AND ',$where);
$count=0; $st=mysqli_prepare($conn,"SELECT COUNT(*) FROM users u WHERE $w"); if($types) mysqli_stmt_bind_param($st,$types,...$params); mysqli_stmt_execute($st); mysqli_stmt_bind_result($st,$count); mysqli_stmt_fetch($st); mysqli_stmt_close($st);
$total_pages=max(1,(int)ceil($count/$per));
$rows=[]; $sql="SELECT u.user_id,u.first_name,u.last_name,u.gender,u.mobile,u.email,u.account_status,u.created_at,
(SELECT COUNT(*) FROM user_profiles p WHERE p.user_id=u.user_id) profile_count,
(SELECT COUNT(*) FROM user_profiles p WHERE p.user_id=u.user_id AND p.verification_status='Pending') pending_count,
(SELECT COUNT(*) FROM verification_logs vl WHERE vl.authenticator_id=u.user_id) review_count
FROM users u WHERE $w ORDER BY u.user_id DESC LIMIT ? OFFSET ?";
$st=mysqli_prepare($conn,$sql); $t2=$types.'ii'; $p2=$params; $p2[]=$per; $p2[]=$offset; mysqli_stmt_bind_param($st,$t2,...$p2); mysqli_stmt_execute($st); $res=mysqli_stmt_get_result($st); while($r=mysqli_fetch_assoc($res))$rows[]=$r; mysqli_stmt_close($st);
$stats=[];
foreach([
 'total'=>"SELECT COUNT(*) FROM users WHERE role='Authenticator'",
 'active'=>"SELECT COUNT(*) FROM users WHERE role='Authenticator' AND account_status='Active'",
 'pending'=>"SELECT COUNT(*) FROM user_profiles p JOIN users u ON u.user_id=p.user_id WHERE u.role<>'Admin' AND p.verification_status='Pending'",
 'verified'=>"SELECT COUNT(*) FROM user_profiles p JOIN users u ON u.user_id=p.user_id WHERE u.role<>'Admin' AND p.verification_status='Verified'"
] as $k=>$s){$r=mysqli_query($conn,$s);$stats[$k]=(int)mysqli_fetch_row($r)[0];}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Authenticator Management</title><link rel="stylesheet" href="../assets/css/admin-managers.css"><link rel="stylesheet" href="../assets/css/admin-authenticators.css"></head>
<body><header class="admin-header"><div><span>SMART MATRIMONY</span><h1>Authenticator Management</h1><p>Manage verification staff and oversee profile verification activity.</p></div><nav><a href="dashboard.php">Admin Dashboard</a><a href="operations.php">Operations</a></nav></header>
<main class="auth-admin-wrap">
<?php if($message):?><div class="flash success"><?=h($message)?></div><?php endif;?><?php if($error):?><div class="flash error"><?=h($error)?></div><?php endif;?>
<section class="auth-stats"><?php foreach([['Authenticators',$stats['total']],['Active',$stats['active']],['Pending Profiles',$stats['pending']],['Verified Profiles',$stats['verified']]] as $s):?><div><span><?=h($s[0])?></span><strong><?=number_format($s[1])?></strong></div><?php endforeach;?></section>
<section class="auth-panel"><div class="panel-head"><div><span class="eyebrow">STAFF MANAGEMENT</span><h2>Create Authenticator Account</h2></div></div>
<form method="post" class="auth-create"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="create_authenticator">
<input name="first_name" placeholder="First name" required><input name="last_name" placeholder="Last name" required><select name="gender" required><option value="">Gender</option><option>Male</option><option>Female</option></select><input name="mobile" placeholder="Mobile" required><input type="email" name="email" placeholder="Email" required><input type="password" name="password" placeholder="Password (8+ chars)" minlength="8" required><button>Create Authenticator</button></form></section>
<section class="auth-panel"><div class="panel-head"><div><span class="eyebrow">VERIFICATION STAFF</span><h2>Authenticators</h2></div></div>
<form method="get" class="auth-filters"><input name="q" value="<?=h($q)?>" placeholder="Search ID, name, email or mobile"><select name="status"><option value="" disabled <?= $status===''?'selected':''?>>Status</option><option value="All" <?= $status==='All'?'selected':''?>>All</option><?php foreach(['Active','Inactive','Suspended'] as $s):?><option value="<?=$s?>" <?= $status===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select><button>Filter</button></form>
<div class="auth-table-wrap"><table><thead><tr><th>ID</th><th>AUTHENTICATOR</th><th>CONTACT</th><th>ACCOUNT</th><th>PROFILES</th><th>REVIEWS</th><th>UPDATE</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><span class="id-pill"><?=h(pid($r['user_id']))?></span></td><td><strong><?=h($r['first_name'].' '.$r['last_name'])?></strong><small><?=h($r['gender'])?></small></td><td><?=h($r['email'])?><small><?=h($r['mobile'])?></small></td><td><form method="post" class="row-form"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="update_authenticator"><input type="hidden" name="user_id" value="<?=$r['user_id']?>"><select name="role"><option>Authenticator</option><option>User</option><option>Manager</option></select><select name="account_status"><?php foreach(['Active','Inactive','Suspended'] as $s):?><option <?=$r['account_status']===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></td><td><?=$r['profile_count']?> <small><?=$r['pending_count']?> pending</small></td><td><?=number_format($r['review_count'])?></td><td><button class="small">Save</button></form></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="7" class="empty">No authenticators found.</td></tr><?php endif;?></tbody></table></div>
<div class="pager"><?php if($page>1):?><a href="?<?=http_build_query(['q'=>$q,'status'=>$status,'page'=>$page-1])?>">← Previous</a><?php endif;?><span>Page <?=$page?> of <?=$total_pages?></span><?php if($page<$total_pages):?><a href="?<?=http_build_query(['q'=>$q,'status'=>$status,'page'=>$page+1])?>">Next →</a><?php endif;?></div>
</section></main></body></html>