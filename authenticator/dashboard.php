<?php
$page_css = 'assets/css/authenticator-verification.css';

// Authenticator has its own session cookie. Select it before config.php so
// the normal user session is never mixed with the Authenticator session.
if (session_status() === PHP_SESSION_NONE) {
    session_name('SMART_AUTH_SESSION');
    session_start();
}

require_once '../config/db.php';
require_once '../includes/profile_completion.php';

if (empty($_SESSION['authenticator_user_id'])) {
    header('Location: login.php');
    exit;
}

$authenticator_id = (int) $_SESSION['authenticator_user_id'];
$auth_stmt = mysqli_prepare($conn, "SELECT user_id, first_name, last_name, role, account_status FROM users WHERE user_id=? LIMIT 1");
mysqli_stmt_bind_param($auth_stmt, 'i', $authenticator_id);
mysqli_stmt_execute($auth_stmt);
$auth = mysqli_fetch_assoc(mysqli_stmt_get_result($auth_stmt));
mysqli_stmt_close($auth_stmt);

if (!$auth || $auth['role'] !== 'Authenticator' || $auth['account_status'] !== 'Active') {
    header('Location: ../login.php');
    exit;
}

if (empty($_SESSION['auth_csrf'])) $_SESSION['auth_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['auth_csrf'];
$action_notice = '';
$action_error = '';
$message_user_id = 0;
$message_recipient = null;
$bulk_selected_ids = [];
$bulk_recipients = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        $action_error = 'Security check failed. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $target_id = (int)($_POST['user_id'] ?? 0);
        $log_id = (int)($_POST['log_id'] ?? 0);

        if ($action === 'send_bulk_message') {
            $bulk_selected_ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['user_ids'] ?? [])), static function (int $id): bool { return $id > 0; })));
            $bulk_message_text = trim((string)($_POST['bulk_message'] ?? ''));
            if (!$bulk_selected_ids) {
                $action_error = 'Please select at least one active member.';
            } elseif ($bulk_message_text === '') {
                $action_error = 'Please write a message before sending.';
            } elseif (mb_strlen($bulk_message_text) > 2000) {
                $action_error = 'Message is too long. Please keep it within 2000 characters.';
            } else {
                $placeholders = implode(',', array_fill(0, count($bulk_selected_ids), '?'));
                $types_bulk = str_repeat('i', count($bulk_selected_ids));
                $verify_sql = "SELECT u.user_id, COALESCE(NULLIF(TRIM(CONCAT(up.first_name, ' ', up.last_name)), ''), NULLIF(TRIM(CONCAT(u.first_name, ' ', u.last_name)), ''), u.email) AS member_name FROM users u INNER JOIN user_profiles up ON up.user_id=u.user_id WHERE u.role='User' AND u.account_status='Active' AND u.user_id IN ({$placeholders})";
                $verify_stmt = mysqli_prepare($conn, $verify_sql);
                mysqli_stmt_bind_param($verify_stmt, $types_bulk, ...$bulk_selected_ids);
                mysqli_stmt_execute($verify_stmt);
                $verify_result = mysqli_stmt_get_result($verify_stmt);
                while ($recipient = mysqli_fetch_assoc($verify_result)) $bulk_recipients[] = $recipient;
                mysqli_stmt_close($verify_stmt);
                if (count($bulk_recipients) !== count($bulk_selected_ids)) {
                    $action_error = 'One or more selected members are no longer available for messaging. Please review your selection and try again.';
                } else {
                    mysqli_begin_transaction($conn);
                    $bulk_stmt = mysqli_prepare($conn, 'INSERT INTO authenticator_messages (authenticator_id, user_id, message) VALUES (?, ?, ?)');
                    $bulk_ok = true;
                    foreach ($bulk_selected_ids as $bulk_user_id) {
                        mysqli_stmt_bind_param($bulk_stmt, 'iis', $authenticator_id, $bulk_user_id, $bulk_message_text);
                        if (!mysqli_stmt_execute($bulk_stmt)) { $bulk_ok = false; break; }
                    }
                    mysqli_stmt_close($bulk_stmt);
                    if ($bulk_ok) {
                        mysqli_commit($conn);
                        $action_notice = count($bulk_selected_ids) . ' message' . (count($bulk_selected_ids) === 1 ? '' : 's') . ' sent successfully.';
                        $bulk_selected_ids = [];
                        $bulk_recipients = [];
                    } else {
                        mysqli_rollback($conn);
                        $action_error = 'Bulk messages could not be sent. No messages were saved. Please try again.';
                    }
                }
            }
        } elseif ($action === 'send_message' && $target_id > 0) {
            $message_text = trim((string)($_POST['message'] ?? ''));
            if ($message_text === '') {
                $action_error = 'Please write a message before sending.';
                $message_user_id = $target_id;
            } elseif (mb_strlen($message_text) > 2000) {
                $action_error = 'Message is too long. Please keep it within 2000 characters.';
                $message_user_id = $target_id;
            } else {
                $recipient_stmt = mysqli_prepare($conn, "SELECT u.user_id FROM users u INNER JOIN user_profiles up ON up.user_id=u.user_id WHERE u.user_id=? AND u.role='User' AND u.account_status='Active' LIMIT 1");
                mysqli_stmt_bind_param($recipient_stmt, 'i', $target_id);
                mysqli_stmt_execute($recipient_stmt);
                $recipient_row = mysqli_fetch_assoc(mysqli_stmt_get_result($recipient_stmt));
                mysqli_stmt_close($recipient_stmt);

                if (!$recipient_row) {
                    $action_error = 'This member is not available for messaging.';
                    $message_user_id = $target_id;
                } else {
                    $send_stmt = mysqli_prepare($conn, 'INSERT INTO authenticator_messages (authenticator_id, user_id, message) VALUES (?, ?, ?)');
                    mysqli_stmt_bind_param($send_stmt, 'iis', $authenticator_id, $target_id, $message_text);
                    $sent = mysqli_stmt_execute($send_stmt);
                    mysqli_stmt_close($send_stmt);
                    if ($sent) {
                        $action_notice = 'Message sent successfully.';
                        $message_user_id = 0;
                    } else {
                        $action_error = 'Message could not be sent. Please try again.';
                        $message_user_id = $target_id;
                    }
                }
            }
        } elseif ($action === 'delete_inactive' && $target_id > 0) {
            $del = mysqli_prepare($conn, "DELETE FROM users WHERE user_id=? AND role='User' AND account_status='Inactive' LIMIT 1");
            mysqli_stmt_bind_param($del, 'i', $target_id);
            mysqli_stmt_execute($del);
            $deleted = mysqli_stmt_affected_rows($del);
            mysqli_stmt_close($del);
            $deleted ? $action_notice = 'Inactive member account deleted successfully.' : $action_error = 'Inactive account could not be deleted.';
        } elseif ($action === 'delete_history' && $log_id > 0) {
            $del = mysqli_prepare($conn, 'DELETE FROM verification_logs WHERE log_id=? AND authenticator_id=? LIMIT 1');
            mysqli_stmt_bind_param($del, 'ii', $log_id, $authenticator_id);
            mysqli_stmt_execute($del);
            $deleted = mysqli_stmt_affected_rows($del);
            mysqli_stmt_close($del);
            $deleted ? $action_notice = 'Verification history entry deleted.' : $action_error = 'History entry could not be deleted.';
        } else {
            $action_error = 'Invalid action.';
        }
    }
}

$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'Pending';
$allowed_statuses = ['All', 'Pending', 'Verified', 'Rejected'];
if (!in_array($status, $allowed_statuses, true)) {
    $status = 'Pending';
}

$gender = $_GET['gender'] ?? 'All';
$allowed_genders = ['All', 'Male', 'Female'];
if (!in_array($gender, $allowed_genders, true)) {
    $gender = 'All';
}

$completion_sort = $_GET['completion_sort'] ?? 'none';
$allowed_completion_sorts = ['none', 'asc', 'desc'];
if (!in_array($completion_sort, $allowed_completion_sorts, true)) {
    $completion_sort = 'none';
}

if ($message_user_id > 0) {
    $message_recipient_stmt = mysqli_prepare($conn, "SELECT u.user_id, up.first_name AS profile_first_name, up.last_name AS profile_last_name, u.first_name, u.last_name, u.email FROM users u INNER JOIN user_profiles up ON up.user_id=u.user_id WHERE u.user_id=? AND u.role='User' AND u.account_status='Active' LIMIT 1");
    mysqli_stmt_bind_param($message_recipient_stmt, 'i', $message_user_id);
    mysqli_stmt_execute($message_recipient_stmt);
    $message_recipient = mysqli_fetch_assoc(mysqli_stmt_get_result($message_recipient_stmt)) ?: null;
    mysqli_stmt_close($message_recipient_stmt);
    if (!$message_recipient) {
        $message_user_id = 0;
        if (!$action_error) $action_error = 'The selected member is not available for messaging.';
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

function count_profiles(mysqli $conn, string $condition = ''): int {
    $sql = "SELECT COUNT(*) AS total FROM user_profiles up INNER JOIN users u ON u.user_id=up.user_id WHERE u.role='User'" . ($condition ? " AND {$condition}" : '');
    $result = mysqli_query($conn, $sql);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    return (int)($row['total'] ?? 0);
}

$pending_count = count_profiles($conn, "up.verification_status='Pending'");
$verified_count = count_profiles($conn, "up.verification_status='Verified'");
$rejected_count = count_profiles($conn, "up.verification_status='Rejected'");
$total_profiles = count_profiles($conn);
$inactive_count_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role='User' AND account_status='Inactive'");
$inactive_count_row = $inactive_count_result ? mysqli_fetch_assoc($inactive_count_result) : null;
$inactive_count = (int)($inactive_count_row['total'] ?? 0);

$where = ["u.role='User'"];
$params = [];
$types = '';

if ($status !== 'All') {
    $where[] = 'up.verification_status=?';
    $params[] = $status;
    $types .= 's';
}

if ($gender !== 'All') {
    $where[] = 'up.gender=?';
    $params[] = $gender;
    $types .= 's';
}

if ($search !== '') {
    // Member IDs are exact searches. Accept both SM-000002 and 000002 formats.
    $normalized_id = null;
    if (preg_match('/^SM-(\d+)$/i', $search, $id_match)) {
        $normalized_id = (int) $id_match[1];
    } elseif (preg_match('/^\d+$/', $search)) {
        $normalized_id = (int) $search;
    }

    if ($normalized_id !== null && $normalized_id > 0) {
        $where[] = 'u.user_id = ?';
        $params[] = $normalized_id;
        $types .= 'i';
    } else {
        // Name/email/mobile searches remain substring-based.
        $where[] = '(CONCAT(up.first_name, " ", COALESCE(up.last_name, "")) LIKE ? OR CONCAT(u.first_name, " ", u.last_name) LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)';
        $like = '%' . $search . '%';
        for ($i=0; $i<4; $i++) $params[] = $like;
        $types .= 'ssss';
    }
}

$where_sql = implode(' AND ', $where);
$list_sql = "SELECT up.profile_id, up.user_id, up.first_name, up.last_name, up.gender, up.date_of_birth, up.religion, up.photo, up.verification_status, up.updated_at, u.email, u.mobile, u.account_status
             FROM user_profiles up INNER JOIN users u ON u.user_id=up.user_id
             WHERE {$where_sql}
             ORDER BY FIELD(up.verification_status,'Pending','Rejected','Verified'), up.updated_at DESC";
$list_stmt = mysqli_prepare($conn, $list_sql);
if ($params) mysqli_stmt_bind_param($list_stmt, $types, ...$params);
mysqli_stmt_execute($list_stmt);
$profiles = mysqli_stmt_get_result($list_stmt);

// Step 3: calculate completion for all filtered members first, then sort and
// paginate the complete result set so low/high sorting works across every page.
$profile_rows = [];
while ($profile_row = mysqli_fetch_assoc($profiles)) {
    $completion = sm_get_profile_completion($conn, (int) $profile_row['user_id']);
    $profile_row['completion_percentage'] = (int) ($completion['percentage'] ?? 0);
    $profile_rows[] = $profile_row;
}
mysqli_stmt_close($list_stmt);

if ($completion_sort !== 'none') {
    usort($profile_rows, static function (array $a, array $b) use ($completion_sort): int {
        $completion_compare = ((int) $a['completion_percentage']) <=> ((int) $b['completion_percentage']);
        if ($completion_compare !== 0) {
            return $completion_sort === 'asc' ? $completion_compare : -$completion_compare;
        }

        // Keep ties stable and predictable: newest profile first.
        $a_updated = strtotime((string) $a['updated_at']);
        $b_updated = strtotime((string) $b['updated_at']);
        return $b_updated <=> $a_updated;
    });
}

$total_rows = count($profile_rows);
$total_pages = max(1, (int) ceil($total_rows / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;
$profile_rows = array_slice($profile_rows, $offset, $per_page);

$inactive_stmt = mysqli_prepare($conn, "SELECT u.user_id, u.first_name, u.last_name, u.email, u.mobile, u.created_at, up.profile_id, up.photo, up.verification_status FROM users u LEFT JOIN user_profiles up ON up.user_id=u.user_id WHERE u.role='User' AND u.account_status='Inactive' ORDER BY u.created_at DESC");
mysqli_stmt_execute($inactive_stmt);
$inactive_accounts = mysqli_stmt_get_result($inactive_stmt);

$history_stmt = mysqli_prepare($conn, "SELECT vl.log_id, vl.action, vl.remarks, vl.action_at, up.profile_id, up.first_name, up.last_name, u.user_id FROM verification_logs vl INNER JOIN user_profiles up ON up.profile_id=vl.profile_id INNER JOIN users u ON u.user_id=up.user_id WHERE vl.authenticator_id=? ORDER BY vl.action_at DESC LIMIT 50");
mysqli_stmt_bind_param($history_stmt, 'i', $authenticator_id);
mysqli_stmt_execute($history_stmt);
$history = mysqli_stmt_get_result($history_stmt);

function qs(array $extra = []): string {
    $base = [
        'q' => $_GET['q'] ?? '',
        'status' => $_GET['status'] ?? 'Pending',
        'gender' => $_GET['gender'] ?? 'All',
        'completion_sort' => $_GET['completion_sort'] ?? 'none'
    ];
    return http_build_query(array_merge($base, $extra));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authenticator Verification Center | Smart Matrimony</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars('../assets/css/authenticator-verification.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="auth-page">
<header class="auth-header">
    <div class="auth-brand">
        <img src="../assets/images/logo/logo.png" alt="Smart Matrimony logo" class="auth-logo">
        <img src="../assets/images/logo/matrimony_title.png" alt="Smart Matrimony" class="auth-title-logo">
    </div>
    <div class="auth-header-actions">
        <span class="identity"><span>AUTHENTICATOR</span> <?= htmlspecialchars($auth['first_name'].' '.$auth['last_name']) ?></span>
        <a href="logout.php" class="header-btn danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
</header>

<main class="auth-container">
    <?php if ($action_notice): ?><div class="flash success"><i class="fa-solid fa-circle-check"></i><?= htmlspecialchars($action_notice) ?></div><?php endif; ?>
    <?php if ($action_error): ?><div class="flash error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($action_error) ?></div><?php endif; ?>
    <section class="intro-card">
        <div>
            <span class="section-label">PROFILE VERIFICATION</span>
            <h2>Authenticator Workspace</h2>
            <p>Profiles are completed through the project's six-step profile process. This verification module reviews the submitted information; it does not modify those six profile steps.</p>
        </div>
        <div class="intro-icon"><i class="fa-solid fa-user-shield"></i></div>
    </section>

    <section class="stats-grid">
        <div class="stat-card"><span>Pending Profiles</span><strong><?= number_format($pending_count) ?></strong><small>Need review</small></div>
        <div class="stat-card"><span>Verified Profiles</span><strong><?= number_format($verified_count) ?></strong><small>Approved</small></div>
        <div class="stat-card"><span>Rejected Profiles</span><strong><?= number_format($rejected_count) ?></strong><small>Needs attention</small></div>
        <div class="stat-card"><span>Total Profiles</span><strong><?= number_format($total_profiles) ?></strong><small>Member profiles</small></div>
        <div class="stat-card"><span>Inactive Accounts</span><strong><?= number_format($inactive_count) ?></strong><small>Inactive members</small></div>
    </section>

    <div class="bulk-message-modal<?= ($action_error && $bulk_selected_ids && $bulk_recipients) ? ' is-open' : '' ?>" id="bulk-message-modal" aria-hidden="<?= ($action_error && $bulk_selected_ids && $bulk_recipients) ? 'false' : 'true' ?>">
        <div class="bulk-message-modal-backdrop" data-bulk-message-close></div>
        <div class="bulk-message-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="bulk-message-modal-title">
            <div class="message-modal-head"><div><span class="section-label">AUTHENTICATOR MESSAGE</span><h2 id="bulk-message-modal-title">Message Selected Members</h2><p>Send the same private message to the selected active members.</p></div><button type="button" class="message-modal-close" data-bulk-message-close aria-label="Close bulk message box"><i class="fa-solid fa-xmark"></i></button></div>
            <form method="post" class="message-compose-form" id="bulk-message-compose-form" action="dashboard.php#member-profiles">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="send_bulk_message">
                <div class="bulk-recipient-list" id="bulk-recipient-list"></div>
                <textarea name="bulk_message" id="bulk-message-text" maxlength="2000" placeholder="Write your message..." required><?= htmlspecialchars((string)($_POST['bulk_message'] ?? '')) ?></textarea>
                <div class="message-compose-footer"><small>Maximum 2000 characters</small><div class="message-modal-actions"><button type="button" class="message-cancel" data-bulk-message-close>Cancel</button><button type="submit" class="message-send-btn"><i class="fa-solid fa-paper-plane"></i> Send to Selected</button></div></div>
            </form>
        </div>
    </div>

    <div class="message-modal<?= $message_recipient ? ' is-open' : '' ?>" id="message-modal" aria-hidden="<?= $message_recipient ? 'false' : 'true' ?>">
        <div class="message-modal-backdrop" data-message-close></div>
        <div class="message-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="message-modal-title">
            <div class="message-modal-head">
                <div>
                    <span class="section-label">AUTHENTICATOR MESSAGE</span>
                    <h2 id="message-modal-title">Message Member</h2>
                    <p id="message-modal-recipient">
                        <?php if ($message_recipient): ?>
                            Send a private message to <strong><?= htmlspecialchars(trim(($message_recipient['profile_first_name'] ?: $message_recipient['first_name']) . ' ' . ($message_recipient['profile_last_name'] ?: $message_recipient['last_name']))) ?></strong> · SM-<?= str_pad((string)$message_recipient['user_id'], 6, '0', STR_PAD_LEFT) ?>
                        <?php else: ?>
                            Select a member and write a private message.
                        <?php endif; ?>
                    </p>
                </div>
                <button type="button" class="message-modal-close" data-message-close aria-label="Close message box"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="post" class="message-compose-form" id="message-compose-form" action="dashboard.php#member-profiles">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="send_message">
                <input type="hidden" name="user_id" id="message-user-id" value="<?= $message_recipient ? (int)$message_recipient['user_id'] : '' ?>">
                <textarea name="message" id="message-text" maxlength="2000" required placeholder="Write your message to this member..."><?= htmlspecialchars((string)($_POST['message'] ?? '')) ?></textarea>
                <div class="message-compose-footer">
                    <small>Maximum 2000 characters.</small>
                    <div class="message-modal-actions">
                        <button type="button" class="message-cancel" data-message-close><i class="fa-solid fa-xmark"></i> Cancel</button>
                        <button type="submit" class="message-send-btn"><i class="fa-solid fa-paper-plane"></i> Send Message</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <section class="queue-card" id="member-profiles">
        <div class="section-heading member-profiles-heading">
            <div><span class="section-label">VERIFICATION QUEUE</span><h2>Member Profiles</h2></div>
            <div class="bulk-message-toolbar"><span class="selected-count" id="selected-count">0 selected</span><button type="button" class="bulk-message-btn" id="open-bulk-message" disabled><i class="fa-solid fa-paper-plane"></i> Message Selected</button><span class="result-count"><?= number_format($total_rows) ?> result<?= $total_rows === 1 ? '' : 's' ?></span></div>
        </div>

        <form class="filter-bar" method="GET" action="dashboard.php#member-profiles">
            <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search ID, name, email or mobile">
            <select name="status" aria-label="Verification status">
                <?php foreach ($allowed_statuses as $item): ?>
                    <option value="<?= $item ?>" <?= $status === $item ? 'selected' : '' ?>><?= $item ?></option>
                <?php endforeach; ?>
            </select>
            <select name="gender" aria-label="Gender">
                <?php foreach ($allowed_genders as $item): ?>
                    <option value="<?= $item ?>" <?= $gender === $item ? 'selected' : '' ?>><?= $item ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
            <?php if ($search !== '' || $status !== 'Pending' || $gender !== 'All' || $completion_sort !== 'none'): ?><a class="clear-btn" href="dashboard.php#member-profiles">Clear</a><?php endif; ?>
        </form>

        <?php if (count($profile_rows) === 0): ?>
            <div class="empty-state"><i class="fa-solid fa-circle-check"></i><h3>No profiles found</h3><p>Try another search or status filter.</p></div>
        <?php else: ?>
            <div class="profile-table-wrap profile-scroll">
                <table class="profile-table">
                    <thead><tr><th class="select-col"><label class="select-all-wrap" title="Select all active members on this page"><input type="checkbox" id="select-all-members" aria-label="Select all active members on this page"><span></span></label></th><th>Profile</th><th>Contact</th><th class="sortable-th">
                                <?php
                                $next_completion_sort = $completion_sort === 'none' ? 'asc' : ($completion_sort === 'asc' ? 'desc' : 'none');
                                $completion_icon = $completion_sort === 'asc' ? 'fa-arrow-up' : ($completion_sort === 'desc' ? 'fa-arrow-down' : 'fa-sort');
                                ?>
                                <a href="?<?= qs(['completion_sort' => $next_completion_sort, 'page' => 1]) ?>#member-profiles">Profile Completion <i class="fa-solid <?= $completion_icon ?>"></i></a>
                            </th><th>Profile Status</th><th>Account</th><th>Updated</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($profile_rows as $row): ?>
                        <tr>
                            <td class="select-col"><label class="member-select-wrap" title="Select this active member"><input type="checkbox" class="member-select" value="<?= (int)$row['user_id'] ?>" data-member-name="<?= htmlspecialchars(trim($row['first_name'].' '.$row['last_name']), ENT_QUOTES) ?>" data-member-code="SM-<?= str_pad((string)$row['user_id'], 6, '0', STR_PAD_LEFT) ?>" <?= $row['account_status'] !== 'Active' ? 'disabled' : '' ?>><span></span></label></td>
                            <td>
                                <div class="member-cell">
                                    <?php if (!empty($row['photo'])): ?><img src="../uploads/profile/<?= htmlspecialchars($row['photo']) ?>" alt="Profile photo"><?php else: ?><div class="avatar-placeholder"><i class="fa-solid fa-user"></i></div><?php endif; ?>
                                    <div><strong><?= htmlspecialchars(trim($row['first_name'].' '.$row['last_name'])) ?></strong><span>SM-<?= str_pad((string)$row['user_id'], 6, '0', STR_PAD_LEFT) ?></span></div>
                                </div>
                            </td>
                            <td><span><?= htmlspecialchars($row['email']) ?></span><small><?= htmlspecialchars($row['mobile']) ?></small></td>
                            <td><span class="completion-percent"><?= (int) $row['completion_percentage'] ?>%</span></td>
                            <td><span class="status-pill <?= strtolower($row['verification_status']) ?>"><?= htmlspecialchars($row['verification_status']) ?></span></td>
                            <td><?= htmlspecialchars($row['account_status']) ?></td>
                            <td><?= htmlspecialchars(date('d M Y', strtotime($row['updated_at']))) ?></td>
                            <td><div class="profile-action-group"><a class="review-btn" href="verify_profile.php?id=<?= (int)$row['profile_id'] ?>"><i class="fa-solid fa-file-shield"></i> Review</a><button type="button" class="message-btn" data-message-open data-user-id="<?= (int)$row['user_id'] ?>" data-member-name="<?= htmlspecialchars(trim($row['first_name'].' '.$row['last_name']), ENT_QUOTES) ?>" data-member-code="SM-<?= str_pad((string)$row['user_id'], 6, '0', STR_PAD_LEFT) ?>"><i class="fa-solid fa-comment-dots"></i> Message</button></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?><a href="?<?= qs(['page'=>$page-1]) ?>#member-profiles">&laquo;</a><?php endif; ?>
                    <span>Page <?= $page ?> of <?= $total_pages ?></span>
                    <?php if ($page < $total_pages): ?><a href="?<?= qs(['page'=>$page+1]) ?>#member-profiles">&raquo;</a><?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <section class="queue-card inactive-card">
        <div class="section-heading"><div><span class="section-label">INACTIVE ACCOUNTS</span><h2>Inactive Members</h2></div><span class="result-count">Member accounts only</span></div>
        <?php if (!$inactive_accounts || mysqli_num_rows($inactive_accounts) === 0): ?>
            <div class="empty-state compact-empty"><i class="fa-solid fa-user-check"></i><h3>No inactive accounts</h3><p>There are currently no inactive member accounts.</p></div>
        <?php else: ?>
            <div class="profile-table-wrap profile-scroll compact-scroll">
                <table class="profile-table"><thead><tr><th>Member</th><th>Contact</th><th>Verification</th><th>Created</th><th>Action</th></tr></thead><tbody>
                <?php while ($inactive = mysqli_fetch_assoc($inactive_accounts)): ?>
                    <tr><td><div class="member-cell"><div class="avatar-placeholder"><i class="fa-solid fa-user"></i></div><div><strong><?= htmlspecialchars(trim($inactive['first_name'].' '.$inactive['last_name'])) ?></strong><span>SM-<?= str_pad((string)$inactive['user_id'],6,'0',STR_PAD_LEFT) ?></span></div></div></td>
                    <td><span><?= htmlspecialchars($inactive['email']) ?></span><small><?= htmlspecialchars($inactive['mobile']) ?></small></td>
                    <td><span class="status-pill <?= strtolower($inactive['verification_status'] ?? 'pending') ?>"><?= htmlspecialchars($inactive['verification_status'] ?? 'Pending') ?></span></td>
                    <td><?= htmlspecialchars(date('d M Y', strtotime($inactive['created_at']))) ?></td>
                    <td><form method="post" onsubmit="return confirm('Delete this inactive member account and its related data permanently?');"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="delete_inactive"><input type="hidden" name="user_id" value="<?= (int)$inactive['user_id'] ?>"><button type="submit" class="delete-btn"><i class="fa-solid fa-trash"></i> Delete</button></form></td></tr>
                <?php endwhile; ?></tbody></table>
            </div>
        <?php endif; ?>
    </section>

    <section class="history-card">
        <div class="section-heading"><div><span class="section-label">HISTORY</span><h2>My Verification History</h2></div><span class="result-count">Latest 50 actions</span></div>
        <?php if (!$history || mysqli_num_rows($history) === 0): ?>
            <div class="empty-state compact-empty"><i class="fa-solid fa-clock-rotate-left"></i><h3>No verification history</h3><p>Your verification actions will appear here.</p></div>
        <?php else: ?>
            <div class="history-scroll"><div class="history-list">
            <?php while ($log = mysqli_fetch_assoc($history)): ?>
                <div class="history-row"><span class="status-pill <?= strtolower($log['action']) ?>"><?= htmlspecialchars($log['action']) ?></span><div><strong><?= htmlspecialchars(trim($log['first_name'].' '.$log['last_name'])) ?> · SM-<?= str_pad((string)$log['user_id'],6,'0',STR_PAD_LEFT) ?></strong><p><?= $log['remarks'] ? nl2br(htmlspecialchars($log['remarks'])) : 'No remarks' ?></p></div><div class="history-meta"><time><?= htmlspecialchars(date('d M Y, h:i A', strtotime($log['action_at']))) ?></time><form method="post" onsubmit="return confirm('Delete this verification history entry?');"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="delete_history"><input type="hidden" name="log_id" value="<?= (int)$log['log_id'] ?>"><button type="submit" class="history-delete" title="Delete history"><i class="fa-solid fa-trash"></i></button></form></div></div>
            <?php endwhile; ?></div></div>
        <?php endif; ?>
    </section>
</main>
<script>
(function () {
    const modal = document.getElementById('message-modal');
    const bulkModal = document.getElementById('bulk-message-modal');
    const selectAll = document.getElementById('select-all-members');
    const memberChecks = Array.from(document.querySelectorAll('.member-select:not(:disabled)'));
    const selectedCount = document.getElementById('selected-count');
    const bulkButton = document.getElementById('open-bulk-message');
    const bulkList = document.getElementById('bulk-recipient-list');
    const bulkText = document.getElementById('bulk-message-text');

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (char) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[char];
        });
    }
    function openMessageModal(button) {
        if (!modal) return;
        const userIdInput = document.getElementById('message-user-id'), messageText = document.getElementById('message-text'), recipient = document.getElementById('message-modal-recipient');
        userIdInput.value = button.dataset.userId || '';
        recipient.innerHTML = 'Send a private message to <strong>' + escapeHtml(button.dataset.memberName || 'Selected member') + '</strong> · ' + escapeHtml(button.dataset.memberCode || '');
        messageText.value = '';
        modal.classList.add('is-open'); modal.setAttribute('aria-hidden','false'); document.body.classList.add('message-modal-open');
        window.setTimeout(() => messageText.focus(), 80);
    }
    function closeMessageModal() {
        if (!modal) return;
        modal.classList.remove('is-open'); modal.setAttribute('aria-hidden','true'); document.body.classList.remove('message-modal-open');
        document.getElementById('message-user-id').value = ''; document.getElementById('message-text').value = '';
    }
    function getSelected() { return memberChecks.filter(check => check.checked); }
    function updateSelectionUI() {
        const selected = getSelected(), count = selected.length;
        if (selectedCount) selectedCount.textContent = count + ' selected';
        if (bulkButton) bulkButton.disabled = count === 0;
        if (selectAll) { selectAll.disabled = memberChecks.length === 0; selectAll.checked = memberChecks.length > 0 && count === memberChecks.length; selectAll.indeterminate = count > 0 && count < memberChecks.length; }
    }
    function openBulkMessageModal() {
        const selected = getSelected();
        if (!bulkModal || !selected.length) return;
        bulkList.innerHTML = '';
        selected.forEach(check => {
            const pill = document.createElement('span'); pill.textContent = (check.dataset.memberName || 'Member') + ' · ' + (check.dataset.memberCode || ''); bulkList.appendChild(pill);
            const hidden = document.createElement('input'); hidden.type='hidden'; hidden.name='user_ids[]'; hidden.value=check.value; bulkList.appendChild(hidden);
        });
        bulkText.value=''; bulkModal.classList.add('is-open'); bulkModal.setAttribute('aria-hidden','false'); document.body.classList.add('message-modal-open');
        window.setTimeout(() => bulkText.focus(), 80);
    }
    function closeBulkMessageModal() {
        if (!bulkModal) return;
        bulkModal.classList.remove('is-open'); bulkModal.setAttribute('aria-hidden','true'); document.body.classList.remove('message-modal-open'); bulkList.innerHTML=''; bulkText.value='';
    }
    document.querySelectorAll('[data-message-open]').forEach(button => button.addEventListener('click', () => openMessageModal(button)));
    if (modal) modal.querySelectorAll('[data-message-close]').forEach(element => element.addEventListener('click', closeMessageModal));
    memberChecks.forEach(check => check.addEventListener('change', updateSelectionUI));
    if (selectAll) selectAll.addEventListener('change', () => { memberChecks.forEach(check => check.checked = selectAll.checked); updateSelectionUI(); });
    if (bulkButton) bulkButton.addEventListener('click', openBulkMessageModal);
    if (bulkModal) bulkModal.querySelectorAll('[data-bulk-message-close]').forEach(element => element.addEventListener('click', closeBulkMessageModal));
    document.addEventListener('keydown', event => { if (event.key === 'Escape') { if (modal && modal.classList.contains('is-open')) closeMessageModal(); if (bulkModal && bulkModal.classList.contains('is-open')) closeBulkMessageModal(); } });
    updateSelectionUI();
    <?php if ($message_recipient): ?>if (modal) { document.body.classList.add('message-modal-open'); window.setTimeout(() => document.getElementById('message-text').focus(), 80); }<?php endif; ?>
    <?php if ($action_error && $bulk_selected_ids && $bulk_recipients): ?>if (bulkModal) { bulkList.innerHTML=''; <?php foreach ($bulk_recipients as $recipient): ?>{ const pill=document.createElement('span'); pill.textContent=<?= json_encode($recipient['member_name'].' · SM-'.str_pad((string)$recipient['user_id'],6,'0',STR_PAD_LEFT)) ?>; bulkList.appendChild(pill); const hidden=document.createElement('input'); hidden.type='hidden'; hidden.name='user_ids[]'; hidden.value='<?= (int)$recipient['user_id'] ?>'; bulkList.appendChild(hidden); }<?php endforeach; ?> document.body.classList.add('message-modal-open'); window.setTimeout(() => bulkText.focus(), 80); }<?php endif; ?>
})();
</script>
</body>
</html>
