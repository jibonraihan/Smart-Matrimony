<?php
require_once __DIR__ . '/admin_guard.php';
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function public_id($id){ return 'SM-' . str_pad((string)(int)$id, 6, '0', STR_PAD_LEFT); }
function admin_redirect($params=[]){ header('Location: users.php' . ($params ? '?' . http_build_query($params) : '')); exit; }

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid <= 0 || $uid === $admin_id) {
            $error = 'Invalid account action.';
        } elseif ($action === 'update_account') {
            $status = $_POST['account_status'] ?? '';
            $allowed_status = ['Active','Inactive','Suspended'];
            if (!in_array($status, $allowed_status, true)) {
                $error = 'Invalid account status.';
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE users SET account_status=? WHERE user_id=? AND role='User'");
                mysqli_stmt_bind_param($stmt, 'si', $status, $uid);
                mysqli_stmt_execute($stmt);
                if (mysqli_stmt_affected_rows($stmt) >= 0) $message = 'User account updated successfully.'; else $error = 'Unable to update this user account.';
                mysqli_stmt_close($stmt);
            }
        } elseif ($action === 'delete_user') {
            $confirm = trim((string)($_POST['confirm_text'] ?? ''));
            if ($confirm !== 'DELETE') {
                $error = 'Type DELETE exactly to permanently remove the account.';
            } else {
                mysqli_begin_transaction($conn);
                try {
                    // These relationships use SET NULL/RESTRICT rather than full cascade.
                    $stmt = mysqli_prepare($conn, "DELETE FROM verification_logs WHERE authenticator_id=?");
                    mysqli_stmt_bind_param($stmt, 'i', $uid); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
                    // Manager-owned packages and bookings are preserved, but ownership is cleared by the FK design.
                    $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE user_id=? AND role='User'");
                    mysqli_stmt_bind_param($stmt, 'i', $uid);
                    if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) throw new Exception('Account could not be removed.');
                    mysqli_stmt_close($stmt);
                    mysqli_commit($conn);
                    header('Location: users.php?deleted=1'); exit;
                } catch (Throwable $e) {
                    mysqli_rollback($conn);
                    $error = 'Unable to permanently remove this account. Please check related records and try again.';
                }
            }
        }
    }
}
if (isset($_GET['deleted'])) $message = 'Account permanently removed.';

$q = trim((string)($_GET['q'] ?? ''));
$role = (string)($_GET['role'] ?? '');
$status = (string)($_GET['status'] ?? '');
$gender = (string)($_GET['gender'] ?? '');
$verification = (string)($_GET['verification'] ?? '');
$sort = (string)($_GET['sort'] ?? 'newest');
if (!in_array($sort, ['newest','oldest'], true)) $sort = 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 40;
$offset = ($page - 1) * $per_page;
$where = ["u.role = 'User'"];
$params = [];
$types = '';
if ($q !== '') {
    $where[] = "(CAST(u.user_id AS CHAR) LIKE ? OR CONCAT('SM-', LPAD(CAST(u.user_id AS CHAR),6,'0')) LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like,$like,$like,$like,$like,$like); $types .= 'ssssss';
}
if (in_array($status,['Active','Inactive','Suspended'],true)) { $where[]='u.account_status=?'; $params[]=$status; $types.='s'; }
if (in_array($gender,['Male','Female'],true)) { $where[]='u.gender=?'; $params[]=$gender; $types.='s'; }
if (in_array($verification,['Pending','Verified','Rejected'],true)) { $where[]='up.verification_status=?'; $params[]=$verification; $types.='s'; } elseif ($verification === 'No profile') { $where[]='up.profile_id IS NULL'; }
$where_sql = implode(' AND ', $where);

$total = 0;
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM users u LEFT JOIN user_profiles up ON up.user_id=u.user_id WHERE $where_sql");
if ($params) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt); mysqli_stmt_bind_result($stmt,$total); mysqli_stmt_fetch($stmt); mysqli_stmt_close($stmt);
$total_pages = max(1, (int)ceil($total/$per_page));
if ($page > $total_pages) { $page=$total_pages; $offset=($page-1)*$per_page; }

$sort_direction = $sort === 'oldest' ? 'ASC' : 'DESC';
$sql = "SELECT u.user_id,u.first_name,u.last_name,u.gender,u.mobile,u.email,u.role,u.account_status,u.created_at,
               up.profile_id,up.verification_status,up.profile_visibility,up.photo_visibility,up.photo
        FROM users u LEFT JOIN user_profiles up ON up.user_id=u.user_id
        WHERE $where_sql ORDER BY u.created_at $sort_direction, u.user_id $sort_direction LIMIT ? OFFSET ?";
$list_params = $params; $list_types = $types . 'ii'; $list_params[]=$per_page; $list_params[]=$offset;
$stmt = mysqli_prepare($conn,$sql); mysqli_stmt_bind_param($stmt,$list_types,...$list_params); mysqli_stmt_execute($stmt); $res=mysqli_stmt_get_result($stmt);
$users=[]; while($r=mysqli_fetch_assoc($res)) $users[]=$r; mysqli_stmt_close($stmt);

function page_url($p){
    $params=$_GET; $params['page']=$p; return 'users.php?' . http_build_query($params);
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>User Management | Smart Matrimony</title><link rel="stylesheet" href="../assets/css/admin.css"><link rel="stylesheet" href="../assets/css/admin-users.css"></head><body>
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
<main class="wrap users-wrap">
<a class="user-back-btn" href="dashboard.php">&#8592; Back to Admin Dashboard</a>
<?php if($message):?><div class="admin-toast success" role="status">✓ <?=h($message)?></div><?php endif;?><?php if($error):?><div class="admin-toast error" role="alert">! <?=h($error)?></div><?php endif;?>
<?php
$user_stats = [
    'total'=>0,'active'=>0,'male'=>0,'female'=>0,'with_profile'=>0,'without_profile'=>0,
    'public_profile'=>0,'hidden_profile'=>0,'pending'=>0,'verified'=>0,'rejected'=>0,
    'with_photo'=>0,'without_photo'=>0,'inactive'=>0,'suspended'=>0,
    'pending_interests'=>0,'accepted_matches'=>0,'active_matches'=>0,'closed_interests'=>0,
    'bookmarks'=>0,'pending_chat_requests'=>0,'active_chats'=>0,'messages'=>0
];
$stats_sql = "SELECT
    COUNT(*) AS total,
    COALESCE(SUM(u.account_status='Active'),0) AS active,
    COALESCE(SUM(u.account_status='Inactive'),0) AS inactive,
    COALESCE(SUM(u.account_status='Suspended'),0) AS suspended,
    COALESCE(SUM(u.gender='Male'),0) AS male,
    COALESCE(SUM(u.gender='Female'),0) AS female,
    COALESCE(SUM(up.profile_id IS NOT NULL),0) AS with_profile,
    COALESCE(SUM(up.profile_id IS NULL),0) AS without_profile,
    COALESCE(SUM(up.profile_visibility='Public'),0) AS public_profile,
    COALESCE(SUM(up.profile_visibility='Hidden'),0) AS hidden_profile,
    COALESCE(SUM(up.verification_status='Pending'),0) AS pending,
    COALESCE(SUM(up.verification_status='Verified'),0) AS verified,
    COALESCE(SUM(up.verification_status='Rejected'),0) AS rejected,
    COALESCE(SUM(up.photo IS NOT NULL AND up.photo<>''),0) AS with_photo,
    COALESCE(SUM(up.profile_id IS NOT NULL AND (up.photo IS NULL OR up.photo='')),0) AS without_photo
    FROM users u LEFT JOIN user_profiles up ON up.user_id=u.user_id
    WHERE u.role='User'";
$stats_stmt = mysqli_prepare($conn, $stats_sql);
if ($stats_stmt && mysqli_stmt_execute($stats_stmt)) {
    $stats_result = mysqli_stmt_get_result($stats_stmt);
    if ($stats_result && ($stats_row = mysqli_fetch_assoc($stats_result))) {
        foreach ($user_stats as $key => $_) {
            if (array_key_exists($key, $stats_row)) $user_stats[$key] = (int)$stats_row[$key];
        }
    }
}
if ($stats_stmt) mysqli_stmt_close($stats_stmt);

$match_stats_sql = "SELECT
    COALESCE(SUM(m.status='Pending' AND su.role='User' AND ru.role='User'),0) AS pending_interests,
    COALESCE(SUM(m.status='Accepted' AND su.role='User' AND ru.role='User'),0) AS accepted_matches,
    COALESCE(SUM(m.status='Accepted' AND m.relationship_active=1 AND su.role='User' AND ru.role='User'),0) AS active_matches,
    COALESCE(SUM(m.status IN ('Rejected','Cancelled','Unmatched') AND su.role='User' AND ru.role='User'),0) AS closed_interests
    FROM matches m
    INNER JOIN users su ON su.user_id=m.sender_user_id
    INNER JOIN users ru ON ru.user_id=m.receiver_user_id";
$match_stmt = mysqli_prepare($conn, $match_stats_sql);
if ($match_stmt && mysqli_stmt_execute($match_stmt)) {
    $match_result = mysqli_stmt_get_result($match_stmt);
    if ($match_result && ($match_row = mysqli_fetch_assoc($match_result))) {
        foreach (['pending_interests','accepted_matches','active_matches','closed_interests'] as $key) $user_stats[$key] = (int)($match_row[$key] ?? 0);
    }
}
if ($match_stmt) mysqli_stmt_close($match_stmt);

$bookmark_sql = "SELECT COUNT(*) AS total FROM bookmarks b INNER JOIN users u ON u.user_id=b.user_id WHERE u.role='User'";
$bookmark_result = mysqli_query($conn, $bookmark_sql);
if ($bookmark_result && ($row = mysqli_fetch_assoc($bookmark_result))) $user_stats['bookmarks'] = (int)$row['total'];

$chat_sql = "SELECT
    COALESCE(SUM(cr.status='Pending' AND su.role='User' AND ru.role='User'),0) AS pending_chat_requests,
    COALESCE(SUM(cr.status='Accepted' AND cr.chat_active=1 AND su.role='User' AND ru.role='User'),0) AS active_chats
    FROM chat_requests cr
    INNER JOIN users su ON su.user_id=cr.sender_user_id
    INNER JOIN users ru ON ru.user_id=cr.receiver_user_id";
$chat_stmt = mysqli_prepare($conn, $chat_sql);
if ($chat_stmt && mysqli_stmt_execute($chat_stmt)) {
    $chat_result = mysqli_stmt_get_result($chat_stmt);
    if ($chat_result && ($chat_row = mysqli_fetch_assoc($chat_result))) {
        $user_stats['pending_chat_requests'] = (int)($chat_row['pending_chat_requests'] ?? 0);
        $user_stats['active_chats'] = (int)($chat_row['active_chats'] ?? 0);
    }
}
if ($chat_stmt) mysqli_stmt_close($chat_stmt);

$message_sql = "SELECT COUNT(*) AS total FROM conversation_messages cm INNER JOIN users u ON u.user_id=cm.sender_user_id WHERE u.role='User'";
$message_result = mysqli_query($conn, $message_sql);
if ($message_result && ($row = mysqli_fetch_assoc($message_result))) $user_stats['messages'] = (int)$row['total'];
?>
<section class="user-stats-block" aria-label="User statistics">
  <div class="stats-section-title"><span class="eyebrow">USER OVERVIEW</span><h2>User Statistics</h2></div>
  <div class="user-stats-grid">
    <div class="user-stat-card total"><div class="user-stat-icon">👥</div><div><span>Total Users</span><strong><?=number_format($user_stats['total'])?></strong></div></div>
    <div class="user-stat-card active"><div class="user-stat-icon">✓</div><div><span>Active Users</span><strong><?=number_format($user_stats['active'])?></strong></div></div>
    <div class="user-stat-card male"><div class="user-stat-icon">♂</div><div><span>Male Users</span><strong><?=number_format($user_stats['male'])?></strong></div></div>
    <div class="user-stat-card female"><div class="user-stat-icon">♀</div><div><span>Female Users</span><strong><?=number_format($user_stats['female'])?></strong></div></div>
    <div class="user-stat-card profile"><div class="user-stat-icon">◉</div><div><span>With Profile</span><strong><?=number_format($user_stats['with_profile'])?></strong></div></div>
    <div class="user-stat-card no-profile"><div class="user-stat-icon">○</div><div><span>Without Profile</span><strong><?=number_format($user_stats['without_profile'])?></strong></div></div>
    <div class="user-stat-card public"><div class="user-stat-icon">◎</div><div><span>Public Profiles</span><strong><?=number_format($user_stats['public_profile'])?></strong></div></div>
    <div class="user-stat-card hidden"><div class="user-stat-icon">◌</div><div><span>Hidden Profiles</span><strong><?=number_format($user_stats['hidden_profile'])?></strong></div></div>
    <div class="user-stat-card pending"><div class="user-stat-icon">◷</div><div><span>Pending Verification</span><strong><?=number_format($user_stats['pending'])?></strong></div></div>
    <div class="user-stat-card verified"><div class="user-stat-icon">✓</div><div><span>Verified Profiles</span><strong><?=number_format($user_stats['verified'])?></strong></div></div>
    <div class="user-stat-card rejected"><div class="user-stat-icon">!</div><div><span>Rejected Profiles</span><strong><?=number_format($user_stats['rejected'])?></strong></div></div>
    <div class="user-stat-card photo"><div class="user-stat-icon">▣</div><div><span>With Profile Photo</span><strong><?=number_format($user_stats['with_photo'])?></strong></div></div>
    <div class="user-stat-card no-photo"><div class="user-stat-icon">□</div><div><span>Without Profile Photo</span><strong><?=number_format($user_stats['without_photo'])?></strong></div></div>
    <div class="user-stat-card inactive"><div class="user-stat-icon">Ⅱ</div><div><span>Inactive Users</span><strong><?=number_format($user_stats['inactive'])?></strong></div></div>
    <div class="user-stat-card suspended"><div class="user-stat-icon">!</div><div><span>Suspended Users</span><strong><?=number_format($user_stats['suspended'])?></strong></div></div>
  </div>
  <div class="stats-section-title compact"><span class="eyebrow">MATCH &amp; COMMUNICATION</span><h3>Activity Statistics</h3></div>
  <div class="user-stats-grid user-stats-grid-activity">
    <div class="user-stat-card match-pending"><div class="user-stat-icon">♡</div><div><span>Pending Interests</span><strong><?=number_format($user_stats['pending_interests'])?></strong></div></div>
    <div class="user-stat-card match-accepted"><div class="user-stat-icon">♥</div><div><span>Accepted Matches</span><strong><?=number_format($user_stats['accepted_matches'])?></strong></div></div>
    <div class="user-stat-card match-active"><div class="user-stat-icon">↔</div><div><span>Active Matches</span><strong><?=number_format($user_stats['active_matches'])?></strong></div></div>
    <div class="user-stat-card match-rejected"><div class="user-stat-icon">×</div><div><span>Rejected / Closed Interests</span><strong><?=number_format($user_stats['closed_interests'])?></strong></div></div>
    <div class="user-stat-card bookmarks"><div class="user-stat-icon">★</div><div><span>Bookmarks</span><strong><?=number_format($user_stats['bookmarks'])?></strong></div></div>
    <div class="user-stat-card chat-pending"><div class="user-stat-icon">◷</div><div><span>Pending Chat Requests</span><strong><?=number_format($user_stats['pending_chat_requests'])?></strong></div></div>
    <div class="user-stat-card chat-active"><div class="user-stat-icon">◌</div><div><span>Active Chats</span><strong><?=number_format($user_stats['active_chats'])?></strong></div></div>
    <div class="user-stat-card messages"><div class="user-stat-icon">✉</div><div><span>User Messages</span><strong><?=number_format($user_stats['messages'])?></strong></div></div>
  </div>
</section>
<section class="panel"><div class="panel-head"><div><span class="eyebrow">ACCOUNT DIRECTORY</span><h2>Registered Users</h2><p class="muted">Search by full public ID, numeric ID, name, email or mobile. Manager and Authenticator accounts are managed separately.</p></div></div>
<section class="user-summary"><div><span>Accounts found</span><strong><?=number_format($total)?></strong></div><div><span>Page</span><strong><?=number_format($page)?> / <?=number_format($total_pages)?></strong></div><div><span>Per page</span><strong><?=number_format($per_page)?></strong></div></section>
<form class="user-filters" method="get"><div class="search-box"><label for="q">Search</label><input id="q" name="q" value="<?=h($q)?>" placeholder="SM-000020, ID, name, email or mobile"></div><div class="filter-field"><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option><?php foreach(['Active','Inactive','Suspended'] as $s):?><option value="<?=h($s)?>" <?=$status===$s?'selected':''?>><?=h($s)?></option><?php endforeach;?></select></div><div class="filter-field"><label for="gender">Gender</label><select id="gender" name="gender"><option value="">All genders</option><option value="Male" <?=$gender==='Male'?'selected':''?>>Male</option><option value="Female" <?=$gender==='Female'?'selected':''?>>Female</option></select></div><div class="filter-field"><label for="sort">Sort</label><select id="sort" name="sort"><option value="newest" <?=$sort==='newest'?'selected':''?>>Newest first</option><option value="oldest" <?=$sort==='oldest'?'selected':''?>>Oldest first</option></select></div><div class="filter-field"><label for="verification">Verification</label><select id="verification" name="verification"><option value="">All verification</option><option value="Pending" <?=$verification==='Pending'?'selected':''?>>Pending</option><option value="Verified" <?=$verification==='Verified'?'selected':''?>>Verified</option><option value="Rejected" <?=$verification==='Rejected'?'selected':''?>>Rejected</option><option value="No profile" <?=$verification==='No profile'?'selected':''?>>No profile</option></select></div><button type="submit">Search</button><?php if($q||$status||$gender||$verification||$sort!=='newest'):?><a class="clear-filter" href="users.php" aria-label="Clear filters" title="Clear filters"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M9 6V4h6v2m-8 0 1 14h8l1-14M10 10v6m4-6v6" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg></a><?php endif;?></form>
<div class="user-table-scroll"><table class="user-table"><thead><tr><th>ID</th><th>User</th><th>Contact</th><th>Role</th><th>Account</th><th>Verification</th><th>Profile</th><th>Actions</th></tr></thead><tbody>
<?php foreach($users as $u):?><tr><td><span class="public-id"><?=h(public_id($u['user_id']))?></span><small>#<?=h($u['user_id'])?></small></td><td><strong><?=h($u['first_name'].' '.$u['last_name'])?></strong><small><?=h($u['gender'])?> · <?=h(date('d M Y',strtotime($u['created_at'])))?></small></td><td><?=h($u['email'])?><small><?=h($u['mobile'])?></small></td><td><span class="role-pill">User</span></td><td><form method="post" class="inline-action"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="update_account"><input type="hidden" name="user_id" value="<?=$u['user_id']?>"><select name="account_status"><?php foreach(['Active','Inactive','Suspended'] as $s):?><option <?=$u['account_status']===$s?'selected':''?>><?=h($s)?></option><?php endforeach;?></select></td><td><span class="status-pill <?=strtolower(h($u['verification_status']?:'No profile'))?>"><?=h($u['verification_status']?:'No profile')?></span></td><td><?= $u['profile_id'] ? h($u['profile_visibility'].' · '.$u['photo_visibility']) : '<span class="muted">No profile</span>' ?></td><td class="actions-cell"><a class="btn-light" href="user_details.php?user_id=<?=$u['user_id']?>">Details</a><button class="btn-save" type="submit">Save</button></form><form method="post" class="delete-mini" onsubmit="return confirm('This permanently deletes the account and all user-owned records. Continue?');"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?=$u['user_id']?>"><input type="hidden" name="confirm_text" value="DELETE"><button type="submit" class="btn-danger">Remove permanently</button></form></td></tr><?php endforeach;?>
<?php if(!$users):?><tr><td colspan="8" class="empty">No accounts match your search.</td></tr><?php endif;?></tbody></table></div>
<?php if($total>0): $show_from=$offset+1; $show_to=min($offset+$per_page,$total); ?><div class="user-results-meta">Showing <?=number_format($show_from)?>–<?=number_format($show_to)?> of <?=number_format($total)?> users</div><?php endif;?>
<?php if($total_pages>1):?><nav class="pagination" aria-label="User pages"><?php if($page>1):?><a href="<?=h(page_url($page-1))?>">← Previous</a><?php endif;?><span>Page <?=number_format($page)?> of <?=number_format($total_pages)?></span><?php if($page<$total_pages):?><a href="<?=h(page_url($page+1))?>">Next →</a><?php endif;?></nav><?php endif;?></section></main><script>
(function(){
  const key='userManagementScroll:'+window.location.pathname;
  const save=()=>{try{sessionStorage.setItem(key,String(window.scrollY));}catch(e){}};
  document.querySelectorAll('form.user-filters, .pagination a, .clear-filter').forEach(el=>el.addEventListener('click',save));
  const filter=document.querySelector('form.user-filters');
  if(filter) filter.addEventListener('submit',save);
  try{
    const y=sessionStorage.getItem(key);
    if(y!==null){sessionStorage.removeItem(key); requestAnimationFrame(()=>requestAnimationFrame(()=>window.scrollTo(0,parseInt(y,10)||0)));}
  }catch(e){}
})();
</script>

</body></html>
