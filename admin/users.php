<?php
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_name('SMART_ADMIN_SESSION'); session_start(); }
if (empty($_SESSION['admin_user_id'])) { header('Location: login.php'); exit; }
$admin_id = (int)$_SESSION['admin_user_id'];
if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['admin_csrf'];
$message = '';
$error = '';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function public_id($id){ return 'SM-' . str_pad((string)(int)$id, 6, '0', STR_PAD_LEFT); }
function admin_redirect($params=[]){ header('Location: users.php' . ($params ? '?' . http_build_query($params) : '')); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid <= 0 || $uid === $admin_id) {
            $error = 'Invalid account action.';
        } elseif ($action === 'update_account') {
            $role = $_POST['role'] ?? '';
            $status = $_POST['account_status'] ?? '';
            $allowed_roles = ['User','Manager','Authenticator'];
            $allowed_status = ['Active','Inactive','Suspended'];
            if (!in_array($role, $allowed_roles, true) || !in_array($status, $allowed_status, true)) {
                $error = 'Invalid role or account status.';
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE users SET role=?, account_status=? WHERE user_id=? AND role<>'Admin'");
                mysqli_stmt_bind_param($stmt, 'ssi', $role, $status, $uid);
                if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) >= 0) $message = 'Account updated successfully.';
                else $error = 'Unable to update this account.';
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
                    $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE user_id=? AND role<>'Admin'");
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
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 40;
$offset = ($page - 1) * $per_page;
$where = ["u.role <> 'Admin'"];
$params = [];
$types = '';
if ($q !== '') {
    $where[] = "(CAST(u.user_id AS CHAR) LIKE ? OR CONCAT('SM-', LPAD(CAST(u.user_id AS CHAR),6,'0')) LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like,$like,$like,$like,$like,$like); $types .= 'ssssss';
}
if (in_array($role,['User','Manager','Authenticator'],true)) { $where[]='u.role=?'; $params[]=$role; $types.='s'; }
if (in_array($status,['Active','Inactive','Suspended'],true)) { $where[]='u.account_status=?'; $params[]=$status; $types.='s'; }
if (in_array($gender,['Male','Female'],true)) { $where[]='u.gender=?'; $params[]=$gender; $types.='s'; }
$where_sql = implode(' AND ', $where);

$total = 0;
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM users u WHERE $where_sql");
if ($params) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt); mysqli_stmt_bind_result($stmt,$total); mysqli_stmt_fetch($stmt); mysqli_stmt_close($stmt);
$total_pages = max(1, (int)ceil($total/$per_page));
if ($page > $total_pages) { $page=$total_pages; $offset=($page-1)*$per_page; }

$sql = "SELECT u.user_id,u.first_name,u.last_name,u.gender,u.mobile,u.email,u.role,u.account_status,u.created_at,
               up.profile_id,up.verification_status,up.profile_visibility,up.photo_visibility,up.photo
        FROM users u LEFT JOIN user_profiles up ON up.user_id=u.user_id
        WHERE $where_sql ORDER BY u.user_id DESC LIMIT ? OFFSET ?";
$list_params = $params; $list_types = $types . 'ii'; $list_params[]=$per_page; $list_params[]=$offset;
$stmt = mysqli_prepare($conn,$sql); mysqli_stmt_bind_param($stmt,$list_types,...$list_params); mysqli_stmt_execute($stmt); $res=mysqli_stmt_get_result($stmt);
$users=[]; while($r=mysqli_fetch_assoc($res)) $users[]=$r; mysqli_stmt_close($stmt);

function page_url($p){
    $params=$_GET; $params['page']=$p; return 'users.php?' . http_build_query($params);
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>User Management | Smart Matrimony</title><link rel="stylesheet" href="../assets/css/admin.css"><link rel="stylesheet" href="../assets/css/admin-users.css"></head><body>
<header class="topbar"><div><span class="eyebrow">SMART MATRIMONY</span><h1>User Management</h1><p class="top-subtitle">Full account, role, status and profile control.</p></div><div class="top-actions"><a href="dashboard.php">Admin Dashboard</a><a href="operations.php">Operations</a><a href="logout.php">Logout</a></div></header>
<main class="wrap users-wrap">
<?php if($message):?><div class="admin-toast success" role="status">✓ <?=h($message)?></div><?php endif;?><?php if($error):?><div class="admin-toast error" role="alert">! <?=h($error)?></div><?php endif;?>
<section class="user-summary"><div><span>Accounts found</span><strong><?=number_format($total)?></strong></div><div><span>Page</span><strong><?=number_format($page)?> / <?=number_format($total_pages)?></strong></div><div><span>Per page</span><strong><?=number_format($per_page)?></strong></div></section>
<section class="panel"><div class="panel-head"><div><span class="eyebrow">ACCOUNT DIRECTORY</span><h2>Users, Managers & Authenticators</h2><p class="muted">Search by full public ID, numeric ID, name, email or mobile. Admin accounts are protected from this directory.</p></div></div>
<form class="user-filters" method="get"><div class="search-box"><label for="q">Search</label><input id="q" name="q" value="<?=h($q)?>" placeholder="SM-000020, ID, name, email or mobile"></div><div class="filter-field"><label for="role">Role</label><select id="role" name="role"><option value="">All roles</option><?php foreach(['User','Manager','Authenticator'] as $r):?><option value="<?=h($r)?>" <?=$role===$r?'selected':''?>><?=h($r)?></option><?php endforeach;?></select></div><div class="filter-field"><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option><?php foreach(['Active','Inactive','Suspended'] as $s):?><option value="<?=h($s)?>" <?=$status===$s?'selected':''?>><?=h($s)?></option><?php endforeach;?></select></div><div class="filter-field"><label for="gender">Gender</label><select id="gender" name="gender"><option value="">All genders</option><option value="Male" <?=$gender==='Male'?'selected':''?>>Male</option><option value="Female" <?=$gender==='Female'?'selected':''?>>Female</option></select></div><button type="submit">Search</button><?php if($q||$role||$status||$gender):?><a class="clear-filter" href="users.php">Clear</a><?php endif;?></form>
<div class="user-table-scroll"><table class="user-table"><thead><tr><th>ID</th><th>User</th><th>Contact</th><th>Role</th><th>Account</th><th>Verification</th><th>Profile</th><th>Actions</th></tr></thead><tbody>
<?php foreach($users as $u):?><tr><td><span class="public-id"><?=h(public_id($u['user_id']))?></span><small>#<?=h($u['user_id'])?></small></td><td><strong><?=h($u['first_name'].' '.$u['last_name'])?></strong><small><?=h($u['gender'])?> · <?=h(date('d M Y',strtotime($u['created_at'])))?></small></td><td><?=h($u['email'])?><small><?=h($u['mobile'])?></small></td><td><form method="post" class="inline-action"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="update_account"><input type="hidden" name="user_id" value="<?=$u['user_id']?>"><select name="role"><?php foreach(['User','Manager','Authenticator'] as $r):?><option <?=$u['role']===$r?'selected':''?>><?=h($r)?></option><?php endforeach;?></select></td><td><select name="account_status"><?php foreach(['Active','Inactive','Suspended'] as $s):?><option <?=$u['account_status']===$s?'selected':''?>><?=h($s)?></option><?php endforeach;?></select></td><td><span class="status-pill <?=strtolower(h($u['verification_status']?:'No profile'))?>"><?=h($u['verification_status']?:'No profile')?></span></td><td><?= $u['profile_id'] ? h($u['profile_visibility'].' · '.$u['photo_visibility']) : '<span class="muted">No profile</span>' ?></td><td class="actions-cell"><a class="btn-light" href="user_details.php?user_id=<?=$u['user_id']?>">Details</a><button class="btn-save" type="submit">Save</button></form><form method="post" class="delete-mini" onsubmit="return confirm('This permanently deletes the account and all user-owned records. Continue?');"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?=$u['user_id']?>"><input type="hidden" name="confirm_text" value="DELETE"><button type="submit" class="btn-danger">Remove permanently</button></form></td></tr><?php endforeach;?>
<?php if(!$users):?><tr><td colspan="8" class="empty">No accounts match your search.</td></tr><?php endif;?></tbody></table></div>
<?php if($total_pages>1):?><nav class="pagination" aria-label="User pages"><?php if($page>1):?><a href="<?=h(page_url($page-1))?>">← Previous</a><?php endif;?><span>Page <?=number_format($page)?> of <?=number_format($total_pages)?></span><?php if($page<$total_pages):?><a href="<?=h(page_url($page+1))?>">Next →</a><?php endif;?></nav><?php endif;?></section></main></body></html>
