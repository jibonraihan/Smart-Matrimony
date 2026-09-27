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
    header('Location: ../login.php');
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
$action_notice = trim((string) ($_GET['success'] ?? ''));
$action_error = '';
$auth_profile = [
    'first_name' => (string) ($auth['first_name'] ?? ''),
    'last_name' => (string) ($auth['last_name'] ?? ''),
    'email' => '',
    'gender' => '',
    'mobile' => ''
];
$auth_profile_stmt = mysqli_prepare($conn, "SELECT first_name, last_name, email, gender, mobile FROM users WHERE user_id=? AND role='Authenticator' LIMIT 1");
if ($auth_profile_stmt) {
    mysqli_stmt_bind_param($auth_profile_stmt, 'i', $authenticator_id);
    mysqli_stmt_execute($auth_profile_stmt);
    $auth_profile = array_merge($auth_profile, mysqli_fetch_assoc(mysqli_stmt_get_result($auth_profile_stmt)) ?: []);
    mysqli_stmt_close($auth_profile_stmt);
}

$auth_profile_image = '';
$auth_profile_stmt = mysqli_prepare($conn, 'SELECT profile_image FROM staff_profiles WHERE user_id=? LIMIT 1');
if ($auth_profile_stmt) {
    mysqli_stmt_bind_param($auth_profile_stmt, 'i', $authenticator_id);
    mysqli_stmt_execute($auth_profile_stmt);
    $auth_profile_row = mysqli_fetch_assoc(mysqli_stmt_get_result($auth_profile_stmt));
    mysqli_stmt_close($auth_profile_stmt);
    $auth_profile_image = trim((string) ($auth_profile_row['profile_image'] ?? ''));
}
$auth_profile_image_exists = $auth_profile_image !== '' && is_file(dirname(__DIR__) . '/uploads/staff/' . basename($auth_profile_image));
$auth_first_name = trim((string) ($auth_profile['first_name'] ?? '')) ?: 'Authenticator';
$auth_last_name = trim((string) ($auth_profile['last_name'] ?? ''));
$auth_full_name = trim($auth_first_name . ' ' . $auth_last_name);
$auth_public_id = 'SM-' . str_pad((string) $authenticator_id, 6, '0', STR_PAD_LEFT);
$auth_email = (string) ($auth_profile['email'] ?? '');
$auth_gender = (string) ($auth_profile['gender'] ?? '');
$auth_mobile = (string) ($auth_profile['mobile'] ?? '');

$admin_messages = [];
$admin_message_unread = 0;
$admin_messages_ready = true;
$admin_messages_table_check = @mysqli_query($conn, "SHOW TABLES LIKE 'staff_admin_messages'");
if (!$admin_messages_table_check || mysqli_num_rows($admin_messages_table_check) === 0) {
    $admin_messages_ready = false;
} else {
    $admin_msg_stmt = mysqli_prepare($conn, "SELECT message_id, thread_id, message, status, created_at
        FROM staff_admin_messages
        WHERE recipient_id=? AND recipient_role='Authenticator' AND sender_role='Admin'
        ORDER BY created_at DESC, message_id DESC
        LIMIT 30");
    if ($admin_msg_stmt) {
        mysqli_stmt_bind_param($admin_msg_stmt, 'i', $authenticator_id);
        mysqli_stmt_execute($admin_msg_stmt);
        $admin_msg_result = mysqli_stmt_get_result($admin_msg_stmt);
        while ($admin_msg = mysqli_fetch_assoc($admin_msg_result)) {
            $admin_messages[] = $admin_msg;
            if (($admin_msg['status'] ?? '') === 'Unread') $admin_message_unread++;
        }
        mysqli_stmt_close($admin_msg_stmt);
    }
}
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

        if ($action === 'send_admin_message') {
            $message_text = trim((string) ($_POST['message'] ?? ''));
            if ($message_text === '') {
                $action_error = 'Please write a message before sending.';
            } elseif (mb_strlen($message_text) > 2000) {
                $action_error = 'Message must be 2000 characters or less.';
            } else {
                $admin_check = mysqli_query($conn, "SELECT user_id FROM users WHERE role='Admin' AND account_status='Active' ORDER BY user_id ASC LIMIT 1");
                if (!$admin_check || mysqli_num_rows($admin_check) === 0) {
                    $action_error = 'No active Admin is available right now.';
                } else {
                    $thread_id = bin2hex(random_bytes(16));
                    $sender_role = 'Authenticator';
                    $recipient_role = 'Admin';
                    $recipient_id = null;
                    $send_stmt = mysqli_prepare($conn, "INSERT INTO staff_admin_messages (thread_id, sender_id, sender_role, recipient_id, recipient_role, message, status) VALUES (?, ?, ?, ?, ?, ?, 'Unread')");
                    if (!$send_stmt) {
                        $action_error = 'The messaging system is not available yet. Please apply the messaging database migration first.';
                    } else {
                        mysqli_stmt_bind_param($send_stmt, 'sisiss', $thread_id, $authenticator_id, $sender_role, $recipient_id, $recipient_role, $message_text);
                        if (mysqli_stmt_execute($send_stmt)) {
                            mysqli_stmt_close($send_stmt);
                            header('Location: dashboard.php?success=' . urlencode('Message sent to Admin successfully.') . '#auth-hero');
                            exit;
                        } else {
                            $action_error = 'Unable to send the message. Please try again.';
                            mysqli_stmt_close($send_stmt);
                        }
                    }
                }
            }
        } elseif ($action === 'mark_admin_message_read') {
            $message_id = (int) ($_POST['message_id'] ?? 0);
            $is_ajax_request = (($_POST['ajax'] ?? '') === '1');
            if ($message_id <= 0) {
                $action_error = 'Invalid message.';
            } else {
                $read_stmt = mysqli_prepare($conn, "UPDATE staff_admin_messages SET status='Read', read_at=CURRENT_TIMESTAMP WHERE message_id=? AND recipient_id=? AND recipient_role='Authenticator' AND sender_role='Admin'");
                if (!$read_stmt) {
                    $action_error = 'The messaging system is not available yet. Please apply the messaging database migration first.';
                } else {
                    mysqli_stmt_bind_param($read_stmt, 'ii', $message_id, $authenticator_id);
                    mysqli_stmt_execute($read_stmt);
                    $read_updated = mysqli_stmt_affected_rows($read_stmt);
                    mysqli_stmt_close($read_stmt);
                    if ($is_ajax_request) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['ok' => true, 'updated' => $read_updated]);
                        exit;
                    }
                }
            }
        } elseif ($action === 'update_authenticator_photo') {
            if (empty($_FILES['profile_image']) || ($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                $action_error = 'Please choose an image first.';
            } else {
                $file = $_FILES['profile_image'];
                $upload_error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
                if ($upload_error !== UPLOAD_ERR_OK) {
                    $action_error = 'The image upload failed. Please try again.';
                } elseif ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
                    $action_error = 'Profile image must be 5 MB or less.';
                } else {
                    $info = @getimagesize($file['tmp_name']);
                    $mime = (string) ($info['mime'] ?? '');
                    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                    if (!$info || !isset($allowed[$mime])) {
                        $action_error = 'Only JPG, PNG or WebP images are allowed.';
                    } else {
                        $staff_upload_dir = dirname(__DIR__) . '/uploads/staff/';
                        if (!is_dir($staff_upload_dir) && !@mkdir($staff_upload_dir, 0755, true)) {
                            $action_error = 'The staff image folder could not be created.';
                        } else {
                            $new_name = 'STAFF_' . $authenticator_id . '_' . bin2hex(random_bytes(10)) . '.' . $allowed[$mime];
                            $destination = $staff_upload_dir . $new_name;
                            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                                $action_error = 'The image could not be saved. Please try again.';
                            } else {
                                $old_name = '';
                                $find = mysqli_prepare($conn, 'SELECT profile_image FROM staff_profiles WHERE user_id=? LIMIT 1');
                                if ($find) {
                                    mysqli_stmt_bind_param($find, 'i', $authenticator_id);
                                    mysqli_stmt_execute($find);
                                    $old_row = mysqli_fetch_assoc(mysqli_stmt_get_result($find));
                                    mysqli_stmt_close($find);
                                    $old_name = trim((string) ($old_row['profile_image'] ?? ''));
                                }
                                $save = mysqli_prepare($conn, 'INSERT INTO staff_profiles (user_id, profile_image) VALUES (?, ?) ON DUPLICATE KEY UPDATE profile_image=VALUES(profile_image), updated_at=CURRENT_TIMESTAMP');
                                if (!$save) {
                                    @unlink($destination);
                                    $action_error = 'The shared staff profile storage is not available. Please apply the staff profile database migration first.';
                                } else {
                                    mysqli_stmt_bind_param($save, 'is', $authenticator_id, $new_name);
                                    if (!mysqli_stmt_execute($save)) {
                                        @unlink($destination);
                                        $action_error = 'Unable to save the Authenticator profile image.';
                                    }
                                    mysqli_stmt_close($save);
                                    if ($action_error === '') {
                                        if ($old_name !== '') {
                                            $old_path = $staff_upload_dir . basename($old_name);
                                            if (is_file($old_path)) @unlink($old_path);
                                        }
                                        header('Location: dashboard.php?success=' . urlencode('Profile image updated successfully.') . '#auth-hero');
                                        exit;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } elseif ($action === 'claim_review' && $target_id > 0) {
            mysqli_begin_transaction($conn);
            try {
                $claim_check = mysqli_prepare($conn, "SELECT up.profile_id, up.verification_status, u.account_status FROM user_profiles up INNER JOIN users u ON u.user_id=up.user_id WHERE up.profile_id=? AND u.role='User' LIMIT 1 FOR UPDATE");
                mysqli_stmt_bind_param($claim_check, 'i', $target_id);
                mysqli_stmt_execute($claim_check);
                $claim_target = mysqli_fetch_assoc(mysqli_stmt_get_result($claim_check));
                mysqli_stmt_close($claim_check);
                if (!$claim_target || $claim_target['account_status'] !== 'Active') throw new Exception('This profile is no longer available for review.');
                if (!in_array($claim_target['verification_status'], ['Pending','Rejected'], true)) throw new Exception('Only pending or previously rejected profiles can be claimed for review.');

                $existing_claim = null;
                $claim_lookup = mysqli_prepare($conn, "SELECT claim_id, authenticator_id, status FROM authenticator_profile_claims WHERE profile_id=? LIMIT 1 FOR UPDATE");
                mysqli_stmt_bind_param($claim_lookup, 'i', $target_id);
                mysqli_stmt_execute($claim_lookup);
                $existing_claim = mysqli_fetch_assoc(mysqli_stmt_get_result($claim_lookup)) ?: null;
                mysqli_stmt_close($claim_lookup);

                if ($existing_claim && $existing_claim['status'] === 'Active' && (int)$existing_claim['authenticator_id'] !== $authenticator_id) {
                    throw new Exception('This profile is currently being reviewed by another Authenticator.');
                }
                if ($existing_claim && $existing_claim['status'] === 'Active') {
                    mysqli_commit($conn);
                    $action_notice = 'This profile is already assigned to you.';
                } elseif ($existing_claim) {
                    $claim_update = mysqli_prepare($conn, "UPDATE authenticator_profile_claims SET authenticator_id=?, status='Active', claimed_at=CURRENT_TIMESTAMP, released_at=NULL, completed_at=NULL WHERE claim_id=?");
                    mysqli_stmt_bind_param($claim_update, 'ii', $authenticator_id, $existing_claim['claim_id']);
                    if (!mysqli_stmt_execute($claim_update)) throw new Exception('Could not claim this profile.');
                    mysqli_stmt_close($claim_update);
                    mysqli_commit($conn);
                    $action_notice = 'Profile claimed successfully. You can now review it.';
                } else {
                    $claim_insert = mysqli_prepare($conn, "INSERT INTO authenticator_profile_claims (profile_id, authenticator_id, status) VALUES (?, ?, 'Active')");
                    mysqli_stmt_bind_param($claim_insert, 'ii', $target_id, $authenticator_id);
                    if (!mysqli_stmt_execute($claim_insert)) throw new Exception('Could not claim this profile. It may have just been claimed by another Authenticator.');
                    mysqli_stmt_close($claim_insert);
                    mysqli_commit($conn);
                    $action_notice = 'Profile claimed successfully. You can now review it.';
                }
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                $action_error = $e->getMessage();
            }
        } elseif ($action === 'release_review' && $target_id > 0) {
            $release = mysqli_prepare($conn, "UPDATE authenticator_profile_claims SET status='Released', released_at=CURRENT_TIMESTAMP WHERE profile_id=? AND authenticator_id=? AND status='Active' LIMIT 1");
            mysqli_stmt_bind_param($release, 'ii', $target_id, $authenticator_id);
            mysqli_stmt_execute($release);
            $released = mysqli_stmt_affected_rows($release);
            mysqli_stmt_close($release);
            $released ? $action_notice = 'Review claim released. The profile is available to other Authenticators.' : $action_error = 'This profile is not currently claimed by you.';
        } elseif ($action === 'send_bulk_message') {
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

$assignment = $_GET['assignment'] ?? 'All';
$allowed_assignments = ['All', 'Available', 'My Reviews', 'Other'];
if (!in_array($assignment, $allowed_assignments, true)) {
    $assignment = 'All';
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
$available_count_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM user_profiles up INNER JOIN users u ON u.user_id=up.user_id LEFT JOIN authenticator_profile_claims c ON c.profile_id=up.profile_id WHERE u.role='User' AND u.account_status='Active' AND up.verification_status IN ('Pending','Rejected') AND (c.claim_id IS NULL OR c.status <> 'Active')");
$available_count_row = $available_count_result ? mysqli_fetch_assoc($available_count_result) : null;
$available_count = (int)($available_count_row['total'] ?? 0);
$my_review_count_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM authenticator_profile_claims c INNER JOIN user_profiles up ON up.profile_id=c.profile_id INNER JOIN users u ON u.user_id=up.user_id WHERE c.authenticator_id={$authenticator_id} AND c.status='Active' AND u.role='User' AND u.account_status='Active'");
$my_review_count_row = $my_review_count_result ? mysqli_fetch_assoc($my_review_count_result) : null;
$my_review_count = (int)($my_review_count_row['total'] ?? 0);
$active_count_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role='User' AND account_status='Active'");
$active_count_row = $active_count_result ? mysqli_fetch_assoc($active_count_result) : null;
$active_count = (int)($active_count_row['total'] ?? 0);

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

if ($assignment === 'Available') {
    $where[] = "(up.verification_status IN ('Pending','Rejected') AND (c.claim_id IS NULL OR c.status <> 'Active'))";
} elseif ($assignment === 'My Reviews') {
    $where[] = "c.status='Active' AND c.authenticator_id=?";
    $params[] = $authenticator_id;
    $types .= 'i';
} elseif ($assignment === 'Other') {
    $where[] = "c.status='Active' AND c.authenticator_id<>?";
    $params[] = $authenticator_id;
    $types .= 'i';
}

$where_sql = implode(' AND ', $where);
$list_sql = "SELECT up.profile_id, up.user_id, up.first_name, up.last_name, up.gender, up.date_of_birth, up.religion, up.photo, up.verification_status, up.updated_at, u.email, u.mobile, u.account_status,
                    c.status AS claim_status, c.authenticator_id AS claim_authenticator_id,
                    ca.first_name AS claim_first_name, ca.last_name AS claim_last_name, c.claimed_at
             FROM user_profiles up INNER JOIN users u ON u.user_id=up.user_id
             LEFT JOIN authenticator_profile_claims c ON c.profile_id=up.profile_id
             LEFT JOIN users ca ON ca.user_id=c.authenticator_id
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

$history_q = trim($_GET['history_q'] ?? '');
$history_action = $_GET['history_action'] ?? 'All';
$allowed_history_actions = ['All', 'Verified', 'Rejected'];
if (!in_array($history_action, $allowed_history_actions, true)) {
    $history_action = 'All';
}
$history_from = trim($_GET['history_from'] ?? '');
$history_to = trim($_GET['history_to'] ?? '');
if ($history_from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $history_from)) $history_from = '';
if ($history_to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $history_to)) $history_to = '';
if ($history_from !== '' && $history_to !== '' && $history_from > $history_to) {
    [$history_from, $history_to] = [$history_to, $history_from];
}
$history_page = max(1, (int)($_GET['history_page'] ?? 1));
$history_per_page = 10;
$history_where = "WHERE vl.authenticator_id=?";
$history_types = 'i';
$history_params = [$authenticator_id];
if ($history_q !== '') {
    $history_where .= " AND (up.first_name LIKE ? OR up.last_name LIKE ? OR u.email LIKE ? OR CAST(u.user_id AS CHAR) LIKE ? OR CAST(up.profile_id AS CHAR) LIKE ? OR CONCAT('SM-', LPAD(u.user_id,6,'0')) LIKE ?)";
    $history_types .= 'ssssss';
    $history_like = '%' . $history_q . '%';
    array_push($history_params, $history_like, $history_like, $history_like, $history_like, $history_like, $history_like);
}
if ($history_action !== 'All') {
    $history_where .= " AND vl.action=?";
    $history_types .= 's';
    $history_params[] = $history_action;
}
if ($history_from !== '') {
    $history_where .= " AND vl.action_at >= ?";
    $history_types .= 's';
    $history_params[] = $history_from . ' 00:00:00';
}
if ($history_to !== '') {
    $history_where .= " AND vl.action_at <= ?";
    $history_types .= 's';
    $history_params[] = $history_to . ' 23:59:59';
}

function bind_dynamic_params(mysqli_stmt $stmt, string $types, array &$params): void {
    $refs = [$types];
    foreach ($params as $key => &$value) $refs[] = &$value;
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

$history_count_sql = "SELECT COUNT(*) AS total FROM verification_logs vl INNER JOIN user_profiles up ON up.profile_id=vl.profile_id INNER JOIN users u ON u.user_id=up.user_id $history_where";
$history_count_stmt = mysqli_prepare($conn, $history_count_sql);
bind_dynamic_params($history_count_stmt, $history_types, $history_params);
mysqli_stmt_execute($history_count_stmt);
$history_count_row = mysqli_fetch_assoc(mysqli_stmt_get_result($history_count_stmt));
mysqli_stmt_close($history_count_stmt);
$history_total = (int)($history_count_row['total'] ?? 0);
$history_pages = max(1, (int)ceil($history_total / $history_per_page));
if ($history_page > $history_pages) $history_page = $history_pages;
$history_offset = ($history_page - 1) * $history_per_page;

$history_sql = "SELECT vl.log_id, vl.action, vl.remarks, vl.action_at, up.profile_id, up.first_name, up.last_name, u.user_id FROM verification_logs vl INNER JOIN user_profiles up ON up.profile_id=vl.profile_id INNER JOIN users u ON u.user_id=up.user_id $history_where ORDER BY vl.action_at DESC, vl.log_id DESC LIMIT ? OFFSET ?";
$history_stmt = mysqli_prepare($conn, $history_sql);
$history_types_with_page = $history_types . 'ii';
$history_params_with_page = $history_params;
$history_params_with_page[] = $history_per_page;
$history_params_with_page[] = $history_offset;
bind_dynamic_params($history_stmt, $history_types_with_page, $history_params_with_page);
mysqli_stmt_execute($history_stmt);
$history = mysqli_stmt_get_result($history_stmt);

function time_ago(string $datetime): string {
    $timestamp = strtotime($datetime);
    if (!$timestamp) return 'Unknown';
    $diff = max(0, time() - $timestamp);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr' . (floor($diff / 3600) === 1 ? '' : 's') . ' ago';
    if ($diff < 604800) return floor($diff / 86400) . ' day' . (floor($diff / 86400) === 1 ? '' : 's') . ' ago';
    return date('d M Y', $timestamp);
}

function qs(array $extra = []): string {
    $base = [
        'q' => $_GET['q'] ?? '',
        'status' => $_GET['status'] ?? 'Pending',
        'gender' => $_GET['gender'] ?? 'All',
        'completion_sort' => $_GET['completion_sort'] ?? 'none',
        'assignment' => $_GET['assignment'] ?? 'All'
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
    <section class="auth-profile-hero" id="auth-hero">
        <button type="button" class="auth-hero-photo" data-auth-photo-open aria-label="Change Authenticator profile image">
            <?php if ($auth_profile_image_exists): ?>
                <img src="../uploads/staff/<?= htmlspecialchars(basename($auth_profile_image)); ?>" alt="Profile image of <?= htmlspecialchars($auth_full_name); ?>">
            <?php else: ?>
                <span class="auth-hero-photo-fallback"><?= htmlspecialchars(strtoupper(substr($auth_first_name, 0, 1) . substr($auth_last_name, 0, 1))); ?></span>
            <?php endif; ?>
            <span class="auth-hero-photo-edit"><i class="fa-solid fa-camera"></i></span>
        </button>
        <div class="auth-hero-copy">
            <span class="auth-hero-kicker">AUTHENTICATOR PROFILE</span>
            <h1>Welcome, <?= htmlspecialchars($auth_first_name); ?>.</h1>
            <p class="auth-hero-lead">Your profile verification workspace is ready. Here is your account identity and verification snapshot at a glance.</p>
            <div class="auth-hero-meta">
                <span><i>ID</i><strong><?= htmlspecialchars($auth_public_id); ?></strong></span>
                <span><i>@</i><?= htmlspecialchars($auth_email); ?></span>
                <span><i>G</i><?= htmlspecialchars($auth_gender); ?></span>
                <span><i>☎</i><?= htmlspecialchars($auth_mobile); ?></span>
            </div>
            <div class="auth-hero-message-actions" aria-label="Admin messaging">
                <button type="button" class="auth-hero-message-btn" data-auth-message-open><i class="fa-solid fa-paper-plane"></i> Message to Admin</button>
                <button type="button" class="auth-hero-message-btn inbox" data-auth-inbox-open><i class="fa-solid fa-inbox"></i> Message from Admin<?php if ($admin_message_unread > 0): ?> <span class="auth-message-badge"><?= $admin_message_unread > 99 ? '99+' : $admin_message_unread; ?></span><?php endif; ?></button>
            </div>
        </div>
    </section>

    <?php if ($action_notice): ?><div class="flash success"><i class="fa-solid fa-circle-check"></i><?= htmlspecialchars($action_notice) ?></div><?php endif; ?>
    <?php if ($action_error): ?><div class="flash error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($action_error) ?></div><?php endif; ?>

    <section class="auth-dashboard-stats" aria-label="Authenticator statistics">
        <article class="auth-dashboard-stat-card"><span class="auth-stat-icon"><i class="fa-solid fa-hourglass-half"></i></span><div><strong><?= number_format($pending_count) ?></strong><small>Pending Profiles</small><em>Need review</em></div></article>
        <article class="auth-dashboard-stat-card"><span class="auth-stat-icon success"><i class="fa-solid fa-circle-check"></i></span><div><strong><?= number_format($verified_count) ?></strong><small>Verified Profiles</small><em>Approved</em></div></article>
        <article class="auth-dashboard-stat-card"><span class="auth-stat-icon danger"><i class="fa-solid fa-circle-xmark"></i></span><div><strong><?= number_format($rejected_count) ?></strong><small>Rejected Profiles</small><em>Needs attention</em></div></article>
        <article class="auth-dashboard-stat-card"><span class="auth-stat-icon info"><i class="fa-solid fa-users"></i></span><div><strong><?= number_format($total_profiles) ?></strong><small>Total Profiles</small><em>Member profiles</em></div></article>
        <article class="auth-dashboard-stat-card"><span class="auth-stat-icon muted"><i class="fa-solid fa-user-check"></i></span><div><strong><?= number_format($active_count) ?></strong><small>Active Accounts</small><em>Currently active</em></div></article>
        <article class="auth-dashboard-stat-card"><span class="auth-stat-icon info"><i class="fa-solid fa-inbox"></i></span><div><strong><?= number_format($available_count) ?></strong><small>Available Reviews</small><em>Unclaimed profiles</em></div></article>
        <article class="auth-dashboard-stat-card"><span class="auth-stat-icon success"><i class="fa-solid fa-user-shield"></i></span><div><strong><?= number_format($my_review_count) ?></strong><small>My Active Reviews</small><em>Claimed by you</em></div></article>
        <article class="auth-dashboard-stat-card"><span class="auth-stat-icon message"><i class="fa-solid fa-envelope"></i></span><div><strong><?= number_format($admin_message_unread) ?></strong><small>Admin Messages</small><em>Unread messages</em></div></article>
    </section>

    <section class="auth-quick-actions" aria-label="Quick verification actions">
        <div class="auth-quick-actions-copy">
            <span class="section-label">QUICK ACTIONS</span>
            <strong>Jump back into your verification work</strong>
        </div>
        <div class="auth-quick-actions-links">
            <a href="dashboard.php?status=Pending#member-profiles" class="auth-quick-action primary"><i class="fa-solid fa-hourglass-half"></i><span>Review Pending</span><b><?= number_format($pending_count) ?></b></a>
            <a href="dashboard.php?assignment=My+Reviews#member-profiles" class="auth-quick-action"><i class="fa-solid fa-user-check"></i><span>My Reviews</span><b><?= number_format($my_review_count) ?></b></a>
            <button type="button" class="auth-quick-action" data-auth-inbox-open><i class="fa-solid fa-inbox"></i><span>Admin Messages</span><b><?= number_format($admin_message_unread) ?></b></button>
        </div>
    </section>

    <div class="auth-staff-message-modal" id="authStaffMessageModal" aria-hidden="true">
        <div class="auth-staff-message-backdrop" data-auth-message-close></div>
        <div class="auth-staff-message-card" role="dialog" aria-modal="true" aria-labelledby="authStaffMessageTitle">
            <button type="button" class="auth-staff-message-close" data-auth-message-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            <span class="auth-hero-kicker">ADMIN COMMUNICATION</span>
            <h2 id="authStaffMessageTitle">Message to Admin</h2>
            <p>Send a private message to the active Admin team.</p>
            <?php if (!$admin_messages_ready): ?><div class="flash error"><i class="fa-solid fa-database"></i>Messaging database setup is required before you can send messages.</div><?php endif; ?>
            <form method="post" action="dashboard.php#auth-hero" class="auth-staff-message-form">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
                <input type="hidden" name="action" value="send_admin_message">
                <label for="authAdminMessage">Your message</label>
                <textarea id="authAdminMessage" name="message" rows="6" maxlength="2000" required placeholder="Write your message to Admin..."></textarea>
                <small>Maximum 2000 characters.</small>
                <div class="auth-staff-message-actions"><button type="button" class="auth-message-cancel" data-auth-message-close>Cancel</button><button type="submit" class="auth-message-send" <?= !$admin_messages_ready ? 'disabled' : ''; ?>><i class="fa-solid fa-paper-plane"></i> Send Message</button></div>
            </form>
        </div>
    </div>

    <div class="auth-staff-message-modal" id="authStaffInboxModal" aria-hidden="true">
        <div class="auth-staff-message-backdrop" data-auth-inbox-close></div>
        <div class="auth-staff-message-card auth-staff-inbox-card" role="dialog" aria-modal="true" aria-labelledby="authStaffInboxTitle">
            <button type="button" class="auth-staff-message-close" data-auth-inbox-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            <span class="auth-hero-kicker">ADMIN COMMUNICATION</span>
            <h2 id="authStaffInboxTitle">Messages from Admin</h2>
            <p>Messages sent to you by an Admin appear here.</p>
            <div class="auth-staff-inbox-list">
                <?php if (!$admin_messages_ready): ?>
                    <div class="auth-staff-inbox-empty"><i class="fa-solid fa-database"></i><strong>Messaging database is not ready</strong><span>Apply the provided messaging migration first.</span></div>
                <?php elseif (!$admin_messages): ?>
                    <div class="auth-staff-inbox-empty"><i class="fa-regular fa-envelope-open"></i><strong>No messages yet</strong><span>When an Admin sends you a message, it will appear here.</span></div>
                <?php else: ?>
                    <?php foreach ($admin_messages as $admin_msg): ?>
                        <article class="auth-staff-inbox-item<?= ($admin_msg['status'] ?? '') === 'Unread' ? ' is-unread' : ''; ?>" data-auth-message-id="<?= (int)$admin_msg['message_id']; ?>">
                            <div class="auth-staff-inbox-top"><span><i class="fa-solid fa-user-shield"></i> Admin</span><?php if (($admin_msg['status'] ?? '') === 'Unread'): ?><b>NEW</b><?php endif; ?></div>
                            <p><?= nl2br(htmlspecialchars($admin_msg['message'])); ?></p>
                            <div class="auth-staff-inbox-bottom"><time><?= htmlspecialchars(date('d M Y · h:i A', strtotime($admin_msg['created_at']))); ?></time><?php if (($admin_msg['status'] ?? '') === 'Unread'): ?><button type="button" class="auth-message-read-btn" data-auth-message-read="<?= (int)$admin_msg['message_id']; ?>">Mark as read</button><?php else: ?><span>Read</span><?php endif; ?></div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="auth-photo-modal" id="authPhotoModal" aria-hidden="true">
        <div class="auth-photo-backdrop" data-auth-photo-close></div>
        <div class="auth-photo-card" role="dialog" aria-modal="true" aria-labelledby="authPhotoTitle">
            <button type="button" class="auth-photo-close" data-auth-photo-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            <span class="auth-hero-kicker">AUTHENTICATOR PROFILE</span>
            <h2 id="authPhotoTitle">Update profile image</h2>
            <p>Choose a clear profile image for your Authenticator hero. This image is stored in the shared staff profile.</p>
            <?php if ($auth_profile_image_exists): ?>
                <div class="auth-photo-preview" id="authPhotoPreview"><img src="../uploads/staff/<?= htmlspecialchars(basename($auth_profile_image)); ?>" alt="Current Authenticator profile image"></div>
            <?php else: ?>
                <div class="auth-photo-preview auth-photo-preview-fallback" id="authPhotoPreview"><?= htmlspecialchars(strtoupper(substr($auth_first_name, 0, 1) . substr($auth_last_name, 0, 1))); ?></div>
            <?php endif; ?>
            <form method="post" action="dashboard.php#auth-hero" enctype="multipart/form-data" class="auth-photo-form">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
                <input type="hidden" name="action" value="update_authenticator_photo">
                <label for="authProfileImage">Profile image</label>
                <input id="authProfileImage" name="profile_image" type="file" accept="image/jpeg,image/png,image/webp" required>
                <small>JPG, PNG or WebP · maximum 5 MB.</small>
                <div class="auth-photo-actions"><button type="button" class="auth-message-cancel" data-auth-photo-close>Cancel</button><button type="submit" class="auth-message-send"><i class="fa-solid fa-cloud-arrow-up"></i> Save image</button></div>
            </form>
        </div>
    </div>

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
            <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search member ID, name, email or mobile">
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
            <select name="assignment" aria-label="Review assignment">
                <?php foreach ($allowed_assignments as $item): ?>
                    <option value="<?= htmlspecialchars($item) ?>" <?= $assignment === $item ? 'selected' : '' ?>><?= htmlspecialchars($item) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
            <?php if ($search !== '' || $status !== 'Pending' || $gender !== 'All' || $completion_sort !== 'none' || $assignment !== 'All'): ?><a class="clear-btn" href="dashboard.php#member-profiles">Clear</a><?php endif; ?>
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
                            </th><th>Profile Status</th><th>Account</th><th>Assignment</th><th>Updated</th><th>Action</th></tr></thead>
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
                            <td>
                                <?php if (($row['claim_status'] ?? '') === 'Active'): ?>
                                    <?php if ((int)$row['claim_authenticator_id'] === $authenticator_id): ?>
                                        <span class="assignment-pill mine"><i class="fa-solid fa-lock"></i> My Review</span>
                                    <?php else: ?>
                                        <span class="assignment-pill other" title="Claimed by <?= htmlspecialchars(trim(($row['claim_first_name'] ?? '').' '.($row['claim_last_name'] ?? ''))) ?>"><i class="fa-solid fa-lock"></i> In Review</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="assignment-pill available"><i class="fa-solid fa-circle-check"></i> Available</span>
                                <?php endif; ?>
                            </td>
                            <td title="<?= htmlspecialchars(date('d M Y, h:i A', strtotime($row['updated_at'])), ENT_QUOTES) ?>"><?= htmlspecialchars(time_ago($row['updated_at'])) ?></td>
                            <td><div class="profile-action-group">
                                <?php if (($row['claim_status'] ?? '') === 'Active' && (int)$row['claim_authenticator_id'] === $authenticator_id): ?>
                                    <a class="review-btn" href="verify_profile.php?id=<?= (int)$row['profile_id'] ?>"><i class="fa-solid fa-file-shield"></i> Review</a>
                                    <form method="post" class="inline-form" onsubmit="return confirm('Release this review claim so another Authenticator can take it?');"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="release_review"><input type="hidden" name="user_id" value="<?= (int)$row['profile_id'] ?>"><button type="submit" class="release-btn"><i class="fa-solid fa-unlock"></i> Release</button></form>
                                <?php elseif (($row['claim_status'] ?? '') === 'Active'): ?>
                                    <button type="button" class="locked-btn" disabled title="Currently being reviewed by another Authenticator"><i class="fa-solid fa-lock"></i> In Review</button>
                                <?php elseif (in_array($row['verification_status'], ['Pending','Rejected'], true) && $row['account_status'] === 'Active'): ?>
                                    <form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="claim_review"><input type="hidden" name="user_id" value="<?= (int)$row['profile_id'] ?>"><button type="submit" class="claim-btn"><i class="fa-solid fa-hand"></i> Take Review</button></form>
                                <?php endif; ?>
                                <button type="button" class="message-btn" data-message-open data-user-id="<?= (int)$row['user_id'] ?>" data-member-name="<?= htmlspecialchars(trim($row['first_name'].' '.$row['last_name']), ENT_QUOTES) ?>" data-member-code="SM-<?= str_pad((string)$row['user_id'], 6, '0', STR_PAD_LEFT) ?>"><i class="fa-solid fa-comment-dots"></i> Message</button></div></td>
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
        <div class="section-heading"><div><span class="section-label">HISTORY</span><h2>My Verification History</h2></div><span class="result-count"><?= number_format($history_total) ?> matching actions</span></div>
        <form method="get" class="history-filter-bar">
            <div class="history-filter-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="history_q" value="<?= htmlspecialchars($history_q) ?>" placeholder="Search name, SM ID, profile ID or email"></div>
            <select name="history_action" aria-label="Filter by action"><option value="All" <?= $history_action==='All'?'selected':'' ?>>All actions</option><option value="Verified" <?= $history_action==='Verified'?'selected':'' ?>>Verified</option><option value="Rejected" <?= $history_action==='Rejected'?'selected':'' ?>>Rejected</option></select>
            <label><span>From</span><input type="date" name="history_from" value="<?= htmlspecialchars($history_from) ?>"></label>
            <label><span>To</span><input type="date" name="history_to" value="<?= htmlspecialchars($history_to) ?>"></label>
            <button type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
            <?php if ($history_q !== '' || $history_action !== 'All' || $history_from !== '' || $history_to !== ''): ?><a class="history-clear" href="dashboard.php#history"><i class="fa-solid fa-rotate-left"></i> Clear</a><?php endif; ?>
        </form>
        <div id="history">
        <?php if (!$history || mysqli_num_rows($history) === 0): ?>
            <div class="empty-state compact-empty"><i class="fa-solid fa-clock-rotate-left"></i><h3>No matching verification history</h3><p>Try another search, action or date range.</p></div>
        <?php else: ?>
            <div class="history-scroll"><div class="history-list">
            <?php while ($log = mysqli_fetch_assoc($history)): ?>
                <div class="history-row"><span class="status-pill <?= strtolower($log['action']) ?>"><?= htmlspecialchars($log['action']) ?></span><div><strong><?= htmlspecialchars(trim($log['first_name'].' '.$log['last_name'])) ?> · SM-<?= str_pad((string)$log['user_id'],6,'0',STR_PAD_LEFT) ?></strong><p><?= $log['remarks'] ? nl2br(htmlspecialchars($log['remarks'])) : 'No remarks' ?></p></div><div class="history-meta"><time><?= htmlspecialchars(date('d M Y, h:i A', strtotime($log['action_at']))) ?></time><form method="post" onsubmit="return confirm('Delete this verification history entry?');"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="delete_history"><input type="hidden" name="log_id" value="<?= (int)$log['log_id'] ?>"><button type="submit" class="history-delete" title="Delete history"><i class="fa-solid fa-trash"></i></button></form></div></div>
            <?php endwhile; ?></div></div>
            <?php if ($history_pages > 1): ?>
                <div class="history-pagination">
                    <?php if ($history_page > 1): ?><a href="?<?= htmlspecialchars(http_build_query(['q'=>$_GET['q']??'','status'=>$_GET['status']??'Pending','gender'=>$_GET['gender']??'All','completion_sort'=>$_GET['completion_sort']??'none','assignment'=>$_GET['assignment']??'All','history_q'=>$history_q,'history_action'=>$history_action,'history_from'=>$history_from,'history_to'=>$history_to,'history_page'=>$history_page-1])) ?>#history"><i class="fa-solid fa-chevron-left"></i></a><?php endif; ?>
                    <span>Page <?= $history_page ?> of <?= $history_pages ?></span>
                    <?php if ($history_page < $history_pages): ?><a href="?<?= htmlspecialchars(http_build_query(['q'=>$_GET['q']??'','status'=>$_GET['status']??'Pending','gender'=>$_GET['gender']??'All','completion_sort'=>$_GET['completion_sort']??'none','assignment'=>$_GET['assignment']??'All','history_q'=>$history_q,'history_action'=>$history_action,'history_from'=>$history_from,'history_to'=>$history_to,'history_page'=>$history_page+1])) ?>#history"><i class="fa-solid fa-chevron-right"></i></a><?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        </div>
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
<script>
(function () {
    const sendModal = document.getElementById('authStaffMessageModal');
    const inboxModal = document.getElementById('authStaffInboxModal');
    const photoModal = document.getElementById('authPhotoModal');

    function openModal(modal) {
        if (!modal) return;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('auth-modal-open');
    }
    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.auth-staff-message-modal.is-open, .auth-photo-modal.is-open')) {
            document.body.classList.remove('auth-modal-open');
        }
    }

    document.querySelectorAll('[data-auth-message-open]').forEach(function (el) {
        el.addEventListener('click', function () { openModal(sendModal); });
    });
    document.querySelectorAll('[data-auth-inbox-open]').forEach(function (el) {
        el.addEventListener('click', function () { openModal(inboxModal); });
    });
    document.querySelectorAll('[data-auth-photo-open]').forEach(function (el) {
        el.addEventListener('click', function () { openModal(photoModal); });
    });
    document.querySelectorAll('[data-auth-message-close]').forEach(function (el) {
        el.addEventListener('click', function () { closeModal(sendModal); });
    });
    document.querySelectorAll('[data-auth-inbox-close]').forEach(function (el) {
        el.addEventListener('click', function () { closeModal(inboxModal); });
    });
    document.querySelectorAll('[data-auth-photo-close]').forEach(function (el) {
        el.addEventListener('click', function () { closeModal(photoModal); });
    });

    document.querySelectorAll('[data-auth-message-read]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = btn.getAttribute('data-auth-message-read');
            const form = new FormData();
            form.append('csrf', <?= json_encode($csrf); ?>);
            form.append('action', 'mark_admin_message_read');
            form.append('message_id', id);
            form.append('ajax', '1');
            fetch('dashboard.php', { method: 'POST', body: form, credentials: 'same-origin' })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data || !data.ok) return;
                    const item = btn.closest('.auth-staff-inbox-item');
                    if (!item) return;
                    item.classList.remove('is-unread');
                    const badge = item.querySelector('.auth-staff-inbox-top b');
                    if (badge) badge.remove();
                    btn.replaceWith(document.createTextNode('Read'));
                })
                .catch(function () {});
        });
    });

    const photoInput = document.getElementById('authProfileImage');
    const photoPreview = document.getElementById('authPhotoPreview');
    if (photoInput && photoPreview) {
        photoInput.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file || !/^image\/(jpeg|png|webp)$/i.test(file.type)) return;
            const url = URL.createObjectURL(file);
            photoPreview.innerHTML = '<img src="' + url + '" alt="Selected profile image preview">';
            photoPreview.classList.remove('auth-photo-preview-fallback');
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        closeModal(sendModal);
        closeModal(inboxModal);
        closeModal(photoModal);
    });
})();
</script>
</body>
</html>
