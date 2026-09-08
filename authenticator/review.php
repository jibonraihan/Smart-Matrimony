<?php
require_once '../config/db.php';
// Keep Authenticator login isolated from the normal user/admin session.
if (session_status() === PHP_SESSION_ACTIVE && session_name() !== 'SMART_AUTH_SESSION') {
    session_write_close();
}
if (session_status() === PHP_SESSION_NONE) {
    session_name('SMART_AUTH_SESSION');
    session_start();
}
$auth_id=(int)($_SESSION['authenticator_user_id']??0);if($auth_id<=0){header('Location: login.php');exit;}
$st=mysqli_prepare($conn,"SELECT first_name,last_name,role,account_status FROM users WHERE user_id=? LIMIT 1");mysqli_stmt_bind_param($st,'i',$auth_id);mysqli_stmt_execute($st);$me=mysqli_fetch_assoc(mysqli_stmt_get_result($st));mysqli_stmt_close($st);
if(!$me||$me['role']!=='Authenticator'||$me['account_status']!=='Active'){$_SESSION=[];session_destroy();header('Location: login.php?reason=access');exit;}
if(empty($_SESSION['auth_csrf']))$_SESSION['auth_csrf']=bin2hex(random_bytes(32));$csrf=$_SESSION['auth_csrf'];$message='';$error='';
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}function pid($id){return 'SM-'.str_pad((string)$id,6,'0',STR_PAD_LEFT);}
$profile_id=(int)($_GET['profile_id']??$_POST['profile_id']??0);
if($profile_id<=0){header('Location: dashboard.php');exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($csrf,(string)($_POST['csrf']??''))){$error='Security check failed. Please refresh and try again.';}
 else{$action=$_POST['verification_action']??'';$remarks=trim($_POST['remarks']??'');
  if(!in_array($action,['Verified','Rejected'],true))$error='Invalid verification action.';
  elseif($action==='Rejected'&&$remarks==='')$error='Please provide a reason when rejecting a profile.';
  else{
   mysqli_begin_transaction($conn);
   try{
    $st=mysqli_prepare($conn,"UPDATE user_profiles SET verification_status=? WHERE profile_id=?");mysqli_stmt_bind_param($st,'si',$action,$profile_id);if(!mysqli_stmt_execute($st)||mysqli_stmt_affected_rows($st)!==1)throw new Exception('Profile could not be updated.');mysqli_stmt_close($st);
    $st=mysqli_prepare($conn,"INSERT INTO verification_logs(authenticator_id,profile_id,action,remarks) VALUES(?,?,?,?)");mysqli_stmt_bind_param($st,'iiss',$auth_id,$profile_id,$action,$remarks);if(!mysqli_stmt_execute($st))throw new Exception('Verification log could not be saved.');mysqli_stmt_close($st);
    mysqli_commit($conn);$message='Profile marked '.$action.'.';
   }catch(Throwable $e){mysqli_rollback($conn);$error='Unable to update verification right now.';}
  }
 }
}
$st=mysqli_prepare($conn,"SELECT p.*,u.email,u.mobile,u.account_status,u.role FROM user_profiles p JOIN users u ON u.user_id=p.user_id WHERE p.profile_id=? AND u.role<>'Admin' LIMIT 1");mysqli_stmt_bind_param($st,'i',$profile_id);mysqli_stmt_execute($st);$p=mysqli_fetch_assoc(mysqli_stmt_get_result($st));mysqli_stmt_close($st);
if(!$p){header('Location: dashboard.php');exit;}
$age='Not informed';if(!empty($p['date_of_birth'])){$dob=new DateTime($p['date_of_birth']);$now=new DateTime();$age=$dob->diff($now)->y.' years';}
$loc=[];foreach(['area','upazila','district','division'] as $k){if(!empty($p[$k]))$loc[]=$p[$k];}$location=$loc?implode(', ',$loc):'Not informed';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Review Profile</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="../assets/css/authenticator.css"></head>
<body><header class="auth-header compact"><div><span>SMART MATRIMONY</span><h1>Profile Verification Review</h1><p>Review submitted information before making a verification decision.</p></div><nav><a href="dashboard.php">← Verification Center</a><a href="logout.php">Logout</a></nav></header>
<main class="auth-wrap"><?php if($message):?><div class="flash success"><?=h($message)?></div><?php endif;?><?php if($error):?><div class="flash error"><?=h($error)?></div><?php endif;?>
<section class="review-hero"><div><span class="id-pill"><?=h(pid($p['user_id']))?></span><h2><?=h($p['first_name'].' '.$p['last_name'])?></h2><p><?=h($p['gender'])?> · <?=h($age)?> · <?=h($p['religion'])?></p></div><span class="status <?=strtolower($p['verification_status'])?>"><?=h($p['verification_status'])?></span></section>
<div class="detail-grid"><section><h3>Personal Information</h3><dl><dt>Marital Status</dt><dd><?=h($p['marital_status']?:'Not informed')?></dd><dt>Madhhab</dt><dd><?=h($p['madhhab']?:'Not informed')?></dd><dt>Education</dt><dd><?=h($p['highest_education']?:'Not informed')?></dd><dt>Profession</dt><dd><?=h($p['profession']?:'Not informed')?></dd><dt>Income</dt><dd><?=h($p['monthly_income']!==null&&$p['monthly_income']!==''?'৳ '.number_format((float)$p['monthly_income'],2):'Not informed')?></dd></dl></section>
<section><h3>Location & Contact</h3><dl><dt>Location</dt><dd><?=h($location)?></dd><dt>Email</dt><dd><?=h($p['email'])?></dd><dt>Mobile</dt><dd><?=h($p['mobile'])?></dd><dt>Country</dt><dd><?=h($p['country']?:'Not informed')?></dd><dt>NID</dt><dd><?=!empty($p['nid_number'])?'•••• '.h(substr($p['nid_number'],-4)):'Not informed'?></dd></dl></section>
<section><h3>Profile Information</h3><dl><dt>Height</dt><dd><?=h($p['height_cm']!==null?$p['height_cm'].' cm':'Not informed')?></dd><dt>Weight</dt><dd><?=h($p['weight_kg']!==null?$p['weight_kg'].' kg':'Not informed')?></dd><dt>Prayer</dt><dd><?=h($p['prayer_status']?:'Not informed')?></dd><dt>Smoking</dt><dd><?=h($p['smoking_status']?:'Not informed')?></dd><dt>Mahram</dt><dd><?=h($p['mahram_maintained']===null?'Not informed':($p['mahram_maintained']?'Yes':'No'))?></dd></dl></section>
<section><h3>Family Information</h3><dl><dt>Father Profession</dt><dd><?=h($p['father_profession']?:'Not informed')?></dd><dt>Mother Profession</dt><dd><?=h($p['mother_profession']?:'Not informed')?></dd><dt>Family Type</dt><dd><?=h($p['family_type']?:'Not informed')?></dd><dt>Family Status</dt><dd><?=h($p['family_status']?:'Not informed')?></dd></dl></section></div>
<section class="decision"><h3>Verification Decision</h3><p>Use <b>Reject</b> only when you can explain what needs attention. The decision is recorded in the verification audit log.</p><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="profile_id" value="<?=$profile_id?>"><textarea name="remarks" placeholder="Remarks / reason (required for rejection)"></textarea><div class="decision-actions"><button name="verification_action" value="Verified" class="verify">✓ Verify Profile</button><button name="verification_action" value="Rejected" class="reject">✕ Reject Profile</button></div></form></section></main></body></html>