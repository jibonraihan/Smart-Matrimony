<?php
/**
 * Central Manager role-transition rules.
 * The same user_id is retained when a Manager becomes User/Authenticator,
 * so name/email/password/history remain attached to the same account.
 */

function manager_role_history_available(mysqli $conn): bool {
    static $available = null;
    if ($available !== null) return $available;
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'manager_role_history'");
    $available = $result && mysqli_num_rows($result) > 0;
    if ($result) mysqli_free_result($result);
    return $available;
}

function manager_package_history_available(mysqli $conn): bool {
    static $available = null;
    if ($available !== null) return $available;
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'manager_package_transfer_history'");
    $available = $result && mysqli_num_rows($result) > 0;
    if ($result) mysqli_free_result($result);
    return $available;
}

function manager_available_replacements(mysqli $conn, int $exclude_user_id): array {
    $rows = [];
    $stmt = mysqli_prepare($conn, "SELECT user_id, first_name, last_name, email FROM users WHERE role='Manager' AND account_status='Active' AND user_id<>? ORDER BY first_name, last_name, user_id");
    mysqli_stmt_bind_param($stmt, 'i', $exclude_user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

function manager_current_package_count(mysqli $conn, int $manager_id): int {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM service_providers WHERE manager_id=?");
    mysqli_stmt_bind_param($stmt, 'i', $manager_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (int)($row['c'] ?? 0);
}

/**
 * Change an account role while enforcing package reassignment.
 * Returns ['ok'=>bool, 'message'=>string, 'requires_reassignment'=>bool].
 */
function transition_manager_role(mysqli $conn, int $user_id, string $target_role, int $admin_id, ?int $replacement_manager_id = null, string $target_status = 'Active', ?callable $before_role_update = null): array {
    $allowed = ['User','Manager','Authenticator'];
    if (!in_array($target_role, $allowed, true) || !in_array($target_status, ['Active','Inactive','Suspended'], true)) {
        return ['ok'=>false, 'message'=>'Invalid target role.', 'requires_reassignment'=>false];
    }
    if ($user_id <= 0 || $user_id === $admin_id) {
        return ['ok'=>false, 'message'=>'Invalid account.', 'requires_reassignment'=>false];
    }
    if (!manager_role_history_available($conn) || !manager_package_history_available($conn)) {
        return ['ok'=>false, 'message'=>'Manager role-transition database foundation is not installed yet. Import the manager reassignment migration first.', 'requires_reassignment'=>false];
    }

    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare($conn, "SELECT user_id, role, account_status FROM users WHERE user_id=? AND role<>'Admin' FOR UPDATE");
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $account = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$account) throw new RuntimeException('Account not found.');

        $previous_role = (string)$account['role'];
        if ($previous_role === $target_role) {
            if ($before_role_update !== null) {
                $before_role_update($conn, $user_id);
            }
            $stmt = mysqli_prepare($conn, "UPDATE users SET account_status=? WHERE user_id=? AND role<>'Admin'");
            mysqli_stmt_bind_param($stmt, 'si', $target_status, $user_id);
            if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('Unable to update the account status.');
            mysqli_stmt_close($stmt);
            mysqli_commit($conn);
            return ['ok'=>true, 'message'=>'Account is already '.$target_role.'. Status updated successfully.', 'requires_reassignment'=>false];
        }

        $package_count = 0;
        if ($previous_role === 'Manager' && $target_role !== 'Manager') {
            $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM service_providers WHERE manager_id=? FOR UPDATE");
            mysqli_stmt_bind_param($stmt, 'i', $user_id);
            mysqli_stmt_execute($stmt);
            $package_count = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'] ?? 0);
            mysqli_stmt_close($stmt);

            if ($package_count > 0) {
                if ($replacement_manager_id === null || $replacement_manager_id <= 0) {
                    mysqli_rollback($conn);
                    return ['ok'=>false, 'message'=>"This manager owns {$package_count} package(s). Select an active replacement Manager before changing the role.", 'requires_reassignment'=>true];
                }
                if ($replacement_manager_id === $user_id) {
                    mysqli_rollback($conn);
                    return ['ok'=>false, 'message'=>'The replacement Manager must be a different account.', 'requires_reassignment'=>true];
                }

                $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_id=? AND role='Manager' AND account_status='Active' FOR UPDATE");
                mysqli_stmt_bind_param($stmt, 'i', $replacement_manager_id);
                mysqli_stmt_execute($stmt);
                $replacement = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);
                if (!$replacement) {
                    mysqli_rollback($conn);
                    return ['ok'=>false, 'message'=>'Selected replacement Manager is not an active Manager.', 'requires_reassignment'=>true];
                }

                $provider_ids = [];
                $stmt = mysqli_prepare($conn, "SELECT provider_id FROM service_providers WHERE manager_id=? FOR UPDATE");
                mysqli_stmt_bind_param($stmt, 'i', $user_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                while ($row = mysqli_fetch_assoc($result)) $provider_ids[] = (int)$row['provider_id'];
                mysqli_stmt_close($stmt);

                $update = mysqli_prepare($conn, "UPDATE service_providers SET manager_id=? WHERE manager_id=?");
                mysqli_stmt_bind_param($update, 'ii', $replacement_manager_id, $user_id);
                if (!mysqli_stmt_execute($update)) throw new RuntimeException('Unable to reassign the manager packages.');
                mysqli_stmt_close($update);

                $history = mysqli_prepare($conn, "INSERT INTO manager_package_transfer_history (provider_id, from_manager_id, to_manager_id, transferred_by_admin_id, transfer_reason) VALUES (?, ?, ?, ?, ?)");
                $reason = 'Automatic package reassignment required before Manager role change to '.$target_role.'.';
                foreach ($provider_ids as $provider_id) {
                    mysqli_stmt_bind_param($history, 'iiiis', $provider_id, $user_id, $replacement_manager_id, $admin_id, $reason);
                    if (!mysqli_stmt_execute($history)) throw new RuntimeException('Unable to record package transfer history.');
                }
                mysqli_stmt_close($history);
            }
        }

        if ($before_role_update !== null) {
            $before_role_update($conn, $user_id);
        }

        $stmt = mysqli_prepare($conn, "UPDATE users SET role=?, account_status=? WHERE user_id=? AND role<>'Admin'");
        mysqli_stmt_bind_param($stmt, 'ssi', $target_role, $target_status, $user_id);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('Unable to update the account role.');
        mysqli_stmt_close($stmt);

        $details = 'Role changed from '.$previous_role.' to '.$target_role.'.';
        if ($package_count > 0 && $replacement_manager_id) {
            $details .= ' '.$package_count.' package(s) reassigned to user #'.$replacement_manager_id.'. Historical booking ownership was not changed.';
        }
        $history = mysqli_prepare($conn, "INSERT INTO manager_role_history (user_id, previous_role, new_role, changed_by_admin_id, details) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($history, 'issis', $user_id, $previous_role, $target_role, $admin_id, $details);
        if (!mysqli_stmt_execute($history)) throw new RuntimeException('Unable to record role history.');
        mysqli_stmt_close($history);

        mysqli_commit($conn);
        return ['ok'=>true, 'message'=>$details, 'requires_reassignment'=>false];
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        return ['ok'=>false, 'message'=>$e->getMessage(), 'requires_reassignment'=>false];
    }
}

function create_replacement_manager(mysqli $conn, array $data, int $admin_id): array {
    $first = trim((string)($data['first_name'] ?? ''));
    $last = trim((string)($data['last_name'] ?? ''));
    $gender = (string)($data['gender'] ?? '');
    $mobile = trim((string)($data['mobile'] ?? ''));
    $email = trim((string)($data['email'] ?? ''));
    $password = (string)($data['password'] ?? '');
    if ($first === '' || $last === '' || !in_array($gender, ['Male','Female'], true) || $mobile === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        return ['ok'=>false, 'message'=>'Provide valid replacement Manager name, gender, mobile, email and a password of at least 8 characters.'];
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn, "INSERT INTO users (first_name,last_name,gender,mobile,email,password,role,account_status) VALUES (?,?,?,?,?,?, 'Manager','Active')");
    mysqli_stmt_bind_param($stmt, 'ssssss', $first, $last, $gender, $mobile, $email, $hash);
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        if (stripos($error, 'Duplicate') !== false) $error = 'That email or mobile number is already in use.';
        return ['ok'=>false, 'message'=>$error ?: 'Unable to create the replacement Manager.'];
    }
    $new_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    if (manager_role_history_available($conn)) {
        $previous_role = 'User'; $new_role = 'Manager'; $details = 'Replacement Manager account created by Admin #'.$admin_id.'.';
        $history = mysqli_prepare($conn, "INSERT INTO manager_role_history (user_id, previous_role, new_role, changed_by_admin_id, details) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($history, 'issis', $new_id, $previous_role, $new_role, $admin_id, $details);
        mysqli_stmt_execute($history); mysqli_stmt_close($history);
    }
    return ['ok'=>true, 'message'=>'Replacement Manager created successfully: SM-'.str_pad((string)$new_id,6,'0',STR_PAD_LEFT).'.', 'user_id'=>$new_id];
}
