<?php
require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/manager_role_transition.php';
require_once __DIR__ . '/../config/mail.php';
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function public_id($id){return 'SM-'.str_pad((string)(int)$id,6,'0',STR_PAD_LEFT);}
$message=''; $error='';
$uid=(int)($_GET['user_id']??$_POST['user_id']??0);
if($uid<=0 || $uid===$admin_id){header('Location: users.php');exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($csrf,(string)($_POST['csrf']??''))){$error='Security check failed. Please refresh and try again.';}
 else{
  $action=$_POST['action']??'';
  if($action==='activity_match_update'){
   $match_id=(int)($_POST['match_id']??0); $status=$_POST['match_status']??''; $relationship_active=!empty($_POST['relationship_active'])?1:0;
   $allowed=['Pending','Accepted','Rejected','Cancelled','Unmatched'];
   if($match_id<=0 || !in_array($status,$allowed,true)){ $error='Invalid match control value.'; }
   else{
    if($status!=='Accepted') $relationship_active=0;
    $st=mysqli_prepare($conn,"UPDATE matches SET status=?, relationship_active=? WHERE match_id=? AND (sender_user_id=? OR receiver_user_id=?)");
    if($st){ mysqli_stmt_bind_param($st,'siiii',$status,$relationship_active,$match_id,$uid,$uid); if(mysqli_stmt_execute($st) && mysqli_stmt_affected_rows($st)>=0) $message='Match activity updated successfully.'; else $error='Unable to update match activity.'; mysqli_stmt_close($st); } else $error='Unable to prepare match control.';
   }
  } elseif($action==='activity_chat_update'){
   $chat_id=(int)($_POST['chat_request_id']??0); $status=$_POST['chat_status']??''; $chat_active=!empty($_POST['chat_active'])?1:0;
   $allowed=['Pending','Accepted','Rejected','Cancelled'];
   if($chat_id<=0 || !in_array($status,$allowed,true)){ $error='Invalid chat control value.'; }
   else{
    if($status!=='Accepted') $chat_active=0;
    $st=mysqli_prepare($conn,"UPDATE chat_requests SET status=?, chat_active=?, responded_at=CASE WHEN ? IN ('Accepted','Rejected','Cancelled') THEN COALESCE(responded_at,NOW()) ELSE responded_at END, closed_at=CASE WHEN ?='Cancelled' OR ?='Rejected' OR ?=0 THEN COALESCE(closed_at,NOW()) ELSE closed_at END WHERE chat_request_id=? AND (sender_user_id=? OR receiver_user_id=?)");
    if($st){ mysqli_stmt_bind_param($st,'sisissiiii',$status,$chat_active,$status,$status,$status,$chat_active,$chat_id,$uid,$uid); if(mysqli_stmt_execute($st)) $message='Chat request updated successfully.'; else $error='Unable to update chat request.'; mysqli_stmt_close($st); } else $error='Unable to prepare chat control.';
   }
  } elseif($action==='activity_bookmark_delete'){
   $bookmark_id=(int)($_POST['bookmark_id']??0);
   $st=mysqli_prepare($conn,"DELETE FROM bookmarks WHERE bookmark_id=? AND user_id=?");
   if($st){mysqli_stmt_bind_param($st,'ii',$bookmark_id,$uid);if(mysqli_stmt_execute($st))$message='Bookmark removed successfully.';else$error='Unable to remove bookmark.';mysqli_stmt_close($st);}else$error='Unable to prepare bookmark control.';
  } elseif($action==='activity_message_delete'){
   $message_id=(int)($_POST['message_id']??0);
   $st=mysqli_prepare($conn,"DELETE FROM conversation_messages WHERE message_id=? AND sender_user_id=?");
   if($st){mysqli_stmt_bind_param($st,'ii',$message_id,$uid);if(mysqli_stmt_execute($st))$message='Message removed successfully.';else$error='Unable to remove message.';mysqli_stmt_close($st);}else$error='Unable to prepare message control.';
  } elseif($action==='activity_booking_update'){
   $booking_id=(int)($_POST['booking_id']??0); $status=$_POST['booking_status']??''; $allowed=['Pending','Confirmed','Completed','Cancelled'];
   if($booking_id<=0 || !in_array($status,$allowed,true)){ $error='Invalid booking control value.'; }
   else{
    $st=mysqli_prepare($conn,"UPDATE bookings SET booking_status=? WHERE booking_id=? AND user_id=?");
    if($st){mysqli_stmt_bind_param($st,'sii',$status,$booking_id,$uid);if(mysqli_stmt_execute($st))$message='Service booking updated successfully.';else$error='Unable to update service booking.';mysqli_stmt_close($st);}else$error='Unable to prepare booking control.';
   }
  } elseif($action==='delete_user'){
   $confirm=trim((string)($_POST['confirm_text']??''));
   if($confirm!=='DELETE'){
    $error='Type DELETE exactly to permanently remove the account.';
   } else {
    mysqli_begin_transaction($conn);
    try {
     // Verification history may reference an authenticator as the acting user.
     $st=mysqli_prepare($conn,"DELETE FROM verification_logs WHERE authenticator_id=?");
     if($st){mysqli_stmt_bind_param($st,'i',$uid);if(!mysqli_stmt_execute($st)){mysqli_stmt_close($st);throw new RuntimeException('Unable to remove verification history.');}mysqli_stmt_close($st);}
     else throw new RuntimeException('Unable to prepare verification history cleanup.');

     // User-owned records are removed by the database's ON DELETE CASCADE rules.
     // Manager-owned packages/providers and manager references use SET NULL in the schema.
     $st=mysqli_prepare($conn,"DELETE FROM users WHERE user_id=? AND role<>'Admin'");
     if(!$st) throw new RuntimeException('Unable to prepare account removal.');
     mysqli_stmt_bind_param($st,'i',$uid);
     if(!mysqli_stmt_execute($st) || mysqli_stmt_affected_rows($st)!==1){mysqli_stmt_close($st);throw new RuntimeException('Account could not be removed.');}
     mysqli_stmt_close($st);
     mysqli_commit($conn);
     header('Location: users.php?deleted=1'); exit;
    } catch(Throwable $e){
     mysqli_rollback($conn);
     $error='Unable to permanently remove this account. Please check related records and try again.';
    }
   }
  } elseif($action==='moderate'){
   $role=$_POST['role']??''; $account=$_POST['account_status']??''; $verification=$_POST['verification_status']??''; $verification_remarks=trim((string)($_POST['verification_remarks']??'')); $profile_visibility=$_POST['profile_visibility']??''; $photo_visibility=$_POST['photo_visibility']??''; $voice_visibility=$_POST['voice_visibility']??''; $video_visibility=$_POST['video_visibility']??'';
   $roles=['User','Manager','Authenticator']; $accounts=['Active','Inactive','Suspended']; $ver=['Pending','Verified','Rejected']; $pv=['Public','Hidden']; $ph=['Everyone','Verified Users','Matched Users','Hidden']; $mv=['Everyone','Verified Users','Matched Users','Private'];
   if(!in_array($role,$roles,true)||!in_array($account,$accounts,true)||!in_array($verification,$ver,true)||!in_array($profile_visibility,$pv,true)||!in_array($photo_visibility,$ph,true)||!in_array($voice_visibility,$mv,true)||!in_array($video_visibility,$mv,true)){$error='Invalid moderation value.';}
   elseif(!empty($_POST['has_profile']) && $verification==='Rejected' && $verification_remarks===''){$error='Please provide a reason when rejecting a profile.';}
   else{
    $has_profile=!empty($_POST['has_profile']);
    $previous_verification=null; $verification_email=''; $verification_first_name='';
    if($has_profile){
     $notify_stmt=mysqli_prepare($conn,"SELECT up.verification_status,u.email,u.first_name FROM user_profiles up INNER JOIN users u ON u.user_id=up.user_id WHERE up.user_id=? AND u.role='User' LIMIT 1");
     if($notify_stmt){mysqli_stmt_bind_param($notify_stmt,'i',$uid);mysqli_stmt_execute($notify_stmt);$notify_row=mysqli_fetch_assoc(mysqli_stmt_get_result($notify_stmt))?:null;mysqli_stmt_close($notify_stmt);if($notify_row){$previous_verification=$notify_row['verification_status'];$verification_email=$notify_row['email'];$verification_first_name=$notify_row['first_name'];}}
    }
    $profile_update=function(mysqli $db, int $account_id) use ($has_profile,$verification,$profile_visibility,$photo_visibility,$voice_visibility,$video_visibility): void {
     if(!$has_profile) return;
     $st=mysqli_prepare($db,"UPDATE user_profiles SET verification_status=?,profile_visibility=?,photo_visibility=? WHERE user_id=?");
     mysqli_stmt_bind_param($st,'sssi',$verification,$profile_visibility,$photo_visibility,$account_id); if(!mysqli_stmt_execute($st)){mysqli_stmt_close($st);throw new RuntimeException('Unable to update profile moderation settings.');} mysqli_stmt_close($st);
     $st=mysqli_prepare($db,"UPDATE profile_media SET visibility=? WHERE user_id=? AND media_type='Voice Introduction' AND status='Active'");
     if(!$st) throw new RuntimeException('Unable to update voice visibility.'); mysqli_stmt_bind_param($st,'si',$voice_visibility,$account_id); if(!mysqli_stmt_execute($st)){mysqli_stmt_close($st);throw new RuntimeException('Unable to update voice visibility.');} mysqli_stmt_close($st);
     $st=mysqli_prepare($db,"UPDATE profile_media SET visibility=? WHERE user_id=? AND media_type='Video Introduction' AND status='Active'");
     if(!$st) throw new RuntimeException('Unable to update video visibility.'); mysqli_stmt_bind_param($st,'si',$video_visibility,$account_id); if(!mysqli_stmt_execute($st)){mysqli_stmt_close($st);throw new RuntimeException('Unable to update video visibility.');} mysqli_stmt_close($st);
    };
    $replacement_raw=$_POST['replacement_manager_id']??''; $replacement=$replacement_raw!==''?(int)$replacement_raw:null;
    $transition=transition_manager_role($conn,$uid,$role,$admin_id,$replacement,$account,$profile_update);
    if($transition['ok']){
     $message=$has_profile?'Account and profile controls updated successfully.':'Account controls updated successfully. This user has no profile yet.';
     $verification_changed=$has_profile && $role==='User' && $previous_verification!==null && in_array($verification,['Verified','Rejected'],true) && $previous_verification!==$verification;
     if($verification_changed && $verification_email!==''){
      $mail_sent=send_verification_status_email($verification_email,$verification_first_name,$verification,$verification_remarks,'Smart Matrimony Administration');
      $message.=$mail_sent?' The member has been notified by email.':' The changes were saved, but the verification notification email could not be sent.';
     }
    } else $error=$transition['message'];
   }
  }
 }
}
$stmt=mysqli_prepare($conn,"SELECT u.*,up.profile_id,up.date_of_birth,up.religion,up.madhhab,up.marital_status,up.bio,up.photo,up.height_cm,up.weight_kg,up.complexion,up.country,up.area,up.area_type,up.residence_type,up.permanent_hometown,up.division_id,up.district_id,up.upazila_id,up.nid_number,up.post_office,up.postal_code,up.highest_education,up.profession,up.occupation_details,up.monthly_income,up.father_name,up.father_profession,up.mother_name,up.mother_profession,up.family_type,up.family_status,up.siblings,up.brothers_count,up.sisters_count,up.living_with_family,up.smoking_status,up.prayer_status,up.beard_status,up.hijab_status,up.quran_reading,up.fasting_status,up.religious_practice,up.islamic_knowledge,up.halal_lifestyle,up.tea_coffee,up.diet_preference,up.sleep_pattern,up.personality_type,up.social_nature,up.free_time_interests,up.travel_interest,up.spending_style,up.pets,up.verification_status,up.profile_visibility,up.photo_visibility,up.updated_at,dv.name_bn AS division_name_bn,ds.name_bn AS district_name_bn,uz.name_bn AS upazila_name_bn
FROM users u LEFT JOIN user_profiles up ON up.user_id=u.user_id LEFT JOIN divisions dv ON dv.id=up.division_id LEFT JOIN districts ds ON ds.id=up.district_id LEFT JOIN upazilas uz ON uz.id=up.upazila_id WHERE u.user_id=? AND u.role<>'Admin' LIMIT 1");
mysqli_stmt_bind_param($stmt,'i',$uid);mysqli_stmt_execute($stmt);$res=mysqli_stmt_get_result($stmt);$user=mysqli_fetch_assoc($res);mysqli_stmt_close($stmt);if(!$user){header('Location: users.php');exit;}
$manager_package_count=0; $manager_replacements=[];
if(($user['role']??'')==='Manager'){ $manager_package_count=manager_current_package_count($conn,$uid); $manager_replacements=manager_available_replacements($conn,$uid); }
$age='Not informed'; if(!empty($user['date_of_birth'])){$dob=new DateTime($user['date_of_birth']);$now=new DateTime();$age=$dob->diff($now)->y.' yrs';}
$height='Not informed'; if(!empty($user['height_cm'])){$cm=(int)$user['height_cm'];$in=round($cm/2.54);$ft=intdiv($in,12);$inch=$in%12;$height=$ft.' ft '. $inch.' in';}
$location_parts=[];foreach(['area','upazila_name_bn','district_name_bn','division_name_bn'] as $f){if(!empty($user[$f]))$location_parts[]=$user[$f];}$location=$location_parts?implode(', ',$location_parts):'Not informed';
$interest_tags=[]; if(!empty($user['free_time_interests'])){ $decoded=json_decode((string)$user['free_time_interests'],true); if(is_array($decoded)){ foreach($decoded as $tag){ if(is_scalar($tag) && trim((string)$tag)!=='') $interest_tags[]=trim((string)$tag); } } else { $parts=preg_split('/\s*,\s*/',(string)$user['free_time_interests']); foreach($parts as $tag){ if(trim($tag)!=='') $interest_tags[]=trim($tag); } } }
$activity=[
 'interests_sent'=>0,'interests_received'=>0,'accepted_matches'=>0,'active_matches'=>0,
 'bookmarks'=>0,'chat_requests'=>0,'active_chats'=>0,'messages'=>0
];
$activity_stmt=mysqli_prepare($conn,"SELECT
 (SELECT COUNT(*) FROM matches WHERE sender_user_id=?) AS interests_sent,
 (SELECT COUNT(*) FROM matches WHERE receiver_user_id=?) AS interests_received,
 (SELECT COUNT(*) FROM matches WHERE (sender_user_id=? OR receiver_user_id=?) AND status='Accepted') AS accepted_matches,
 (SELECT COUNT(*) FROM matches WHERE (sender_user_id=? OR receiver_user_id=?) AND status='Accepted' AND relationship_active=1) AS active_matches,
 (SELECT COUNT(*) FROM bookmarks WHERE user_id=?) AS bookmarks,
 (SELECT COUNT(*) FROM chat_requests WHERE sender_user_id=? OR receiver_user_id=?) AS chat_requests,
 (SELECT COUNT(*) FROM chat_requests WHERE (sender_user_id=? OR receiver_user_id=?) AND status='Accepted' AND chat_active=1) AS active_chats,
 (SELECT COUNT(*) FROM conversation_messages WHERE sender_user_id=?) AS messages");
if($activity_stmt){ mysqli_stmt_bind_param($activity_stmt,'iiiiiiiiiiii',$uid,$uid,$uid,$uid,$uid,$uid,$uid,$uid,$uid,$uid,$uid,$uid); mysqli_stmt_execute($activity_stmt); $activity_row=mysqli_fetch_assoc(mysqli_stmt_get_result($activity_stmt)); if($activity_row){ foreach($activity as $key=>$_){$activity[$key]=(int)($activity_row[$key]??0);} } mysqli_stmt_close($activity_stmt);}
$voice_visibility='Verified Users'; $video_visibility='Verified Users'; $media_records=['Profile Photo'=>null,'Voice Introduction'=>null,'Video Introduction'=>null];
$media_stmt=mysqli_prepare($conn,"SELECT media_id,media_type,file_path,duration_seconds,visibility,status,created_at,updated_at FROM profile_media WHERE user_id=? AND status IN ('Active','Pending','Rejected') ORDER BY media_id DESC");
if($media_stmt){mysqli_stmt_bind_param($media_stmt,'i',$uid);mysqli_stmt_execute($media_stmt);$media_result=mysqli_stmt_get_result($media_stmt);while($media_result && ($media_row=mysqli_fetch_assoc($media_result))){$type=$media_row['media_type']; if($media_records[$type]===null) $media_records[$type]=$media_row; if($type==='Voice Introduction' && $media_row['status']==='Active' && $voice_visibility==='Verified Users') $voice_visibility=(string)$media_row['visibility']; if($type==='Video Introduction' && $media_row['status']==='Active' && $video_visibility==='Verified Users') $video_visibility=(string)$media_row['visibility'];}mysqli_stmt_close($media_stmt);}
$match_records=[]; $st=mysqli_prepare($conn,"SELECT m.match_id,m.status,m.relationship_active,m.created_at,m.sender_user_id,m.receiver_user_id,CONCAT(CASE WHEN m.sender_user_id=? THEN ru.first_name ELSE su.first_name END,' ',CASE WHEN m.sender_user_id=? THEN ru.last_name ELSE su.last_name END) AS other_name FROM matches m JOIN users su ON su.user_id=m.sender_user_id JOIN users ru ON ru.user_id=m.receiver_user_id WHERE m.sender_user_id=? OR m.receiver_user_id=? ORDER BY m.created_at DESC,m.match_id DESC LIMIT 20"); if($st){mysqli_stmt_bind_param($st,'iiii',$uid,$uid,$uid,$uid);mysqli_stmt_execute($st);$rr=mysqli_stmt_get_result($st);while($rr && ($r=mysqli_fetch_assoc($rr)))$match_records[]=$r;mysqli_stmt_close($st);}
$chat_records=[]; $st=mysqli_prepare($conn,"SELECT cr.chat_request_id,cr.status,cr.chat_active,cr.created_at,CONCAT(CASE WHEN cr.sender_user_id=? THEN ru.first_name ELSE su.first_name END,' ',CASE WHEN cr.sender_user_id=? THEN ru.last_name ELSE su.last_name END) AS other_name FROM chat_requests cr JOIN users su ON su.user_id=cr.sender_user_id JOIN users ru ON ru.user_id=cr.receiver_user_id WHERE cr.sender_user_id=? OR cr.receiver_user_id=? ORDER BY cr.created_at DESC,cr.chat_request_id DESC LIMIT 20"); if($st){mysqli_stmt_bind_param($st,'iiii',$uid,$uid,$uid,$uid);mysqli_stmt_execute($st);$rr=mysqli_stmt_get_result($st);while($rr && ($r=mysqli_fetch_assoc($rr)))$chat_records[]=$r;mysqli_stmt_close($st);}
$bookmark_records=[]; $st=mysqli_prepare($conn,"SELECT b.bookmark_id,b.created_at,CONCAT(u.first_name,' ',u.last_name) AS other_name FROM bookmarks b JOIN users u ON u.user_id=b.bookmarked_user_id WHERE b.user_id=? ORDER BY b.created_at DESC,b.bookmark_id DESC LIMIT 20"); if($st){mysqli_stmt_bind_param($st,'i',$uid);mysqli_stmt_execute($st);$rr=mysqli_stmt_get_result($st);while($rr && ($r=mysqli_fetch_assoc($rr)))$bookmark_records[]=$r;mysqli_stmt_close($st);}
$message_records=[]; $st=mysqli_prepare($conn,"SELECT cm.message_id,cm.message,cm.created_at,cm.conversation_id,CONCAT(u.first_name,' ',u.last_name) AS sender_name FROM conversation_messages cm JOIN users u ON u.user_id=cm.sender_user_id WHERE cm.sender_user_id=? ORDER BY cm.created_at DESC,cm.message_id DESC LIMIT 20"); if($st){mysqli_stmt_bind_param($st,'i',$uid);mysqli_stmt_execute($st);$rr=mysqli_stmt_get_result($st);while($rr && ($r=mysqli_fetch_assoc($rr)))$message_records[]=$r;mysqli_stmt_close($st);}
$booking_records=[]; $st=mysqli_prepare($conn,"SELECT b.booking_id,b.total_price,b.booking_status,b.booking_date,bd.event_date,bd.quantity,sp.provider_name,sp.package_name,s.service_name FROM bookings b LEFT JOIN booking_details bd ON bd.booking_id=b.booking_id LEFT JOIN service_providers sp ON sp.provider_id=bd.provider_id LEFT JOIN services s ON s.service_id=sp.service_id WHERE b.user_id=? ORDER BY b.booking_date DESC,b.booking_id DESC,bd.detail_id ASC LIMIT 30"); if($st){mysqli_stmt_bind_param($st,'i',$uid);mysqli_stmt_execute($st);$rr=mysqli_stmt_get_result($st);while($rr && ($r=mysqli_fetch_assoc($rr)))$booking_records[]=$r;mysqli_stmt_close($st);}

?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>User Details | Smart Matrimony</title><link rel="stylesheet" href="../assets/css/admin.css"><link rel="stylesheet" href="../assets/css/admin-users.css"></head><body>
<header class="user-admin-topbar">
  <div class="user-admin-brand">
    <img src="../assets/images/logo/logo.png" alt="Smart Matrimony logo" class="user-admin-logo">
    <img src="../assets/images/logo/matrimony_title.png" alt="Smart Matrimony" class="user-admin-title-image">
  </div>
  <a href="logout.php" class="user-admin-logout" aria-label="Logout">
    <svg class="admin-logout-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path d="M10 5H6.8A1.8 1.8 0 0 0 5 6.8v10.4A1.8 1.8 0 0 0 6.8 19H10" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
      <path d="M13 8l4 4-4 4M9 12h8" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <span>Logout</span>
  </a>
</header>
<main class="wrap detail-wrap">
<a class="user-back-btn" href="users.php">&#8592; Back to User Management</a>
<?php if($message):?><div class="admin-toast success">✓ <?=h($message)?></div><?php endif;?><?php if($error):?><div class="admin-toast error">! <?=h($error)?></div><?php endif;?>
<section class="detail-hero panel">
  <div class="detail-avatar"><?php if(!empty($user['photo'])):?><img src="../uploads/profile/<?=h($user['photo'])?>" alt="Profile photo of <?=h($user['first_name'].' '.$user['last_name'])?>"><?php else:?><span aria-hidden="true">👤</span><?php endif;?></div>
  <div class="detail-hero-copy">
    <div class="detail-hero-topline"><span class="eyebrow">USER PROFILE</span><span class="public-id large"><?=h(public_id($uid))?></span></div>
    <h1><?=h($user['first_name'].' '.$user['last_name'])?></h1>
    <p class="detail-hero-lead">Administrative profile overview and current account status.</p>
    <div class="detail-meta">
      <span><i aria-hidden="true">◈</i><?=h($user['gender'])?></span>
      <span><i aria-hidden="true">@</i><?=h($user['email'])?></span>
      <span><i aria-hidden="true">☎</i><?=h($user['mobile'])?></span>
      <span><i aria-hidden="true">⌖</i><?=h($location)?></span>
    </div>
    <div class="detail-badges" aria-label="Account status">
      <span class="detail-badge role"><?=h($user['role'])?></span>
      <span class="detail-badge account <?=strtolower(h($user['account_status']))?>"><?=h($user['account_status'])?></span>
      <?php if($user['profile_id']):?><span class="detail-badge verification <?=strtolower(h($user['verification_status']))?>"><?=h($user['verification_status'])?></span><?php else:?><span class="detail-badge neutral">No Profile</span><?php endif;?>
    </div>
  </div>
</section>
<section class="panel moderation-panel">
  <div class="panel-head moderation-head">
    <div><span class="eyebrow">ADMIN CONTROL</span><h2>Account &amp; Profile Moderation</h2></div>
    <span class="moderation-note">Update account, verification and media visibility controls.</span>
  </div>
  <form method="post" class="moderation-grid compact">
    <input type="hidden" name="csrf" value="<?=h($csrf)?>">
    <input type="hidden" name="user_id" value="<?=$uid?>">
    <input type="hidden" name="action" value="moderate">
    <input type="hidden" name="has_profile" value="<?=!empty($user['profile_id'])?1:0?>">
    <label class="moderation-field role-field"><span>Role</span><select name="role"><?php foreach(['User','Manager','Authenticator'] as $r):?><option <?=$user['role']===$r?'selected':''?>><?=$r?></option><?php endforeach; ?></select></label>
    <label class="moderation-field account-field"><span>Account status</span><select name="account_status"><?php foreach(['Active','Inactive','Suspended'] as $s):?><option <?=$user['account_status']===$s?'selected':''?>><?=$s?></option><?php endforeach; ?></select></label>
    <?php if(($user['role']??'')==='Manager' && $manager_package_count>0): ?>
      <label class="moderation-field replacement-field"><span>Package replacement Manager</span><select name="replacement_manager_id"><option value="">Select replacement</option><?php foreach($manager_replacements as $replacement): ?><option value="<?=h($replacement['user_id'])?>"><?=h($replacement['first_name'].' '.$replacement['last_name'])?> · <?=h(public_id($replacement['user_id']))?></option><?php endforeach; ?></select></label>
    <?php endif; ?>
    <label class="moderation-field verification-field"><span>Verification</span><select name="verification_status" <?=!$user['profile_id']?'disabled':''?>><?php foreach(['Pending','Verified','Rejected'] as $v):?><option <?=$user['verification_status']===$v?'selected':''?>><?=$v?></option><?php endforeach; ?></select></label>
    <label class="moderation-field profile-field"><span>Profile visibility</span><select name="profile_visibility" <?=!$user['profile_id']?'disabled':''?>><?php foreach(['Public','Hidden'] as $v):?><option <?=$user['profile_visibility']===$v?'selected':''?>><?=$v?></option><?php endforeach; ?></select></label>
    <label class="moderation-field photo-field"><span>Photo visibility</span><select name="photo_visibility" <?=!$user['profile_id']?'disabled':''?>><?php foreach(['Everyone','Verified Users','Matched Users','Hidden'] as $v):?><option <?=$user['photo_visibility']===$v?'selected':''?>><?=$v?></option><?php endforeach;?></select></label>
    <label class="moderation-field voice-field"><span>Voice visibility</span><select name="voice_visibility" <?=!$user['profile_id']?'disabled':''?>><?php foreach(['Everyone','Verified Users','Matched Users','Private'] as $v):?><option <?=$voice_visibility===$v?'selected':''?>><?=$v?></option><?php endforeach;?></select></label>
    <label class="moderation-field video-field"><span>Video visibility</span><select name="video_visibility" <?=!$user['profile_id']?'disabled':''?>><?php foreach(['Everyone','Verified Users','Matched Users','Private'] as $v):?><option <?=$video_visibility===$v?'selected':''?>><?=$v?></option><?php endforeach;?></select></label>
    <label class="verification-remarks-field"><span>Verification remarks</span><textarea name="verification_remarks" rows="2" placeholder="Optional for verification; required for rejection"></textarea></label>
    <button type="submit" class="moderation-save">Save Changes</button>
  </form>
</section>
<section class="profile-view-grid">
  <div class="panel profile-view-panel">
    <div class="panel-head profile-view-head"><div><span class="eyebrow">PROFILE INFORMATION</span><h2>Personal &amp; Matrimony</h2></div></div>
    <dl class="info-list">
      <dt>Age</dt><dd><?=h($age)?></dd>
      <dt>Religion</dt><dd><?=h($user['religion']?:'Not informed')?><?=!empty($user['madhhab'])?' · '.h($user['madhhab']):''?></dd>
      <dt>Marital status</dt><dd><?=h($user['marital_status']?:'Not informed')?></dd>
      <dt>Education</dt><dd><?=h($user['highest_education']?:'Not informed')?></dd>
      <dt>Profession</dt><dd><?=h($user['profession']?:'Not informed')?></dd>
      <dt>Occupation</dt><dd><?=h($user['occupation_details']?:'Not informed')?></dd>
      <dt>Income</dt><dd><?=!empty($user['monthly_income'])?'৳ '.number_format((float)$user['monthly_income'],2):'Not informed'?></dd>
      <dt>Bio</dt><dd><?=h($user['bio']?:'Not informed')?></dd>
    </dl>
  </div>
  <div class="panel profile-view-panel">
    <div class="panel-head profile-view-head"><div><span class="eyebrow">APPEARANCE &amp; RESIDENCE</span><h2>Physical &amp; Location</h2></div></div>
    <dl class="info-list">
      <dt>Height</dt><dd><?=h($height)?></dd>
      <dt>Weight</dt><dd><?=!empty($user['weight_kg'])?h($user['weight_kg']).' kg':'Not informed'?></dd>
      <dt>Complexion</dt><dd><?=h($user['complexion']?:'Not informed')?></dd>
      <dt>Location</dt><dd><?=h($location)?></dd>
      <dt>Area type</dt><dd><?=h($user['area_type']?:'Not informed')?></dd>
      <dt>Residence</dt><dd><?=h($user['residence_type']?:'Not informed')?></dd>
      <dt>Hometown</dt><dd><?=h($user['permanent_hometown']?:'Not informed')?></dd>
      <dt>Post office</dt><dd><?=h($user['post_office']?:'Not informed')?><?=!empty($user['postal_code'])?' · '.h($user['postal_code']):''?></dd>
    </dl>
  </div>
  <div class="panel profile-view-panel">
    <div class="panel-head profile-view-head"><div><span class="eyebrow">FAMILY</span><h2>Family Overview</h2></div></div>
    <dl class="info-list">
      <dt>Family type</dt><dd><?=h($user['family_type']?:'Not informed')?></dd>
      <dt>Family status</dt><dd><?=h($user['family_status']?:'Not informed')?></dd>
      <dt>Father</dt><dd><?=h($user['father_name']?:'Not informed')?><?=!empty($user['father_profession'])?' · '.h($user['father_profession']):''?></dd>
      <dt>Mother</dt><dd><?=h($user['mother_name']?:'Not informed')?><?=!empty($user['mother_profession'])?' · '.h($user['mother_profession']):''?></dd>
      <dt>Siblings</dt><dd><?=isset($user['siblings']) && $user['siblings']!==null?h($user['siblings']):'Not informed'?></dd>
      <dt>Brothers / Sisters</dt><dd><?=isset($user['brothers_count']) && $user['brothers_count']!==null?h($user['brothers_count']):'—'?> / <?=isset($user['sisters_count']) && $user['sisters_count']!==null?h($user['sisters_count']):'—'?></dd>
      <dt>Living with family</dt><dd><?=h($user['living_with_family']?:'Not informed')?></dd>
    </dl>
  </div>
  <div class="panel profile-view-panel">
    <div class="panel-head profile-view-head"><div><span class="eyebrow">LIFESTYLE &amp; FAITH</span><h2>Habits &amp; Interests</h2></div></div>
    <dl class="info-list">
      <dt>Prayer</dt><dd><?=h($user['prayer_status']?:'Not informed')?></dd>
      <dt>Quran reading</dt><dd><?=h($user['quran_reading']?:'Not informed')?></dd>
      <dt>Fasting</dt><dd><?=h($user['fasting_status']?:'Not informed')?></dd>
      <dt>Religious practice</dt><dd><?=h($user['religious_practice']?:'Not informed')?></dd>
      <dt>Smoking</dt><dd><?=h($user['smoking_status']?:'Not informed')?></dd>
      <dt>Diet</dt><dd><?=h($user['diet_preference']?:'Not informed')?></dd>
      <dt>Personality</dt><dd><?=h($user['personality_type']?:'Not informed')?></dd>
      <dt>Interests</dt><dd><?php if($interest_tags): ?><div class="interest-tags"><?php foreach($interest_tags as $tag): ?><span class="interest-tag"><?=h($tag)?></span><?php endforeach; ?></div><?php else: ?>Not informed<?php endif; ?></dd>
    </dl>
  </div>
</section>
<section class="media-privacy-panel panel">
  <div class="panel-head profile-view-head">
    <div><span class="eyebrow">MEDIA &amp; PRIVACY</span><h2>Media &amp; Visibility Overview</h2></div>
    <span class="media-privacy-note">View-only administrative overview</span>
  </div>
  <div class="media-privacy-grid">
    <div class="media-privacy-card media-photo-card">
      <div class="media-card-icon">▣</div>
      <div class="media-card-body"><strong>Profile Photo</strong><span><?= $media_records['Profile Photo'] ? 'Available' : (!empty($user['photo']) ? 'Available' : 'Not available') ?></span></div>
      <div class="media-card-meta"><b><?=h($user['photo_visibility']?:'No profile')?></b><small>Visibility</small></div>
    </div>
    <div class="media-privacy-card">
      <div class="media-card-icon">◉</div>
      <div class="media-card-body"><strong>Voice Introduction</strong><span><?= $media_records['Voice Introduction'] ? ucfirst(strtolower($media_records['Voice Introduction']['status'])) : 'Not uploaded' ?></span></div>
      <div class="media-card-meta"><b><?=h($media_records['Voice Introduction']['visibility'] ?? $voice_visibility)?></b><small>Visibility<?=!empty($media_records['Voice Introduction']['duration_seconds'])?' · '.h($media_records['Voice Introduction']['duration_seconds']).' sec':''?></small></div>
    </div>
    <div class="media-privacy-card">
      <div class="media-card-icon">▶</div>
      <div class="media-card-body"><strong>Video Introduction</strong><span><?= $media_records['Video Introduction'] ? ucfirst(strtolower($media_records['Video Introduction']['status'])) : 'Not uploaded' ?></span></div>
      <div class="media-card-meta"><b><?=h($media_records['Video Introduction']['visibility'] ?? $video_visibility)?></b><small>Visibility<?=!empty($media_records['Video Introduction']['duration_seconds'])?' · '.h($media_records['Video Introduction']['duration_seconds']).' sec':''?></small></div>
    </div>
    <div class="media-privacy-card privacy-card">
      <div class="media-card-icon">◉</div>
      <div class="media-card-body"><strong>Profile Privacy</strong><span><?=h($user['profile_visibility']?:'No profile')?></span></div>
      <div class="media-card-meta"><b><?=h($user['verification_status']?:'No profile')?></b><small>Verification</small></div>
    </div>
  </div>
</section>
<section class="detail-grid account-system-grid">
  <div class="panel"><span class="eyebrow">ACCOUNT INFORMATION</span><h2>System Data</h2><dl class="info-list"><dt>User ID</dt><dd><?=h(public_id($uid))?> (#<?=h($uid)?>)</dd><dt>Email</dt><dd><?=h($user['email'])?></dd><dt>Mobile</dt><dd><?=h($user['mobile'])?></dd><dt>Gender</dt><dd><?=h($user['gender'])?></dd><dt>Created</dt><dd><?=h(date('d M Y H:i',strtotime($user['created_at'])))?></dd><dt>Profile updated</dt><dd><?=!empty($user['updated_at'])?h(date('d M Y H:i',strtotime($user['updated_at']))):'No profile'?></dd><dt>Photo visibility</dt><dd><?=h($user['photo_visibility']?:'No profile')?></dd><dt>Voice visibility</dt><dd><?=h($voice_visibility?:'No profile')?></dd><dt>Video visibility</dt><dd><?=h($video_visibility?:'No profile')?></dd></dl></div>
  <div class="panel activity-panel"><div class="panel-head profile-view-head"><div><span class="eyebrow">USER ACTIVITY</span><h2>Matches &amp; Communication</h2></div></div><div class="activity-stats-grid">
    <div class="activity-stat"><span>Interests sent</span><strong><?=number_format($activity['interests_sent'])?></strong></div>
    <div class="activity-stat"><span>Interests received</span><strong><?=number_format($activity['interests_received'])?></strong></div>
    <div class="activity-stat"><span>Accepted matches</span><strong><?=number_format($activity['accepted_matches'])?></strong></div>
    <div class="activity-stat"><span>Active matches</span><strong><?=number_format($activity['active_matches'])?></strong></div>
    <div class="activity-stat"><span>Bookmarks</span><strong><?=number_format($activity['bookmarks'])?></strong></div>
    <div class="activity-stat"><span>Chat requests</span><strong><?=number_format($activity['chat_requests'])?></strong></div>
    <div class="activity-stat"><span>Active chats</span><strong><?=number_format($activity['active_chats'])?></strong></div>
    <div class="activity-stat"><span>Messages sent</span><strong><?=number_format($activity['messages'])?></strong></div>
  </div>
    <div class="activity-controls">
      <div class="activity-controls-head"><div><span class="eyebrow">ADMIN CONTROLS</span><h3>Activity &amp; Service Management</h3></div><span class="activity-controls-note">View and manage the user's activity records</span></div>
      <details open><summary>Matches &amp; interests <span><?=count($match_records)?></span></summary><div class="activity-records"><?php if($match_records): foreach($match_records as $r): ?><form method="post" class="activity-record activity-action-form"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="activity_match_update"><input type="hidden" name="user_id" value="<?=$uid?>"><input type="hidden" name="match_id" value="<?=h($r['match_id'])?>"><div><strong><?=h($r['other_name'])?></strong><small><?=h($r['sender_user_id']==$uid?'Interest sent':'Interest received')?> · <?=h(date('d M Y',strtotime($r['created_at'])))?></small></div><select name="match_status"><?php foreach(['Pending','Accepted','Rejected','Cancelled','Unmatched'] as $v): ?><option value="<?=$v?>" <?=$r['status']===$v?'selected':''?>><?=$v?></option><?php endforeach; ?></select><label class="mini-check"><input type="checkbox" name="relationship_active" value="1" <?=$r['relationship_active']?'checked':''?>> Active</label><button type="submit">Save</button></form><?php endforeach; else: ?><p class="activity-empty">No match or interest records.</p><?php endif; ?></div></details>
      <details><summary>Chat requests <span><?=count($chat_records)?></span></summary><div class="activity-records"><?php if($chat_records): foreach($chat_records as $r): ?><form method="post" class="activity-record activity-action-form"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="activity_chat_update"><input type="hidden" name="user_id" value="<?=$uid?>"><input type="hidden" name="chat_request_id" value="<?=h($r['chat_request_id'])?>"><div><strong><?=h($r['other_name'])?></strong><small><?=h(date('d M Y',strtotime($r['created_at'])))?></small></div><select name="chat_status"><?php foreach(['Pending','Accepted','Rejected','Cancelled'] as $v): ?><option value="<?=$v?>" <?=$r['status']===$v?'selected':''?>><?=$v?></option><?php endforeach; ?></select><label class="mini-check"><input type="checkbox" name="chat_active" value="1" <?=$r['chat_active']?'checked':''?>> Active</label><button type="submit">Save</button></form><?php endforeach; else: ?><p class="activity-empty">No chat request records.</p><?php endif; ?></div></details>
      <details><summary>Bookmarks <span><?=count($bookmark_records)?></span></summary><div class="activity-records"><?php if($bookmark_records): foreach($bookmark_records as $r): ?><form method="post" class="activity-record simple"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="activity_bookmark_delete"><input type="hidden" name="user_id" value="<?=$uid?>"><input type="hidden" name="bookmark_id" value="<?=h($r['bookmark_id'])?>"><div><strong><?=h($r['other_name'])?></strong><small>Bookmarked <?=h(date('d M Y',strtotime($r['created_at'])))?></small></div><button type="submit" class="activity-remove">Remove</button></form><?php endforeach; else: ?><p class="activity-empty">No bookmarks.</p><?php endif; ?></div></details>
      <details><summary>Messages sent <span><?=count($message_records)?></span></summary><div class="activity-records"><?php if($message_records): foreach($message_records as $r): ?><form method="post" class="activity-record simple message-record"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="activity_message_delete"><input type="hidden" name="user_id" value="<?=$uid?>"><input type="hidden" name="message_id" value="<?=h($r['message_id'])?>"><div><strong><?=h(mb_strimwidth($r['message'],0,90,'…','UTF-8'))?></strong><small><?=h(date('d M Y H:i',strtotime($r['created_at'])))?> · Conversation #<?=h($r['conversation_id'])?></small></div><button type="submit" class="activity-remove" onclick="return confirm('Remove this message?')">Remove</button></form><?php endforeach; else: ?><p class="activity-empty">No messages sent.</p><?php endif; ?></div></details>
      <details><summary>Service bookings <span><?=count($booking_records)?></span></summary><div class="activity-records booking-records"><?php if($booking_records): foreach($booking_records as $r): ?><form method="post" class="activity-record booking-record activity-action-form"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="activity_booking_update"><input type="hidden" name="user_id" value="<?=$uid?>"><input type="hidden" name="booking_id" value="<?=h($r['booking_id'])?>"><div><strong>#<?=h($r['booking_id'])?> · <?=h($r['service_name']?:'Service')?></strong><small><?=h($r['provider_name']?:'Provider not found')?><?=!empty($r['package_name'])?' · '.h($r['package_name']):''?> · Event <?=!empty($r['event_date'])?h(date('d M Y',strtotime($r['event_date']))):'—'?> · Qty <?=h($r['quantity']??1)?></small></div><strong class="booking-price">৳ <?=number_format((float)$r['total_price'],2)?></strong><select name="booking_status"><?php foreach(['Pending','Confirmed','Completed','Cancelled'] as $v): ?><option value="<?=$v?>" <?=$r['booking_status']===$v?'selected':''?>><?=$v?></option><?php endforeach; ?></select><button type="submit">Save</button></form><?php endforeach; else: ?><p class="activity-empty">No service bookings found for this user.</p><?php endif; ?></div></details>
      <p class="activity-control-note">Match, chat and service-booking statuses can be moderated here. Bookmarks and sent messages can be removed. Records are scoped to this user only.</p>
    </div>
  </div></div>
</section>
<section class="danger-panel"><div><span class="eyebrow">DANGER ZONE</span><h2>Permanently remove account</h2><p>This permanently deletes the account and all user-owned records covered by the database cascade rules. Manager-owned packages/bookings are not deleted; their owner reference is cleared by the database.</p></div><button type="button" class="btn-danger big" id="open-delete">Remove Account Permanently</button></section>
<div class="delete-modal" id="delete-modal" aria-hidden="true"><div class="delete-card"><div class="warning-icon">!</div><h2>Permanently delete this account?</h2><p>This cannot be undone. Type <strong>DELETE</strong> to confirm.</p><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?=$uid?>"><input name="confirm_text" autocomplete="off" placeholder="Type DELETE" required><div><button type="button" class="btn-light" id="close-delete">Cancel</button><button class="btn-danger" type="submit">Yes, permanently delete</button></div></form></div></div>
</main><script>document.querySelectorAll('form.activity-action-form').forEach(f=>f.addEventListener('submit',()=>sessionStorage.setItem('userDetailsScroll',String(window.scrollY))));window.addEventListener('load',()=>{const y=sessionStorage.getItem('userDetailsScroll');if(y!==null){sessionStorage.removeItem('userDetailsScroll');requestAnimationFrame(()=>window.scrollTo(0,Number(y)));}});const m=document.getElementById('delete-modal');document.getElementById('open-delete').onclick=()=>{m.classList.add('show');m.setAttribute('aria-hidden','false')};document.getElementById('close-delete').onclick=()=>{m.classList.remove('show');m.setAttribute('aria-hidden','true')};m.addEventListener('click',e=>{if(e.target===m){m.classList.remove('show');m.setAttribute('aria-hidden','true')}});</script></body></html>
