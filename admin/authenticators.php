<?php
require_once __DIR__ . '/admin_guard.php';
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function pid($id){return 'SM-'.str_pad((string)$id,6,'0',STR_PAD_LEFT);}
function claim_age($claimed_at){
  $ts=strtotime((string)$claimed_at);
  if(!$ts)return '—';
  $seconds=max(0,time()-$ts);
  $days=intdiv($seconds,86400); $hours=intdiv($seconds%86400,3600); $minutes=intdiv($seconds%3600,60);
  if($days>0)return $days.'d '.$hours.'h';
  if($hours>0)return $hours.'h '.$minutes.'m';
  return max(1,$minutes).'m';
}

$message = '';
$error = '';

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
    }elseif($action==='release_authenticator_claim'){
      $claim_id=(int)($_POST['claim_id']??0);
      if($claim_id<=0){
        $error='Invalid verification assignment.';
      }else{
        $st=mysqli_prepare($conn,"UPDATE authenticator_profile_claims SET status='Released', released_at=CURRENT_TIMESTAMP WHERE claim_id=? AND status='Active' LIMIT 1");
        mysqli_stmt_bind_param($st,'i',$claim_id);
        if(mysqli_stmt_execute($st) && mysqli_stmt_affected_rows($st)>0) $message='Verification assignment released successfully.';
        else $error='This verification assignment is no longer active.';
        mysqli_stmt_close($st);
      }
    }elseif($action==='delete_verification_logs'){
      $selected=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['log_ids']??[])),fn($id)=>$id>0)));
      if(!$selected){
        $error='Select at least one verification activity to delete.';
      }else{
        $placeholders=implode(',',array_fill(0,count($selected),'?'));
        $st=mysqli_prepare($conn,"DELETE vl FROM verification_logs vl INNER JOIN users au ON au.user_id=vl.authenticator_id WHERE vl.log_id IN ($placeholders) AND au.role='Authenticator'");
        $types=str_repeat('i',count($selected));
        mysqli_stmt_bind_param($st,$types,...$selected);
        if(mysqli_stmt_execute($st)){
          $deleted=mysqli_stmt_affected_rows($st);
          $message=$deleted===1?'1 verification activity deleted successfully.':$deleted.' verification activities deleted successfully.';
        }else $error='Unable to delete the selected verification activities.';
        mysqli_stmt_close($st);
      }
    }elseif($action==='update_authenticator'){
      $uid=(int)($_POST['user_id']??0); $status=$_POST['account_status']??'Active';
      $statuses=['Active','Inactive','Suspended'];
      if($uid<=0||$uid===$admin_id||!in_array($status,$statuses,true)) $error='Invalid account update.';
      else{
        $st=mysqli_prepare($conn,"UPDATE users SET account_status=? WHERE user_id=? AND role='Authenticator' AND role<>'Admin'");
        mysqli_stmt_bind_param($st,'si',$status,$uid);
        if(mysqli_stmt_execute($st)) $message='Authenticator account updated successfully.';
        else $error='Unable to update authenticator account.';
        mysqli_stmt_close($st);
      }
    }
  }
}
$q=trim((string)($_GET['q']??'')); $status=$_GET['status']??'All'; $page=max(1,(int)($_GET['page']??1)); $per=30; $offset=($page-1)*$per;
$where=["u.role='Authenticator'"]; $types=''; $params=[];
if($q!==''){
  $numeric_id=null;
  if(preg_match('/^SM[- ]?(\d+)$/i',$q,$m)) $numeric_id=(int)$m[1];
  elseif(preg_match('/^\d+$/',$q)) $numeric_id=(int)$q;
  if($numeric_id!==null){
    $where[]='u.user_id=?'; $types.='i'; $params[]=$numeric_id;
  }elseif(filter_var($q,FILTER_VALIDATE_EMAIL)){
    $where[]='u.email=?'; $types.='s'; $params[]=$q;
  }elseif(preg_match('/^01[3-9][0-9]{8}$/',$q)){
    $where[]='u.mobile=?'; $types.='s'; $params[]=$q;
  }else{
    $like='%'.$q.'%';
    $where[]='(CONCAT(u.first_name," ",u.last_name)=? OR CONCAT(u.first_name," ",u.last_name) LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)';
    $types.='ssss'; $params[]=$q; $params[]=$like; $params[]=$like; $params[]=$like;
  }
}
if($status!=='All' && in_array($status,['Active','Inactive','Suspended'],true)){ $where[]='u.account_status=?'; $types.='s'; $params[]=$status; }
$w=implode(' AND ',$where);
$count=0; $st=mysqli_prepare($conn,"SELECT COUNT(*) FROM users u WHERE $w"); if($types) mysqli_stmt_bind_param($st,$types,...$params); mysqli_stmt_execute($st); mysqli_stmt_bind_result($st,$count); mysqli_stmt_fetch($st); mysqli_stmt_close($st);
$total_pages=max(1,(int)ceil($count/$per));
$rows=[]; $sql="SELECT u.user_id,u.first_name,u.last_name,u.gender,u.mobile,u.email,u.role,u.account_status,u.created_at,
COALESCE(sp.profile_image,'') AS profile_image,
(SELECT COUNT(*) FROM user_profiles p WHERE p.user_id=u.user_id) profile_count,
(SELECT COUNT(*) FROM user_profiles p WHERE p.user_id=u.user_id AND p.verification_status='Pending') pending_count,
(SELECT COUNT(*) FROM verification_logs vl WHERE vl.authenticator_id=u.user_id) review_count,
(SELECT COUNT(*) FROM verification_logs vl WHERE vl.authenticator_id=u.user_id AND vl.action='Verified') verified_review_count,
(SELECT COUNT(*) FROM verification_logs vl WHERE vl.authenticator_id=u.user_id AND vl.action='Rejected') rejected_review_count
FROM users u LEFT JOIN staff_profiles sp ON sp.user_id=u.user_id WHERE $w ORDER BY u.user_id DESC LIMIT ? OFFSET ?";
$st=mysqli_prepare($conn,$sql); $t2=$types.'ii'; $p2=$params; $p2[]=$per; $p2[]=$offset; mysqli_stmt_bind_param($st,$t2,...$p2); mysqli_stmt_execute($st); $res=mysqli_stmt_get_result($st); while($r=mysqli_fetch_assoc($res))$rows[]=$r; mysqli_stmt_close($st);
$filtered_ids=[];
$st=mysqli_prepare($conn,"SELECT u.user_id FROM users u WHERE $w ORDER BY u.user_id DESC");
if($types) mysqli_stmt_bind_param($st,$types,...$params);
mysqli_stmt_execute($st); $id_res=mysqli_stmt_get_result($st); while($ir=mysqli_fetch_assoc($id_res)) $filtered_ids[]=(int)$ir['user_id']; mysqli_stmt_close($st);
$id_list=$filtered_ids ? implode(',',array_map('intval',$filtered_ids)) : '0';
$auth_totals=['total'=>0,'active'=>0,'inactive'=>0,'suspended'=>0,'reviews'=>0,'verified_reviews'=>0,'rejected_reviews'=>0];
$auth_stat_res=mysqli_query($conn,"SELECT COUNT(*) AS total,SUM(account_status='Active') AS active,SUM(account_status='Inactive') AS inactive,SUM(account_status='Suspended') AS suspended FROM users WHERE role='Authenticator' AND user_id IN ($id_list)");
if($auth_stat_res && ($sr=mysqli_fetch_assoc($auth_stat_res))){$auth_totals['total']=(int)$sr['total'];$auth_totals['active']=(int)$sr['active'];$auth_totals['inactive']=(int)$sr['inactive'];$auth_totals['suspended']=(int)$sr['suspended'];}
$review_stat_res=mysqli_query($conn,"SELECT COUNT(*) AS reviews,SUM(action='Verified') AS verified_reviews,SUM(action='Rejected') AS rejected_reviews FROM verification_logs WHERE authenticator_id IN ($id_list)");
if($review_stat_res && ($sr=mysqli_fetch_assoc($review_stat_res))){$auth_totals['reviews']=(int)$sr['reviews'];$auth_totals['verified_reviews']=(int)$sr['verified_reviews'];$auth_totals['rejected_reviews']=(int)$sr['rejected_reviews'];}
$stats=['pending'=>0,'verified'=>0];
foreach([
 'pending'=>"SELECT COUNT(*) FROM user_profiles p JOIN users u ON u.user_id=p.user_id WHERE u.role<>'Admin' AND p.verification_status='Pending'",
 'verified'=>"SELECT COUNT(*) FROM user_profiles p JOIN users u ON u.user_id=p.user_id WHERE u.role<>'Admin' AND p.verification_status='Verified'"
] as $k=>$s){$r=mysqli_query($conn,$s);$stats[$k]=(int)mysqli_fetch_row($r)[0];}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Authenticator Management</title><link rel="stylesheet" href="../assets/css/admin.css"><link rel="stylesheet" href="../assets/css/admin-managers.css"><link rel="stylesheet" href="../assets/css/admin-authenticators.css"></head>
<body>
<?php
$admin_header_title = 'Authenticator Management';
$admin_header_subtitle = 'Manage verification staff and oversee profile verification activity.';
$admin_header_actions = [['label'=>'Admin Dashboard','href'=>'dashboard.php'],['label'=>'Operations','href'=>'operations.php'],['label'=>'Logout','href'=>'logout.php','danger'=>true]];
require __DIR__ . '/admin_header.php';
?>
<main class="auth-admin-wrap">
<?php if($message):?><div class="flash success"><?=h($message)?></div><?php endif;?><?php if($error):?><div class="flash error"><?=h($error)?></div><?php endif;?>
<a class="manager-back-dashboard" href="dashboard.php">&#8592; Back to Admin Dashboard</a>
<section class="manager-global-search-section">
  <form class="manager-global-search" method="get">
    <div class="manager-global-search-field"><label for="globalAuthenticatorSearch">Search Authenticator</label><input id="globalAuthenticatorSearch" name="q" value="<?=h($q)?>" placeholder="SM-000023, 000023, 23, name, email or mobile" autocomplete="off"></div>
    <div class="manager-global-status"><label for="globalAuthenticatorStatus">Status</label><select id="globalAuthenticatorStatus" name="status"><option value="All">All Authenticators</option><?php foreach(['Active','Inactive','Suspended'] as $s):?><option value="<?=$s?>" <?=$status===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></div>
    <button type="submit" class="manager-global-search-btn">Search</button>
    <?php if($q!==''||$status!=='All'):?><a class="clear-btn" href="authenticators.php">Clear</a><?php endif;?>
  </form>
</section>
<section class="manager-profile-section">
  <div class="manager-section-heading"><div><span class="eyebrow">AUTHENTICATOR DIRECTORY</span><h2>Authenticators <span class="manager-count">(<?=number_format($auth_totals['total'])?>)</span></h2><p>View authenticator profiles and their current verification activity.</p></div><div class="manager-carousel-controls"><button type="button" class="carousel-btn" id="authPrev" aria-label="Previous authenticators">&#8592;</button><span class="carousel-page" id="authCarouselPage">1 / 1</span><button type="button" class="carousel-btn" id="authNext" aria-label="Next authenticators">&#8594;</button></div></div>
  <div class="manager-profile-viewport" id="authProfileViewport"><div class="manager-profile-track" id="authProfileTrack">
    <?php foreach($rows as $r):
      $profile_image=(string)($r['profile_image']??''); $profile_file=$profile_image!=='' ? basename($profile_image) : ''; $profile_url=$profile_file!=='' ? '../uploads/staff/'.rawurlencode($profile_file) : ''; $initial=strtoupper(substr(trim((string)$r['first_name']),0,1));
    ?>
    <article class="manager-profile-card">
      <div class="manager-card-photo-wrap"><?php if($profile_url && is_file(__DIR__.'/../uploads/staff/'.$profile_file)): ?><img src="<?=h($profile_url)?>" alt="<?=h($r['first_name'].' '.$r['last_name'])?> profile photo"><?php else: ?><span class="manager-card-initial"><?=h($initial ?: 'A')?></span><?php endif; ?></div>
      <div class="manager-card-body"><span class="manager-card-status status-<?=strtolower(h($r['account_status']))?>"><?=h($r['account_status'])?></span><h3><?=h($r['first_name'].' '.$r['last_name'])?></h3><strong class="manager-card-id"><?=h(pid($r['user_id']))?></strong><div class="manager-card-info"><span><?=h($r['gender'])?></span><span><?=h($r['mobile'])?></span><span class="manager-card-email"><?=h($r['email'])?></span></div><div class="manager-card-mini-stats"><span><b><?=number_format((int)$r['profile_count'])?></b><small>Profiles</small></span><span><b><?=number_format((int)$r['pending_count'])?></b><small>Pending</small></span><span><b><?=number_format((int)$r['review_count'])?></b><small>Reviews</small></span></div><div class="manager-card-actions"><a class="manager-message-btn" href="staff_messages.php?filter=Authenticator&amp;staff=Authenticator%3A<?=rawurlencode((string)$r['user_id'])?>">&#9993; Message</a><a class="manager-view-btn" href="authenticator_details.php?id=<?=rawurlencode((string)$r['user_id'])?>">&#128065; View Profile</a></div></div>
    </article>
    <?php endforeach; ?>
    <?php if(!$rows): ?><div class="manager-profile-empty">No authenticators found for the current search or filter.</div><?php endif; ?>
  </div></div>
</section>
<section class="manager-stats-section"><div class="manager-section-heading compact"><div><span class="eyebrow">LIVE OVERVIEW</span><h2>Statistics</h2></div></div>
  <div class="manager-stats-grid">
    <?php $stat_icons=[
      ['staff','<path d="M16 11a4 4 0 1 0-3.9-5A4 4 0 0 0 16 11Zm-8 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8.7 2h-1.4a5.3 5.3 0 0 0-4.8 3 5.3 5.3 0 0 0-4.8-3H4.3A4.3 4.3 0 0 0 0 17.3V20h24v-2.7a4.3 4.3 0 0 0-4.3-4.3ZM8.5 15a3.3 3.3 0 0 1 3 2h-6a3.3 3.3 0 0 1 3-2Zm7.5 0a3.3 3.3 0 0 1 3 2h-6a3.3 3.3 0 0 1 3-2Z"/>'],
      ['active','<path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm-1.1 14.2-4-4 1.4-1.4 2.6 2.6 5.8-5.8 1.4 1.4Z"/>'],
      ['inactive','<path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm-1 13H8V9h3Zm5 0h-3V9h3Z"/>'],
      ['suspended','<path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm5.2 13.8L8.2 6.8A7.96 7.96 0 0 1 17.2 15.8ZM6.8 8.2l9 9A7.96 7.96 0 0 1 6.8 8.2Z"/>'],
      ['reviews','<path d="m12 2 9 4.5v11L12 22l-9-4.5v-11Zm0 2.2L6 7l6 3 6-3Zm-7 4.4v7.7l6 3v-7.7Zm8 13.1 6-3V8.6l-6 3Z"/>'],
      ['verified','<path d="M7 2h2v3h6V2h2v3h3v17H4V5h3Zm-1 8v10h14V10Zm3 6 2 2 5-5-1.4-1.4-3.6 3.6-.6-.6Z"/>'],
      ['rejected','<path d="m6 6 12 12M18 6 6 18"/>'],
      ['pending','<path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm1 5h-2v6l5 3 1-1.7-4-2.3Z"/>'],
      ['profile-verified','<path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm-1.1 14.2-4-4 1.4-1.4 2.6 2.6 5.8-5.8 1.4 1.4Z"/>']
    ]; $stat_values=[[$auth_totals['total'],'Total Authenticators'],[$auth_totals['active'],'Active'],[$auth_totals['inactive'],'Inactive'],[$auth_totals['suspended'],'Suspended'],[$auth_totals['reviews'],'Total Reviews'],[$auth_totals['verified_reviews'],'Verified Reviews'],[$auth_totals['rejected_reviews'],'Rejected Reviews'],[$stats['pending'],'Pending Profiles'],[$stats['verified'],'Verified Profiles']]; foreach($stat_values as $i=>$sv): ?>
    <article><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><?=$stat_icons[$i][1]?></svg></span><div><strong><?=number_format((int)$sv[0])?></strong><small><?=h($sv[1])?></small></div></article>
    <?php endforeach; ?>
  </div>
</section>
<?php
$verification_summary=[];
$verification_recent=[];
$active_claims=[];
$active_q=trim((string)($_GET['active_q']??''));
$active_auth=$_GET['active_auth']??'All';
$active_age=$_GET['active_age']??'All';
$active_sort=$_GET['active_sort']??'oldest';
$active_page=max(1,(int)($_GET['active_page']??1));
$active_per_page=8;
$active_where=["c.status='Active'","au.user_id IN ($id_list)"];
$active_types=''; $active_params=[];
if($active_q!==''){
  $active_like='%'.$active_q.'%';
  if(preg_match('/^PF[- ]?(\d+)$/i',$active_q,$am)){
    $active_where[]='c.profile_id=?'; $active_types.='i'; $active_params[]=(int)$am[1];
  }elseif(preg_match('/^SM[- ]?(\d+)$/i',$active_q,$am)){
    $active_where[]='(au.user_id=? OR p.user_id=?)'; $active_types.='ii'; $active_params[]=(int)$am[1]; $active_params[]=(int)$am[1];
  }elseif(preg_match('/^\d+$/',$active_q)){
    $active_where[]='(c.profile_id=? OR au.user_id=? OR p.user_id=?)'; $active_types.='iii'; $active_params[]=(int)$active_q; $active_params[]=(int)$active_q; $active_params[]=(int)$active_q;
  }else{
    $active_where[]='(CONCAT(p.first_name," ",p.last_name) LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR CONCAT(au.first_name," ",au.last_name) LIKE ? OR au.first_name LIKE ? OR au.last_name LIKE ? OR au.email LIKE ? OR pu.email LIKE ? OR pu.mobile LIKE ?)';
    $active_types.='sssssssss'; array_push($active_params,$active_like,$active_like,$active_like,$active_like,$active_like,$active_like,$active_like,$active_like,$active_like);
  }
}
if($active_auth!=='All' && in_array($active_auth,array_map('strval',$filtered_ids),true)){ $active_where[]='au.user_id=?'; $active_types.='i'; $active_params[]=(int)$active_auth; }
if(in_array($active_age,['under24','24plus','48plus'],true)){
  $active_where[] = $active_age==='under24' ? 'c.claimed_at > DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 24 HOUR)' : ($active_age==='24plus' ? 'c.claimed_at <= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 24 HOUR)' : 'c.claimed_at <= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 48 HOUR)');
}
$active_where_sql=implode(' AND ',$active_where);
$active_order=$active_sort==='newest' ? 'c.claimed_at DESC,c.claim_id DESC' : 'c.claimed_at ASC,c.claim_id ASC';
$active_count=0;
$active_count_sql="SELECT COUNT(*) AS total
FROM authenticator_profile_claims c
JOIN users au ON au.user_id=c.authenticator_id AND au.role='Authenticator'
JOIN user_profiles p ON p.profile_id=c.profile_id
LEFT JOIN users pu ON pu.user_id=p.user_id
WHERE $active_where_sql";
$ac=mysqli_prepare($conn,$active_count_sql);
if($ac){
  if($active_types) mysqli_stmt_bind_param($ac,$active_types,...$active_params);
  mysqli_stmt_execute($ac); $acres=mysqli_stmt_get_result($ac); $acrow=mysqli_fetch_assoc($acres); $active_count=(int)($acrow['total']??0); mysqli_stmt_close($ac);
}
$active_total_pages=max(1,(int)ceil($active_count/$active_per_page));
if($active_page>$active_total_pages)$active_page=$active_total_pages;
$active_offset=($active_page-1)*$active_per_page;
$active_claims_sql="SELECT c.claim_id,c.profile_id,c.authenticator_id,c.claimed_at,
au.first_name AS auth_first,au.last_name AS auth_last,au.email AS auth_email,au.account_status AS auth_status,
p.user_id AS profile_user_id,p.first_name AS profile_first,p.last_name AS profile_last,p.gender AS profile_gender,p.verification_status,
pu.email AS profile_email
FROM authenticator_profile_claims c
JOIN users au ON au.user_id=c.authenticator_id AND au.role='Authenticator'
JOIN user_profiles p ON p.profile_id=c.profile_id
LEFT JOIN users pu ON pu.user_id=p.user_id
WHERE $active_where_sql
ORDER BY $active_order
LIMIT $active_per_page OFFSET $active_offset";
$active_st=mysqli_prepare($conn,$active_claims_sql);
if($active_st){
  if($active_types) mysqli_stmt_bind_param($active_st,$active_types,...$active_params);
  mysqli_stmt_execute($active_st); $active_res=mysqli_stmt_get_result($active_st); while($r=mysqli_fetch_assoc($active_res))$active_claims[]=$r; mysqli_stmt_close($active_st);
}
$active_authenticators=[];
$active_auth_sql="SELECT user_id,first_name,last_name FROM users WHERE role='Authenticator' AND user_id IN ($id_list) ORDER BY first_name,last_name,user_id";
if($aar=mysqli_query($conn,$active_auth_sql)){while($r=mysqli_fetch_assoc($aar))$active_authenticators[]=$r;}
$active_query_base=http_build_query(['q'=>$q,'status'=>$status,'page'=>$page,'active_q'=>$active_q,'active_auth'=>$active_auth,'active_age'=>$active_age,'active_sort'=>$active_sort,'active_page'=>$active_page,'pending_q'=>$pending_q??'','pending_gender'=>$pending_gender??'All','pending_account'=>$pending_account??'All','pending_sort'=>$pending_sort??'oldest','pending_page'=>$pending_page??1]);
$verification_summary_sql="SELECT u.user_id,u.first_name,u.last_name,u.account_status,
(SELECT COUNT(*) FROM user_profiles p WHERE p.user_id=u.user_id) AS profile_count,
(SELECT COUNT(*) FROM user_profiles p WHERE p.user_id=u.user_id AND p.verification_status='Pending') AS pending_count,
COUNT(vl.log_id) AS total_reviews,
SUM(vl.action='Verified') AS verified_reviews,
SUM(vl.action='Rejected') AS rejected_reviews,
MAX(vl.action_at) AS last_review
FROM users u
LEFT JOIN verification_logs vl ON vl.authenticator_id=u.user_id
WHERE u.role='Authenticator' AND u.user_id IN ($id_list)
GROUP BY u.user_id,u.first_name,u.last_name,u.account_status
ORDER BY total_reviews DESC,u.user_id DESC";
if($vr=mysqli_query($conn,$verification_summary_sql)){while($r=mysqli_fetch_assoc($vr))$verification_summary[]=$r;}
$verification_page=max(1,(int)($_GET['verification_page']??1));
$verification_per_page=8;
$verification_total=0;
$verification_count_sql="SELECT COUNT(*) FROM verification_logs vl JOIN users au ON au.user_id=vl.authenticator_id WHERE au.role='Authenticator' AND au.user_id IN ($id_list)";
if($vcr=mysqli_query($conn,$verification_count_sql)){$verification_total=(int)mysqli_fetch_row($vcr)[0];}
$verification_total_pages=max(1,(int)ceil($verification_total/$verification_per_page));
if($verification_page>$verification_total_pages)$verification_page=$verification_total_pages;
$verification_offset=($verification_page-1)*$verification_per_page;
$verification_recent_sql="SELECT vl.log_id,vl.profile_id,vl.action,vl.remarks,vl.action_at,
au.user_id AS authenticator_id,au.first_name AS auth_first,au.last_name AS auth_last,
p.first_name AS profile_first,p.last_name AS profile_last
FROM verification_logs vl
JOIN users au ON au.user_id=vl.authenticator_id
LEFT JOIN user_profiles p ON p.profile_id=vl.profile_id
WHERE au.role='Authenticator' AND au.user_id IN ($id_list)
ORDER BY vl.action_at DESC,vl.log_id DESC LIMIT $verification_per_page OFFSET $verification_offset";
if($vr=mysqli_query($conn,$verification_recent_sql)){while($r=mysqli_fetch_assoc($vr))$verification_recent[]=$r;}
$verification_query_base=http_build_query(['q'=>$q,'status'=>$status,'page'=>$page,'active_q'=>$active_q??'','active_auth'=>$active_auth??'All','active_age'=>$active_age??'all','active_sort'=>$active_sort??'oldest','active_page'=>$active_page??1,'pending_q'=>$pending_q??'','pending_gender'=>$pending_gender??'All','pending_account'=>$pending_account??'All','pending_sort'=>$pending_sort??'oldest','pending_page'=>$pending_page??1]);
$pending_unclaimed=[];
$pending_q=trim((string)($_GET['pending_q']??''));
$pending_gender=$_GET['pending_gender']??'All';
$pending_account=$_GET['pending_account']??'All';
$pending_sort=$_GET['pending_sort']??'oldest';
$pending_where=["p.verification_status='Pending'","pu.role='User'","c.claim_id IS NULL"];
$pending_types=''; $pending_params=[];
if($pending_q!==''){
  $pending_like='%'.$pending_q.'%';
  if(preg_match('/^PF[- ]?(\d+)$/i',$pending_q,$pm)){
    $pending_where[]='p.profile_id=?'; $pending_types.='i'; $pending_params[]=(int)$pm[1];
  }elseif(preg_match('/^\d+$/',$pending_q)){
    $pending_where[]='p.profile_id=?'; $pending_types.='i'; $pending_params[]=(int)$pending_q;
  }else{
    $pending_where[]='(CONCAT(p.first_name," ",p.last_name) LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR pu.email LIKE ? OR pu.mobile LIKE ?)';
    $pending_types.='sssss'; array_push($pending_params,$pending_like,$pending_like,$pending_like,$pending_like,$pending_like);
  }
}
if(in_array($pending_gender,['Male','Female'],true)){ $pending_where[]='p.gender=?'; $pending_types.='s'; $pending_params[]=$pending_gender; }
if(in_array($pending_account,['Active','Inactive','Suspended'],true)){ $pending_where[]='pu.account_status=?'; $pending_types.='s'; $pending_params[]=$pending_account; }
$pending_order=$pending_sort==='newest' ? 'p.created_at DESC,p.profile_id DESC' : 'p.created_at ASC,p.profile_id ASC';
$pending_where_sql=implode(' AND ',$pending_where);
$pending_count=0;
$pending_count_sql="SELECT COUNT(*) AS total
FROM user_profiles p
JOIN users pu ON pu.user_id=p.user_id
LEFT JOIN authenticator_profile_claims c ON c.profile_id=p.profile_id AND c.status='Active'
WHERE $pending_where_sql";
$pc=mysqli_prepare($conn,$pending_count_sql);
if($pc){
  if($pending_types) mysqli_stmt_bind_param($pc,$pending_types,...$pending_params);
  mysqli_stmt_execute($pc); $pcres=mysqli_stmt_get_result($pc); $pcrow=mysqli_fetch_assoc($pcres); $pending_count=(int)($pcrow['total']??0); mysqli_stmt_close($pc);
}
$pending_per_page=8;
$pending_total_pages=max(1,(int)ceil($pending_count/$pending_per_page));
$pending_page=max(1,(int)($_GET['pending_page']??1));
if($pending_page>$pending_total_pages)$pending_page=$pending_total_pages;
$pending_offset=($pending_page-1)*$pending_per_page;
$pending_unclaimed_sql="SELECT p.profile_id,p.user_id,p.first_name,p.last_name,p.gender,p.created_at,pu.email AS profile_email,pu.account_status AS profile_account_status
FROM user_profiles p
JOIN users pu ON pu.user_id=p.user_id
LEFT JOIN authenticator_profile_claims c ON c.profile_id=p.profile_id AND c.status='Active'
WHERE $pending_where_sql
ORDER BY $pending_order
LIMIT $pending_per_page OFFSET $pending_offset";
$pr=mysqli_prepare($conn,$pending_unclaimed_sql);
if($pr){
  if($pending_types) mysqli_stmt_bind_param($pr,$pending_types,...$pending_params);
  mysqli_stmt_execute($pr); $pres=mysqli_stmt_get_result($pr); while($r=mysqli_fetch_assoc($pres))$pending_unclaimed[]=$r; mysqli_stmt_close($pr);
}
$pending_query_base=http_build_query(['q'=>$q,'status'=>$status,'page'=>$page,'active_q'=>$active_q,'active_auth'=>$active_auth,'active_age'=>$active_age,'active_sort'=>$active_sort,'pending_q'=>$pending_q,'pending_gender'=>$pending_gender,'pending_account'=>$pending_account,'pending_sort'=>$pending_sort]);
?>
<section class="auth-panel"><div class="panel-head"><div><span class="eyebrow">STAFF MANAGEMENT</span><h2>Create Authenticator Account</h2><p>Add a new verification staff account with the required contact and access details.</p></div></div>
<form method="post" class="auth-create"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="create_authenticator">
<input name="first_name" placeholder="First name" required><input name="last_name" placeholder="Last name" required><select name="gender" required><option value="">Gender</option><option>Male</option><option>Female</option></select><input name="mobile" placeholder="Mobile" required><input type="email" name="email" placeholder="Email" required><input type="password" name="password" placeholder="Password (8+ chars)" minlength="8" required><button>Create Authenticator</button></form></section>

<section class="auth-panel"><div class="panel-head"><div><span class="eyebrow">ACCOUNT CONTROLS</span><h2>Authenticator Directory &amp; Controls</h2><p>Manage authenticator account status and access-related actions. Verification workload and review activity are shown in the overview below.</p></div></div>
<div class="auth-table-wrap"><table><thead><tr><th>ID</th><th>AUTHENTICATOR</th><th>CONTACT</th><th>ACCOUNT STATUS</th><th>UPDATE</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><span class="id-pill"><?=h(pid($r['user_id']))?></span></td><td><strong><?=h($r['first_name'].' '.$r['last_name'])?></strong><small><?=h($r['gender'])?></small></td><td><?=h($r['email'])?><small><?=h($r['mobile'])?></small></td><td><form method="post" class="row-form"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="update_authenticator"><input type="hidden" name="user_id" value="<?=$r['user_id']?>"><select name="account_status"><?php foreach(['Active','Inactive','Suspended'] as $s):?><option <?=$r['account_status']===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></td><td><button class="small">Save</button></form></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="5" class="empty">No authenticators found.</td></tr><?php endif;?></tbody></table></div>
<div class="pager"><?php if($page>1):?><a href="?<?=http_build_query(['q'=>$q,'status'=>$status,'page'=>$page-1])?>">← Previous</a><?php endif;?><span>Page <?=$page?> of <?=$total_pages?></span><?php if($page<$total_pages):?><a href="?<?=http_build_query(['q'=>$q,'status'=>$status,'page'=>$page+1])?>">Next →</a><?php endif;?></div>

</section>

<?php
$auth_message_overview=[];
$auth_message_total_unread=0;
$auth_message_res=mysqli_query($conn,"SELECT u.user_id,u.first_name,u.last_name,u.email,
(SELECT COUNT(*) FROM staff_admin_messages m WHERE m.sender_role='Authenticator' AND m.sender_id=u.user_id AND m.recipient_role='Admin' AND m.status='Unread') AS unread_count,
(SELECT MAX(m.created_at) FROM staff_admin_messages m WHERE ((m.sender_role='Authenticator' AND m.sender_id=u.user_id AND m.recipient_role='Admin') OR (m.sender_role='Admin' AND m.recipient_role='Authenticator' AND m.recipient_id=u.user_id))) AS last_message_at
FROM users u WHERE u.role='Authenticator' AND u.user_id IN ($id_list) ORDER BY unread_count DESC,last_message_at DESC,u.user_id DESC");
if($auth_message_res){while($mr=mysqli_fetch_assoc($auth_message_res)){ $mr['unread_count']=(int)$mr['unread_count']; $auth_message_total_unread += $mr['unread_count']; $auth_message_overview[]=$mr; }}
?>
<section class="auth-panel auth-communication-section">
  <div class="panel-head auth-communication-head">
    <div><span class="eyebrow">STAFF COMMUNICATION</span><h2>Authenticator Communication</h2><p>See message activity at a glance and open a direct Admin conversation when needed.</p></div>
    <a class="auth-communication-open-all" href="staff_messages.php?filter=Authenticator">Open Staff Messages</a>
  </div>
  <div class="auth-communication-summary">
    <div class="auth-communication-stat"><strong><?=number_format(count($auth_message_overview))?></strong><span>Authenticators</span></div>
    <div class="auth-communication-stat unread"><strong><?=number_format($auth_message_total_unread)?></strong><span>Unread Replies</span></div>
  </div>
  <div class="auth-communication-list">
    <?php foreach($auth_message_overview as $mr): ?>
      <article class="auth-communication-row">
        <div class="auth-communication-person"><span class="auth-communication-avatar"><?=h(strtoupper(substr(trim((string)$mr['first_name']),0,1)) ?: 'A')?></span><div><strong><?=h($mr['first_name'].' '.$mr['last_name'])?></strong><small><?=h(pid($mr['user_id']))?> · <?=h($mr['email'])?></small></div></div>
        <div class="auth-communication-meta">
          <?php if($mr['unread_count']>0): ?><span class="auth-unread-badge"><?=number_format($mr['unread_count'])?> unread</span><?php else: ?><span class="auth-read-badge">No unread</span><?php endif; ?>
          <small><?= $mr['last_message_at'] ? h(date('d M Y, h:i A',strtotime($mr['last_message_at']))) : 'No messages yet' ?></small>
        </div>
        <a class="auth-communication-message-btn" href="staff_messages.php?filter=Authenticator&amp;staff=Authenticator%3A<?=rawurlencode((string)$mr['user_id'])?>">&#9993; Message</a>
      </article>
    <?php endforeach; ?>
    <?php if(!$auth_message_overview): ?><div class="auth-communication-empty">No active Authenticators are available for communication.</div><?php endif; ?>
  </div>
</section>

<section class="auth-verification-section auth-verification-overview-section">
  <div class="auth-section-heading"><div><span class="eyebrow">VERIFICATION ACTIVITY</span><h2>Verification Overview</h2><p>Review authenticator activity and verification decision totals at a glance.</p></div></div>
  <div class="auth-verification-grid auth-verification-grid-single">
<article class="auth-verification-panel">
      <div class="auth-subheading"><h3>Authenticator Activity</h3><span><?=number_format(count($verification_summary))?> staff</span></div>
      <div class="auth-activity-table-wrap">
        <table class="auth-activity-table">
          <thead><tr><th>Authenticator</th><th>Profiles</th><th>Pending</th><th>Reviews</th><th>Verified</th><th>Rejected</th><th>Last Review</th></tr></thead>
          <tbody>
          <?php foreach($verification_summary as $vr): ?>
            <tr>
              <td><strong><?=h($vr['first_name'].' '.$vr['last_name'])?></strong><small><?=h(pid($vr['user_id']))?></small></td>
              <td><?=number_format((int)$vr['profile_count'])?></td>
              <td><?=number_format((int)$vr['pending_count'])?></td>
              <td><?=number_format((int)$vr['total_reviews'])?></td>
              <td><span class="auth-result verified"><?=number_format((int)$vr['verified_reviews'])?></span></td>
              <td><span class="auth-result rejected"><?=number_format((int)$vr['rejected_reviews'])?></span></td>
              <td><?= $vr['last_review'] ? h(date('d M Y, h:i A',strtotime($vr['last_review']))) : '<span class="auth-no-data">No activity</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if(!$verification_summary): ?><tr><td colspan="7" class="auth-activity-empty">No verification activity found for the current search or filter.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </article>
  </div>
</section>

<section class="auth-verification-section auth-claims-section">
  <div class="auth-section-heading"><div><span class="eyebrow">LIVE ASSIGNMENTS</span><h2>Active Verification Assignments</h2><p>See which profiles are currently claimed by an authenticator and waiting for review completion.</p></div></div>
  <form method="get" class="auth-queue-filters auth-active-filters">
    <input type="hidden" name="q" value="<?=h($q)?>"><input type="hidden" name="status" value="<?=h($status)?>"><input type="hidden" name="page" value="<?=h($page)?>">
    <input type="hidden" name="pending_q" value="<?=h($pending_q)?>"><input type="hidden" name="pending_gender" value="<?=h($pending_gender)?>"><input type="hidden" name="pending_account" value="<?=h($pending_account)?>"><input type="hidden" name="pending_sort" value="<?=h($pending_sort)?>"><input type="hidden" name="pending_page" value="<?=h($pending_page)?>">
    <div class="auth-queue-filter-field search"><label for="active_q">Search assignment</label><input id="active_q" name="active_q" value="<?=h($active_q)?>" placeholder="PF/SM ID, profile, authenticator or email"></div>
    <div class="auth-queue-filter-field"><label for="active_auth">Authenticator</label><select id="active_auth" name="active_auth"><option value="All">All</option><?php foreach($active_authenticators as $aa): ?><option value="<?=h($aa['user_id'])?>" <?=$active_auth===(string)$aa['user_id']?'selected':''?>><?=h($aa['first_name'].' '.$aa['last_name'])?></option><?php endforeach; ?></select></div>
    <div class="auth-queue-filter-field"><label for="active_age">Claim age</label><select id="active_age" name="active_age"><option value="All" <?=$active_age==='All'?'selected':''?>>All</option><option value="under24" <?=$active_age==='under24'?'selected':''?>>Under 24 hours</option><option value="24plus" <?=$active_age==='24plus'?'selected':''?>>24+ hours</option><option value="48plus" <?=$active_age==='48plus'?'selected':''?>>48+ hours</option></select></div>
    <div class="auth-queue-filter-field"><label for="active_sort">Sort</label><select id="active_sort" name="active_sort"><option value="oldest" <?=$active_sort==='oldest'?'selected':''?>>Oldest claim first</option><option value="newest" <?=$active_sort==='newest'?'selected':''?>>Newest claim first</option></select></div>
    <div class="auth-queue-filter-actions"><button type="submit" class="auth-queue-filter-btn">Apply</button><?php if($active_q!==''||$active_auth!=='All'||$active_age!=='All'||$active_sort!=='oldest'): ?><a href="?<?=http_build_query(['q'=>$q,'status'=>$status,'page'=>$page,'pending_q'=>$pending_q,'pending_gender'=>$pending_gender,'pending_account'=>$pending_account,'pending_sort'=>$pending_sort,'pending_page'=>$pending_page])?>" class="auth-queue-clear-btn">Clear</a><?php endif; ?></div>
  </form>
  <div class="auth-verification-grid auth-verification-grid-single">
    <article class="auth-verification-panel">
      <div class="auth-subheading"><h3>Currently Claimed Profiles</h3><span><?=number_format($active_count)?> active</span></div>
      <div class="auth-activity-table-wrap">
        <table class="auth-activity-table auth-claims-table">
          <thead><tr><th>Profile</th><th>Authenticator</th><th>Claimed At</th><th>Claimed For</th><th>Profile Status</th><th>Account</th><th>Action</th></tr></thead>
          <tbody>
          <?php foreach($active_claims as $claim): ?>
            <tr>
              <td class="auth-claim-profile-cell"><strong class="auth-claim-profile-name"><?=h(trim($claim['profile_first'].' '.$claim['profile_last']))?></strong><small class="auth-claim-profile-meta"><?=h('PF-'.str_pad((string)$claim['profile_id'],6,'0',STR_PAD_LEFT))?><?= $claim['profile_gender'] ? ' · '.h($claim['profile_gender']) : '' ?></small></td>
              <td><strong><?=h($claim['auth_first'].' '.$claim['auth_last'])?></strong><small><?=h(pid($claim['authenticator_id']))?></small></td>
              <td><?=h(date('d M Y, h:i A',strtotime($claim['claimed_at'])))?></td>
              <td><span class="auth-claim-age"><?=h(claim_age($claim['claimed_at']))?></span></td>
              <td><span class="auth-claim-status <?=strtolower(h($claim['verification_status']))?>"><?=h($claim['verification_status'])?></span></td>
              <td><span class="auth-claim-status <?=strtolower(h($claim['auth_status']))?>"><?=h($claim['auth_status'])?></span></td>
              <td><div class="auth-claim-actions"><a class="auth-claim-view-btn" href="user_details.php?user_id=<?=rawurlencode((string)$claim['profile_user_id'])?>">&#128065; View Profile</a><form method="post" class="auth-claim-release-form"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="release_authenticator_claim"><input type="hidden" name="claim_id" value="<?=h($claim['claim_id'])?>"><button type="submit" class="auth-claim-release-btn" onclick="return confirm('Release this active verification assignment? The profile will become available for another Authenticator.');">Release</button></form></div></td>
            </tr>
          <?php endforeach; ?>
          <?php if(!$active_claims): ?><tr><td colspan="7" class="auth-activity-empty">No profiles are currently claimed for verification.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if($active_count>$active_per_page): ?>
      <div class="auth-queue-pagination" aria-label="Active verification assignments pagination">
        <span>Showing <?=number_format($active_offset+($active_count?1:0))?>–<?=number_format(min($active_offset+count($active_claims),$active_count))?> of <?=number_format($active_count)?></span>
        <div class="auth-queue-pagination-links">
          <?php if($active_page>1): ?><a href="?<?=$active_query_base?>&active_page=<?=$active_page-1?>" class="auth-queue-page-btn">&larr; Previous</a><?php endif; ?>
          <strong>Page <?=number_format($active_page)?> of <?=number_format($active_total_pages)?></strong>
          <?php if($active_page<$active_total_pages): ?><a href="?<?=$active_query_base?>&active_page=<?=$active_page+1?>" class="auth-queue-page-btn">Next &rarr;</a><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </article>
  </div>
</section>
<section class="auth-verification-section auth-pending-section">
  <div class="auth-section-heading"><div><span class="eyebrow">VERIFICATION QUEUE</span><h2>Pending Verification Queue</h2><p>Pending profiles that are not currently claimed by an authenticator and are available for review.</p></div></div>
  <form method="get" class="auth-queue-filters">
    <input type="hidden" name="q" value="<?=h($q)?>"><input type="hidden" name="status" value="<?=h($status)?>"><input type="hidden" name="page" value="<?=h($page)?>">
    <input type="hidden" name="active_q" value="<?=h($active_q)?>"><input type="hidden" name="active_auth" value="<?=h($active_auth)?>"><input type="hidden" name="active_age" value="<?=h($active_age)?>"><input type="hidden" name="active_sort" value="<?=h($active_sort)?>"><input type="hidden" name="active_page" value="<?=h($active_page)?>">
    <div class="auth-queue-filter-field search"><label for="pending_q">Search profile</label><input id="pending_q" name="pending_q" value="<?=h($pending_q)?>" placeholder="PF-000001, name, email or mobile"></div>
    <div class="auth-queue-filter-field"><label for="pending_gender">Gender</label><select id="pending_gender" name="pending_gender"><option value="All" <?=$pending_gender==='All'?'selected':''?>>All</option><option value="Male" <?=$pending_gender==='Male'?'selected':''?>>Male</option><option value="Female" <?=$pending_gender==='Female'?'selected':''?>>Female</option></select></div>
    <div class="auth-queue-filter-field"><label for="pending_account">Account</label><select id="pending_account" name="pending_account"><option value="All" <?=$pending_account==='All'?'selected':''?>>All</option><option value="Active" <?=$pending_account==='Active'?'selected':''?>>Active</option><option value="Inactive" <?=$pending_account==='Inactive'?'selected':''?>>Inactive</option><option value="Suspended" <?=$pending_account==='Suspended'?'selected':''?>>Suspended</option></select></div>
    <div class="auth-queue-filter-field"><label for="pending_sort">Sort</label><select id="pending_sort" name="pending_sort"><option value="oldest" <?=$pending_sort==='oldest'?'selected':''?>>Oldest first</option><option value="newest" <?=$pending_sort==='newest'?'selected':''?>>Newest first</option></select></div>
    <div class="auth-queue-filter-actions"><button type="submit" class="auth-queue-filter-btn">Apply</button><?php if($pending_q!==''||$pending_gender!=='All'||$pending_account!=='All'||$pending_sort!=='oldest'): ?><a href="authenticators.php" class="auth-queue-clear-btn">Clear</a><?php endif; ?></div>
  </form>
  <div class="auth-verification-grid auth-verification-grid-single">
    <article class="auth-verification-panel">
      <div class="auth-subheading"><h3>Available for Claim</h3><span><?=number_format($pending_count)?> total</span></div>
      <div class="auth-activity-table-wrap">
        <table class="auth-activity-table auth-pending-table">
          <thead><tr><th>Profile</th><th>Submitted</th><th>Contact</th><th>Account</th><th>Action</th></tr></thead>
          <tbody>
          <?php foreach($pending_unclaimed as $pending): ?>
            <tr>
              <td class="auth-pending-profile-cell"><strong class="auth-pending-profile-id"><?=h('PF-'.str_pad((string)$pending['profile_id'],6,'0',STR_PAD_LEFT))?></strong><span class="auth-pending-profile-name"><?=h(trim($pending['first_name'].' '.$pending['last_name']))?></span><?php if($pending['gender']): ?><span class="auth-pending-profile-gender"><?=h($pending['gender'])?></span><?php endif; ?></td>
              <td><?=h(date('d M Y, h:i A',strtotime($pending['created_at'])))?></td>
              <td><strong><?=h($pending['profile_email'])?></strong></td>
              <td><span class="auth-claim-status <?=strtolower(h($pending['profile_account_status']))?>"><?=h($pending['profile_account_status'])?></span></td>
              <td><a class="auth-claim-view-btn" href="user_details.php?user_id=<?=rawurlencode((string)$pending['user_id'])?>">&#128065; View Profile</a></td>
            </tr>
          <?php endforeach; ?>
          <?php if(!$pending_unclaimed): ?><tr><td colspan="5" class="auth-activity-empty">No unclaimed pending profiles are currently waiting for verification.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if($pending_count>$pending_per_page): ?>
      <div class="auth-queue-pagination" aria-label="Pending verification pagination">
        <span>Showing <?=number_format($pending_offset+($pending_count?1:0))?>–<?=number_format(min($pending_offset+count($pending_unclaimed),$pending_count))?> of <?=number_format($pending_count)?></span>
        <div class="auth-queue-pagination-links">
          <?php if($pending_page>1): ?><a href="?<?=$pending_query_base?>&pending_page=<?=$pending_page-1?>" class="auth-queue-page-btn">&larr; Previous</a><?php endif; ?>
          <strong>Page <?=number_format($pending_page)?> of <?=number_format($pending_total_pages)?></strong>
          <?php if($pending_page<$pending_total_pages): ?><a href="?<?=$pending_query_base?>&pending_page=<?=$pending_page+1?>" class="auth-queue-page-btn">Next &rarr;</a><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </article>
  </div>
</section>
<section class="auth-verification-section auth-recent-section">
  <div class="auth-section-heading"><div><span class="eyebrow">LATEST ACTIVITY</span><h2>Recent Verification Activity</h2><p>The latest profile verification decisions recorded by the authentication team.</p></div></div>
  <div class="auth-verification-grid auth-verification-grid-single">
<article class="auth-verification-panel">
      <div class="auth-subheading auth-recent-toolbar"><div><h3>Recent Verification Activity</h3><span class="auth-recent-count"><?=number_format($verification_total)?> total activities</span></div><?php if($verification_recent): ?><button type="submit" form="verificationDeleteForm" class="auth-recent-delete-btn" onclick="return confirm('Delete the selected verification activities? This action cannot be undone.');">Delete Selected</button><?php endif; ?></div>
      <form method="post" id="verificationDeleteForm" class="auth-recent-delete-form">
        <input type="hidden" name="csrf" value="<?=h($csrf)?>">
        <input type="hidden" name="action" value="delete_verification_logs">
        <div class="auth-recent-list-wrap">
          <?php if($verification_recent): ?>
          <div class="auth-recent-selectbar"><label><input type="checkbox" id="verificationSelectAll"> Select all on this page</label><span>Page <?=number_format($verification_page)?> of <?=number_format($verification_total_pages)?></span></div>
          <?php endif; ?>
          <div class="auth-recent-list">
            <?php foreach($verification_recent as $vr): ?>
              <div class="auth-recent-item">
                <label class="auth-recent-check"><input type="checkbox" name="log_ids[]" value="<?=h($vr['log_id'])?>" aria-label="Select verification activity"></label>
                <div class="auth-recent-icon <?=strtolower($vr['action'])?>"><?= $vr['action']==='Verified' ? '&#10003;' : '&#10005;' ?></div>
                <div class="auth-recent-main">
                  <strong><?=h($vr['auth_first'].' '.$vr['auth_last'])?></strong>
                  <span><?=h($vr['action'])?> profile <b><?=h('PF-'.str_pad((string)$vr['profile_id'],6,'0',STR_PAD_LEFT))?></b><?=($vr['profile_first']||$vr['profile_last'])?' — '.h(trim($vr['profile_first'].' '.$vr['profile_last'])):''?></span>
                  <?php if(trim((string)$vr['remarks'])!==''): ?><div class="auth-recent-message"><span>Authenticator message</span><p><?=nl2br(h($vr['remarks']))?></p></div><?php endif; ?>
                  <small><?=h(date('d M Y, h:i A',strtotime($vr['action_at'])))?></small>
                </div>
              </div>
            <?php endforeach; ?>
            <?php if(!$verification_recent): ?><div class="auth-activity-empty">No verification activity found for the current search or filter.</div><?php endif; ?>
          </div>
        </div>
      </form>
      <?php if($verification_total>$verification_per_page): ?>
      <div class="auth-recent-pagination">
        <span>Showing <?=number_format($verification_offset+($verification_total?1:0))?>–<?=number_format(min($verification_offset+count($verification_recent),$verification_total))?> of <?=number_format($verification_total)?></span>
        <div><?php if($verification_page>1): ?><a href="?<?=$verification_query_base?>&verification_page=<?=$verification_page-1?>" class="auth-queue-page-btn">&larr; Previous</a><?php endif; ?><strong>Page <?=number_format($verification_page)?> of <?=number_format($verification_total_pages)?></strong><?php if($verification_page<$verification_total_pages): ?><a href="?<?=$verification_query_base?>&verification_page=<?=$verification_page+1?>" class="auth-queue-page-btn">Next &rarr;</a><?php endif; ?></div>
      </div>
      <?php endif; ?>
    </article>
  </div>
</section>

</section></main><script>
(function(){
 const viewport=document.getElementById('authProfileViewport'),track=document.getElementById('authProfileTrack'),prev=document.getElementById('authPrev'),next=document.getElementById('authNext'),page=document.getElementById('authCarouselPage');
 if(!viewport||!track||!prev||!next||!page)return;
 function metrics(){const cards=track.querySelectorAll('.manager-profile-card');if(!cards.length){page.textContent='0 / 0';prev.disabled=next.disabled=true;return {pages:0,index:0};}const card=cards[0],gap=parseFloat(getComputedStyle(track).gap)||0,visible=Math.max(1,Math.floor((viewport.clientWidth+gap)/(card.getBoundingClientRect().width+gap))),pages=Math.max(1,Math.ceil(cards.length/visible));let index=Math.round(viewport.scrollLeft/Math.max(1,viewport.clientWidth));index=Math.min(index,pages-1);return {pages,index};}
 function update(){const m=metrics();page.textContent=m.pages?((m.index+1)+' / '+m.pages):'0 / 0';prev.disabled=m.index<=0;next.disabled=m.index>=m.pages-1;}
 prev.addEventListener('click',()=>viewport.scrollBy({left:-viewport.clientWidth,behavior:'smooth'}));next.addEventListener('click',()=>viewport.scrollBy({left:viewport.clientWidth,behavior:'smooth'}));viewport.addEventListener('scroll',()=>requestAnimationFrame(update));window.addEventListener('resize',update);update();
})();
(function(){
 const selectAll=document.getElementById('verificationSelectAll');
 const verificationForm=document.getElementById('verificationDeleteForm');
 if(selectAll&&verificationForm){selectAll.addEventListener('change',function(){verificationForm.querySelectorAll('input[name=\"log_ids[]\"]').forEach(function(cb){cb.checked=selectAll.checked;});});verificationForm.addEventListener('change',function(e){if(e.target.name==='log_ids[]'&&!e.target.checked)selectAll.checked=false;});}
 const scrollKey='authenticatorPageScroll:'+window.location.pathname;
 const saved=window.sessionStorage.getItem(scrollKey);
 if(saved!==null){
   window.sessionStorage.removeItem(scrollKey);
   window.requestAnimationFrame(function(){window.requestAnimationFrame(function(){window.scrollTo(0,parseInt(saved,10)||0);});});
 }
 function saveIfSamePage(target){
   try{const url=new URL(target,window.location.href);if(url.pathname===window.location.pathname)window.sessionStorage.setItem(scrollKey,String(window.scrollY||window.pageYOffset||0));}catch(e){}
 }
 document.querySelectorAll('form').forEach(function(form){form.addEventListener('submit',function(){saveIfSamePage(form.getAttribute('action')||window.location.href);});});
 document.querySelectorAll('a[href]').forEach(function(link){link.addEventListener('click',function(){if(!link.target||link.target==='_self')saveIfSamePage(link.href);});});
})();
</script>
</body></html>