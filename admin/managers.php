<?php
require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/manager_role_transition.php';
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function pid($id){return 'SM-'.str_pad((string)(int)$id,6,'0',STR_PAD_LEFT);}
$message=''; $error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($csrf,(string)($_POST['csrf']??''))){$error='Security check failed. Please refresh and try again.';}
 else{
  $uid=(int)($_POST['user_id']??0); $action=$_POST['action']??'';
  if($action==='create_manager'){
   $first_name=trim((string)($_POST['first_name']??''));
   $last_name=trim((string)($_POST['last_name']??''));
   $gender=trim((string)($_POST['gender']??''));
   $mobile=trim((string)($_POST['mobile']??''));
   $email=strtolower(trim((string)($_POST['email']??'')));
   $password=(string)($_POST['password']??'');
   if($first_name===''||$last_name===''||$gender===''||$mobile===''||$email===''||$password===''){$error='Please complete all manager fields.';}
   elseif(!preg_match('/^[A-Za-z ]+$/',$first_name)||!preg_match('/^[A-Za-z ]+$/',$last_name)){$error='First and last name may contain letters and spaces only.';}
   elseif(!in_array($gender,['Male','Female'],true)){$error='Invalid gender selected.';}
   elseif(!preg_match('/^01[3-9][0-9]{8}$/',$mobile)){$error='Invalid mobile number.';}
   elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)){$error='Invalid email address.';}
   elseif(strlen($password)<8||!preg_match('/[A-Z]/',$password)||!preg_match('/[a-z]/',$password)||!preg_match('/[0-9]/',$password)||!preg_match('/[^A-Za-z0-9]/',$password)){$error='Password must be at least 8 characters and include uppercase, lowercase, number and special character.';}
   else{
    $dup=mysqli_prepare($conn,'SELECT user_id FROM users WHERE email=? OR mobile=? LIMIT 1');
    mysqli_stmt_bind_param($dup,'ss',$email,$mobile); mysqli_stmt_execute($dup); mysqli_stmt_store_result($dup);
    $exists=mysqli_stmt_num_rows($dup)>0; mysqli_stmt_close($dup);
    if($exists){$error='Email or Mobile already exists.';}
    else{
     $hash=password_hash($password,PASSWORD_DEFAULT);
     $ins=mysqli_prepare($conn,"INSERT INTO users(first_name,last_name,gender,mobile,email,password,role,account_status) VALUES(?,?,?,?,?,?, 'Manager','Active')");
     mysqli_stmt_bind_param($ins,'ssssss',$first_name,$last_name,$gender,$mobile,$email,$hash);
     if(mysqli_stmt_execute($ins)){$message='Manager account created successfully.';}else{$error='Unable to create manager account. Please try again.';}
     mysqli_stmt_close($ins);
    }
   }
  }elseif($action==='update_managers'){
   $statuses=$_POST['account_status']??[];
   if(!is_array($statuses)||!$statuses){$error='No manager status changes were submitted.';}
   else{
    $valid_statuses=['Active','Inactive','Suspended'];
    $updates=[]; $invalid=false;
    foreach($statuses as $manager_id=>$manager_status){
     $manager_id=(int)$manager_id;
     if($manager_id<=0||$manager_id===$admin_id||!in_array($manager_status,$valid_statuses,true)){$invalid=true;break;}
     $updates[$manager_id]=$manager_status;
    }
    if($invalid||!$updates){$error='Invalid manager status data.';}
    else{
     mysqli_begin_transaction($conn);
     $stmt=mysqli_prepare($conn,"UPDATE users SET account_status=? WHERE user_id=? AND role='Manager'");
     $ok=(bool)$stmt;
     if($ok){
      foreach($updates as $manager_id=>$manager_status){
       mysqli_stmt_bind_param($stmt,'si',$manager_status,$manager_id);
       if(!mysqli_stmt_execute($stmt)){$ok=false;break;}
      }
     }
     if($stmt) mysqli_stmt_close($stmt);
     if($ok){mysqli_commit($conn);$message='Manager account statuses updated successfully.';}
     else{mysqli_rollback($conn);$error='Unable to update manager statuses. Please try again.';}
    }
   }
  }elseif($uid<=0||$uid===$admin_id){$error='Invalid manager account.';}
 }
}
$q=trim((string)($_GET['q']??''));
$status=$_GET['status']??'All';
$where=["u.role='Manager'"];
$params=[]; $types='';
$normalized_q=preg_replace('/[^0-9]/','',$q);
$numeric_id=null;
if($q!==''){
    if(preg_match('/^SM[- ]?(\d+)$/i',$q,$qm)) $numeric_id=(int)$qm[1];
    elseif(preg_match('/^\d+$/',$q)) $numeric_id=(int)$q;
    if($numeric_id!==null){
        $where[]='u.user_id=?'; $params[]=$numeric_id; $types.='i';
    }elseif(filter_var($q,FILTER_VALIDATE_EMAIL)){
        $where[]='u.email=?'; $params[]=$q; $types.='s';
    }elseif(preg_match('/^01[3-9][0-9]{8}$/',$q)){
        $where[]='u.mobile=?'; $params[]=$q; $types.='s';
    }else{
        $like='%'.$q.'%';
        $where[]='(CONCAT(u.first_name," ",u.last_name)=? OR CONCAT(u.first_name," ",u.last_name) LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)';
        $params=[$q,$like,$like,$like]; $types='ssss';
    }
}
if($status!=='All'&&in_array($status,['Active','Inactive','Suspended'],true)){$where[]='u.account_status=?';$params[]=$status;$types.='s';}
$scope_where=implode(' AND ',$where);
$sql='SELECT u.user_id,u.first_name,u.last_name,u.gender,u.email,u.mobile,u.account_status,u.created_at,
       COALESCE(sp.profile_image,\'\') AS profile_image,
       (SELECT COUNT(*) FROM service_providers sp2 WHERE sp2.manager_id=u.user_id) package_count,
       (SELECT COUNT(*) FROM bookings b WHERE b.manager_id=u.user_id) booking_count
       FROM users u LEFT JOIN staff_profiles sp ON sp.user_id=u.user_id
       WHERE '.$scope_where.' ORDER BY u.user_id DESC LIMIT 100';
$st=mysqli_prepare($conn,$sql); if($params) mysqli_stmt_bind_param($st,$types,...$params); mysqli_stmt_execute($st); $res=mysqli_stmt_get_result($st); $managers=[]; while($r=mysqli_fetch_assoc($res))$managers[]=$r; mysqli_stmt_close($st);

$filtered_ids=array_map('intval',array_column($managers,'user_id'));
$id_list=$filtered_ids ? implode(',',array_map('intval',$filtered_ids)) : '0';
$has_manager_filter=($q!=='' || $status!=='All');
$manager_totals=['total'=>0,'active'=>0,'inactive'=>0,'suspended'=>0,'packages'=>0,'bookings'=>0];
$stat_res=mysqli_query($conn,"SELECT COUNT(*) AS total,
    SUM(account_status='Active') AS active,
    SUM(account_status='Inactive') AS inactive,
    SUM(account_status='Suspended') AS suspended
    FROM users WHERE role='Manager' AND user_id IN ($id_list)");
if($stat_res && ($sr=mysqli_fetch_assoc($stat_res))){
    $manager_totals['total']=(int)$sr['total'];
    $manager_totals['active']=(int)$sr['active'];
    $manager_totals['inactive']=(int)$sr['inactive'];
    $manager_totals['suspended']=(int)$sr['suspended'];
}
$pkg_stat=mysqli_query($conn,"SELECT COUNT(*) AS total FROM service_providers WHERE manager_id IN ($id_list)");
if($pkg_stat && ($sr=mysqli_fetch_assoc($pkg_stat))) $manager_totals['packages']=(int)$sr['total'];
$booking_stat=mysqli_query($conn,"SELECT COUNT(*) AS total FROM bookings WHERE manager_id IN ($id_list)");
if($booking_stat && ($sr=mysqli_fetch_assoc($booking_stat))) $manager_totals['bookings']=(int)$sr['total'];

$package_overview=['total'=>0,'active'=>0,'inactive'=>0,'with_manager'=>0];
$package_overview_res=mysqli_query($conn,"SELECT COUNT(*) AS total, SUM(status='Active') AS active, SUM(status='Inactive') AS inactive, COUNT(DISTINCT manager_id) AS with_manager FROM service_providers WHERE manager_id IN ($id_list)");
if($package_overview_res && ($sr=mysqli_fetch_assoc($package_overview_res))){
    $package_overview['total']=(int)$sr['total'];
    $package_overview['active']=(int)$sr['active'];
    $package_overview['inactive']=(int)$sr['inactive'];
    $package_overview['with_manager']=(int)$sr['with_manager'];
}
$booking_overview=['total'=>0,'pending'=>0,'confirmed'=>0,'completed'=>0,'cancelled'=>0,'value'=>0.0];
$booking_overview_res=mysqli_query($conn,"SELECT COUNT(*) AS total,
    SUM(booking_status='Pending') AS pending,
    SUM(booking_status='Confirmed') AS confirmed,
    SUM(booking_status='Completed') AS completed,
    SUM(booking_status='Cancelled') AS cancelled,
    COALESCE(SUM(total_price),0) AS value
    FROM bookings WHERE manager_id IN ($id_list)");
if($booking_overview_res && ($sr=mysqli_fetch_assoc($booking_overview_res))){
    $booking_overview['total']=(int)$sr['total'];
    $booking_overview['pending']=(int)$sr['pending'];
    $booking_overview['confirmed']=(int)$sr['confirmed'];
    $booking_overview['completed']=(int)$sr['completed'];
    $booking_overview['cancelled']=(int)$sr['cancelled'];
    $booking_overview['value']=(float)$sr['value'];
}
$manager_activity=[];
$activity_sql="SELECT u.user_id,u.first_name,u.last_name,u.account_status,
    COALESCE((SELECT COUNT(*) FROM service_providers sp3 WHERE sp3.manager_id=u.user_id),0) AS package_count,
    COALESCE((SELECT SUM(sp4.status='Active') FROM service_providers sp4 WHERE sp4.manager_id=u.user_id),0) AS active_package_count,
    COALESCE((SELECT COUNT(*) FROM bookings b2 WHERE b2.manager_id=u.user_id),0) AS booking_count,
    COALESCE((SELECT SUM(b3.booking_status='Pending') FROM bookings b3 WHERE b3.manager_id=u.user_id),0) AS pending_booking_count,
    COALESCE((SELECT SUM(b4.booking_status='Confirmed') FROM bookings b4 WHERE b4.manager_id=u.user_id),0) AS confirmed_booking_count,
    COALESCE((SELECT SUM(b5.booking_status='Completed') FROM bookings b5 WHERE b5.manager_id=u.user_id),0) AS completed_booking_count,
    COALESCE((SELECT SUM(b6.booking_status='Cancelled') FROM bookings b6 WHERE b6.manager_id=u.user_id),0) AS cancelled_booking_count,
    COALESCE((SELECT SUM(b7.total_price) FROM bookings b7 WHERE b7.manager_id=u.user_id),0) AS booking_value
    FROM users u WHERE u.role='Manager' AND u.user_id IN ($id_list) ORDER BY booking_count DESC, package_count DESC, u.user_id DESC";
$activity_res=mysqli_query($conn,$activity_sql);
if($activity_res) while($r=mysqli_fetch_assoc($activity_res)) $manager_activity[]=$r;

?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Managers | Smart Matrimony</title><link rel="stylesheet" href="../assets/css/admin.css"><link rel="stylesheet" href="../assets/css/admin-managers.css"></head><body>
<header class="manager-topbar">
  <div class="manager-brand">
    <img src="../assets/images/logo/logo.png" alt="Smart Matrimony" class="manager-brand-logo">
    <img src="../assets/images/logo/matrimony_title.png" alt="Smart Matrimony" class="manager-brand-title">
  </div>
  <a class="manager-topbar-logout" href="logout.php">↪ <span>Logout</span></a>
</header>
<main class="manager-wrap">
<div class="manager-page-top-actions">
<a class="manager-back-dashboard" href="dashboard.php">&#8592; Back to Admin Dashboard</a>
<div class="manager-top-booking-value" aria-label="Total Booking Value"><span class="manager-top-booking-icon">৳</span><div><strong>৳<?=number_format($booking_overview['value'],2)?></strong><small>Total Booking Value</small></div></div>
</div>
<?php if($message):?><div class="manager-toast success"><?=h($message)?></div><?php endif;?><?php if($error):?><div class="manager-toast error"><?=h($error)?></div><?php endif;?>
<section class="manager-global-search-section">
  <form class="manager-global-search" method="get">
    <div class="manager-global-search-field"><label for="globalManagerSearch">Search Manager</label><input id="globalManagerSearch" name="q" value="<?=h($q)?>" placeholder="SM-000020, 000020, 20, name, email or mobile" autocomplete="off"></div>
    <div class="manager-global-status"><label for="globalManagerStatus">Status</label><select id="globalManagerStatus" name="status"><option value="All">All Managers</option><?php foreach(['Active','Inactive','Suspended'] as $s):?><option value="<?=$s?>" <?=$status===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></div>
    <button type="submit" class="manager-global-search-btn">Search</button>
    <?php if($q!==''||$status!=='All'):?><a class="clear-btn" href="managers.php">Clear</a><?php endif;?>
  </form>
</section>
<section class="manager-profile-section">
  <div class="manager-section-heading"><div><span class="eyebrow">MANAGER DIRECTORY</span><h2>Managers <span class="manager-count">(<?=number_format($manager_totals['total'])?>)</span></h2><p>View manager profiles, start a conversation, or open detailed controls.</p></div><div class="manager-carousel-controls"><button type="button" class="carousel-btn" id="managerPrev" aria-label="Previous managers">&#8592;</button><span class="carousel-page" id="managerCarouselPage">1 / 1</span><button type="button" class="carousel-btn" id="managerNext" aria-label="Next managers">&#8594;</button></div></div>
  <div class="manager-profile-viewport" id="managerProfileViewport">
    <div class="manager-profile-track" id="managerProfileTrack">
      <?php foreach($managers as $m):
        $profile_image=(string)($m['profile_image']??'');
        $profile_file=$profile_image!=='' ? basename($profile_image) : '';
        $profile_url=$profile_file!=='' ? '../uploads/staff/'.rawurlencode($profile_file) : '';
        $initial=strtoupper(substr(trim((string)$m['first_name']),0,1));
        $staff_key='Manager:'.(int)$m['user_id'];
      ?>
      <article class="manager-profile-card">
        <div class="manager-card-photo-wrap">
          <?php if($profile_url && is_file(__DIR__.'/../uploads/staff/'.$profile_file)): ?><img src="<?=h($profile_url)?>" alt="<?=h($m['first_name'].' '.$m['last_name'])?> profile photo"><?php else: ?><span class="manager-card-initial"><?=h($initial ?: 'M')?></span><?php endif; ?>
        </div>
        <div class="manager-card-body">
          <span class="manager-card-status status-<?=strtolower(h($m['account_status']))?>"><?=h($m['account_status'])?></span>
          <h3><?=h($m['first_name'].' '.$m['last_name'])?></h3>
          <strong class="manager-card-id"><?=h(pid($m['user_id']))?></strong>
          <div class="manager-card-info"><span><?=h($m['gender'])?></span><span><?=h($m['mobile'])?></span><span class="manager-card-email"><?=h($m['email'])?></span></div>
          <div class="manager-card-mini-stats"><span><b><?=number_format((int)$m['package_count'])?></b><small>Packages</small></span><span><b><?=number_format((int)$m['booking_count'])?></b><small>Bookings</small></span></div>
          <div class="manager-card-actions"><a class="manager-message-btn" href="staff_messages.php?filter=Manager&amp;staff=<?=rawurlencode($staff_key)?>"><i class="fa-solid fa-envelope"></i> Message</a><a class="manager-view-btn" href="manager_details.php?user_id=<?=(int)$m['user_id']?>"><i class="fa-solid fa-eye"></i> View</a></div>
        </div>
      </article>
      <?php endforeach; ?>
      <?php if(!$managers): ?><div class="manager-profile-empty">No managers found for the current filter.</div><?php endif; ?>
    </div>
  </div>
</section>

<section class="manager-stats-section"><div class="manager-section-heading compact"><div><span class="eyebrow">LIVE OVERVIEW</span><h2>Statistics</h2></div></div>
  <div class="manager-stats-grid">
    <article><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M16 11a4 4 0 1 0-3.9-5A4 4 0 0 0 16 11Zm-8 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8.7 2h-1.4a5.3 5.3 0 0 0-4.8 3 5.3 5.3 0 0 0-4.8-3H4.3A4.3 4.3 0 0 0 0 17.3V20h24v-2.7a4.3 4.3 0 0 0-4.3-4.3ZM8.5 15a3.3 3.3 0 0 1 3 2h-6a3.3 3.3 0 0 1 3-2Zm7.5 0a3.3 3.3 0 0 1 3 2h-6a3.3 3.3 0 0 1 3-2Z"/></svg></span><div><strong><?=number_format($manager_totals['total'])?></strong><small>Total Managers</small></div></article>
    <article><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm-1.1 14.2-4-4 1.4-1.4 2.6 2.6 5.8-5.8 1.4 1.4Z"/></svg></span><div><strong><?=number_format($manager_totals['active'])?></strong><small>Active</small></div></article>
    <article><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm-1 13H8V9h3Zm5 0h-3V9h3Z"/></svg></span><div><strong><?=number_format($manager_totals['inactive'])?></strong><small>Inactive</small></div></article>
    <article><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm5.2 13.8L8.2 6.8A7.96 7.96 0 0 1 17.2 15.8ZM6.8 8.2l9 9A7.96 7.96 0 0 1 6.8 8.2Z"/></svg></span><div><strong><?=number_format($manager_totals['suspended'])?></strong><small>Suspended</small></div></article>
    <article><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="m12 2 9 4.5v11L12 22l-9-4.5v-11Zm0 2.2L6 7l6 3 6-3Zm-7 4.4v7.7l6 3v-7.7Zm8 13.1 6-3V8.6l-6 3Z"/></svg></span><div><strong><?=number_format($manager_totals['packages'])?></strong><small>Total Packages</small></div></article>
    <article><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M7 2h2v3h6V2h2v3h3v17H4V5h3Zm-1 8v10h14V10Zm3 6 2 2 5-5-1.4-1.4-3.6 3.6-0.6-.6Z"/></svg></span><div><strong><?=number_format($manager_totals['bookings'])?></strong><small>Total Bookings</small></div></article>
  </div>
</section>

<section class="panel manager-service-summary-section">
  <div class="manager-section-heading"><div><span class="eyebrow">SERVICE COVERAGE</span><h2>Service Package Overview</h2><p>See how many packages are currently available under each service.</p></div></div>
  <div class="manager-service-summary-grid">
    <?php
    $service_summary_res=mysqli_query($conn,"SELECT s.service_id,s.service_name,COUNT(sp.provider_id) AS package_count FROM services s LEFT JOIN service_providers sp ON sp.service_id=s.service_id GROUP BY s.service_id,s.service_name ORDER BY s.service_id ASC");
    if($service_summary_res): while($service_row=mysqli_fetch_assoc($service_summary_res)): ?>
      <article class="manager-service-summary-item">
        <span class="manager-service-summary-name"><?=h($service_row['service_name'])?></span>
        <strong><?=number_format((int)$service_row['package_count'])?></strong>
        <small>Packages</small>
      </article>
    <?php endwhile; else: ?>
      <div class="manager-service-summary-empty">Service data is currently unavailable.</div>
    <?php endif; ?>
  </div>
</section>

<section class="panel manager-create-section">
  <div class="manager-section-heading"><div><span class="eyebrow">CREATE MANAGER</span><h2>Create Manager</h2><p>Add a new manager account with direct login access.</p></div></div>
  <form class="manager-create-form" method="post" autocomplete="off">
    <input type="hidden" name="csrf" value="<?=h($csrf)?>">
    <input type="hidden" name="action" value="create_manager">
    <div class="manager-create-grid">
      <div><label for="managerFirstName">First Name</label><input id="managerFirstName" type="text" name="first_name" maxlength="50" required></div>
      <div><label for="managerLastName">Last Name</label><input id="managerLastName" type="text" name="last_name" maxlength="50" required></div>
      <div><label for="managerEmail">Email</label><input id="managerEmail" type="email" name="email" maxlength="100" required></div>
      <div><label for="managerGender">Gender</label><select id="managerGender" name="gender" required><option value="">Select Gender</option><option value="Male">Male</option><option value="Female">Female</option></select></div>
      <div><label for="managerMobile">Mobile</label><input id="managerMobile" type="tel" name="mobile" maxlength="15" inputmode="numeric" placeholder="01XXXXXXXXX" required></div>
      <div><label for="managerPassword">Password</label><input id="managerPassword" type="password" name="password" minlength="8" required><small>8+ chars with uppercase, lowercase, number &amp; special character.</small></div>
    </div>
    <div class="manager-create-actions"><button type="submit" class="manager-create-btn">Create Manager</button></div>
  </form>
</section>

<section class="panel manager-directory-section"><div class="panel-head"><div><span class="eyebrow">MANAGER MANAGEMENT</span><h2>Manager Directory &amp; Controls</h2><p class="section-note">Manage the manager account status. Detailed controls remain available from View.</p></div></div>
<form class="manager-status-form" method="post" id="managerStatusForm">
<input type="hidden" name="csrf" value="<?=h($csrf)?>">
<input type="hidden" name="action" value="update_managers">
<div class="manager-table-scroll"><table class="manager-table"><thead><tr><th>ID</th><th>Manager</th><th>Contact</th><th>Account</th><th>Packages</th><th>Bookings</th><th>Actions</th></tr></thead><tbody>
<?php foreach($managers as $m):?><tr><td><span class="public-id"><?=h(pid($m['user_id']))?></span></td><td><strong><?=h($m['first_name'].' '.$m['last_name'])?></strong><small><?=h($m['gender'])?></small></td><td><?=h($m['email'])?><small><?=h($m['mobile'])?></small></td><td><select name="account_status[<?=h($m['user_id'])?>]" aria-label="Account status for <?=h($m['first_name'].' '.$m['last_name'])?>"><?php foreach(['Active','Inactive','Suspended'] as $s):?><option value="<?=$s?>" <?=$m['account_status']===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></td><td><?=number_format((int)$m['package_count'])?></td><td><?=number_format((int)$m['booking_count'])?></td><td class="manager-actions"><a class="btn-light" href="manager_details.php?user_id=<?=$m['user_id']?>">View</a></td></tr><?php endforeach;?><?php if(!$managers):?><tr><td colspan="7" class="empty">No managers found.</td></tr><?php endif;?></tbody></table></div>
<div class="manager-directory-save-wrap"><button type="submit" class="btn-save manager-directory-save">Save Changes</button></div>
</form></section>

<section class="panel manager-overview-section">
  <div class="manager-section-heading"><div><span class="eyebrow">SERVICE ACTIVITY</span><h2>Package Management Overview</h2><p>Monitor package activity across all managers. Detailed package controls remain available from each manager profile.</p></div></div>
  <div class="manager-overview-stats">
    <article><span class="overview-icon">▦</span><div><strong><?=number_format($package_overview['total'])?></strong><small>Total Packages</small></div></article>
    <article><span class="overview-icon">✓</span><div><strong><?=number_format($package_overview['active'])?></strong><small>Active Packages</small></div></article>
    <article><span class="overview-icon">Ⅱ</span><div><strong><?=number_format($package_overview['inactive'])?></strong><small>Inactive Packages</small></div></article>
    <article><span class="overview-icon">M</span><div><strong><?=number_format($package_overview['with_manager'])?></strong><small>Managers with Packages</small></div></article>
  </div>
  <div class="manager-overview-table-wrap">
    <table class="manager-overview-table"><thead><tr><th>Manager</th><th>Status</th><th>Total</th><th>Active</th><th>Inactive</th><th>Action</th></tr></thead><tbody>
    <?php foreach($manager_activity as $ma): ?>
      <tr><td><strong><?=h($ma['first_name'].' '.$ma['last_name'])?></strong><small><?=h(pid($ma['user_id']))?></small></td><td><span class="overview-status status-<?=strtolower(h($ma['account_status']))?>"><?=h($ma['account_status'])?></span></td><td><?=number_format((int)$ma['package_count'])?></td><td><?=number_format((int)$ma['active_package_count'])?></td><td><?=number_format(max(0,(int)$ma['package_count']-(int)$ma['active_package_count']))?></td><td><a class="overview-view-btn" href="manager_details.php?user_id=<?=(int)$ma['user_id']?>">View Manager</a></td></tr>
    <?php endforeach; ?>
    <?php if(!$manager_activity): ?><tr><td colspan="6" class="overview-empty">No manager package activity found.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
</section>

<section class="panel manager-overview-section">
  <div class="manager-section-heading"><div><span class="eyebrow">BOOKING ACTIVITY</span><h2>Booking Management Overview</h2><p>Monitor booking status and booking value across manager accounts.</p></div></div>
  <div class="manager-overview-stats booking-overview-stats">
    <article><span class="overview-icon">#</span><div><strong><?=number_format($booking_overview['total'])?></strong><small>Total Bookings</small></div></article>
    <article><span class="overview-icon">…</span><div><strong><?=number_format($booking_overview['pending'])?></strong><small>Pending</small></div></article>
    <article><span class="overview-icon">✓</span><div><strong><?=number_format($booking_overview['confirmed'])?></strong><small>Confirmed</small></div></article>
    <article><span class="overview-icon">✓✓</span><div><strong><?=number_format($booking_overview['completed'])?></strong><small>Completed</small></div></article>
    <article><span class="overview-icon">×</span><div><strong><?=number_format($booking_overview['cancelled'])?></strong><small>Cancelled</small></div></article>
    <article><span class="overview-icon">৳</span><div><strong>৳<?=number_format($booking_overview['value'],2)?></strong><small>Total Booking Value</small></div></article>
  </div>
  <div class="manager-overview-table-wrap">
    <table class="manager-overview-table booking-overview-table"><thead><tr><th>Manager</th><th>Pending</th><th>Confirmed</th><th>Completed</th><th>Cancelled</th><th>Booking Value</th><th>Action</th></tr></thead><tbody>
    <?php foreach($manager_activity as $ma): ?>
      <tr><td><strong><?=h($ma['first_name'].' '.$ma['last_name'])?></strong><small><?=h(pid($ma['user_id']))?></small></td><td><?=number_format((int)$ma['pending_booking_count'])?></td><td><?=number_format((int)$ma['confirmed_booking_count'])?></td><td><?=number_format((int)$ma['completed_booking_count'])?></td><td><?=number_format((int)$ma['cancelled_booking_count'])?></td><td>৳<?=number_format((float)$ma['booking_value'],2)?></td><td><a class="overview-view-btn" href="manager_details.php?user_id=<?=(int)$ma['user_id']?>">View Manager</a></td></tr>
    <?php endforeach; ?>
    <?php if(!$manager_activity): ?><tr><td colspan="7" class="overview-empty">No manager booking activity found.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
</section>

</main>
<script>
(function(){
 const viewport=document.getElementById('managerProfileViewport'), track=document.getElementById('managerProfileTrack'), prev=document.getElementById('managerPrev'), next=document.getElementById('managerNext'), page=document.getElementById('managerCarouselPage');
 if(!viewport||!track||!prev||!next||!page) return;
 function metrics(){
   const cards=track.querySelectorAll('.manager-profile-card');
   if(!cards.length){page.textContent='0 / 0'; prev.disabled=next.disabled=true; return {pages:0,index:0};}
   const card=cards[0], gap=parseFloat(getComputedStyle(track).gap)||0, visible=Math.max(1,Math.floor((viewport.clientWidth+gap)/(card.getBoundingClientRect().width+gap))), pages=Math.max(1,Math.ceil(cards.length/visible));
   let index=Math.round(viewport.scrollLeft/Math.max(1,viewport.clientWidth));
   index=Math.min(index,pages-1); return {pages,index};
 }
 function update(){const m=metrics(); page.textContent=m.pages?((m.index+1)+' / '+m.pages):'0 / 0'; prev.disabled=m.index<=0; next.disabled=m.index>=m.pages-1;}
 prev.addEventListener('click',()=>viewport.scrollBy({left:-viewport.clientWidth,behavior:'smooth'}));
 next.addEventListener('click',()=>viewport.scrollBy({left:viewport.clientWidth,behavior:'smooth'}));
 viewport.addEventListener('scroll',()=>requestAnimationFrame(update)); window.addEventListener('resize',update); update();
})();


(function(){
 const scrollKey='managerPageScroll:'+window.location.pathname+window.location.search;
 const saved=window.sessionStorage.getItem(scrollKey);
 if(saved!==null){
  window.sessionStorage.removeItem(scrollKey);
  window.requestAnimationFrame(function(){window.scrollTo(0,parseInt(saved,10)||0);});
 }
 document.querySelectorAll('form[method="post"], form[method="POST"]').forEach(function(form){
  form.addEventListener('submit',function(){
   window.sessionStorage.setItem(scrollKey,String(window.scrollY||window.pageYOffset||0));
  });
 });
})();
</script>
</body></html>
