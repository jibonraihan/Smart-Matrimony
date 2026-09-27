<?php
require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/manager_role_transition.php';
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function pid($id){return 'SM-'.str_pad((string)$id,6,'0',STR_PAD_LEFT);}
function count_for(mysqli $conn, string $sql, int $uid): int {
    $st=mysqli_prepare($conn,$sql); mysqli_stmt_bind_param($st,'i',$uid); mysqli_stmt_execute($st);
    $row=mysqli_fetch_assoc(mysqli_stmt_get_result($st)); mysqli_stmt_close($st); return (int)($row['c']??0);
}
$uid=(int)($_GET['id']??0);
if($uid<=0){header('Location: authenticators.php');exit;}
$message=''; $error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals($csrf,(string)($_POST['csrf']??''))){$error='Security check failed. Please refresh and try again.';}
    else{
        $action=(string)($_POST['action']??'');
        if($action==='update_account'){
            $role=(string)($_POST['role']??''); $status=(string)($_POST['account_status']??'');
            $allowed_roles=['User','Authenticator','Manager'];
            if(!in_array($role,$allowed_roles,true)||!in_array($status,['Active','Inactive','Suspended'],true)){$error='Invalid account role or status.';}
            else{
                $transition=transition_manager_role($conn,$uid,$role,$admin_id,null,$status);
                if($transition['ok']) $message='Account access updated successfully.'; else $error=$transition['message'];
            }
        }elseif($action==='reset_password'){
            $new=(string)($_POST['new_password']??'');
            if(!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/', $new))$error='Password must be 8+ characters with uppercase, lowercase, number and special character.';
            else{
                $hash=password_hash($new,PASSWORD_DEFAULT);
                $st=mysqli_prepare($conn,"UPDATE users SET password=? WHERE user_id=? AND role<>'Admin'"); mysqli_stmt_bind_param($st,'si',$hash,$uid);
                if(mysqli_stmt_execute($st))$message='Authenticator password reset successfully.';else$error='Unable to reset the password right now.';
                mysqli_stmt_close($st);
            }
        }
    }
}
$st=mysqli_prepare($conn,"SELECT u.user_id,u.first_name,u.last_name,u.gender,u.email,u.mobile,u.role,u.account_status,u.created_at,COALESCE(sp.profile_image,'') AS profile_image FROM users u LEFT JOIN staff_profiles sp ON sp.user_id=u.user_id WHERE u.user_id=? AND u.role<>'Admin' LIMIT 1");
mysqli_stmt_bind_param($st,'i',$uid);mysqli_stmt_execute($st);$auth=mysqli_fetch_assoc(mysqli_stmt_get_result($st));mysqli_stmt_close($st);
if(!$auth){header('Location: authenticators.php');exit;}
$name=trim($auth['first_name'].' '.$auth['last_name']);$initial=strtoupper(substr(trim((string)$auth['first_name']),0,1))?:'A';
$profile_file=basename((string)$auth['profile_image']);$profile_url=$profile_file!==''?'../uploads/staff/'.rawurlencode($profile_file):'';$has_image=$profile_file!==''&&is_file(__DIR__.'/../uploads/staff/'.$profile_file);
$total_reviews=count_for($conn,"SELECT COUNT(*) c FROM verification_logs WHERE authenticator_id=?",$uid);
$verified_reviews=count_for($conn,"SELECT COUNT(*) c FROM verification_logs WHERE authenticator_id=? AND action='Verified'",$uid);
$rejected_reviews=count_for($conn,"SELECT COUNT(*) c FROM verification_logs WHERE authenticator_id=? AND action='Rejected'",$uid);
$profile_reviews=count_for($conn,"SELECT COUNT(DISTINCT profile_id) c FROM verification_logs WHERE authenticator_id=?",$uid);
$last_activity='No verification activity yet';
$st=mysqli_prepare($conn,"SELECT action_at FROM verification_logs WHERE authenticator_id=? ORDER BY action_at DESC,log_id DESC LIMIT 1");mysqli_stmt_bind_param($st,'i',$uid);mysqli_stmt_execute($st);$lr=mysqli_fetch_assoc(mysqli_stmt_get_result($st));mysqli_stmt_close($st);if($lr)$last_activity=date('d M Y, h:i A',strtotime($lr['action_at']));
$history_q=trim((string)($_GET['history_q']??''));
$history_action=(string)($_GET['history_action']??'');
$history_from=(string)($_GET['history_from']??'');
$history_to=(string)($_GET['history_to']??'');
$history_per_page=10;
$history_page=max(1,(int)($_GET['history_page']??1));

if($_SERVER['REQUEST_METHOD']==='POST' && (string)($_POST['action']??'')==='delete_history' && $error===''){
    $selected=[];
    foreach((array)($_POST['history_ids']??[]) as $log_id){$log_id=(int)$log_id;if($log_id>0)$selected[]=$log_id;}
    $selected=array_values(array_unique($selected));
    if(!$selected){$error='Please select at least one verification record to delete.';}
    else{
        $placeholders=implode(',',array_fill(0,count($selected),'?'));
        $types=str_repeat('i',count($selected)+1);
        $params=array_merge([$uid],$selected);
        $st=mysqli_prepare($conn,"DELETE FROM verification_logs WHERE authenticator_id=? AND log_id IN ($placeholders)");
        mysqli_stmt_bind_param($st,$types,...$params);
        if(mysqli_stmt_execute($st)){
            $deleted=mysqli_stmt_affected_rows($st);
            $message=$deleted>0?($deleted.' verification '.($deleted===1?'record':'records').' deleted successfully.'):'No matching verification records were deleted.';
            $history_page=1;
        }else{$error='Unable to delete the selected verification records right now.';}
        mysqli_stmt_close($st);
    }
}

$history_where=["vl.authenticator_id=?"];
$history_types='i';
$history_params=[$uid];
if($history_q!==''){
    $history_where[]="(CONCAT(p.first_name,' ',p.last_name) LIKE ? OR u.email LIKE ? OR CAST(p.user_id AS CHAR) LIKE ? OR CONCAT('SM-',LPAD(p.user_id,6,'0')) LIKE ? OR CAST(vl.profile_id AS CHAR) LIKE ?)";
    $like='%'.$history_q.'%';
    for($i=0;$i<5;$i++){$history_types.='s';$history_params[]=$like;}
}
if(in_array($history_action,['Verified','Rejected'],true)){$history_where[]='vl.action=?';$history_types.='s';$history_params[]=$history_action;}
if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$history_from)){$history_where[]='vl.action_at>=?';$history_types.='s';$history_params[]=$history_from.' 00:00:00';}
if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$history_to)){$history_where[]='vl.action_at<=?';$history_types.='s';$history_params[]=$history_to.' 23:59:59';}
$history_sql_where=implode(' AND ',$history_where);
$st=mysqli_prepare($conn,"SELECT COUNT(*) AS c FROM verification_logs vl JOIN user_profiles p ON p.profile_id=vl.profile_id LEFT JOIN users u ON u.user_id=p.user_id WHERE $history_sql_where");
mysqli_stmt_bind_param($st,$history_types,...$history_params);mysqli_stmt_execute($st);$history_total=(int)(mysqli_fetch_assoc(mysqli_stmt_get_result($st))['c']??0);mysqli_stmt_close($st);
$history_pages=max(1,(int)ceil($history_total/$history_per_page));
if($history_page>$history_pages)$history_page=$history_pages;
$history_offset=($history_page-1)*$history_per_page;
$logs=[];
$st=mysqli_prepare($conn,"SELECT vl.log_id,vl.profile_id,vl.action,vl.remarks,vl.action_at,p.first_name,p.last_name,p.gender,p.verification_status,p.user_id AS profile_user_id,u.email AS profile_email FROM verification_logs vl JOIN user_profiles p ON p.profile_id=vl.profile_id LEFT JOIN users u ON u.user_id=p.user_id WHERE $history_sql_where ORDER BY vl.action_at DESC,vl.log_id DESC LIMIT ? OFFSET ?");
$history_types_page=$history_types.'ii';$history_params_page=$history_params; $history_params_page[]=$history_per_page;$history_params_page[]=$history_offset;
mysqli_stmt_bind_param($st,$history_types_page,...$history_params_page);mysqli_stmt_execute($st);$rr=mysqli_stmt_get_result($st);while($r=mysqli_fetch_assoc($rr))$logs[]=$r;mysqli_stmt_close($st);
$history_start=$history_total?($history_offset+1):0;$history_end=min($history_offset+$history_per_page,$history_total);
$history_query_base=http_build_query(array_filter(['history_q'=>$history_q,'history_action'=>$history_action,'history_from'=>$history_from,'history_to'=>$history_to],static fn($v)=>$v!==''));
$staff_key='Authenticator:'.$uid;
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($name)?> · Authenticator Details</title><link rel="stylesheet" href="../assets/css/admin.css"><link rel="stylesheet" href="../assets/css/manager-details.css"><link rel="stylesheet" href="../assets/css/admin-authenticator-details.css"></head><body>
<?php $admin_header_title='Authenticator Details';$admin_header_subtitle='Review this authenticator account, verification activity and access controls.';$admin_header_actions=[['label'=>'Admin Dashboard','href'=>'dashboard.php'],['label'=>'Authenticators','href'=>'authenticators.php'],['label'=>'Logout','href'=>'logout.php','danger'=>true]];require __DIR__.'/admin_header.php';?>
<main class="manager-wrap manager-details-wrap auth-details-wrap">
<?php if($message):?><div class="flash success"><?=h($message)?></div><?php endif;?><?php if($error):?><div class="flash error"><?=h($error)?></div><?php endif;?>
<a class="manager-back-dashboard auth-detail-back" href="authenticators.php">&#8592; Back to Authenticators</a>
<section class="manager-detail-hero manager-detail-hero-v2">
  <div class="manager-detail-photo auth-detail-photo"><?php if($has_image):?><img src="<?=h($profile_url)?>" alt="<?=h($name)?> profile photo"><?php else:?><span><?=h($initial)?></span><?php endif;?></div>
  <div class="manager-detail-hero-main"><span class="detail-eyebrow">AUTHENTICATOR PROFILE</span><div class="manager-detail-title-row"><div><h1><?=h($name)?></h1><strong class="manager-detail-id"><?=h(pid($auth['user_id']))?></strong></div><span class="manager-detail-status status-<?=strtolower(h($auth['account_status']))?>"><?=h($auth['account_status'])?></span></div><div class="manager-detail-contact"><span><?=h($auth['gender'])?></span><span><?=h($auth['email'])?></span><span><?=h($auth['mobile'])?></span><span>Joined <?=h(date('d M Y',strtotime($auth['created_at'])))?></span></div><div class="manager-detail-hero-actions"><a class="btn-primary" href="staff_messages.php?filter=Authenticator&amp;staff=<?=rawurlencode($staff_key)?>">&#9993; Message Authenticator</a><a class="btn-light" href="authenticators.php">Authenticator Directory</a></div></div>
</section>
<section class="manager-detail-stats-section"><div class="manager-detail-section-heading"><span class="eyebrow">LIVE OVERVIEW</span><h2>Authenticator Statistics</h2></div><div class="manager-detail-stats-grid auth-detail-stats"><article><span class="detail-stat-icon">#</span><div><strong><?=number_format($total_reviews)?></strong><small>Total Reviews</small></div></article><article><span class="detail-stat-icon">✓</span><div><strong><?=number_format($verified_reviews)?></strong><small>Verified</small></div></article><article><span class="detail-stat-icon">×</span><div><strong><?=number_format($rejected_reviews)?></strong><small>Rejected</small></div></article><article><span class="detail-stat-icon">▣</span><div><strong><?=number_format($profile_reviews)?></strong><small>Profiles Reviewed</small></div></article><article class="detail-stat-wide"><span class="detail-stat-icon">◷</span><div><strong><?=h($last_activity)?></strong><small>Last Verification Activity</small></div></article></div></section>
<section class="manager-control manager-detail-section-card manager-detail-panel"><div class="manager-detail-panel-head"><div><span class="eyebrow">ACCOUNT &amp; ACCESS</span><h2>Authenticator Account Control</h2><p>Manage identity access, role and account status for this authenticator.</p></div></div><form method="post" class="manager-account-form"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="update_account"><label>First Name<input value="<?=h($auth['first_name'])?>" disabled></label><label>Last Name<input value="<?=h($auth['last_name'])?>" disabled></label><label>Gender<input value="<?=h($auth['gender'])?>" disabled></label><label>Email<input value="<?=h($auth['email'])?>" disabled></label><label>Mobile<input value="<?=h($auth['mobile'])?>" disabled></label><label>Role<select name="role"><option value="Authenticator" <?=$auth['role']==='Authenticator'?'selected':''?>>Authenticator</option><option value="User" <?=$auth['role']==='User'?'selected':''?>>User</option><option value="Manager" <?=$auth['role']==='Manager'?'selected':''?>>Manager</option></select></label><label>Status<select name="account_status"><option value="Active" <?=$auth['account_status']==='Active'?'selected':''?>>Active</option><option value="Inactive" <?=$auth['account_status']==='Inactive'?'selected':''?>>Inactive</option><option value="Suspended" <?=$auth['account_status']==='Suspended'?'selected':''?>>Suspended</option></select></label><div class="manager-form-action"><button type="submit">Save Account Changes</button></div></form></section>
<section class="manager-detail-section panel manager-detail-section-card"><div class="panel-head"><div><span class="eyebrow">SECURITY</span><h2>Password Control</h2><p class="section-note">Set a new password for this staff account.</p></div></div><form method="post" class="auth-password-form"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="reset_password"><label>New Password<input type="password" name="new_password" minlength="8" placeholder="At least 8 characters" required><small class="password-hint">8+ chars • uppercase • lowercase • number • special character</small></label><button type="submit" class="btn-save">Reset Password</button></form></section>
<section class="manager-detail-section panel manager-detail-section-card"><div class="panel-head auth-history-head"><div><span class="eyebrow">VERIFICATION ACTIVITY</span><h2>Verification History</h2><p class="section-note">Search, filter, review and manage verification records for this authenticator.</p></div><span class="detail-section-count"><?=number_format($history_total)?> <?=($history_total===1?'Review':'Reviews')?></span></div>
<form method="get" class="auth-history-filters"><input type="hidden" name="id" value="<?=h($uid)?>"><div><label for="history_q">Search</label><input id="history_q" type="search" name="history_q" value="<?=h($history_q)?>" placeholder="Name, SM ID, email or profile ID"></div><div><label for="history_action">Status</label><select id="history_action" name="history_action"><option value="">All Actions</option><option value="Verified" <?=$history_action==='Verified'?'selected':''?>>Verified</option><option value="Rejected" <?=$history_action==='Rejected'?'selected':''?>>Rejected</option></select></div><div><label for="history_from">From</label><input id="history_from" type="date" name="history_from" value="<?=h($history_from)?>"></div><div><label for="history_to">To</label><input id="history_to" type="date" name="history_to" value="<?=h($history_to)?>"></div><div class="auth-history-filter-actions"><button type="submit" class="auth-history-filter-btn">Apply</button><a href="authenticator_details.php?id=<?=h($uid)?>#verification-history" class="auth-history-clear-btn">Clear</a></div></form>
<div id="verification-history"></div>
<form method="post" id="verification-history-form"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="delete_history"><div class="auth-history-toolbar"><label class="auth-history-select-all"><input type="checkbox" id="history_select_all"><span>Select all on page</span></label><button type="submit" class="auth-history-delete-btn" id="history_delete_btn" disabled>Delete Selected</button><span class="auth-history-range">Showing <?=number_format($history_start)?>–<?=number_format($history_end)?> of <?=number_format($history_total)?></span></div><div class="auth-detail-table-wrap"><table class="auth-detail-table auth-history-table"><thead><tr><th class="history-check-col"><span class="sr-only">Select</span></th><th>Profile</th><th>Gender</th><th>Action</th><th>Remarks</th><th>Date &amp; Time</th></tr></thead><tbody><?php if($logs): foreach($logs as $log): ?><tr><td class="history-check-col"><input class="history-row-check" type="checkbox" name="history_ids[]" value="<?=h($log['log_id'])?>" aria-label="Select verification record"></td><td><strong><?=h(trim($log['first_name'].' '.$log['last_name']))?></strong><small class="verification-profile-meta"><span class="verification-profile-id"><?=h(pid($log['profile_user_id']))?></span><?php if(!empty($log['profile_email'])):?><span class="verification-profile-email">· <?=h($log['profile_email'])?></span><button type="button" class="verification-copy-email" data-copy-email="<?=h($log['profile_email'])?>" aria-label="Copy email" title="Copy email"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 9h10v10H9z"/><path d="M5 5h10v2H7v8H5z"/></svg></button><?php endif;?></small></td><td><?=h($log['gender'])?></td><td><span class="mini-status mini-status-<?=strtolower(h($log['action']))?>"><?=h($log['action'])?></span></td><td><?=trim((string)$log['remarks'])!==''?h($log['remarks']):'—'?></td><td><?=h(date('d M Y, h:i A',strtotime($log['action_at'])))?></td></tr><?php endforeach; else: ?><tr><td colspan="6" class="empty">No verification activity found for the selected filters.</td></tr><?php endif;?></tbody></table></div></form>
<?php if($history_pages>1):?><nav class="auth-history-pagination" aria-label="Verification history pages"><?php if($history_page>1):?><a href="?id=<?=h($uid)?>&amp;<?=$history_query_base?>&amp;history_page=<?=$history_page-1?>#verification-history">&#8592; Previous</a><?php endif;?><?php $start_page=max(1,$history_page-2);$end_page=min($history_pages,$history_page+2);for($pg=$start_page;$pg<=$end_page;$pg++):?><a class="<?=$pg===$history_page?'active':''?>" href="?id=<?=h($uid)?>&amp;<?=$history_query_base?>&amp;history_page=<?=$pg?>#verification-history"><?=$pg?></a><?php endfor;?><?php if($history_page<$history_pages):?><a href="?id=<?=h($uid)?>&amp;<?=$history_query_base?>&amp;history_page=<?=$history_page+1?>#verification-history">Next &#8594;</a><?php endif;?></nav><?php endif;?></section>
<section class="manager-detail-communication"><div><span class="eyebrow">COMMUNICATION</span><h2>Authenticator Communication</h2><p>Open the existing Admin ↔ Authenticator conversation for this authenticator.</p></div><a class="btn-primary" href="staff_messages.php?filter=Authenticator&amp;staff=<?=rawurlencode($staff_key)?>">Open Conversation &#8594;</a></section>
</main>
<script>(function(){var key='authenticatorDetailsScroll:'+window.location.pathname+window.location.search.split('&action=')[0];var saved=sessionStorage.getItem(key);if(saved!==null){sessionStorage.removeItem(key);window.requestAnimationFrame(function(){window.scrollTo(0,parseInt(saved,10)||0);});}document.querySelectorAll('form[method="post"]').forEach(function(form){form.addEventListener('submit',function(){sessionStorage.setItem(key,String(window.scrollY||window.pageYOffset||0));});});var historyForm=document.querySelector('form.auth-history-filters');var historyKey='authenticatorHistoryScroll:'+window.location.pathname+window.location.search.split('&action=')[0];var historySaved=sessionStorage.getItem(historyKey);if(historySaved!==null){sessionStorage.removeItem(historyKey);window.requestAnimationFrame(function(){window.scrollTo(0,parseInt(historySaved,10)||0);});}if(historyForm){historyForm.addEventListener('submit',function(){sessionStorage.setItem(historyKey,String(window.scrollY||window.pageYOffset||0));});}})();</script>
<script>(function(){document.querySelectorAll('.verification-copy-email').forEach(function(btn){btn.addEventListener('click',function(){var email=btn.getAttribute('data-copy-email')||'';if(!email)return;var done=function(){btn.classList.add('copied');btn.setAttribute('title','Copied');setTimeout(function(){btn.classList.remove('copied');btn.setAttribute('title','Copy email');},1200);};if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(email).then(done).catch(function(){});}else{var ta=document.createElement('textarea');ta.value=email;ta.style.position='fixed';ta.style.opacity='0';document.body.appendChild(ta);ta.select();try{document.execCommand('copy');done();}catch(e){}document.body.removeChild(ta);}});});})();</script>
<script>(function(){var selectAll=document.getElementById('history_select_all'),form=document.getElementById('verification-history-form'),deleteBtn=document.getElementById('history_delete_btn');if(!form)return;function rows(){return Array.prototype.slice.call(form.querySelectorAll('.history-row-check'));}function sync(){var items=rows(),checked=items.filter(function(i){return i.checked;});if(selectAll){selectAll.checked=items.length>0&&checked.length===items.length;selectAll.indeterminate=checked.length>0&&checked.length<items.length;}if(deleteBtn){deleteBtn.disabled=checked.length===0;}}if(selectAll){selectAll.addEventListener('change',function(){rows().forEach(function(i){i.checked=selectAll.checked;});sync();});}rows().forEach(function(i){i.addEventListener('change',sync);});form.addEventListener('submit',function(e){var checked=rows().filter(function(i){return i.checked;});if(!checked.length){e.preventDefault();return;}if(!window.confirm('Delete the selected verification record(s)? This action cannot be undone.'))e.preventDefault();});sync();})();</script>
</body></html>
