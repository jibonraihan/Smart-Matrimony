<?php
require_once __DIR__ . '/admin_guard.php';

function staff_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function staff_time(string $value): string {
    $ts = strtotime($value);
    return $ts ? date('d M Y, h:i A', $ts) : 'Unknown';
}
function staff_conv_key(string $role, int $user_id): string { return $role . ':' . $user_id; }

$filter = $_GET['filter'] ?? 'All';
$allowed_filters = ['All','Manager','Authenticator','Unread'];
if (!in_array($filter, $allowed_filters, true)) $filter = 'All';
$selected_key = trim((string)($_GET['staff'] ?? ''));
$legacy_thread = trim((string)($_GET['thread'] ?? ''));
$message = '';
$error = '';

// The UI is conversation-based: one Manager/Authenticator = one conversation.
// Existing rows may have different thread_id values, so grouping is intentionally
// done by the other participant (role + user_id), not by thread_id.
$rows = [];
$list_sql = "SELECT m.message_id,m.thread_id,m.sender_id,m.sender_role,m.recipient_id,m.recipient_role,m.message,m.status,m.created_at,m.read_at,
                    s.first_name AS sender_first,s.last_name AS sender_last,
                    r.first_name AS recipient_first,r.last_name AS recipient_last,
                    sp_sender.profile_image AS sender_profile_image,
                    sp_recipient.profile_image AS recipient_profile_image
             FROM staff_admin_messages m
             LEFT JOIN users s ON s.user_id=m.sender_id
             LEFT JOIN users r ON r.user_id=m.recipient_id
             LEFT JOIN staff_profiles sp_sender ON sp_sender.user_id=m.sender_id
             LEFT JOIN staff_profiles sp_recipient ON sp_recipient.user_id=m.recipient_id
             WHERE (m.sender_role='Admin' OR m.recipient_role='Admin')";
if ($filter === 'Manager') $list_sql .= " AND (m.sender_role='Manager' OR m.recipient_role='Manager')";
elseif ($filter === 'Authenticator') $list_sql .= " AND (m.sender_role='Authenticator' OR m.recipient_role='Authenticator')";
elseif ($filter === 'Unread') $list_sql .= " AND m.recipient_role='Admin' AND m.status='Unread'";
$list_sql .= " ORDER BY m.created_at DESC, m.message_id DESC LIMIT 1000";
$list_stmt = mysqli_prepare($conn, $list_sql);
if ($list_stmt) {
    mysqli_stmt_execute($list_stmt);
    $result = mysqli_stmt_get_result($list_stmt);
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($list_stmt);
}

$threads = [];
foreach ($rows as $row) {
    $other_role = $row['sender_role'] === 'Admin' ? (string)$row['recipient_role'] : (string)$row['sender_role'];
    $other_id = $row['sender_role'] === 'Admin' ? (int)$row['recipient_id'] : (int)$row['sender_id'];
    if ($other_id <= 0 || !in_array($other_role, ['Manager','Authenticator'], true)) continue;
    $key = staff_conv_key($other_role, $other_id);
    $other_name = $row['sender_role'] === 'Admin'
        ? trim(($row['recipient_first'] ?? '') . ' ' . ($row['recipient_last'] ?? ''))
        : trim(($row['sender_first'] ?? '') . ' ' . ($row['sender_last'] ?? ''));
    if ($other_name === '') $other_name = $other_role;
    $other_image = $row['sender_role'] === 'Admin'
        ? trim((string)($row['recipient_profile_image'] ?? ''))
        : trim((string)($row['sender_profile_image'] ?? ''));

    if (!isset($threads[$key])) {
        $threads[$key] = [
            'key' => $key,
            'role' => $other_role,
            'user_id' => $other_id,
            'name' => $other_name,
            'latest_message' => $row['message'],
            'latest_at' => $row['created_at'],
            'thread_id' => (string)$row['thread_id'],
            'profile_image' => '',
            'unread' => 0
        ];
    }
    if ($threads[$key]['profile_image'] === '' && $other_image !== '') {
        $candidate = basename($other_image);
        if (is_file(__DIR__ . '/../uploads/staff/' . $candidate)) $threads[$key]['profile_image'] = $candidate;
    }
    if ($row['recipient_role'] === 'Admin' && $row['status'] === 'Unread') $threads[$key]['unread']++;
}
$threads = array_values($threads);

// Keep old ?thread=... links working and map them to the participant conversation.
if ($selected_key === '' && $legacy_thread !== '') {
    $legacy_stmt = mysqli_prepare($conn, "SELECT sender_id,sender_role,recipient_id,recipient_role FROM staff_admin_messages WHERE thread_id=? AND (sender_role='Admin' OR recipient_role='Admin') ORDER BY message_id ASC LIMIT 1");
    if ($legacy_stmt) {
        mysqli_stmt_bind_param($legacy_stmt, 's', $legacy_thread);
        mysqli_stmt_execute($legacy_stmt);
        $legacy_row = mysqli_fetch_assoc(mysqli_stmt_get_result($legacy_stmt));
        mysqli_stmt_close($legacy_stmt);
        if ($legacy_row) {
            $legacy_role = $legacy_row['sender_role'] === 'Admin' ? $legacy_row['recipient_role'] : $legacy_row['sender_role'];
            $legacy_id = $legacy_row['sender_role'] === 'Admin' ? (int)$legacy_row['recipient_id'] : (int)$legacy_row['sender_id'];
            if ($legacy_id > 0) $selected_key = staff_conv_key((string)$legacy_role, $legacy_id);
        }
    }
}
if ($selected_key === '' && $threads) $selected_key = $threads[0]['key'];

$active_thread = null;
foreach ($threads as $t) {
    if ($t['key'] === $selected_key) { $active_thread = $t; break; }
}

// Build a participant-safe WHERE clause used by all conversation actions.
$active_role = $active_thread['role'] ?? '';
$active_user_id = isset($active_thread['user_id']) ? (int)$active_thread['user_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax'] ?? '') === '1' && in_array((string)($_POST['action'] ?? ''), ['send_staff_message','delete_message'], true)) {
    header('Content-Type: application/json; charset=UTF-8');
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        echo json_encode(['ok' => false, 'error' => 'Security check failed. Please refresh the page and try again.']);
        exit;
    }

    $ajax_action = (string)$_POST['action'];
    $role = (string)($_POST['recipient_role'] ?? '');
    $uid = (int)($_POST['recipient_id'] ?? 0);
    if (!in_array($role, ['Manager','Authenticator'], true) || $uid <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Conversation could not be identified.']);
        exit;
    }

    if ($ajax_action === 'delete_message') {
        $message_id = (int)($_POST['message_id'] ?? 0);
        if ($message_id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Message could not be identified.']);
            exit;
        }
        $del = mysqli_prepare($conn, "DELETE FROM staff_admin_messages WHERE message_id=? AND ((sender_role='Admin' AND recipient_role=? AND recipient_id=?) OR (recipient_role='Admin' AND sender_role=? AND sender_id=?)) LIMIT 1");
        mysqli_stmt_bind_param($del, 'isisi', $message_id, $role, $uid, $role, $uid);
        mysqli_stmt_execute($del);
        $deleted = mysqli_stmt_affected_rows($del) > 0;
        mysqli_stmt_close($del);
        echo json_encode($deleted ? ['ok' => true, 'message_id' => $message_id] : ['ok' => false, 'error' => 'That message was not found or is no longer available.']);
        exit;
    }

    $text = trim((string)($_POST['message'] ?? ''));
    if ($text === '') { echo json_encode(['ok' => false, 'error' => 'Please write a message before sending.']); exit; }
    if (mb_strlen($text) > 2000) { echo json_encode(['ok' => false, 'error' => 'Message is too long. Please keep it within 2000 characters.']); exit; }
    $check = mysqli_prepare($conn, "SELECT user_id,first_name,last_name FROM users WHERE user_id=? AND role=? AND account_status='Active' LIMIT 1");
    mysqli_stmt_bind_param($check, 'is', $uid, $role);
    mysqli_stmt_execute($check);
    $staff_row = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
    mysqli_stmt_close($check);
    if (!$staff_row) { echo json_encode(['ok' => false, 'error' => 'The selected staff account is not available.']); exit; }

    $new_thread = '';
    $find_thread = mysqli_prepare($conn, "SELECT thread_id FROM staff_admin_messages WHERE ((sender_role='Admin' AND recipient_role=? AND recipient_id=?) OR (recipient_role='Admin' AND sender_role=? AND sender_id=?)) ORDER BY created_at DESC,message_id DESC LIMIT 1");
    if ($find_thread) {
        mysqli_stmt_bind_param($find_thread, 'sisi', $role, $uid, $role, $uid);
        mysqli_stmt_execute($find_thread);
        $found = mysqli_fetch_assoc(mysqli_stmt_get_result($find_thread));
        mysqli_stmt_close($find_thread);
        if ($found) $new_thread = (string)$found['thread_id'];
    }
    if ($new_thread === '') $new_thread = bin2hex(random_bytes(16));

    $mark_reply = mysqli_prepare($conn, "UPDATE staff_admin_messages SET status='Read', read_at=CURRENT_TIMESTAMP WHERE recipient_role='Admin' AND sender_role=? AND sender_id=? AND status='Unread'");
    mysqli_stmt_bind_param($mark_reply, 'si', $role, $uid);
    mysqli_stmt_execute($mark_reply);
    mysqli_stmt_close($mark_reply);

    $sender_role = 'Admin';
    $ins = mysqli_prepare($conn, "INSERT INTO staff_admin_messages (thread_id,sender_id,sender_role,recipient_id,recipient_role,message,status) VALUES (?,?,?,?,?,?,'Unread')");
    mysqli_stmt_bind_param($ins, 'sisiss', $new_thread, $admin_id, $sender_role, $uid, $role, $text);
    $sent = mysqli_stmt_execute($ins);
    $new_message_id = mysqli_insert_id($conn);
    mysqli_stmt_close($ins);
    if (!$sent) { echo json_encode(['ok' => false, 'error' => 'Message could not be sent. Please try again.']); exit; }

    // Return fresh unread counts so the UI can update immediately without a page reload.
    $thread_unread_count = 0;
    $unread_stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM staff_admin_messages WHERE recipient_role='Admin' AND sender_role=? AND sender_id=? AND status='Unread'");
    if ($unread_stmt) {
        mysqli_stmt_bind_param($unread_stmt, 'si', $role, $uid);
        mysqli_stmt_execute($unread_stmt);
        $unread_row = mysqli_fetch_row(mysqli_stmt_get_result($unread_stmt));
        $thread_unread_count = (int)($unread_row[0] ?? 0);
        mysqli_stmt_close($unread_stmt);
    }
    $total_unread_result = mysqli_query($conn, "SELECT COUNT(*) FROM staff_admin_messages WHERE recipient_role='Admin' AND status='Unread'");
    $total_unread_count = $total_unread_result ? (int)(mysqli_fetch_row($total_unread_result)[0] ?? 0) : 0;

    echo json_encode([
        'ok' => true,
        'unread_total' => $total_unread_count,
        'conversation_unread' => $thread_unread_count,
        'message' => [
            'message_id' => $new_message_id,
            'message' => $text,
            'created_at' => date('Y-m-d H:i:s'),
            'sender_name' => 'Admin'
        ]
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_read') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'Security check failed. Please refresh the page and try again.';
    } else {
        $role = (string)($_POST['recipient_role'] ?? '');
        $uid = (int)($_POST['recipient_id'] ?? 0);
        if (in_array($role, ['Manager','Authenticator'], true) && $uid > 0) {
            $mark = mysqli_prepare($conn, "UPDATE staff_admin_messages SET status='Read', read_at=CURRENT_TIMESTAMP WHERE recipient_role='Admin' AND sender_role=? AND sender_id=? AND status='Unread'");
            mysqli_stmt_bind_param($mark, 'si', $role, $uid);
            mysqli_stmt_execute($mark);
            mysqli_stmt_close($mark);
            header('Location: staff_messages.php?filter=' . rawurlencode($filter) . '&staff=' . rawurlencode(staff_conv_key($role, $uid))); exit;
        }
        $error = 'Conversation could not be identified.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_message') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'Security check failed. Please refresh the page and try again.';
    } else {
        $message_id = (int)($_POST['message_id'] ?? 0);
        $role = (string)($_POST['recipient_role'] ?? '');
        $uid = (int)($_POST['recipient_id'] ?? 0);
        if ($message_id <= 0 || !in_array($role, ['Manager','Authenticator'], true) || $uid <= 0) {
            $error = 'Message could not be identified.';
        } else {
            $del = mysqli_prepare($conn, "DELETE FROM staff_admin_messages WHERE message_id=? AND ((sender_role='Admin' AND recipient_role=? AND recipient_id=?) OR (recipient_role='Admin' AND sender_role=? AND sender_id=?)) LIMIT 1");
            mysqli_stmt_bind_param($del, 'isisi', $message_id, $role, $uid, $role, $uid);
            mysqli_stmt_execute($del);
            $deleted = mysqli_stmt_affected_rows($del) > 0;
            mysqli_stmt_close($del);
            if ($deleted) {
                header('Location: staff_messages.php?filter=' . rawurlencode($filter) . '&staff=' . rawurlencode(staff_conv_key($role, $uid)) . '&deleted=1'); exit;
            }
            $error = 'That message was not found or is no longer available.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_conversation') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'Security check failed. Please refresh the page and try again.';
    } else {
        $role = (string)($_POST['recipient_role'] ?? '');
        $uid = (int)($_POST['recipient_id'] ?? 0);
        if (!in_array($role, ['Manager','Authenticator'], true) || $uid <= 0) {
            $error = 'Conversation could not be identified.';
        } else {
            $del = mysqli_prepare($conn, "DELETE FROM staff_admin_messages WHERE (sender_role='Admin' AND recipient_role=? AND recipient_id=?) OR (recipient_role='Admin' AND sender_role=? AND sender_id=? )");
            mysqli_stmt_bind_param($del, 'sisi', $role, $uid, $role, $uid);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);
            header('Location: staff_messages.php?filter=' . rawurlencode($filter) . '&deleted=conversation'); exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_staff_message') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'Security check failed. Please refresh the page and try again.';
    } else {
        $recipient_id = (int)($_POST['recipient_id'] ?? 0);
        $recipient_role = (string)($_POST['recipient_role'] ?? '');
        $text = trim((string)($_POST['message'] ?? ''));
        $reply_thread = trim((string)($_POST['thread_id'] ?? ''));
        if ($recipient_id <= 0 || !in_array($recipient_role, ['Manager','Authenticator'], true)) $error = 'Please select a valid Manager or Authenticator.';
        elseif ($text === '') $error = 'Please write a message before sending.';
        elseif (mb_strlen($text) > 2000) $error = 'Message is too long. Please keep it within 2000 characters.';
        else {
            $check = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_id=? AND role=? AND account_status='Active' LIMIT 1");
            mysqli_stmt_bind_param($check, 'is', $recipient_id, $recipient_role);
            mysqli_stmt_execute($check);
            $ok = (bool)mysqli_fetch_assoc(mysqli_stmt_get_result($check));
            mysqli_stmt_close($check);
            if (!$ok) $error = 'The selected staff account is not available.';
            else {
                // Reuse the latest existing thread_id when possible. The UI still treats
                // the participant as one conversation, so old split thread IDs are merged visually.
                $new_thread = '';
                $find_thread = mysqli_prepare($conn, "SELECT thread_id FROM staff_admin_messages WHERE ((sender_role='Admin' AND recipient_role=? AND recipient_id=?) OR (recipient_role='Admin' AND sender_role=? AND sender_id=?)) ORDER BY created_at DESC,message_id DESC LIMIT 1");
                if ($find_thread) {
                    mysqli_stmt_bind_param($find_thread, 'sisi', $recipient_role, $recipient_id, $recipient_role, $recipient_id);
                    mysqli_stmt_execute($find_thread);
                    $found = mysqli_fetch_assoc(mysqli_stmt_get_result($find_thread));
                    mysqli_stmt_close($find_thread);
                    if ($found) $new_thread = (string)$found['thread_id'];
                }
                if ($reply_thread !== '' && $new_thread === '') $new_thread = $reply_thread;
                if ($new_thread === '') $new_thread = bin2hex(random_bytes(16));

                // Replying to a conversation consumes its incoming unread state.
                $mark_reply = mysqli_prepare($conn, "UPDATE staff_admin_messages SET status='Read', read_at=CURRENT_TIMESTAMP WHERE recipient_role='Admin' AND sender_role=? AND sender_id=? AND status='Unread'");
                mysqli_stmt_bind_param($mark_reply, 'si', $recipient_role, $recipient_id);
                mysqli_stmt_execute($mark_reply);
                mysqli_stmt_close($mark_reply);

                $sender_role = 'Admin';
                $ins = mysqli_prepare($conn, "INSERT INTO staff_admin_messages (thread_id,sender_id,sender_role,recipient_id,recipient_role,message,status) VALUES (?,?,?,?,?,?,'Unread')");
                mysqli_stmt_bind_param($ins, 'sisiss', $new_thread, $admin_id, $sender_role, $recipient_id, $recipient_role, $text);
                $sent = mysqli_stmt_execute($ins);
                mysqli_stmt_close($ins);
                if ($sent) {
                    header('Location: staff_messages.php?filter=' . rawurlencode($filter === 'Unread' ? 'All' : $filter) . '&staff=' . rawurlencode(staff_conv_key($recipient_role, $recipient_id)) . '&sent=1'); exit;
                }
                $error = 'Message could not be sent. Please try again.';
            }
        }
    }
}

if (isset($_GET['sent'])) $message = 'Message sent successfully.';
if (isset($_GET['deleted'])) $message = $_GET['deleted'] === 'conversation' ? 'Conversation deleted successfully.' : 'Message deleted successfully.';

// Load the complete conversation for the selected participant, regardless of the
// individual thread_id values used by older messages.
if ($active_thread) {
    $detail = [];
    $stmt = mysqli_prepare($conn, "SELECT m.message_id,m.thread_id,m.sender_id,m.sender_role,m.recipient_id,m.recipient_role,m.message,m.status,m.created_at,m.read_at,
                                      s.first_name AS sender_first,s.last_name AS sender_last,
                                      r.first_name AS recipient_first,r.last_name AS recipient_last
                               FROM staff_admin_messages m
                               LEFT JOIN users s ON s.user_id=m.sender_id
                               LEFT JOIN users r ON r.user_id=m.recipient_id
                               WHERE ((m.sender_role='Admin' AND m.recipient_role=? AND m.recipient_id=?) OR (m.recipient_role='Admin' AND m.sender_role=? AND m.sender_id=?))
                               ORDER BY m.created_at ASC,m.message_id ASC");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'sisi', $active_role, $active_user_id, $active_role, $active_user_id);
        mysqli_stmt_execute($stmt);
        $rr = mysqli_stmt_get_result($stmt);
        while ($x = mysqli_fetch_assoc($rr)) $detail[] = $x;
        mysqli_stmt_close($stmt);
    }
    $active_thread['messages'] = $detail;
    if ($detail) $active_thread['thread_id'] = (string)$detail[count($detail)-1]['thread_id'];
}

$staff = [];
$sr = mysqli_query($conn, "SELECT user_id,first_name,last_name,role,email FROM users WHERE role IN ('Manager','Authenticator') AND account_status='Active' ORDER BY role,first_name,last_name,user_id");
if ($sr) while ($x = mysqli_fetch_assoc($sr)) $staff[] = $x;

$staff_unread_result = mysqli_query($conn, "SELECT COUNT(*) FROM staff_admin_messages WHERE recipient_role='Admin' AND status='Unread'");
$staff_unread_count = $staff_unread_result ? (int)(mysqli_fetch_row($staff_unread_result)[0] ?? 0) : 0;
$admin_show_staff_messages = true;
$admin_staff_unread_count = $staff_unread_count;
$admin_header_title = 'Staff Messages';
$admin_header_subtitle = 'Manager and Authenticator communication center.';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Staff Messages | Smart Matrimony</title>
<style id="admin-inline-css">
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f3f9f8;color:#123c3b}.topbar{padding:28px 5vw;display:flex;justify-content:space-between;align-items:center;gap:20px;background:#0d8179;color:#fff}.brand-block{min-width:0}.brand-block h1{margin:4px 0 0;font-size:30px;line-height:1.15}.admin-identity{display:flex!important;align-items:center;gap:9px;width:max-content;margin-top:9px;padding:7px 12px;border:1px solid rgba(255,255,255,.28);border-radius:14px;background:rgba(255,255,255,.10);color:#fff}.admin-identity-label{display:inline-flex;align-items:center;justify-content:center;padding:4px 7px;border-radius:999px;background:#fff;color:#0f7f78;font-size:10px!important;font-weight:900!important;letter-spacing:1px!important}.admin-identity strong{display:inline-block;font-size:14px;line-height:1;white-space:nowrap}.eyebrow{font-size:11px;letter-spacing:1.5px;font-weight:800;opacity:.8}.top-actions{display:flex;align-items:center;gap:14px}.top-actions a{color:#fff;text-decoration:none;border:1px solid rgba(255,255,255,.35);padding:10px 16px;border-radius:12px}.wrap{max-width:1280px;margin:28px auto;padding:0 20px}.stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:22px}.stat,.panel{background:#fff;border:1px solid #dcebea;border-radius:20px;box-shadow:0 10px 30px rgba(15,76,73,.05)}.stat{padding:20px}.stat span{display:block;color:#6d8584;font-size:13px}.stat strong{display:block;margin-top:8px;font-size:28px}.panel{padding:24px;margin-bottom:22px}.panel-head h2{margin:4px 0 18px}.manager-form,.filters{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;width:100%;max-width:100%}.manager-form input,.manager-form select,.manager-form button{width:100%;min-width:0;max-width:100%}.manager-form>*{min-width:0}.filters{grid-template-columns:2fr 1fr 1fr auto;margin-bottom:18px}input,select,button{font:inherit;border:1px solid #d7e7e5;border-radius:11px;padding:11px 12px;background:#fff}button{border:0;background:#0d8179;color:#fff;font-weight:800;cursor:pointer}button.small{padding:9px 12px}.alert{padding:14px 18px;border-radius:14px;margin-bottom:18px;font-weight:700}.alert.success{background:#e8f8ef;color:#176842}.alert.error{background:#fff0f0;color:#a83232}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:900px}th,td{text-align:left;padding:14px 10px;border-bottom:1px solid #edf3f2;vertical-align:middle}th{font-size:12px;text-transform:uppercase;letter-spacing:.8px;color:#708685}td small{display:block;color:#7d9190;margin-top:4px}.row-form{display:contents}.empty{text-align:center;padding:30px}.auth-shell{min-height:100vh;display:grid;place-items:center;padding:24px}.auth-card{width:min(440px,100%);background:#fff;border:1px solid #dcebea;border-radius:24px;padding:34px;box-shadow:0 20px 60px rgba(15,76,73,.08)}.auth-card .brand{font-size:24px;font-weight:900;color:#0d8179;margin-bottom:28px}.auth-card h1{margin:6px 0;font-size:34px}.auth-card p{color:#6d8584}.auth-card form{display:grid;gap:15px;margin-top:24px}.auth-card label{display:grid;gap:7px;font-weight:700}.auth-card button{padding:13px}.back{display:block;margin-top:20px;color:#0d8179;text-decoration:none;text-align:center;font-weight:700}@media(max-width:1200px){.topbar{flex-wrap:wrap}.brand-block{flex:1 1 100%;}.top-actions{width:100%;justify-content:flex-end;flex-wrap:wrap}.stats{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:900px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.manager-form{grid-template-columns:repeat(2,minmax(0,1fr))}.topbar{padding-top:22px;padding-bottom:22px}.top-actions{justify-content:flex-start}}@media(max-width:600px){.stats{grid-template-columns:1fr}.manager-form,.filters{grid-template-columns:1fr}.topbar{align-items:flex-start;flex-direction:column}.admin-identity{margin-top:8px}.top-actions{width:100%;flex-wrap:wrap;justify-content:stretch}.top-actions a{flex:1 1 140px;text-align:center}}

/* Admin dashboard — overview and quick-access polish */
.dashboard-intro{padding:30px 28px;background:linear-gradient(135deg,#ffffff 0%,#f8fcfb 100%)}
.dashboard-intro h2{margin:8px 0 10px;font-size:30px;line-height:1.2;color:#0b4f4c}
.dashboard-intro p{margin:0;max-width:920px;color:#5f7776;font-size:15px;line-height:1.7}

.quick-panel{padding:28px}
.panel-head{margin-bottom:20px}
.panel-head h2{margin:5px 0 0;font-size:28px;line-height:1.2;color:#0b4f4c}
.quick-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}
.quick-card{display:flex;flex-direction:column;min-width:0;min-height:205px;padding:21px 20px 19px;background:#f8fcfb;border:1px solid #dcebea;border-radius:17px;color:#123c3b;text-decoration:none;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease,background .18s ease}
.quick-card:hover{transform:translateY(-3px);background:#fff;border-color:#b9dcd8;box-shadow:0 12px 28px rgba(15,76,73,.09)}
.quick-card:focus-visible{outline:3px solid rgba(13,129,121,.25);outline-offset:3px}
.quick-label{display:inline-flex;align-self:flex-start;align-items:center;padding:5px 9px;border-radius:999px;background:#e6f5f3;color:#0a726c;font-size:10px;font-weight:900;letter-spacing:1px;line-height:1.2}
.quick-card strong{display:block;margin-top:14px;font-size:18px;line-height:1.3;color:#0b5f5b}
.quick-card small{display:block;margin-top:8px;color:#6b8281;font-size:13px;line-height:1.6}
.quick-link{display:block;margin-top:auto;padding-top:16px;color:#0d8179;font-size:12px;font-weight:900;line-height:1.4}

@media(max-width:1100px){
  .quick-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:600px){
  .dashboard-intro,.quick-panel{padding:22px 18px}
  .dashboard-intro h2{font-size:26px}
  .dashboard-intro p{font-size:14px;line-height:1.6}
  .quick-grid{grid-template-columns:1fr;gap:12px}
  .quick-card{min-height:185px}
}


/* Admin shared header */
.admin-topbar{background:linear-gradient(120deg,#0f8b83,#086b67);color:#fff;padding:15px clamp(18px,5vw,92px);display:flex;justify-content:space-between;align-items:center;gap:24px;min-height:88px;box-shadow:0 10px 30px rgba(8,47,73,.08)}
.admin-brand{display:flex;align-items:center;gap:16px;min-width:0}
.admin-logo{width:58px;height:58px;object-fit:contain;flex:0 0 58px;background:#fff;border:1px solid rgba(255,255,255,.45);border-radius:16px;padding:7px}
.admin-title-logo{display:block;width:min(190px,32vw);height:auto;max-height:48px;object-fit:contain;object-position:left center}
.admin-header-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px;min-width:0}
.admin-identity{display:inline-flex;align-items:center;gap:8px;min-width:0;padding:9px 13px;border:1px solid rgba(255,255,255,.35);border-radius:14px;color:#fff;background:rgba(255,255,255,.08);white-space:nowrap}
.admin-identity-role{display:inline-flex;align-items:center;justify-content:center;padding:4px 8px;border-radius:999px;background:#fff;color:#086f69;font-size:10px!important;font-weight:900!important;letter-spacing:1px!important;line-height:1}
.admin-identity-name{font-size:14px;font-weight:800;line-height:1.2;overflow:hidden;text-overflow:ellipsis}
.admin-identity-id{padding-left:8px;border-left:1px solid rgba(255,255,255,.24);font-size:12px;font-weight:700;opacity:.9}
.admin-logout-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 14px;border:1px solid rgba(255,255,255,.42);border-radius:12px;background:rgba(255,255,255,.06);color:#fff;text-decoration:none;font-size:13px;font-weight:800;line-height:1;white-space:nowrap;transition:background .18s ease,border-color .18s ease,transform .18s ease}
.admin-logout-btn:hover{background:rgba(255,255,255,.14);border-color:rgba(255,255,255,.68);transform:translateY(-1px)}
.admin-logout-btn:focus-visible{outline:3px solid rgba(255,255,255,.35);outline-offset:2px}
.admin-logout-icon{width:18px;height:18px;flex:0 0 18px}
/* Equalize the three shared header controls vertically. */
.admin-header-actions>.admin-staff-messages-btn,.admin-header-actions>.admin-identity,.admin-header-actions>.admin-logout-btn{height:40px;min-height:40px;box-sizing:border-box;margin:0;align-self:center}.admin-header-actions>.admin-staff-messages-btn{display:inline-flex;align-items:center;justify-content:center}.admin-header-actions>.admin-identity{display:inline-flex;align-items:center}.admin-header-actions>.admin-logout-btn{display:inline-flex;align-items:center;justify-content:center}

/* Admin messaging modal */
body.admin-message-modal-open{overflow:hidden}
.admin-message-modal{position:fixed;inset:0;z-index:1000;display:none;align-items:center;justify-content:center;padding:20px}
.admin-message-modal.is-open{display:flex}
.admin-message-backdrop{position:absolute;inset:0;background:rgba(8,47,73,.48);backdrop-filter:blur(2px)}
.admin-message-dialog{position:relative;width:min(620px,100%);max-height:min(90vh,720px);overflow:auto;background:#fff;border:1px solid #dcebea;border-radius:24px;box-shadow:0 24px 70px rgba(8,47,73,.22);padding:28px;animation:adminMessageIn .16s ease-out}
.admin-message-head{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;margin-bottom:18px}
.admin-message-head h2{margin:4px 0;font-size:24px;color:#0b4f4c}
.admin-message-head p{margin:5px 0 0;color:#6d8584;line-height:1.55}
.admin-message-close{width:40px;height:40px;flex:0 0 40px;border:1px solid #dcebea;border-radius:12px;background:#fff;color:#6d8584;font-size:18px;cursor:pointer}
.admin-message-close:hover{background:#f4faf9;color:#0b5f5b}
.admin-message-form textarea{width:100%;min-height:150px;border:1px solid #cfe2e1;border-radius:13px;padding:13px 14px;font:inherit;outline:none;background:#fff;resize:vertical}
.admin-message-form textarea:focus{border-color:#0f8b83;box-shadow:0 0 0 3px rgba(15,139,131,.08)}
.admin-message-footer{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-top:14px}
.admin-message-footer small{color:#6d8584}
.admin-message-actions{display:flex;align-items:center;justify-content:flex-end;gap:9px}
.admin-message-cancel,.admin-message-send{border-radius:13px;padding:10px 14px;font:700 13px Inter,system-ui,sans-serif;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}
.admin-message-cancel{background:#fff;color:#6d8584;border:1px solid #dcebea}
.admin-message-send{background:#0f8b83;color:#fff;border:1px solid #0f8b83}
.admin-message-send:hover{background:#086b67;border-color:#086b67}
.message-btn{display:inline-flex;align-items:center;justify-content:center;border:1px solid #cbe4e1;background:#eaf7f5;color:#0b716b;border-radius:11px;padding:8px 11px;font-weight:800;text-decoration:none;cursor:pointer;white-space:nowrap}
.message-btn:hover{background:#dff2ef;border-color:#b9dcd8}
.actions-cell .message-btn{margin-left:5px}
@keyframes adminMessageIn{from{opacity:0;transform:translateY(6px) scale(.99)}to{opacity:1;transform:translateY(0) scale(1)}}

@media(max-width:1000px){.admin-topbar{padding-left:24px;padding-right:24px}.admin-brand{flex:1}.admin-header-actions{flex-shrink:0}}
@media(max-width:700px){.admin-staff-messages-btn{padding:9px 10px;font-size:10px}.admin-staff-messages-badge{min-width:18px;height:18px;font-size:9px}.admin-topbar{padding:11px 16px 13px;gap:10px;min-height:0;flex-wrap:wrap}.admin-brand{gap:10px;flex:1 1 100%;width:100%;justify-content:flex-start}.admin-logo{width:46px;height:46px;flex-basis:46px;border-radius:12px;padding:5px}.admin-title-logo{width:min(160px,50vw);max-height:36px}.admin-header-actions{width:100%;justify-content:space-between;gap:8px}.admin-identity{padding:7px 9px;gap:6px;max-width:calc(100% - 92px);overflow:hidden}.admin-identity-role{font-size:8px!important;padding:3px 6px}.admin-identity-name{font-size:11px}.admin-identity-id{font-size:10px;padding-left:6px}.admin-logout-btn{padding:9px 11px;font-size:11px;flex:0 0 auto}.admin-logout-icon{width:16px;height:16px;flex-basis:16px}.admin-message-modal{padding:12px}.admin-message-dialog{width:100%;max-height:92vh;border-radius:18px;padding:20px}.admin-message-head{gap:10px}.admin-message-footer{align-items:stretch;flex-direction:column}.admin-message-actions{width:100%;display:grid;grid-template-columns:1fr 1fr}.admin-message-cancel,.admin-message-send{width:100%;height:46px}}

/* Admin header — Staff Messages quick access */
.admin-staff-messages-btn{position:relative;display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:10px 13px;border:1px solid rgba(255,255,255,.38);border-radius:12px;background:rgba(255,255,255,.07);color:#fff;text-decoration:none;font-size:12px;font-weight:850;line-height:1;white-space:nowrap;transition:background .18s ease,border-color .18s ease,transform .18s ease}
.admin-staff-messages-btn:hover{background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.68);transform:translateY(-1px)}
.admin-staff-messages-btn:focus-visible{outline:3px solid rgba(255,255,255,.35);outline-offset:2px}
.admin-staff-messages-icon{font-size:14px;line-height:1}
.admin-staff-messages-badge{display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:20px;padding:0 6px;border-radius:999px;background:#fff0d1;color:#9a6500;font-size:10px;font-weight:900;line-height:1}

/* Admin dashboard — profile hero + categorized system overview */
.admin-dashboard{max-width:1400px;margin-top:26px}
.admin-hero{position:relative;display:grid;grid-template-columns:minmax(205px,255px) minmax(0,1fr);align-items:stretch;gap:0;min-height:285px;padding:0;overflow:hidden;background:linear-gradient(135deg,#ffffff 0%,#f8fcfb 62%,#edf8f6 100%)}
.admin-hero::after{content:"";position:absolute;width:330px;height:330px;right:-120px;bottom:-185px;border-radius:50%;background:rgba(15,139,131,.07);pointer-events:none}
.admin-hero-photo{position:relative;z-index:1;display:block;width:100%;height:285px;margin:0;padding:0;border:0;border-radius:18px 0 0 18px;background:linear-gradient(145deg,#dff3f0,#bfe4df);overflow:hidden;cursor:pointer}
.admin-hero-photo img{display:block;width:100%;height:100%;object-fit:cover}
.admin-hero-photo .admin-avatar-fallback{display:flex;align-items:center;justify-content:center;width:100%;height:100%;color:#0b706a;font-size:72px;font-weight:900}
.admin-photo-edit-badge{position:absolute;right:13px;bottom:13px;display:flex;align-items:center;justify-content:center;width:38px;height:38px;border:3px solid #fff;border-radius:12px;background:#0f8b83;color:#fff;font-size:16px;box-shadow:0 7px 16px rgba(8,79,76,.2)}
.admin-hero-copy{position:relative;z-index:1;min-width:0;padding:34px clamp(24px,4vw,50px);display:flex;flex-direction:column;justify-content:center}
.admin-hero-copy h1{margin:7px 0 10px;color:#084f4c;font-size:clamp(31px,4vw,45px);line-height:1.08;letter-spacing:-.7px}
.admin-hero-lead{margin:0;max-width:820px;color:#5f7776;font-size:15px;line-height:1.7}
.admin-hero-meta{display:flex;flex-wrap:wrap;align-items:center;gap:9px 18px;margin-top:20px;color:#466967;font-size:13px}
.admin-hero-meta>span{display:inline-flex;align-items:center;gap:7px;min-width:0}
.admin-hero-meta strong{color:#123c3b;font-size:14px}
.admin-inline-icon{display:inline-flex;align-items:center;justify-content:center;width:23px;height:23px;border-radius:7px;background:#e6f5f3;color:#0a746d;font-size:9px;font-style:normal;font-weight:900;letter-spacing:.3px}
.admin-profile-edit{display:inline-flex;align-items:center;gap:8px;align-self:flex-start;margin-top:20px;padding:9px 13px;border:1px solid #c6e0dd;border-radius:11px;background:#fff;color:#0b716b;font-size:12px;font-weight:850;cursor:pointer;box-shadow:0 5px 15px rgba(15,76,73,.04);transition:background .18s ease,border-color .18s ease,transform .18s ease}
.admin-profile-edit:hover{background:#eef9f7;border-color:#9ecfc9;transform:translateY(-1px)}
.admin-camera-icon{display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:6px;background:#0f8b83;color:#fff;font-size:15px;line-height:1}
.system-overview{margin-top:28px}
.section-heading{margin:0 0 18px;padding:0 3px}
.section-heading h2{margin:6px 0 5px;color:#084f4c;font-size:29px;line-height:1.15}
.section-heading p{margin:0;color:#6a8381;font-size:13px;line-height:1.6}
.stats-category{margin-bottom:22px}
.stats-category-head{display:flex;align-items:end;justify-content:space-between;gap:16px;margin:0 3px 9px}
.stats-category-head h3{margin:0;color:#154d4a;font-size:16px;line-height:1.3}
.stats-category-head span{color:#7b9290;font-size:10px;font-weight:800;letter-spacing:.6px;text-transform:uppercase;white-space:nowrap}
.stats-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px}
.stat-card{position:relative;min-width:0;min-height:92px;padding:12px 13px;background:#fff;border:1px solid #dcebea;border-radius:13px;box-shadow:0 6px 18px rgba(15,76,73,.035);overflow:hidden;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}
.stat-card::after{content:"";position:absolute;right:-29px;bottom:-35px;width:85px;height:85px;border-radius:50%;background:rgba(15,139,131,.04);pointer-events:none}
.stat-card:hover{transform:translateY(-2px);border-color:#b9dcd8;box-shadow:0 10px 23px rgba(15,76,73,.07)}
.stat-card-top{display:flex;align-items:center;gap:7px;min-width:0}
.stat-icon{display:inline-flex;align-items:center;justify-content:center;width:27px;height:27px;flex:0 0 27px;border-radius:8px;background:#eaf7f5;color:#0a746d;font-size:12px;font-weight:900}
.stat-label{min-width:0;color:#66817f;font-size:10.5px;font-weight:750;line-height:1.28}
.stat-card>strong{display:block;position:relative;z-index:1;margin-top:9px;color:#073f3c;font-size:24px;line-height:1;font-weight:900;letter-spacing:-.3px}
.stat-pending .stat-icon,.stat-booking-pending .stat-icon,.stat-chat-pending .stat-icon,.stat-unread .stat-icon,.stat-auth-unread .stat-icon,.stat-site-new .stat-icon,.stat-email-pending .stat-icon{background:#fff5df;color:#a76a00}
.stat-rejected .stat-icon,.stat-reject .stat-icon,.stat-suspended .stat-icon,.stat-booking-cancelled .stat-icon,.stat-cancel .stat-icon{background:#fff0f0;color:#a53b3b}
.stat-verified .stat-icon,.stat-active .stat-icon,.stat-service-active .stat-icon,.stat-booking-confirmed .stat-icon,.stat-booking-complete .stat-icon,.stat-chat-accepted .stat-icon,.stat-email-ok .stat-icon{background:#e9f8ef;color:#23704a}
.admin-modules{margin-top:30px;padding-bottom:8px}
.admin-module-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px}
.admin-module-card{position:relative;display:flex;align-items:flex-start;gap:12px;min-width:0;padding:17px 15px;text-decoration:none;background:#fff;border:1px solid #dcebea;border-radius:15px;box-shadow:0 7px 20px rgba(15,76,73,.035);transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease}
.admin-module-card:hover{transform:translateY(-2px);border-color:#a9d2ce;box-shadow:0 12px 26px rgba(15,76,73,.075)}
.admin-module-icon{display:flex;align-items:center;justify-content:center;width:36px;height:36px;flex:0 0 36px;border-radius:10px;background:#eaf7f5;color:#0a746d;font-size:16px;font-weight:900}
.admin-module-copy{display:flex;flex-direction:column;gap:5px;min-width:0;padding-right:18px}
.admin-module-copy strong{color:#0a4f4c;font-size:13px;line-height:1.3}
.admin-module-copy small{color:#718987;font-size:10.5px;line-height:1.45}
.admin-module-arrow{position:absolute;right:13px;bottom:13px;color:#0f8b83;font-size:16px;font-weight:900}
.admin-modal{position:fixed;inset:0;z-index:1200;display:flex;align-items:center;justify-content:center;padding:20px}
.admin-modal[hidden]{display:none}
.admin-modal-backdrop{position:absolute;inset:0;background:rgba(8,47,73,.5);backdrop-filter:blur(3px)}
.admin-modal-card{position:relative;width:min(500px,100%);max-height:90vh;overflow:auto;padding:28px;background:#fff;border:1px solid #dcebea;border-radius:22px;box-shadow:0 28px 80px rgba(8,47,73,.24);animation:adminModalIn .16s ease-out}
.admin-modal-close{position:absolute;top:17px;right:17px;width:36px;height:36px;padding:0;border:1px solid #dcebea;border-radius:10px;background:#fff;color:#607876;font-size:22px;line-height:1;font-weight:500;cursor:pointer}
.admin-modal-close:hover{background:#f3f9f8;color:#0b5f5b}
.admin-modal-card h2{margin:5px 45px 5px 0;color:#0b4f4c;font-size:25px}
.admin-modal-card>p{margin:0;color:#6a8381;font-size:13px;line-height:1.55}
.admin-photo-preview{display:flex;align-items:center;justify-content:center;width:145px;height:175px;margin:22px auto 18px;border:5px solid #fff;border-radius:15px;background:#e6f5f3;box-shadow:0 12px 28px rgba(15,76,73,.12);overflow:hidden}
.admin-photo-preview img{width:100%;height:100%;object-fit:cover;display:block}
.admin-photo-preview span{color:#0b716b;font-size:48px;font-weight:900}
.admin-photo-form{display:grid;gap:9px}
.admin-file-picker{display:flex;align-items:center;justify-content:center;min-height:50px;padding:10px 14px;border:1px dashed #a8d2cd;border-radius:12px;background:#f7fcfb;color:#0b716b;font-size:13px;font-weight:800;cursor:pointer}
.admin-file-picker:hover{background:#eef9f7;border-color:#77bdb5}
.admin-file-picker input{display:none}
.admin-photo-form small{color:#7a9190;text-align:center;font-size:11px}
.admin-photo-form button{min-height:46px;margin-top:4px;border-radius:12px;background:#0f8b83;color:#fff;font-weight:850}
body.admin-modal-open{overflow:hidden}
@keyframes adminModalIn{from{opacity:0;transform:translateY(8px) scale(.985)}to{opacity:1;transform:translateY(0) scale(1)}}

@media(max-width:1200px){
  .admin-hero{grid-template-columns:minmax(190px,225px) minmax(0,1fr)}
  .admin-hero-photo{height:285px}
  .stats-grid{grid-template-columns:repeat(5,minmax(0,1fr))}
  .admin-module-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:1000px){
  .stats-grid{grid-template-columns:repeat(4,minmax(0,1fr))}
}
@media(max-width:780px){
  .admin-dashboard{margin-top:20px}
  .admin-hero{grid-template-columns:175px minmax(0,1fr);min-height:250px}
  .admin-hero-photo{height:250px;border-radius:16px 0 0 16px}
  .admin-hero-copy{padding:24px 20px}
  .admin-hero-copy h1{font-size:30px}
  .admin-hero-lead{font-size:13px;line-height:1.6}
  .admin-hero-meta{gap:8px 12px;margin-top:15px}
  .admin-profile-edit{margin-top:15px}
  .stats-grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}
  .stat-card{min-height:86px;padding:11px}
  .stat-card>strong{font-size:22px}
  .stat-label{font-size:10px}
  .admin-module-grid{grid-template-columns:1fr}
}
@media(max-width:560px){
  .admin-hero{grid-template-columns:1fr;min-height:0}
  .admin-hero-photo{height:220px;border-radius:16px 16px 0 0}
  .admin-hero-copy{padding:22px 18px 24px}
  .admin-hero-copy h1{font-size:29px}
  .admin-hero-meta{display:grid;grid-template-columns:1fr;gap:8px}
  .admin-profile-edit{width:100%;justify-content:center}
  .stats-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}
  .section-heading h2{font-size:26px}
  .stats-category-head{align-items:flex-start;flex-direction:column;gap:5px}
  .admin-modal{padding:12px}
  .admin-modal-card{padding:22px 18px;border-radius:18px}
  .admin-photo-preview{width:120px;height:150px}
}
@media(max-width:360px){
  .stats-grid{grid-template-columns:1fr}
}


.conversation-person{display:flex;align-items:center;gap:12px;min-width:0}
.conversation-avatar{width:46px;height:46px;flex:0 0 46px;border-radius:14px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#edf1fb;color:#4b5fa8;font-size:16px;font-weight:900}
.conversation-avatar.auth{background:#e0f4f1;color:#0d8179}
.conversation-avatar.has-photo{padding:0;background:#eef6f5}
.conversation-avatar img{display:block;width:100%;height:100%;object-fit:cover;border-radius:inherit}
.thread-avatar{overflow:hidden}
.thread-avatar.has-photo{padding:0}
.thread-avatar img{display:block;width:100%;height:100%;object-fit:cover;border-radius:inherit}
.conversation-head>.conversation-person{flex:1 1 auto}
.conversation-head>.conversation-actions{flex:0 0 auto}
@media(max-width:760px){.conversation-head>.conversation-person{width:100%}.conversation-head>.conversation-actions{width:100%;justify-content:flex-start}}
</style>
<style id="staff-messages-inline-css">

.staff-messages-page{max-width:1400px;margin-top:26px}.staff-alert{padding:13px 16px;border-radius:13px;margin-bottom:16px;font-weight:750}.staff-alert.success{background:#e7f7ed;color:#176842}.staff-alert.error{background:#fff0f0;color:#a83232}.staff-back-row{margin-bottom:14px}.staff-back-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:9px 13px;border:1px solid #cfe2e1;border-radius:11px;background:#fff;color:#0b6f69;text-decoration:none;font:800 11px Inter,system-ui,sans-serif;line-height:1;transition:background .18s ease,border-color .18s ease,transform .18s ease}.staff-back-btn:hover{background:#f1f9f8;border-color:#a9cfcb;transform:translateY(-1px)}.staff-back-btn:focus-visible{outline:3px solid rgba(15,139,131,.18);outline-offset:2px}.staff-page-head{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;margin-bottom:18px}.staff-page-head h1{margin:5px 0 6px;color:#084f4c;font-size:32px}.staff-page-head p{margin:0;color:#6a8381;font-size:13px}.staff-new-btn,.staff-send-btn{border:0;border-radius:12px;background:#0f8b83;color:#fff;font:800 12px Inter,system-ui,sans-serif;padding:11px 15px;cursor:pointer}.staff-new-btn:hover,.staff-send-btn:hover{background:#086b67}.staff-message-layout{display:grid;grid-template-columns:370px minmax(0,1fr);height:700px;min-height:0;background:#fff;border:1px solid #dcebea;border-radius:20px;box-shadow:0 10px 30px rgba(15,76,73,.05);overflow:hidden}.thread-sidebar{border-right:1px solid #e5eeee;background:#fbfefd;display:flex;flex-direction:column}.thread-filters{display:flex;gap:6px;flex-wrap:wrap;padding:14px;border-bottom:1px solid #e5eeee}.thread-filter{display:inline-flex;align-items:center;gap:5px;padding:7px 9px;border-radius:10px;color:#68817f;text-decoration:none;font-size:10px;font-weight:800;background:#f2f8f7}.thread-filter.active{background:#0f8b83;color:#fff}.thread-filter b{min-width:17px;height:17px;padding:0 4px;border-radius:999px;background:#fff0d1;color:#9a6500;display:inline-grid;place-items:center;font-size:9px}.thread-filter.active b{background:#fff;color:#9a6500}.thread-list{overflow:auto;min-height:0;flex:1}.thread-item{position:relative;display:grid;grid-template-columns:38px 1fr auto;gap:10px;padding:14px;border-bottom:1px solid #edf3f2;text-decoration:none;color:inherit}.thread-item:hover{background:#f5fbfa}.thread-item.active{background:#eaf7f5}.thread-avatar{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;background:#dff2ef;color:#08746e;font-size:14px;font-weight:900}.thread-avatar.auth{background:#edf0fb;color:#5261a4}.thread-copy{min-width:0}.thread-top{display:flex;justify-content:space-between;gap:8px}.thread-top strong{font-size:12px;color:#173f3d;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.thread-top small{color:#78908e;font-size:9px;white-space:nowrap}.thread-copy p{margin:5px 0 3px;color:#6d8584;font-size:10.5px;line-height:1.4;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.thread-copy time{color:#9aabaa;font-size:9px}.thread-unread{align-self:start;min-width:20px;height:20px;padding:0 6px;border-radius:999px;background:#fff0d1;color:#9a6500;display:grid;place-items:center;font-size:9px;font-weight:900}.thread-empty{padding:35px 20px;text-align:center;color:#809695;font-size:12px}.conversation-panel{min-width:0;min-height:0;height:100%;display:flex;flex-direction:column;background:#fff}.conversation-head{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:20px 22px;border-bottom:1px solid #e5eeee;background:#fbfefd}.conversation-head .eyebrow{color:#0f8179}.conversation-head h2{margin:4px 0 2px;color:#0b4f4c;font-size:21px}.conversation-head small{color:#7a9190;font-size:10px}.mark-read-btn{border:1px solid #dcebea;background:#fff;color:#5e7977;border-radius:10px;padding:9px 11px;font:700 10px Inter,system-ui,sans-serif;cursor:pointer}.mark-read-btn:hover{background:#f2f9f8;color:#0b5f5b}.conversation-list{flex:1;min-height:0;max-height:none;overflow:auto;padding:20px 22px;background:linear-gradient(180deg,#fbfefd,#f6faf9)}.chat-bubble-row{display:flex;justify-content:flex-start;margin-bottom:12px}.chat-bubble-row.mine{justify-content:flex-end}.chat-bubble{max-width:min(78%,620px);padding:11px 13px;border:1px solid #dcebea;border-radius:14px 14px 14px 4px;background:#fff;box-shadow:0 5px 14px rgba(15,76,73,.035)}.chat-bubble-row.mine .chat-bubble{border-color:#bfe0dc;background:#eaf7f5;border-radius:14px 14px 4px 14px}.chat-meta{display:flex;align-items:center;gap:7px;margin-bottom:5px}.chat-meta strong{color:#244e4b;font-size:10px}.chat-meta time{color:#91a5a3;font-size:8px}.new-pill{padding:3px 5px;border-radius:999px;background:#fff0d1;color:#9a6500;font-size:7px;font-weight:900}.chat-bubble p{margin:0;color:#4f6c6a;font-size:11px;line-height:1.6;word-break:break-word}.reply-form{padding:15px 18px;border-top:1px solid #e5eeee;background:#fff}.reply-form textarea,.staff-compose-card textarea{width:100%;min-height:92px;resize:vertical;border:1px solid #cfe2e1;border-radius:12px;padding:11px 12px;font:inherit;outline:none;color:#264e4c}.reply-form textarea:focus,.staff-compose-card textarea:focus{border-color:#0f8b83;box-shadow:0 0 0 3px rgba(15,139,131,.08)}.reply-form>div,.compose-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:9px}.reply-form small,.compose-footer small{color:#7a9190;font-size:9px}.conversation-empty{margin:auto;text-align:center;padding:50px 25px;color:#708987}.empty-icon{width:58px;height:58px;margin:0 auto 12px;border-radius:16px;background:#eaf7f5;color:#0f8179;display:grid;place-items:center;font-size:24px}.conversation-empty h2{margin:0 0 5px;color:#174744;font-size:20px}.conversation-empty p{margin:0 auto 16px;max-width:420px;font-size:12px;line-height:1.6}.staff-compose-modal{position:fixed;inset:0;z-index:1200;display:flex;align-items:center;justify-content:center;padding:20px}.staff-compose-modal[hidden]{display:none}.staff-compose-backdrop{position:absolute;inset:0;background:rgba(8,47,73,.5);backdrop-filter:blur(3px)}.staff-compose-card{position:relative;width:min(560px,100%);max-height:90vh;overflow:auto;padding:28px;background:#fff;border:1px solid #dcebea;border-radius:22px;box-shadow:0 28px 80px rgba(8,47,73,.24)}.staff-compose-card>p{margin:0 0 18px;color:#6a8381;font-size:12px}.staff-compose-card h2{margin:5px 45px 6px 0;color:#0b4f4c;font-size:24px}.staff-compose-card form{display:grid;gap:12px}.staff-compose-card label{display:grid;gap:7px;color:#476764;font-size:11px;font-weight:800}.staff-compose-card select{width:100%;height:45px;border:1px solid #cfe2e1;border-radius:11px;padding:0 12px;background:#fff;font:inherit}.staff-compose-close{position:absolute;top:16px;right:16px;width:36px;height:36px;border:1px solid #dcebea;border-radius:10px;background:#fff;color:#607876;font-size:22px;cursor:pointer}.staff-compose-open{overflow:hidden}
.conversation-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap}.delete-conversation-btn{border:1px solid #f0caca;background:#fff6f6;color:#a33b3b;border-radius:10px;padding:9px 11px;font:700 10px Inter,system-ui,sans-serif;cursor:pointer}.delete-conversation-btn:hover{background:#ffecec}.message-delete-form{margin-left:auto;display:inline-flex;opacity:0;transition:opacity .15s ease}.chat-bubble:hover .message-delete-form,.message-delete-form:focus-within{opacity:1}.message-delete-btn{width:20px;height:20px;padding:0;border:1px solid #e4eceb;border-radius:7px;background:#fff;color:#a05a5a;font:800 14px/18px system-ui,sans-serif;cursor:pointer}.message-delete-btn:hover{background:#fff0f0;color:#a83232}.conversation-list{scroll-behavior:smooth}@media(max-width:760px){.staff-message-layout{height:760px}.thread-sidebar{max-height:none}.conversation-actions{width:100%;justify-content:flex-start}}@media(max-width:950px){.staff-message-layout{grid-template-columns:320px 1fr}}@media(max-width:760px){.staff-page-head{align-items:flex-start;flex-direction:column}.staff-message-layout{grid-template-columns:1fr;min-height:0}.thread-sidebar{border-right:0;border-bottom:1px solid #e5eeee;max-height:360px}.conversation-list{max-height:440px}.conversation-head{align-items:flex-start;flex-direction:column}.chat-bubble{max-width:90%}}@media(max-width:600px){.staff-messages-page{margin-top:18px}.staff-page-head h1{font-size:27px}.staff-new-btn{width:100%}.staff-compose-modal{padding:12px}.staff-compose-card{padding:22px 18px;border-radius:18px}.reply-form>div,.compose-footer{align-items:stretch;flex-direction:column}.staff-send-btn{width:100%;min-height:44px}}




/* Staff Messages — mobile-only header fit + visible chat scrollbar */
@media (max-width: 600px){
  /* Mobile header: balanced three-button row; desktop/tablet untouched. */
  .admin-topbar .admin-header-actions{
    width:100%;
    display:grid;
    grid-template-columns:minmax(0,1fr) minmax(82px,.72fr);
    grid-template-rows:34px 34px;
    align-items:stretch;
    gap:6px;
    flex-wrap:nowrap;
  }
  .admin-topbar .admin-staff-messages-btn{grid-column:1;grid-row:1;height:34px;min-height:34px}
  .admin-topbar .admin-identity{grid-column:2;grid-row:1;height:34px;min-height:34px}
  .admin-topbar .admin-logout-btn{grid-column:1 / -1;grid-row:2;height:34px;min-height:34px;width:48%;justify-self:center}
  .admin-topbar .admin-staff-messages-btn{
    width:100%;
    min-width:0;
    min-height:34px;
    padding:8px 6px;
    gap:5px;
    font-size:10px;
    line-height:1;
    overflow:hidden;
  }
  .admin-topbar .admin-staff-messages-icon{font-size:12px;flex:0 0 auto;}
  .admin-topbar .admin-staff-messages-btn > span:not(.admin-staff-messages-icon):not(.admin-staff-messages-badge){
    min-width:0;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
  }
  .admin-topbar .admin-staff-messages-badge{min-width:16px;height:16px;padding:0 4px;font-size:8px;flex:0 0 auto;}
  .admin-topbar .admin-identity{
    width:100%;
    max-width:none;
    min-width:0;
    min-height:34px;
    padding:6px 6px;
    gap:4px;
    overflow:hidden;
    justify-content:center;
  }
  .admin-topbar .admin-identity-role{
    font-size:7px!important;
    padding:3px 5px;
    letter-spacing:.6px!important;
    flex:0 0 auto;
  }
  .admin-topbar .admin-identity-name{display:none;}
  .admin-topbar .admin-identity-id{
    min-width:0;
    padding-left:5px;
    border-left:1px solid rgba(255,255,255,.24);
    font-size:9px;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  .admin-topbar .admin-logout-btn{
    width:100%;
    min-width:0;
    min-height:34px;
    padding:8px 6px;
    gap:5px;
    font-size:10px;
    white-space:nowrap;
    overflow:hidden;
  }
  .admin-topbar .admin-logout-btn span:last-child{
    overflow:hidden;
    text-overflow:ellipsis;
  }

  .staff-back-row{margin-bottom:12px}
  .staff-back-btn{padding:8px 11px;font-size:10px;border-radius:10px}
  .conversation-list{
    scrollbar-width:thin;
    scrollbar-color:#8daaa7 #edf5f4;
    -webkit-overflow-scrolling:touch;
    scrollbar-gutter:stable;
  }
  .conversation-list::-webkit-scrollbar{width:7px;}
  .conversation-list::-webkit-scrollbar-track{background:#edf5f4;border-radius:10px;}
  .conversation-list::-webkit-scrollbar-thumb{background:#8daaa7;border-radius:10px;border:1px solid #edf5f4;}
  .conversation-list::-webkit-scrollbar-thumb:hover{background:#6f9290;}
}

/* Staff Messages — mobile header: two compact buttons on one row only */
@media (max-width: 600px){
  .admin-topbar .admin-header-actions{
    width:100%;
    display:grid;
    grid-template-columns:minmax(0,1fr) minmax(0,1fr);
    grid-template-rows:32px;
    gap:6px;
    align-items:stretch;
    flex-wrap:nowrap;
  }
  .admin-topbar .admin-staff-messages-btn{
    grid-column:1;
    grid-row:1;
    width:100%;
    min-width:0;
    min-height:32px;
    height:32px;
    padding:6px 7px;
    gap:4px;
    font-size:9px;
    line-height:1;
    white-space:nowrap;
  }
  .admin-topbar .admin-logout-btn{
    grid-column:2;
    grid-row:1;
    width:100%;
    min-width:0;
    min-height:32px;
    height:32px;
    padding:6px 7px;
    gap:4px;
    font-size:9px;
    line-height:1;
    white-space:nowrap;
  }
  .admin-topbar .admin-staff-messages-icon,
  .admin-topbar .admin-logout-icon{
    width:11px;
    height:11px;
    flex:0 0 auto;
  }
  .admin-topbar .admin-staff-messages-btn > span:not(.admin-staff-messages-icon):not(.admin-staff-messages-badge),
  .admin-topbar .admin-logout-btn > span:last-child{
    min-width:0;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
  }
  .admin-topbar .admin-staff-messages-badge{
    min-width:14px;
    height:14px;
    padding:0 3px;
    font-size:7px;
    flex:0 0 auto;
  }
}
</style>
</head>
<body>
<?php require __DIR__.'/admin_header.php'; ?>
<main class="wrap staff-messages-page">
  <div class="staff-back-row"><a href="dashboard.php" class="staff-back-btn">← Back to Admin Dashboard</a></div>
  <?php if($message): ?><div class="staff-alert success"><?=staff_h($message)?></div><?php endif; ?>
  <?php if($error): ?><div class="staff-alert error"><?=staff_h($error)?></div><?php endif; ?>
  <section class="staff-page-head"><div><span class="eyebrow">STAFF COMMUNICATION</span><h1>Staff Messages</h1><p>Manage private conversations with Managers and Authenticators from one place.</p></div><button type="button" class="staff-new-btn" data-open-compose>✉ New Message</button></section>
  <section class="staff-message-layout">
    <aside class="thread-sidebar">
      <div class="thread-filters">
        <?php foreach($allowed_filters as $f): ?><a class="thread-filter <?= $filter===$f?'active':'' ?>" href="?filter=<?=rawurlencode($f)?>"><?=staff_h($f)?><?php if($f==='Unread' && $staff_unread_count): ?><b><?=staff_h($staff_unread_count>99?'99+':$staff_unread_count)?></b><?php endif; ?></a><?php endforeach; ?>
      </div>
      <div class="thread-list">
        <?php if(!$threads): ?><div class="thread-empty">No staff conversations found.</div><?php endif; ?>
        <?php foreach($threads as $t): ?>
          <a class="thread-item <?= $active_thread && $active_thread['key']===$t['key']?'active':'' ?>" href="?filter=<?=rawurlencode($filter)?>&staff=<?=rawurlencode($t['key'])?>">
            <?php if (!empty($t['profile_image'])): ?><div class="thread-avatar <?=strtolower($t['role'])==='authenticator'?'auth':''?> has-photo"><img src="../uploads/staff/<?=staff_h($t['profile_image'])?>" alt="<?=staff_h($t['name'])?>"></div><?php else: ?><div class="thread-avatar <?=strtolower($t['role'])==='authenticator'?'auth':''?>"><?= $t['role']==='Manager'?'M':'A' ?></div><?php endif; ?>
            <div class="thread-copy"><div class="thread-top"><strong><?=staff_h($t['name'])?></strong><small><?=staff_h($t['role'])?></small></div><p><?=staff_h(mb_strimwidth($t['latest_message'],0,74,'…','UTF-8'))?></p><time><?=staff_h(staff_time($t['latest_at']))?></time></div>
            <?php if($t['unread']): ?><span class="thread-unread"><?=staff_h($t['unread']>99?'99+':$t['unread'])?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </aside>
    <section class="conversation-panel">
      <?php if(!$active_thread): ?>
        <div class="conversation-empty"><div class="empty-icon">✉</div><h2>No conversation selected</h2><p>Select a Manager or Authenticator conversation, or start a new message.</p><button type="button" class="staff-new-btn" data-open-compose>New Message</button></div>
      <?php else: ?>
        <div class="conversation-head"><div class="conversation-person"><?php if (!empty($active_thread['profile_image'])): ?><div class="conversation-avatar has-photo"><img src="../uploads/staff/<?=staff_h($active_thread['profile_image'])?>" alt="<?=staff_h($active_thread['name'])?>"></div><?php else: ?><div class="conversation-avatar <?=strtolower($active_thread['role'])==='authenticator'?'auth':''?>"><?= $active_thread['role']==='Manager'?'M':'A' ?></div><?php endif; ?><div><span class="eyebrow"><?=staff_h($active_thread['role'])?></span><h2><?=staff_h($active_thread['name'])?></h2><small>SM-<?=str_pad((string)$active_thread['user_id'],6,'0',STR_PAD_LEFT)?></small></div></div><div class="conversation-actions"><form method="post"><input type="hidden" name="csrf" value="<?=staff_h($csrf)?>"><input type="hidden" name="action" value="mark_read"><input type="hidden" name="recipient_id" value="<?=staff_h($active_thread['user_id'])?>"><input type="hidden" name="recipient_role" value="<?=staff_h($active_thread['role'])?>"><button type="submit" class="mark-read-btn">✓ Mark unread as read</button></form><form method="post" onsubmit="return confirm('Delete this entire conversation? This cannot be undone.');"><input type="hidden" name="csrf" value="<?=staff_h($csrf)?>"><input type="hidden" name="action" value="delete_conversation"><input type="hidden" name="recipient_id" value="<?=staff_h($active_thread['user_id'])?>"><input type="hidden" name="recipient_role" value="<?=staff_h($active_thread['role'])?>"><button type="submit" class="delete-conversation-btn">🗑 Delete</button></form></div></div>
        <div class="conversation-list">
          <?php foreach($active_thread['messages'] as $m): $mine=$m['sender_role']==='Admin'; ?>
            <article class="chat-bubble-row <?=$mine?'mine':''?>"><div class="chat-bubble"><div class="chat-meta"><strong><?=staff_h($mine?'Admin':$active_thread['name'])?></strong><time><?=staff_h(staff_time($m['created_at']))?></time><?php if(!$mine && $m['status']==='Unread'): ?><span class="new-pill">NEW</span><?php endif; ?><form method="post" class="message-delete-form" data-delete-message onsubmit="return confirm('Delete this message? This cannot be undone.');"><input type="hidden" name="csrf" value="<?=staff_h($csrf)?>"><input type="hidden" name="action" value="delete_message"><input type="hidden" name="message_id" value="<?=staff_h($m['message_id'])?>"><input type="hidden" name="recipient_id" value="<?=staff_h($active_thread['user_id'])?>"><input type="hidden" name="recipient_role" value="<?=staff_h($active_thread['role'])?>"><button type="submit" class="message-delete-btn" title="Delete message" aria-label="Delete message">×</button></form></div><p><?=nl2br(staff_h($m['message']))?></p></div></article>
          <?php endforeach; ?>
        </div>
        <form method="post" class="reply-form" data-reply-form><input type="hidden" name="csrf" value="<?=staff_h($csrf)?>"><input type="hidden" name="action" value="send_staff_message"><input type="hidden" name="thread_id" value="<?=staff_h($active_thread['thread_id'])?>"><input type="hidden" name="recipient_id" value="<?=staff_h($active_thread['user_id'])?>"><input type="hidden" name="recipient_role" value="<?=staff_h($active_thread['role'])?>"><textarea name="message" maxlength="2000" placeholder="Write a reply..." required></textarea><div><small>Maximum 2000 characters</small><button type="submit" class="staff-send-btn">Send Reply →</button></div></form>
      <?php endif; ?>
    </section>
  </section>
</main>
<div class="staff-compose-modal" data-compose-modal hidden><div class="staff-compose-backdrop" data-close-compose></div><section class="staff-compose-card" role="dialog" aria-modal="true" aria-labelledby="compose-title"><button type="button" class="staff-compose-close" data-close-compose>×</button><span class="eyebrow">STAFF COMMUNICATION</span><h2 id="compose-title">New Staff Message</h2><p>Send a private message to a Manager or Authenticator.</p><form method="post"><input type="hidden" name="csrf" value="<?=staff_h($csrf)?>"><input type="hidden" name="action" value="send_staff_message"><input type="hidden" name="thread_id" value=""><label>Recipient<select name="recipient_id" id="new-recipient" required><option value="">Select staff member</option><?php foreach($staff as $person): ?><option value="<?=staff_h($person['user_id'])?>" data-role="<?=staff_h($person['role'])?>"><?=staff_h($person['first_name'].' '.$person['last_name'])?> · <?=staff_h($person['role'])?></option><?php endforeach; ?></select></label><input type="hidden" name="recipient_role" id="new-recipient-role"><label>Message<textarea name="message" maxlength="2000" placeholder="Write your message..." required></textarea></label><div class="compose-footer"><small>Maximum 2000 characters</small><button type="submit" class="staff-send-btn">Send Message →</button></div></form></section></div>
<script>
(function(){
 const modal=document.querySelector('[data-compose-modal]');
 const recipient=document.getElementById('new-recipient');
 const role=document.getElementById('new-recipient-role');
 const chat=document.querySelector('.conversation-list');
 const replyForm=document.querySelector('[data-reply-form]');
 function open(){if(modal){modal.hidden=false;document.body.classList.add('staff-compose-open');}}
 function close(){if(modal){modal.hidden=true;document.body.classList.remove('staff-compose-open');}}
 document.querySelectorAll('[data-open-compose]').forEach(b=>b.addEventListener('click',open));
 document.querySelectorAll('[data-close-compose]').forEach(b=>b.addEventListener('click',close));
 if(recipient) recipient.addEventListener('change',function(){role.value=this.options[this.selectedIndex]?.dataset.role||'';});
 document.addEventListener('keydown',e=>{if(e.key==='Escape')close();});

 function scrollChatToLatest(){
   if(!chat) return;
   // The conversation should open at the latest message, not at the first one.
   // Temporarily disable smooth scrolling so the initial positioning does not animate.
   const previousBehavior=chat.style.scrollBehavior;
   chat.style.scrollBehavior='auto';
   chat.scrollTop=chat.scrollHeight;
   chat.style.scrollBehavior=previousBehavior;
 }

 // On page load / filter switch, open the selected conversation at its latest message.
 if(chat){
   requestAnimationFrame(()=>requestAnimationFrame(scrollChatToLatest));
 }

 function updateUnreadCount(total){
   if(typeof total !== 'number') return;
   document.querySelectorAll('.thread-filter').forEach(el=>{
     const label=el.textContent.trim().replace(/\s+/g,' ');
     if(!label.startsWith('Unread')) return;
     let badge=el.querySelector('b');
     if(total > 0){
       if(!badge){
         badge=document.createElement('b');
         el.appendChild(badge);
       }
       badge.textContent=total>99?'99+':String(total);
     }else if(badge){
       badge.remove();
     }
   });
 }

 function clearActiveConversationUnread(){
   const activeThread=document.querySelector('.thread-item.active');
   if(activeThread){
     const badge=activeThread.querySelector('.thread-unread');
     if(badge) badge.remove();
   }
   if(chat){
     chat.querySelectorAll('.new-pill').forEach(el=>el.remove());
   }
 }

 function appendReply(data){
   if(!chat) return;
   const wasNearBottom = (chat.scrollHeight - chat.scrollTop - chat.clientHeight) < 70;
   const row=document.createElement('article');
   row.className='chat-bubble-row mine';
   const bubble=document.createElement('div');
   bubble.className='chat-bubble';
   const meta=document.createElement('div');
   meta.className='chat-meta';
   const strong=document.createElement('strong'); strong.textContent='Admin';
   const time=document.createElement('time');
   const d=new Date(data.created_at.replace(' ','T'));
   time.textContent=isNaN(d.getTime()) ? 'Just now' : d.toLocaleDateString(undefined,{day:'2-digit',month:'short',year:'numeric'})+', '+d.toLocaleTimeString(undefined,{hour:'2-digit',minute:'2-digit'});
   meta.append(strong,time);
   const p=document.createElement('p'); p.textContent=data.message;
   bubble.append(meta,p); row.appendChild(bubble); chat.appendChild(row);
   if(wasNearBottom) chat.scrollTop=chat.scrollHeight;
 }

 if(replyForm){
   replyForm.addEventListener('submit',async function(e){
     e.preventDefault();
     const button=replyForm.querySelector('.staff-send-btn');
     const textarea=replyForm.querySelector('textarea[name="message"]');
     const original=button ? button.textContent : '';
     if(button){button.disabled=true;button.textContent='Sending…';}
     try{
       const fd=new FormData(replyForm); fd.set('ajax','1');
       const res=await fetch(window.location.href,{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});
       const data=await res.json();
       if(!data.ok) throw new Error(data.error||'Message could not be sent.');
       appendReply(data.message);
       if(textarea) textarea.value='';
       clearActiveConversationUnread();
       updateUnreadCount(Number(data.unread_total));
     }catch(err){ alert(err.message||'Message could not be sent.'); }
     finally{ if(button){button.disabled=false;button.textContent=original;} }
   });
 }

 document.querySelectorAll('[data-delete-message]').forEach(form=>form.addEventListener('submit',async function(e){
   e.preventDefault();
   const row=form.closest('.chat-bubble-row');
   if(!row) return;
   const next=row.nextElementSibling;
   const prev=row.previousElementSibling;
   const anchor=next||prev;
   const anchorTop=anchor ? anchor.getBoundingClientRect().top : null;
   const fd=new FormData(form); fd.set('ajax','1');
   const button=form.querySelector('.message-delete-btn'); if(button) button.disabled=true;
   try{
     const res=await fetch(window.location.href,{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});
     const data=await res.json();
     if(!data.ok) throw new Error(data.error||'Message could not be deleted.');
     row.remove();
     if(chat && anchor && anchor.isConnected && anchorTop !== null){
       const newTop=anchor.getBoundingClientRect().top;
       chat.scrollTop += newTop-anchorTop;
     }
   }catch(err){ alert(err.message||'Message could not be deleted.'); if(button) button.disabled=false; }
 }));
})();
</script></body></html>