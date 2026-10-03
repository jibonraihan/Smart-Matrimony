<?php
$page_css = 'assets/css/settings.css';

require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/dropdowns.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

if (!function_exists('settings_public_id')) {
    function settings_public_id($id) {
        return 'SM-' . str_pad((string) (int) $id, 6, '0', STR_PAD_LEFT);
    }
}

if (empty($_SESSION['settings_csrf'])) {
    $_SESSION['settings_csrf'] = bin2hex(random_bytes(32));
}
$settings_csrf = $_SESSION['settings_csrf'];
$blocking_flash = $_SESSION['settings_blocking_flash'] ?? null;
unset($_SESSION['settings_blocking_flash']);

$account_message = '';
$account_error = '';
$security_message = '';
$security_error = '';
$privacy_message = '';
$privacy_error = '';
$notification_message = '';
$notification_error = '';
$matching_message = '';
$matching_error = '';
$matching_weights_message = '';
$matching_weights_error = '';
$preferences_message = '';
$preferences_error = '';
$profile_media_message = '';
$profile_media_error = '';
$blocking_message = is_array($blocking_flash) && ($blocking_flash['type'] ?? '') === 'success' ? (string) ($blocking_flash['message'] ?? '') : '';
$blocking_error = is_array($blocking_flash) && ($blocking_flash['type'] ?? '') === 'error' ? (string) ($blocking_flash['message'] ?? '') : '';
$notifications_enabled = true;

$stmt = mysqli_prepare($conn, "
    SELECT
        u.user_id,
        u.first_name,
        u.last_name,
        u.gender,
        u.email,
        u.mobile,
        u.role,
        u.account_status,
        u.password,
        u.name_change_count,
        u.mobile_change_count,
        up.photo,
        up.profile_visibility,
        up.photo_visibility,
        up.verification_status
    FROM users u
    LEFT JOIN user_profiles up ON up.user_id = u.user_id
    WHERE u.user_id = ?
    LIMIT 1
");

mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$current_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$current_user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Lightweight user lookup used by the Blocking & Reports selector.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['settings_lookup_user'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $lookup_input = trim((string) ($_GET['user_id'] ?? ''));
    $lookup_id = 0;
    if (preg_match('/^SM-(\d+)$/i', $lookup_input, $match)) {
        $lookup_id = (int) $match[1];
    } elseif (ctype_digit($lookup_input)) {
        $lookup_id = (int) $lookup_input;
    }

    if ($lookup_id <= 0 || $lookup_id === $user_id) {
        echo json_encode(['success' => false, 'message' => 'User not found.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $lookup_stmt = mysqli_prepare($conn, 'SELECT u.user_id, u.first_name, u.last_name, u.role, u.account_status, up.photo FROM users u LEFT JOIN user_profiles up ON up.user_id = u.user_id WHERE u.user_id = ? LIMIT 1');
    if (!$lookup_stmt) {
        echo json_encode(['success' => false, 'message' => 'Unable to search right now.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    mysqli_stmt_bind_param($lookup_stmt, 'i', $lookup_id);
    mysqli_stmt_execute($lookup_stmt);
    $lookup_user = mysqli_fetch_assoc(mysqli_stmt_get_result($lookup_stmt));
    mysqli_stmt_close($lookup_stmt);

    if (!$lookup_user || ($lookup_user['role'] ?? '') !== 'User') {
        echo json_encode(['success' => false, 'message' => 'User not found.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success' => true,
        'user' => [
            'id' => (int) $lookup_user['user_id'],
            'public_id' => settings_public_id($lookup_user['user_id']),
            'name' => trim(($lookup_user['first_name'] ?? '') . ' ' . ($lookup_user['last_name'] ?? '')),
            'photo' => !empty($lookup_user['photo']) ? (BASE_URL . 'uploads/profile/' . $lookup_user['photo']) : '',
            'account_status' => (string) ($lookup_user['account_status'] ?? '')
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$notification_settings_result = mysqli_query($conn, "SELECT notifications_enabled FROM notification_settings WHERE setting_id = 1 LIMIT 1");
if ($notification_settings_result) {
    $notification_settings_row = mysqli_fetch_assoc($notification_settings_result);
    if ($notification_settings_row !== null) {
        $notifications_enabled = ((int) ($notification_settings_row['notifications_enabled'] ?? 1)) === 1;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['settings_action'] ?? ''), ['deactivate_account', 'delete_account'], true)) {
    $account_control_action = (string) ($_POST['settings_action'] ?? '');
    $posted_token = (string) ($_POST['csrf_token'] ?? '');
    $control_password = (string) ($_POST['control_password'] ?? '');

    if (!hash_equals($settings_csrf, $posted_token)) {
        $account_error = 'The security token is invalid. Please refresh the page and try again.';
    } elseif (($current_user['role'] ?? '') !== 'User') {
        $account_error = 'Account control is available only for user accounts.';
    } elseif ($control_password === '' || !password_verify($control_password, (string) ($current_user['password'] ?? ''))) {
        $account_error = 'Enter your current password.';
    } elseif ($account_control_action === 'delete_account' && (string) ($_POST['confirm_delete'] ?? '') !== '1') {
        $account_error = 'Confirm that you want to permanently delete your account.';
    } elseif ($account_control_action === 'deactivate_account') {
        $update = mysqli_prepare($conn, "UPDATE users SET account_status='Inactive' WHERE user_id=? AND role='User' LIMIT 1");
        if ($update) {
            mysqli_stmt_bind_param($update, 'i', $user_id);
            $ok = mysqli_stmt_execute($update);
            mysqli_stmt_close($update);
            if ($ok) {
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
                }
                session_destroy();
                header('Location: login.php?reason=deactivated');
                exit;
            }
        }
        $account_error = 'Unable to deactivate your account right now. Please try again.';
    } else {
        $media_files = [];
        $media_stmt = mysqli_prepare($conn, 'SELECT file_path FROM profile_media WHERE user_id = ?');
        if ($media_stmt) {
            mysqli_stmt_bind_param($media_stmt, 'i', $user_id);
            mysqli_stmt_execute($media_stmt);
            $media_result = mysqli_stmt_get_result($media_stmt);
            while ($media_result && ($media_row = mysqli_fetch_assoc($media_result))) {
                $file = basename((string) ($media_row['file_path'] ?? ''));
                if ($file !== '') $media_files[$file] = true;
            }
            mysqli_stmt_close($media_stmt);
        }
        $photo_stmt = mysqli_prepare($conn, 'SELECT photo FROM user_profiles WHERE user_id = ? LIMIT 1');
        if ($photo_stmt) {
            mysqli_stmt_bind_param($photo_stmt, 'i', $user_id);
            mysqli_stmt_execute($photo_stmt);
            $photo_row = mysqli_fetch_assoc(mysqli_stmt_get_result($photo_stmt));
            mysqli_stmt_close($photo_stmt);
            $photo_file = basename((string) ($photo_row['photo'] ?? ''));
            if ($photo_file !== '') $media_files[$photo_file] = true;
        }

        mysqli_begin_transaction($conn);
        $delete = mysqli_prepare($conn, "DELETE FROM users WHERE user_id=? AND role='User' LIMIT 1");
        $ok = false;
        if ($delete) {
            mysqli_stmt_bind_param($delete, 'i', $user_id);
            $ok = mysqli_stmt_execute($delete) && mysqli_stmt_affected_rows($delete) === 1;
            mysqli_stmt_close($delete);
        }
        if ($ok) {
            mysqli_commit($conn);
            foreach (array_keys($media_files) as $media_file) {
                $path = __DIR__ . '/uploads/profile/' . $media_file;
                if (is_file($path)) @unlink($path);
            }
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            header('Location: login.php?reason=deleted');
            exit;
        }
        mysqli_rollback($conn);
        $account_error = 'Unable to delete your account right now. Please try again.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['settings_action'] ?? '') === 'update_notifications') {
    $posted_token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($settings_csrf, $posted_token)) {
        $notification_error = 'The security token is invalid. Please refresh the page and try again.';
    } else {
        $requested_state = (string) ($_POST['notifications_enabled'] ?? '0');
        $new_state = $requested_state === '1' ? 1 : 0;
        $update = mysqli_prepare($conn, 'UPDATE notification_settings SET notifications_enabled = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_id = 1 LIMIT 1');
        if ($update) {
            mysqli_stmt_bind_param($update, 'i', $new_state);
            if (mysqli_stmt_execute($update)) {
                $notifications_enabled = $new_state === 1;
                $notification_message = $notifications_enabled
                    ? 'Notifications are now ON for everyone.'
                    : 'Notifications are now OFF for everyone.';
            } else {
                $notification_error = 'Unable to update the notification setting right now. Please try again.';
            }
            mysqli_stmt_close($update);
        } else {
            $notification_error = 'Unable to prepare the notification setting update. Please try again.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['settings_action'] ?? ''), ['block_user', 'unblock_user', 'report_user'], true)) {
    $posted_token = (string) ($_POST['csrf_token'] ?? '');
    $action = (string) ($_POST['settings_action'] ?? '');

    if (!hash_equals($settings_csrf, $posted_token)) {
        $blocking_error = 'The security token is invalid. Please refresh the page and try again.';
    } else {
        $target_input = trim((string) ($_POST['target_user_id'] ?? $_POST['blocked_user_id'] ?? $_POST['reported_user_id'] ?? ''));
        $target_id = 0;
        if (preg_match('/^SM-(\d+)$/i', $target_input, $match)) {
            $target_id = (int) $match[1];
        } elseif (ctype_digit($target_input)) {
            $target_id = (int) $target_input;
        }

        if ($target_id <= 0) {
            $blocking_error = 'Enter a valid User ID.';
        } elseif ($target_id === $user_id) {
            $blocking_error = 'You cannot block or report your own account.';
        } else {
            $target_stmt = mysqli_prepare($conn, "SELECT user_id, role, account_status, first_name, last_name FROM users WHERE user_id = ? LIMIT 1");
            if ($target_stmt) {
                mysqli_stmt_bind_param($target_stmt, 'i', $target_id);
                mysqli_stmt_execute($target_stmt);
                $target_user = mysqli_fetch_assoc(mysqli_stmt_get_result($target_stmt));
                mysqli_stmt_close($target_stmt);
            } else {
                $target_user = null;
            }

            if (!$target_user || ($action !== 'unblock_user' && ($target_user['role'] ?? '') !== 'User')) {
                $blocking_error = 'User not found.';
            } elseif ($action === 'block_user') {
                $insert = mysqli_prepare($conn, 'INSERT INTO user_blocks (blocker_user_id, blocked_user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE created_at = created_at');
                if ($insert) {
                    mysqli_stmt_bind_param($insert, 'ii', $user_id, $target_id);
                    if (mysqli_stmt_execute($insert)) {
                        $_SESSION['settings_blocking_flash'] = ['type' => 'success', 'message' => settings_public_id($target_id) . ' blocked.'];
                        header('Location: settings.php#blocking-panel');
                        exit;
                    } else {
                        $blocking_error = 'Unable to block this user right now.';
                    }
                    mysqli_stmt_close($insert);
                } else {
                    $blocking_error = 'Unable to process the block request right now.';
                }
            } elseif ($action === 'unblock_user') {
                $delete = mysqli_prepare($conn, 'DELETE FROM user_blocks WHERE blocker_user_id = ? AND blocked_user_id = ? LIMIT 1');
                if ($delete) {
                    mysqli_stmt_bind_param($delete, 'ii', $user_id, $target_id);
                    if (mysqli_stmt_execute($delete)) {
                        $_SESSION['settings_blocking_flash'] = ['type' => 'success', 'message' => settings_public_id($target_id) . ' unblocked.'];
                        header('Location: settings.php#blocking-panel');
                        exit;
                    } else {
                        $blocking_error = 'Unable to unblock this user right now.';
                    }
                    mysqli_stmt_close($delete);
                } else {
                    $blocking_error = 'Unable to process the unblock request right now.';
                }
            } elseif ($action === 'report_user') {
                $reason = trim((string) ($_POST['report_reason'] ?? ''));
                $details = trim((string) ($_POST['report_details'] ?? ''));
                $valid_reasons = ['Fake profile', 'Harassment', 'Inappropriate content', 'Scam or fraud', 'Impersonation', 'Other'];
                if (!in_array($reason, $valid_reasons, true)) {
                    $blocking_error = 'Select a valid report reason.';
                } elseif (mb_strlen($details) > 1000) {
                    $blocking_error = 'Report details must be 1000 characters or fewer.';
                } else {
                    $report = mysqli_prepare($conn, 'INSERT INTO user_reports (reporter_user_id, reported_user_id, reason, details) VALUES (?, ?, ?, ?)');
                    if ($report) {
                        mysqli_stmt_bind_param($report, 'iiss', $user_id, $target_id, $reason, $details);
                        if (mysqli_stmt_execute($report)) {
                            $_SESSION['settings_blocking_flash'] = ['type' => 'success', 'message' => 'Report submitted.'];
                            header('Location: settings.php#blocking-panel');
                            exit;
                        } else {
                            $blocking_error = 'Unable to submit the report right now.';
                        }
                        mysqli_stmt_close($report);
                    } else {
                        $blocking_error = 'Unable to process the report right now.';
                    }
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['settings_action'] ?? '') === 'update_matching') {
    $settings_return_section = (string) ($_POST['settings_return_section'] ?? '');
    $posted_token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($settings_csrf, $posted_token)) {
        $matching_error = 'The security token is invalid. Please refresh the page and try again.';
    } else {
        $gender_stmt = mysqli_prepare($conn, 'SELECT gender FROM users WHERE user_id = ? LIMIT 1');
        mysqli_stmt_bind_param($gender_stmt, 'i', $user_id);
        mysqli_stmt_execute($gender_stmt);
        $gender_row = mysqli_fetch_assoc(mysqli_stmt_get_result($gender_stmt)) ?: [];
        mysqli_stmt_close($gender_stmt);

        $own_gender = (string) ($gender_row['gender'] ?? '');
        $preferred_gender = $own_gender === 'Male' ? 'Female' : ($own_gender === 'Female' ? 'Male' : '');
        $min_age = trim((string) ($_POST['min_age'] ?? ''));
        $max_age = trim((string) ($_POST['max_age'] ?? ''));
        $min_height_ft = trim((string) ($_POST['min_height_ft'] ?? ''));
        $min_height_in = trim((string) ($_POST['min_height_in'] ?? ''));
        $max_height_ft = trim((string) ($_POST['max_height_ft'] ?? ''));
        $max_height_in = trim((string) ($_POST['max_height_in'] ?? ''));
        $min_weight_kg = trim((string) ($_POST['min_weight_kg'] ?? ''));
        $max_weight_kg = trim((string) ($_POST['max_weight_kg'] ?? ''));
        $complexion = trim((string) ($_POST['complexion'] ?? ''));
        $religion = trim((string) ($_POST['religion'] ?? ''));
        $marital_status = trim((string) ($_POST['marital_status'] ?? ''));
        $family_status = trim((string) ($_POST['family_status'] ?? ''));
        $madhhab = trim((string) ($_POST['madhhab'] ?? ''));
        $prayer_status = trim((string) ($_POST['prayer_status'] ?? ''));
        $halal_lifestyle = trim((string) ($_POST['halal_lifestyle'] ?? ''));
        $mahram_maintained = trim((string) ($_POST['mahram_maintained'] ?? ''));
        $islamic_knowledge = trim((string) ($_POST['islamic_knowledge'] ?? ''));
        $hijab_status = trim((string) ($_POST['hijab_status'] ?? ''));
        $beard_status = trim((string) ($_POST['beard_status'] ?? ''));
        $personality_type = trim((string) ($_POST['personality_type'] ?? ''));
        $education = trim((string) ($_POST['education'] ?? ''));
        $profession = trim((string) ($_POST['profession'] ?? ''));
        $min_monthly_income = trim((string) ($_POST['min_monthly_income'] ?? ''));
        $division_id = trim((string) ($_POST['division_id'] ?? ''));
        $district_id = trim((string) ($_POST['district_id'] ?? ''));
        $upazila_id = trim((string) ($_POST['upazila_id'] ?? ''));
        $blood_group = trim((string) ($_POST['blood_group'] ?? ''));
        $accept_smoker = (string) ($_POST['accept_smoker'] ?? '1');
        $accept_alcohol = (string) ($_POST['accept_alcohol'] ?? '1');
        $accept_disability = (string) ($_POST['accept_disability'] ?? '1');
        $accept_chronic_disease = (string) ($_POST['accept_chronic_disease'] ?? '1');
        $additional_preferences = trim((string) ($_POST['additional_preferences'] ?? ''));

        $toIntOrNull = static function ($value) { return $value === '' ? null : (int) $value; };
        $toFloatOrNull = static function ($value) { return $value === '' ? null : (float) $value; };
        $min_age_v = $toIntOrNull($min_age);
        $max_age_v = $toIntOrNull($max_age);
        $heightToCm = static function ($feet, $inches) {
            if ($feet === '' && $inches === '') {
                return null;
            }
            if ($feet === '' || $inches === '') {
                return false;
            }
            return round((((int) $feet * 12) + (int) $inches) * 2.54, 2);
        };
        $min_height_v = $heightToCm($min_height_ft, $min_height_in);
        $max_height_v = $heightToCm($max_height_ft, $max_height_in);
        $min_weight_v = $toFloatOrNull($min_weight_kg);
        $max_weight_v = $toFloatOrNull($max_weight_kg);
        $min_income_v = $toFloatOrNull($min_monthly_income);
        $division_v = $toIntOrNull($division_id);
        $district_v = $toIntOrNull($district_id);
        $upazila_v = $toIntOrNull($upazila_id);

        $validComplexions = $complexions;
        $validReligions = $religions;
        $validMarital = $marital_statuses;
        $validFamilyStatus = $family_statuses;
        $validMadhhabs = $madhhabs;
        $validPrayer = $prayer_statuses;
        $validHalal = $halal_lifestyle_options;
        $validKnowledge = $islamic_knowledge_levels;
        $validHijab = $hijab_statuses;
        $validBeard = $beard_statuses;
        $validPersonality = $personality_types;
        $validEducation = $education_levels;
        $validProfession = $user_professions;
        $validBlood = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];

        if ($preferred_gender === '') {
            $matching_error = 'A valid account gender is required before saving matching preferences.';
        } elseif (($min_age_v !== null && ($min_age_v < 15 || $min_age_v > 80)) || ($max_age_v !== null && ($max_age_v < 15 || $max_age_v > 80)) || ($min_age_v !== null && $max_age_v !== null && $min_age_v > $max_age_v)) {
            $matching_error = 'Please enter a valid age range between 15 and 80.';
        } elseif ($min_height_v === false || $max_height_v === false) {
            $matching_error = 'Please select both feet and inches for each height range.';
        } elseif (($min_height_v !== null && ($min_height_v < 120 || $min_height_v > 250)) || ($max_height_v !== null && ($max_height_v < 120 || $max_height_v > 250)) || ($min_height_v !== null && $max_height_v !== null && $min_height_v > $max_height_v)) {
            $matching_error = 'Please select a valid height range between 4 ft 0 in and 8 ft 2 in.';
        } elseif (($min_weight_v !== null && ($min_weight_v < 1 || $min_weight_v > 500)) || ($max_weight_v !== null && ($max_weight_v < 1 || $max_weight_v > 500)) || ($min_weight_v !== null && $max_weight_v !== null && $min_weight_v > $max_weight_v)) {
            $matching_error = 'Please enter a valid weight range between 1 and 500 kg.';
        } elseif ($complexion !== '' && !in_array($complexion, $validComplexions, true)) {
            $matching_error = 'Please select a valid skin colour preference.';
        } elseif ($religion !== '' && !in_array($religion, $validReligions, true)) {
            $matching_error = 'Please select a valid religion preference.';
        } elseif ($marital_status !== '' && !in_array($marital_status, $validMarital, true)) {
            $matching_error = 'Please select a valid marital status preference.';
        } elseif ($family_status !== '' && !in_array($family_status, $validFamilyStatus, true)) {
            $matching_error = 'Please select a valid family status preference.';
        } elseif ($madhhab !== '' && !in_array($madhhab, $validMadhhabs, true)) {
            $matching_error = 'Please select a valid madhhab preference.';
        } elseif ($prayer_status !== '' && !in_array($prayer_status, $validPrayer, true)) {
            $matching_error = 'Please select a valid prayer status preference.';
        } elseif ($halal_lifestyle !== '' && !in_array($halal_lifestyle, $validHalal, true)) {
            $matching_error = 'Please select a valid halal lifestyle preference.';
        } elseif ($mahram_maintained !== '' && !in_array($mahram_maintained, ['0','1'], true)) {
            $matching_error = 'Please select a valid mahram preference.';
        } elseif ($islamic_knowledge !== '' && !in_array($islamic_knowledge, $validKnowledge, true)) {
            $matching_error = 'Please select a valid Islamic knowledge preference.';
        } elseif ($hijab_status !== '' && !in_array($hijab_status, $validHijab, true)) {
            $matching_error = 'Please select a valid hijab preference.';
        } elseif ($beard_status !== '' && !in_array($beard_status, $validBeard, true)) {
            $matching_error = 'Please select a valid beard preference.';
        } elseif ($personality_type !== '' && !in_array($personality_type, $validPersonality, true)) {
            $matching_error = 'Please select a valid personality preference.';
        } elseif ($education !== '' && !in_array($education, $validEducation, true)) {
            $matching_error = 'Please select a valid education preference.';
        } elseif ($profession !== '' && !in_array($profession, $validProfession, true)) {
            $matching_error = 'Please select a valid profession preference.';
        } elseif ($blood_group !== '' && !in_array($blood_group, $validBlood, true)) {
            $matching_error = 'Please select a valid blood group preference.';
        } elseif (!in_array($accept_smoker, ['0','1'], true) || !in_array($accept_alcohol, ['0','1'], true) || !in_array($accept_disability, ['0','1'], true) || !in_array($accept_chronic_disease, ['0','1'], true)) {
            $matching_error = 'Please select valid lifestyle and health preferences.';
        } elseif (mb_strlen($additional_preferences) > 5000) {
            $matching_error = 'Additional preferences must be 5000 characters or fewer.';
        } else {
            if ($religion !== 'Islam') {
                $madhhab = $prayer_status = $halal_lifestyle = $mahram_maintained = $islamic_knowledge = $hijab_status = $beard_status = '';
            } elseif ($preferred_gender === 'Female') {
                $beard_status = '';
            } elseif ($preferred_gender === 'Male') {
                $hijab_status = '';
            }

            $madhhab_v = $madhhab === '' ? null : $madhhab;
            $prayer_v = $prayer_status === '' ? null : $prayer_status;
            $halal_v = $halal_lifestyle === '' ? null : $halal_lifestyle;
            $mahram_v = $mahram_maintained === '' ? null : (int) $mahram_maintained;
            $knowledge_v = $islamic_knowledge === '' ? null : $islamic_knowledge;
            $hijab_v = $hijab_status === '' ? null : $hijab_status;
            $beard_v = $beard_status === '' ? null : $beard_status;
            $complexion_v = $complexion === '' ? null : $complexion;
            $religion_v = $religion === '' ? null : $religion;
            $marital_v = $marital_status === '' ? null : $marital_status;
            $education_v = $education === '' ? null : $education;
            $profession_v = $profession === '' ? null : $profession;
            $personality_v = $personality_type === '' ? null : $personality_type;
            $blood_v = $blood_group === '' ? null : $blood_group;
            $additional_v = $additional_preferences === '' ? null : $additional_preferences;
            $accept_smoker_v = (int) $accept_smoker;
            $accept_alcohol_v = (int) $accept_alcohol;
            $accept_disability_v = (int) $accept_disability;
            $accept_chronic_v = (int) $accept_chronic_disease;

            $exists_stmt = mysqli_prepare($conn, 'SELECT preference_id FROM search_preferences WHERE user_id = ? LIMIT 1');
            mysqli_stmt_bind_param($exists_stmt, 'i', $user_id);
            mysqli_stmt_execute($exists_stmt);
            $exists = mysqli_num_rows(mysqli_stmt_get_result($exists_stmt)) > 0;
            mysqli_stmt_close($exists_stmt);

            if ($exists) {
                $sql = 'UPDATE search_preferences SET preferred_gender=?, min_age=?, max_age=?, min_height_cm=?, max_height_cm=?, complexion=?, min_weight_kg=?, max_weight_kg=?, religion=?, marital_status=?, madhhab=?, prayer_status=?, halal_lifestyle=?, mahram_maintained=?, islamic_knowledge=?, hijab_status=?, beard_status=?, personality_type=?, education=?, profession=?, min_monthly_income=?, division_id=?, district_id=?, upazila_id=?, blood_group=?, accept_smoker=?, accept_alcohol=?, accept_disability=?, accept_chronic_disease=?, additional_preferences=? WHERE user_id=? LIMIT 1';
                $save = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($save, 'siiddsddsssssissssssdiiisiiiisi', $preferred_gender, $min_age_v, $max_age_v, $min_height_v, $max_height_v, $complexion_v, $min_weight_v, $max_weight_v, $religion_v, $marital_v, $madhhab_v, $prayer_v, $halal_v, $mahram_v, $knowledge_v, $hijab_v, $beard_v, $personality_v, $education_v, $profession_v, $min_income_v, $division_v, $district_v, $upazila_v, $blood_v, $accept_smoker_v, $accept_alcohol_v, $accept_disability_v, $accept_chronic_v, $additional_v, $user_id);
            } else {
                $sql = 'INSERT INTO search_preferences (user_id, preferred_gender, min_age, max_age, min_height_cm, max_height_cm, complexion, min_weight_kg, max_weight_kg, religion, marital_status, madhhab, prayer_status, halal_lifestyle, mahram_maintained, islamic_knowledge, hijab_status, beard_status, personality_type, education, profession, min_monthly_income, division_id, district_id, upazila_id, blood_group, accept_smoker, accept_alcohol, accept_disability, accept_chronic_disease, additional_preferences) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                $save = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($save, 'isiidsddsssssisisssssdiiisiiiis', $user_id, $preferred_gender, $min_age_v, $max_age_v, $min_height_v, $max_height_v, $complexion_v, $min_weight_v, $max_weight_v, $religion_v, $marital_v, $madhhab_v, $prayer_v, $halal_v, $mahram_v, $knowledge_v, $hijab_v, $beard_v, $personality_v, $education_v, $profession_v, $min_income_v, $division_v, $district_v, $upazila_v, $blood_v, $accept_smoker_v, $accept_alcohol_v, $accept_disability_v, $accept_chronic_v, $additional_v);
            }

            if ($save && mysqli_stmt_execute($save)) {
                if ($save) mysqli_stmt_close($save);
                $family_status_v = $family_status === '' ? null : $family_status;
                $family_save = mysqli_prepare($conn, 'UPDATE search_preferences SET family_status=? WHERE user_id=? LIMIT 1');
                if ($family_save) {
                    mysqli_stmt_bind_param($family_save, 'si', $family_status_v, $user_id);
                    $family_ok = mysqli_stmt_execute($family_save);
                    mysqli_stmt_close($family_save);
                } else {
                    $family_ok = false;
                }
                if ($family_ok) {
                    $matching_message = 'Your matching preferences have been updated successfully.';
                } else {
                    $matching_error = 'Matching preferences were saved, but family-status preference could not be updated.';
                }
            } else {
                $matching_error = 'Unable to save matching preferences right now. Please try again.';
                if ($save) mysqli_stmt_close($save);
            }
        }
    }
}


if (($settings_return_section ?? '') === 'preferences') {
    $preferences_message = $matching_message;
    $preferences_error = $matching_error;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['settings_action'] ?? '') === 'update_matching_weights') {
    $posted_token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($settings_csrf, $posted_token)) {
        $matching_weights_error = 'The security token is invalid. Please refresh the page and try again.';
    } else {
        $weight_fields = [
            'age_weight','height_weight','religion_weight','islamic_practice_weight','marital_status_weight',
            'education_weight','profession_weight','location_weight','lifestyle_weight','qna_weight',
            'skin_colour_weight','weight_weight','family_status_weight'
        ];
        $weights = [];
        $total = 0;
        foreach ($weight_fields as $field) {
            $raw = trim((string) ($_POST[$field] ?? ''));
            if ($raw === '' || !is_numeric($raw) || (float)$raw < 0 || (float)$raw > 100 || floor((float)$raw) != (float)$raw) {
                $matching_weights_error = 'Each matching weight must be a whole number from 0 to 100.';
                break;
            }
            $weights[$field] = (int)$raw;
            $total += (int)$raw;
        }
        if ($matching_weights_error === '' && $total !== 100) {
            $matching_weights_error = 'Matching weights must total exactly 100% before saving.';
        }
        if ($matching_weights_error === '') {
            $existing_stmt = mysqli_prepare($conn, 'SELECT weight_id FROM user_match_weights WHERE user_id=? LIMIT 1');
            $existing_id = null;
            if ($existing_stmt) {
                mysqli_stmt_bind_param($existing_stmt, 'i', $user_id);
                mysqli_stmt_execute($existing_stmt);
                $existing_row = mysqli_fetch_assoc(mysqli_stmt_get_result($existing_stmt));
                $existing_id = $existing_row['weight_id'] ?? null;
                mysqli_stmt_close($existing_stmt);
            }
            $ok = false;
            if ($existing_id !== null) {
                $sql = 'UPDATE user_match_weights SET age_weight=?, height_weight=?, religion_weight=?, islamic_practice_weight=?, marital_status_weight=?, education_weight=?, profession_weight=?, location_weight=?, lifestyle_weight=?, qna_weight=?, skin_colour_weight=?, weight_weight=?, family_status_weight=? WHERE user_id=? LIMIT 1';
                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'iiiiiiiiiiiiii', $weights['age_weight'],$weights['height_weight'],$weights['religion_weight'],$weights['islamic_practice_weight'],$weights['marital_status_weight'],$weights['education_weight'],$weights['profession_weight'],$weights['location_weight'],$weights['lifestyle_weight'],$weights['qna_weight'],$weights['skin_colour_weight'],$weights['weight_weight'],$weights['family_status_weight'],$user_id);
                    $ok = mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            } else {
                $sql = 'INSERT INTO user_match_weights (user_id, age_weight, height_weight, religion_weight, islamic_practice_weight, marital_status_weight, education_weight, profession_weight, location_weight, lifestyle_weight, qna_weight, skin_colour_weight, weight_weight, family_status_weight) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'iiiiiiiiiiiiii', $user_id,$weights['age_weight'],$weights['height_weight'],$weights['religion_weight'],$weights['islamic_practice_weight'],$weights['marital_status_weight'],$weights['education_weight'],$weights['profession_weight'],$weights['location_weight'],$weights['lifestyle_weight'],$weights['qna_weight'],$weights['skin_colour_weight'],$weights['weight_weight'],$weights['family_status_weight']);
                    $ok = mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
            if ($ok) $matching_weights_message = 'Your matching weights have been updated successfully.';
            else $matching_weights_error = 'Unable to save matching weights right now. Please try again.';
        }
    }
}

$matching_pref = [
    'preferred_gender' => (($current_user['gender'] ?? '') === 'Male') ? 'Female' : 'Male',
    'min_age' => '', 'max_age' => '', 'min_height_cm' => '', 'max_height_cm' => '',
    'complexion' => '', 'min_weight_kg' => '', 'max_weight_kg' => '', 'religion' => '',
    'marital_status' => '', 'family_status' => '', 'madhhab' => '', 'prayer_status' => '', 'halal_lifestyle' => '',
    'mahram_maintained' => '', 'islamic_knowledge' => '', 'hijab_status' => '', 'beard_status' => '',
    'personality_type' => '', 'education' => '', 'profession' => '', 'min_monthly_income' => '',
    'division_id' => '', 'district_id' => '', 'upazila_id' => '', 'blood_group' => '',
    'accept_smoker' => '1', 'accept_alcohol' => '1', 'accept_disability' => '1',
    'accept_chronic_disease' => '1', 'additional_preferences' => ''
];
$pref_stmt = mysqli_prepare($conn, 'SELECT preferred_gender, min_age, max_age, min_height_cm, max_height_cm, complexion, min_weight_kg, max_weight_kg, religion, marital_status, madhhab, prayer_status, halal_lifestyle, mahram_maintained, islamic_knowledge, hijab_status, beard_status, personality_type, education, profession, min_monthly_income, division_id, district_id, upazila_id, blood_group, accept_smoker, accept_alcohol, accept_disability, accept_chronic_disease, additional_preferences FROM search_preferences WHERE user_id = ? LIMIT 1');
if ($pref_stmt) {
    mysqli_stmt_bind_param($pref_stmt, 'i', $user_id);
    mysqli_stmt_execute($pref_stmt);
    $saved_matching_pref = mysqli_fetch_assoc(mysqli_stmt_get_result($pref_stmt));
    if ($saved_matching_pref) $matching_pref = array_merge($matching_pref, $saved_matching_pref);
    mysqli_stmt_close($pref_stmt);
}
$family_pref_stmt = mysqli_prepare($conn, 'SELECT family_status FROM search_preferences WHERE user_id=? LIMIT 1');
if ($family_pref_stmt) {
    mysqli_stmt_bind_param($family_pref_stmt, 'i', $user_id);
    mysqli_stmt_execute($family_pref_stmt);
    $family_pref_row = mysqli_fetch_assoc(mysqli_stmt_get_result($family_pref_stmt));
    if ($family_pref_row) $matching_pref['family_status'] = $family_pref_row['family_status'] ?? '';
    mysqli_stmt_close($family_pref_stmt);
}

$matching_weights = [
    'age_weight'=>8, 'height_weight'=>5, 'religion_weight'=>10, 'islamic_practice_weight'=>10,
    'marital_status_weight'=>10, 'education_weight'=>10, 'profession_weight'=>10, 'location_weight'=>10,
    'lifestyle_weight'=>8, 'qna_weight'=>5, 'skin_colour_weight'=>10, 'weight_weight'=>4, 'family_status_weight'=>0
];
$weight_stmt = mysqli_prepare($conn, 'SELECT age_weight, height_weight, religion_weight, islamic_practice_weight, marital_status_weight, education_weight, profession_weight, location_weight, lifestyle_weight, qna_weight, skin_colour_weight, weight_weight, family_status_weight FROM user_match_weights WHERE user_id=? LIMIT 1');
if ($weight_stmt) {
    mysqli_stmt_bind_param($weight_stmt, 'i', $user_id);
    mysqli_stmt_execute($weight_stmt);
    $saved_weights = mysqli_fetch_assoc(mysqli_stmt_get_result($weight_stmt));
    if ($saved_weights) foreach ($matching_weights as $key => $value) if (isset($saved_weights[$key])) $matching_weights[$key] = (int)$saved_weights[$key];
    mysqli_stmt_close($weight_stmt);
}

$heightToFeetInches = static function ($cm) {
    if ($cm === null || $cm === '' || !is_numeric($cm)) {
        return ['', ''];
    }
    $totalInches = (int) round(((float) $cm) / 2.54);
    return [intdiv($totalInches, 12), $totalInches % 12];
};
[$matching_min_height_ft, $matching_min_height_in] = $heightToFeetInches($matching_pref['min_height_cm'] ?? null);
[$matching_max_height_ft, $matching_max_height_in] = $heightToFeetInches($matching_pref['max_height_cm'] ?? null);
$divisions = [];
$division_result = mysqli_query($conn, 'SELECT id, name_bn, name_en FROM divisions ORDER BY name_en');
if ($division_result) {
    while ($row = mysqli_fetch_assoc($division_result)) $divisions[] = $row;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['settings_action'] ?? '') === 'update_profile_media') {
    $posted_token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($settings_csrf, $posted_token)) {
        $profile_media_error = 'The security token is invalid. Please refresh the page and try again.';
    } else {
        $voice_visibility = (string) ($_POST['voice_visibility'] ?? '');
        $video_visibility = (string) ($_POST['video_visibility'] ?? '');
        $valid_visibility = ['Everyone', 'Verified Users', 'Matched Users', 'Hidden'];

        if (!in_array($voice_visibility, $valid_visibility, true) || !in_array($video_visibility, $valid_visibility, true)) {
            $profile_media_error = 'Please select valid media visibility options.';
        } else {
            $voice_db = $voice_visibility === 'Hidden' ? 'Private' : $voice_visibility;
            $video_db = $video_visibility === 'Hidden' ? 'Private' : $video_visibility;
            mysqli_begin_transaction($conn);
            try {
                $voice_stmt = mysqli_prepare($conn, 'UPDATE profile_media SET visibility = ? WHERE user_id = ? AND media_type = "Voice Introduction" AND status = "Active"');
                if (!$voice_stmt) {
                    throw new RuntimeException('Unable to prepare voice visibility update.');
                }
                mysqli_stmt_bind_param($voice_stmt, 'si', $voice_db, $user_id);
                if (!mysqli_stmt_execute($voice_stmt)) {
                    mysqli_stmt_close($voice_stmt);
                    throw new RuntimeException('Unable to update voice introduction visibility.');
                }
                mysqli_stmt_close($voice_stmt);

                $video_stmt = mysqli_prepare($conn, 'UPDATE profile_media SET visibility = ? WHERE user_id = ? AND media_type = "Video Introduction" AND status = "Active"');
                if (!$video_stmt) {
                    throw new RuntimeException('Unable to prepare video visibility update.');
                }
                mysqli_stmt_bind_param($video_stmt, 'si', $video_db, $user_id);
                if (!mysqli_stmt_execute($video_stmt)) {
                    mysqli_stmt_close($video_stmt);
                    throw new RuntimeException('Unable to update video introduction visibility.');
                }
                mysqli_stmt_close($video_stmt);

                mysqli_commit($conn);
                $profile_media_message = 'Your active introduction media visibility has been updated successfully.';
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                $profile_media_error = $e->getMessage() ?: 'Unable to update media visibility right now. Please try again.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['settings_action'] ?? '') === 'update_security') {
    $posted_token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($settings_csrf, $posted_token)) {
        $security_error = 'The security token is invalid. Please refresh the page and try again.';
    } else {
        $current_password = (string) ($_POST['current_password'] ?? '');
        $new_password = (string) ($_POST['new_password'] ?? '');
        $confirm_password = (string) ($_POST['confirm_password'] ?? '');

        if ($current_password === '' || $new_password === '' || $confirm_password === '') {
            $security_error = 'Please complete all password fields.';
        } elseif (!password_verify($current_password, (string) ($current_user['password'] ?? ''))) {
            $security_error = 'Your current password is incorrect.';
        } elseif (strlen($new_password) < 8) {
            $security_error = 'New password must be at least 8 characters long.';
        } elseif (!preg_match('/[A-Z]/', $new_password)) {
            $security_error = 'New password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[a-z]/', $new_password)) {
            $security_error = 'New password must contain at least one lowercase letter.';
        } elseif (!preg_match('/[0-9]/', $new_password)) {
            $security_error = 'New password must contain at least one number.';
        } elseif ($new_password !== $confirm_password) {
            $security_error = 'New password and confirm password do not match.';
        } elseif (password_verify($new_password, (string) ($current_user['password'] ?? ''))) {
            $security_error = 'New password must be different from your current password.';
        } else {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update = mysqli_prepare($conn, 'UPDATE users SET password = ? WHERE user_id = ? LIMIT 1');
            mysqli_stmt_bind_param($update, 'si', $new_hash, $user_id);
            if (mysqli_stmt_execute($update)) {
                $security_message = 'Your password has been changed successfully.';
                $current_user['password'] = $new_hash;
            } else {
                $security_error = 'Unable to change your password right now. Please try again.';
            }
            mysqli_stmt_close($update);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['settings_action'] ?? '') === 'update_privacy') {
    $posted_token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($settings_csrf, $posted_token)) {
        $privacy_error = 'The security token is invalid. Please refresh the page and try again.';
    } else {
        $profile_visibility = (string) ($_POST['profile_visibility'] ?? '');
        $photo_visibility = (string) ($_POST['photo_visibility'] ?? '');
        $valid_profile_visibility = ['Public', 'Hidden'];
        $valid_photo_visibility = ['Everyone', 'Verified Users', 'Matched Users', 'Hidden'];

        if (!in_array($profile_visibility, $valid_profile_visibility, true)) {
            $privacy_error = 'Please select a valid profile visibility option.';
        } elseif (!in_array($photo_visibility, $valid_photo_visibility, true)) {
            $privacy_error = 'Please select a valid photo visibility option.';
        } else {
            $update = mysqli_prepare($conn, 'UPDATE user_profiles SET profile_visibility = ?, photo_visibility = ? WHERE user_id = ? LIMIT 1');
            if ($update) {
                mysqli_stmt_bind_param($update, 'ssi', $profile_visibility, $photo_visibility, $user_id);
                if (mysqli_stmt_execute($update)) {
                    $privacy_message = 'Your privacy settings have been updated successfully.';
                    $current_user['profile_visibility'] = $profile_visibility;
                    $current_user['photo_visibility'] = $photo_visibility;
                } else {
                    $privacy_error = 'Unable to update your privacy settings right now. Please try again.';
                }
                mysqli_stmt_close($update);
            } else {
                $privacy_error = 'Unable to prepare the privacy update. Please try again.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['settings_action'] ?? '') === 'update_account') {
    $posted_token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($settings_csrf, $posted_token)) {
        $account_error = 'The security token is invalid. Please refresh the page and try again.';
    } else {
        $first_name = trim((string) ($_POST['first_name'] ?? ''));
        $last_name = trim((string) ($_POST['last_name'] ?? ''));
        $mobile = trim((string) ($_POST['mobile'] ?? ''));

        $current_first_name = (string) ($current_user['first_name'] ?? '');
        $current_last_name = (string) ($current_user['last_name'] ?? '');
        $current_mobile = (string) ($current_user['mobile'] ?? '');
        $name_change_count = (int) ($current_user['name_change_count'] ?? 0);
        $mobile_change_count = (int) ($current_user['mobile_change_count'] ?? 0);
        $name_changed = ($first_name !== $current_first_name || $last_name !== $current_last_name);
        $mobile_changed = ($mobile !== $current_mobile);

        if ($first_name === '' || mb_strlen($first_name) > 50) {
            $account_error = 'First name is required and must be 50 characters or fewer.';
        } elseif ($last_name === '' || mb_strlen($last_name) > 50) {
            $account_error = 'Last name is required and must be 50 characters or fewer.';
        } elseif (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
            $account_error = 'Enter a valid mobile number using 10 to 15 digits.';
        } elseif ($name_changed && $name_change_count >= 2) {
            $account_error = 'Your name change limit has been reached. You cannot change your name again.';
        } elseif ($mobile_changed && $mobile_change_count >= 2) {
            $account_error = 'Your mobile number change limit has been reached. You cannot change your mobile number again.';
        } elseif ($name_changed || $mobile_changed) {
            $check = mysqli_prepare($conn, 'SELECT user_id FROM users WHERE mobile = ? AND user_id <> ? LIMIT 1');
            mysqli_stmt_bind_param($check, 'si', $mobile, $user_id);
            mysqli_stmt_execute($check);
            $duplicate = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
            mysqli_stmt_close($check);

            if ($duplicate) {
                $account_error = 'That mobile number is already associated with another account.';
            } else {
                $next_name_count = $name_change_count + ($name_changed ? 1 : 0);
                $next_mobile_count = $mobile_change_count + ($mobile_changed ? 1 : 0);

                $update = mysqli_prepare($conn, '
                    UPDATE users
                    SET first_name = ?,
                        last_name = ?,
                        mobile = ?,
                        name_change_count = ?,
                        mobile_change_count = ?
                    WHERE user_id = ?
                      AND name_change_count <= 2
                      AND mobile_change_count <= 2
                    LIMIT 1
                ');
                mysqli_stmt_bind_param($update, 'sssiii', $first_name, $last_name, $mobile, $next_name_count, $next_mobile_count, $user_id);
                if (mysqli_stmt_execute($update) && mysqli_stmt_affected_rows($update) === 1) {
                    $account_message = 'Account information updated successfully.';
                } else {
                    $account_error = 'Your change limit may have been reached. Please refresh the page and try again.';
                }
                mysqli_stmt_close($update);
            }
        } else {
            $account_message = 'No account information was changed.';
        }
    }

    // Reload the account state so the remaining change opportunities are shown immediately.
    $stmt = mysqli_prepare($conn, "
        SELECT
            u.user_id,
            u.first_name,
            u.last_name,
            u.gender,
            u.email,
            u.mobile,
            u.role,
            u.account_status,
            u.name_change_count,
            u.mobile_change_count,
            up.photo,
            up.profile_visibility,
            up.verification_status
        FROM users u
        LEFT JOIN user_profiles up ON up.user_id = u.user_id
        WHERE u.user_id = ?
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $current_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

$display_name = trim(($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? ''));
$display_name = $display_name !== '' ? $display_name : 'Member';
$profile_link = BASE_URL . 'profile/view_profile.php?user_id=' . $user_id;
$name_changes_used = (int) ($current_user['name_change_count'] ?? 0);
$name_changes_left = max(0, 2 - $name_changes_used);
$mobile_changes_used = (int) ($current_user['mobile_change_count'] ?? 0);
$mobile_changes_left = max(0, 2 - $mobile_changes_used);

$settings_sections = [
    [
        'id' => 'account',
        'icon' => 'fa-user-gear',
        'title' => 'Account',
        'description' => 'Manage your basic account information, contact details and account status.',
        'meta' => 'Personal account controls'
    ],
    [
        'id' => 'security',
        'icon' => 'fa-shield-halved',
        'title' => 'Security',
        'description' => 'Password, sign-in protection and other controls related to account security.',
        'meta' => 'Password & sign-in'
    ],
    [
        'id' => 'privacy',
        'icon' => 'fa-user-shield',
        'title' => 'Privacy',
        'description' => 'Control profile visibility and how selected profile information is shared.',
        'meta' => 'Visibility & privacy'
    ],
    [
        'id' => 'notifications',
        'icon' => 'fa-bell',
        'title' => 'Notifications',
        'description' => 'Choose how you want to receive important updates and activity notifications.',
        'meta' => 'Alerts & updates'
    ],
    [
        'id' => 'matching',
        'icon' => 'fa-heart',
        'title' => 'Matching Preferences',
        'description' => 'Set preferences that help shape your partner discovery experience.',
        'meta' => 'Partner discovery'
    ],
    [
        'id' => 'matching-weights',
        'icon' => 'fa-sliders',
        'title' => 'Matching Weights',
        'description' => 'Decide how strongly each matching factor should influence your compatibility score.',
        'meta' => 'Compatibility priorities'
    ],
    [
        'id' => 'profile-media',
        'icon' => 'fa-images',
        'title' => 'Profile & Media',
        'description' => 'Manage profile presentation, photos and supported introduction media.',
        'meta' => 'Profile presentation'
    ],
    [
        'id' => 'blocking',
        'icon' => 'fa-user-slash',
        'title' => 'Blocking & Reports',
        'description' => 'Review blocked users and access controls related to reporting and safety.',
        'meta' => 'Safety controls'
    ],
    [
        'id' => 'preferences',
        'icon' => 'fa-sliders',
        'title' => 'Preferences',
        'description' => '',
        'meta' => ''
    ],
    [
        'id' => 'account-control',
        'icon' => 'fa-user-lock',
        'title' => 'Account Control',
        'description' => 'Review account lifecycle options, including temporary deactivation and related controls.',
        'meta' => 'Account lifecycle'
    ],
];


$profile_media_records = [
    'Voice Introduction' => null,
    'Video Introduction' => null,
];
$media_stmt = mysqli_prepare($conn, '
    SELECT media_id, media_type, duration_seconds, visibility, status
    FROM profile_media
    WHERE user_id = ?
      AND media_type IN ("Voice Introduction", "Video Introduction")
      AND status IN ("Active", "Pending", "Rejected")
    ORDER BY FIELD(status, "Active", "Pending", "Rejected"), media_id DESC
');
if ($media_stmt) {
    mysqli_stmt_bind_param($media_stmt, 'i', $user_id);
    mysqli_stmt_execute($media_stmt);
    $media_result = mysqli_stmt_get_result($media_stmt);
    while ($media_result && ($media_row = mysqli_fetch_assoc($media_result))) {
        $type = (string) ($media_row['media_type'] ?? '');
        if (isset($profile_media_records[$type]) && $profile_media_records[$type] === null) {
            $profile_media_records[$type] = $media_row;
        }
    }
    mysqli_stmt_close($media_stmt);
}

$blocked_users = [];
$blocked_stmt = mysqli_prepare($conn, '
    SELECT b.blocked_user_id, u.first_name, u.last_name
    FROM user_blocks b
    INNER JOIN users u ON u.user_id = b.blocked_user_id
    WHERE b.blocker_user_id = ?
    ORDER BY b.created_at DESC, b.block_id DESC
');
if ($blocked_stmt) {
    mysqli_stmt_bind_param($blocked_stmt, 'i', $user_id);
    mysqli_stmt_execute($blocked_stmt);
    $blocked_result = mysqli_stmt_get_result($blocked_stmt);
    while ($blocked_result && ($row = mysqli_fetch_assoc($blocked_result))) $blocked_users[] = $row;
    mysqli_stmt_close($blocked_stmt);
}

$user_reports = [];
$reports_total = 0;
$reports_per_page = 10;
$reports_page = max(1, (int) ($_GET['report_page'] ?? 1));
$reports_count_stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM user_reports WHERE reporter_user_id = ?');
if ($reports_count_stmt) {
    mysqli_stmt_bind_param($reports_count_stmt, 'i', $user_id);
    mysqli_stmt_execute($reports_count_stmt);
    $reports_count_row = mysqli_fetch_assoc(mysqli_stmt_get_result($reports_count_stmt));
    $reports_total = (int) ($reports_count_row['total'] ?? 0);
    mysqli_stmt_close($reports_count_stmt);
}
$reports_total_pages = max(1, (int) ceil($reports_total / $reports_per_page));
$reports_page = min($reports_page, $reports_total_pages);
$reports_offset = ($reports_page - 1) * $reports_per_page;
$reports_stmt = mysqli_prepare($conn, '
    SELECT r.report_id, r.reported_user_id, r.reason, r.details, r.status, r.created_at,
           u.first_name, u.last_name
    FROM user_reports r
    INNER JOIN users u ON u.user_id = r.reported_user_id
    WHERE r.reporter_user_id = ?
    ORDER BY r.created_at DESC, r.report_id DESC
    LIMIT ? OFFSET ?
');
if ($reports_stmt) {
    mysqli_stmt_bind_param($reports_stmt, 'iii', $user_id, $reports_per_page, $reports_offset);
    mysqli_stmt_execute($reports_stmt);
    $reports_result = mysqli_stmt_get_result($reports_stmt);
    while ($reports_result && ($row = mysqli_fetch_assoc($reports_result))) $user_reports[] = $row;
    mysqli_stmt_close($reports_stmt);
}

include 'includes/header.php';
?>

<div class="settings-page">
    <header class="settings-topbar">
        <div class="settings-container settings-topbar-inner">
            <a href="<?= BASE_URL; ?>dashboard.php" class="settings-brand" aria-label="Back to Smart Matrimony Dashboard">
                <span class="settings-brand-logo">
                    <img src="<?= BASE_URL; ?>assets/images/logo/logo.png" alt="Smart Matrimony Logo">
                </span>
                <span class="settings-brand-title">
                    <img src="<?= BASE_URL; ?>assets/images/logo/matrimony_title.png" alt="Smart Matrimony">
                </span>
            </a>

            <a href="<?= BASE_URL; ?>dashboard.php" class="settings-dashboard-link">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>
    </header>

    <main class="settings-main">
        <div class="settings-container">
            <section class="settings-hero" aria-labelledby="settingsPageTitle">
                <div class="settings-hero-icon" aria-hidden="true">
                    <i class="fa-solid fa-gear"></i>
                </div>
                <div class="settings-hero-copy">
                    <span class="settings-kicker">ACCOUNT CONTROL CENTER</span>
                    <h1 id="settingsPageTitle">Settings</h1>
                    <p>Manage your Smart Matrimony account, privacy, security and personal experience from one place.</p>
                </div>
                <div class="settings-user-card">
                    <div class="settings-user-avatar">
                        <?php if (!empty($current_user['photo'])): ?>
                            <img src="<?= BASE_URL; ?>uploads/profile/<?= htmlspecialchars($current_user['photo']); ?>" alt="Profile photo">
                        <?php else: ?>
                            <i class="fa-solid <?= ($current_user['gender'] ?? '') === 'Female' ? 'fa-user-large' : 'fa-user'; ?>"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong><?= htmlspecialchars($display_name); ?></strong>
                        <span><?= htmlspecialchars($current_user['email'] ?? ''); ?></span>
                    </div>
                </div>
            </section>

            <section class="settings-summary" aria-label="Account summary">
                <div class="settings-summary-item">
                    <span class="settings-summary-label">Account Status</span>
                    <strong><?= htmlspecialchars($current_user['account_status'] ?? 'Active'); ?></strong>
                </div>
                <div class="settings-summary-item">
                    <span class="settings-summary-label">Verification</span>
                    <strong><?= htmlspecialchars($current_user['verification_status'] ?? 'Pending'); ?></strong>
                </div>
                <div class="settings-summary-item">
                    <span class="settings-summary-label">Profile Visibility</span>
                    <strong><?= htmlspecialchars($current_user['profile_visibility'] ?? 'Public'); ?></strong>
                </div>
            </section>

            <section class="settings-section-heading">
                <div>
                    <span class="settings-kicker">SETTINGS</span>
                    <h2>Manage your account</h2>
                </div>
            </section>

            <section class="settings-grid" aria-label="Settings categories">
                <?php foreach ($settings_sections as $section): ?>
                    <article class="settings-card <?= $section['id'] === 'account' ? 'settings-card-active' : ''; ?>" id="<?= htmlspecialchars($section['id']); ?>">
                        <div class="settings-card-icon" aria-hidden="true">
                            <i class="fa-solid <?= htmlspecialchars($section['icon']); ?>"></i>
                        </div>
                        <div class="settings-card-content">
                            <div class="settings-card-heading">
                                <h3><?= htmlspecialchars($section['title']); ?></h3>
                            </div>
                        </div>
                        <?php if (in_array($section['id'], ['account', 'security', 'privacy', 'notifications', 'matching', 'matching-weights', 'profile-media', 'blocking', 'preferences', 'account-control'], true)): ?>
                            <button type="button" class="settings-card-button settings-card-button-primary" data-settings-toggle="<?= htmlspecialchars($section['id'] === 'profile-media' ? 'profile-media-panel' : ($section['id'] === 'account' ? 'account-panel' : $section['id'] . '-panel')); ?>" aria-expanded="false" aria-controls="<?= htmlspecialchars($section['id'] === 'profile-media' ? 'profile-media-panel' : ($section['id'] === 'account' ? 'account-panel' : $section['id'] . '-panel')); ?>">
                                <span>Manage</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>
                        <?php else: ?>
                            <button type="button" class="settings-card-button" disabled aria-disabled="true">
                                <span>Manage</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="settings-detail-panel" id="account-panel" hidden aria-labelledby="account-panel-title">
                <div class="settings-detail-header">
                    <div>
                        <span class="settings-kicker">ACCOUNT</span>
                        <h2 id="account-panel-title">Basic account information</h2>
                        <p>Update the basic information stored with your Smart Matrimony account.</p>
                    </div>
                    <button type="button" class="settings-detail-close" data-settings-toggle="account-panel" aria-label="Close account settings">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <?php if ($account_message !== ''): ?>
                    <div class="settings-alert settings-alert-success" role="status">
                        <i class="fa-solid fa-circle-check"></i>
                        <span><?= htmlspecialchars($account_message); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($account_error !== ''): ?>
                    <div class="settings-alert settings-alert-error" role="alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($account_error); ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" class="settings-form" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>#account-panel">
                    <input type="hidden" name="settings_action" value="update_account">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($settings_csrf); ?>">

                    <div class="settings-change-limit" role="status">
                        <div class="settings-change-limit-icon"><i class="fa-solid fa-circle-info"></i></div>
                        <div>
                            <strong>Limited changes</strong>
                            <p>Name can be changed <b><?= $name_changes_left; ?></b> more <?= $name_changes_left === 1 ? 'time' : 'times'; ?>, and mobile number can be changed <b><?= $mobile_changes_left; ?></b> more <?= $mobile_changes_left === 1 ? 'time' : 'times'; ?>.</p>
                        </div>
                    </div>

                    <div class="settings-form-grid">
                        <label class="settings-field">
                            <span>First name</span>
                            <input type="text" name="first_name" maxlength="50" required value="<?= htmlspecialchars($current_user['first_name'] ?? ''); ?>" <?= $name_changes_left === 0 ? 'readonly' : ''; ?>>
                        </label>
                        <label class="settings-field">
                            <span>Last name</span>
                            <input type="text" name="last_name" maxlength="50" required value="<?= htmlspecialchars($current_user['last_name'] ?? ''); ?>" <?= $name_changes_left === 0 ? 'readonly' : ''; ?>>
                        </label>
                        <label class="settings-field">
                            <span>Mobile number</span>
                            <input type="tel" name="mobile" maxlength="15" inputmode="numeric" pattern="[0-9]{10,15}" required value="<?= htmlspecialchars($current_user['mobile'] ?? ''); ?>" <?= $mobile_changes_left === 0 ? 'readonly' : ''; ?>>
                        </label>
                        <label class="settings-field">
                            <span>Email address</span>
                            <input type="email" value="<?= htmlspecialchars($current_user['email'] ?? ''); ?>" readonly>
                            <small>Email changes are kept outside this basic account form.</small>
                        </label>
                    </div>

                    <div class="settings-form-footer">
                        <span><i class="fa-solid fa-circle-info"></i> Account status and verification are managed separately.</span>
                        <button type="submit" class="settings-save-button">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span>Save Changes</span>
                        </button>
                    </div>
                </form>
            </section>

            <section class="settings-detail-panel" id="security-panel" hidden aria-labelledby="security-panel-title">
                <div class="settings-detail-header">
                    <div>
                        <span class="settings-kicker">SECURITY</span>
                        <h2 id="security-panel-title">Password & sign-in security</h2>
                        <p>Change your Smart Matrimony account password securely. Your current password is required before a new password can be saved.</p>
                    </div>
                    <button type="button" class="settings-detail-close" data-settings-toggle="security-panel" aria-label="Close security settings">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <?php if ($security_message !== ''): ?>
                    <div class="settings-alert settings-alert-success" role="status">
                        <i class="fa-solid fa-circle-check"></i>
                        <span><?= htmlspecialchars($security_message); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($security_error !== ''): ?>
                    <div class="settings-alert settings-alert-error" role="alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($security_error); ?></span>
                    </div>
                <?php endif; ?>

                <div class="settings-security-note">
                    <i class="fa-solid fa-shield-halved"></i>
                    <div>
                        <strong>Password conditions</strong>
                        <p>Your new password must meet all four conditions below. The checks update as you type.</p>
                    </div>
                </div>

                <div class="settings-password-conditions" id="passwordConditions" aria-live="polite">
                    <div class="password-condition" data-condition="length"><i class="fa-solid fa-circle-xmark"></i><span>At least 8 characters</span></div>
                    <div class="password-condition" data-condition="uppercase"><i class="fa-solid fa-circle-xmark"></i><span>At least 1 uppercase letter (A-Z)</span></div>
                    <div class="password-condition" data-condition="lowercase"><i class="fa-solid fa-circle-xmark"></i><span>At least 1 lowercase letter (a-z)</span></div>
                    <div class="password-condition" data-condition="number"><i class="fa-solid fa-circle-xmark"></i><span>At least 1 number (0-9)</span></div>
                </div>

                <form method="post" class="settings-form" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>#security-panel">
                    <input type="hidden" name="settings_action" value="update_security">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($settings_csrf); ?>">

                    <div class="settings-form-grid settings-security-grid">
                        <label class="settings-field">
                            <span>Current password</span>
                            <input type="password" name="current_password" autocomplete="current-password" required>
                        </label>
                        <div></div>
                        <label class="settings-field">
                            <span>New password</span>
                            <input type="password" name="new_password" minlength="8" autocomplete="new-password" required aria-describedby="passwordConditions">
                        </label>
                        <label class="settings-field">
                            <span>Confirm new password</span>
                            <input type="password" name="confirm_password" minlength="8" autocomplete="new-password" required aria-describedby="passwordMatch">
                            <small class="settings-password-match" id="passwordMatch" aria-live="polite"></small>
                        </label>
                    </div>

                    <div class="settings-form-footer">
                        <span><i class="fa-solid fa-lock"></i> Password changes are protected by CSRF validation.</span>
                        <button type="submit" class="settings-save-button">
                            <i class="fa-solid fa-key"></i>
                            <span>Change Password</span>
                        </button>
                    </div>
                </form>
            </section>

            <section class="settings-detail-panel" id="notifications-panel" hidden aria-labelledby="notifications-panel-title">
                <div class="settings-detail-header">
                    <div>
                        <span class="settings-kicker">NOTIFICATIONS</span>
                        <h2 id="notifications-panel-title">Notification Control</h2>
                        <p>Use the slider below to turn the Smart Matrimony notification system on or off for everyone.</p>
                    </div>
                    <button type="button" class="settings-detail-close" data-settings-toggle="notifications-panel" aria-label="Close notification settings">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <?php if ($notification_message !== ''): ?>
                    <div class="settings-alert settings-alert-success" role="status">
                        <i class="fa-solid fa-circle-check"></i>
                        <span><?= htmlspecialchars($notification_message); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($notification_error !== ''): ?>
                    <div class="settings-alert settings-alert-error" role="alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($notification_error); ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" class="settings-form" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>#notifications-panel">
                    <input type="hidden" name="settings_action" value="update_notifications">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($settings_csrf); ?>">
                    <input type="hidden" name="notifications_enabled" id="notificationsEnabledInput" value="<?= $notifications_enabled ? '1' : '0'; ?>">

                    <div class="settings-notification-toggle-card">
                        <div class="settings-notification-toggle-copy">
                            <span class="settings-notification-toggle-icon"><i class="fa-solid fa-bell"></i></span>
                            <div>
                                <strong>Notifications</strong>
                                <p id="notificationsStatusText"><?= $notifications_enabled ? 'ON — notifications are enabled for everyone.' : 'OFF — notifications are disabled for everyone.'; ?></p>
                            </div>
                        </div>
                        <button type="button" class="settings-switch <?= $notifications_enabled ? 'is-on' : ''; ?>" id="notificationsSwitch" role="switch" aria-checked="<?= $notifications_enabled ? 'true' : 'false'; ?>" aria-label="Toggle notifications for everyone">
                            <span class="settings-switch-track"><span class="settings-switch-thumb"></span></span>
                            <span class="settings-switch-label" id="notificationsSwitchLabel"><?= $notifications_enabled ? 'ON' : 'OFF'; ?></span>
                        </button>
                    </div>

                    <div class="settings-notification-note">
                        <i class="fa-solid fa-circle-info"></i>
                        <div><strong>Global control</strong><p>This is one shared system control. Changing it affects notification availability for all users; it is not a per-user preference.</p></div>
                    </div>

                    <div class="settings-form-footer">
                        <span><i class="fa-solid fa-lock"></i> This setting is protected by CSRF validation.</span>
                        <button type="submit" class="settings-save-button">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span>Save Notification Setting</span>
                        </button>
                    </div>
                </form>
            </section>

            <section class="settings-detail-panel settings-matching-panel" id="matching-panel" hidden aria-labelledby="matching-panel-title">
                <div class="settings-detail-header">
                    <div>
                        <span class="settings-kicker">MATCHING PREFERENCES</span>
                        <h2 id="matching-panel-title">Partner discovery preferences</h2>
                        <p>Set the partner criteria you want Smart Matrimony to use when shaping your discovery and matching experience.</p>
                    </div>
                    <button type="button" class="settings-detail-close" data-settings-toggle="matching-panel" aria-label="Close matching preferences">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <?php if ($matching_message !== ''): ?>
                    <div class="settings-alert settings-alert-success" role="status"><i class="fa-solid fa-circle-check"></i><span><?= htmlspecialchars($matching_message); ?></span></div>
                <?php endif; ?>
                <?php if ($matching_error !== ''): ?>
                    <div class="settings-alert settings-alert-error" role="alert"><i class="fa-solid fa-circle-exclamation"></i><span><?= htmlspecialchars($matching_error); ?></span></div>
                <?php endif; ?>

                <form method="post" class="settings-form" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>#matching-panel">
                    <input type="hidden" name="settings_action" value="update_matching">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($settings_csrf); ?>">

                    <div class="settings-match-section">
                        <div class="settings-match-heading"><span><i class="fa-solid fa-sliders"></i></span><div><strong>Basic criteria</strong><small>Choose the broad range you prefer. Empty fields mean no specific limit.</small></div></div>
                        <div class="settings-form-grid settings-matching-grid">
                            <label class="settings-field"><span>Preferred gender</span><select name="preferred_gender" disabled><option value="Male" <?= ($matching_pref['preferred_gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option><option value="Female" <?= ($matching_pref['preferred_gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option></select><small>Automatically follows your account gender.</small></label>
                            <div></div>
                            <label class="settings-field"><span>Minimum age</span><input type="number" name="min_age" min="15" max="80" value="<?= htmlspecialchars((string)($matching_pref['min_age'] ?? '')); ?>" placeholder="Any"></label>
                            <label class="settings-field"><span>Maximum age</span><input type="number" name="max_age" min="15" max="80" value="<?= htmlspecialchars((string)($matching_pref['max_age'] ?? '')); ?>" placeholder="Any"></label>
                            <label class="settings-field"><span>Minimum height</span><div class="settings-height-selects"><select name="min_height_ft" aria-label="Minimum height feet"><option value="">Any</option><?php for ($ft = 4; $ft <= 8; $ft++): ?><option value="<?= $ft; ?>" <?= (string)$matching_min_height_ft === (string)$ft ? 'selected' : ''; ?>><?= $ft; ?> ft</option><?php endfor; ?></select><select name="min_height_in" aria-label="Minimum height inches"><option value="">Any</option><?php for ($inch = 0; $inch <= 11; $inch++): ?><option value="<?= $inch; ?>" <?= (string)$matching_min_height_in === (string)$inch ? 'selected' : ''; ?>><?= $inch; ?> in</option><?php endfor; ?></select></div></label>
                            <label class="settings-field"><span>Maximum height</span><div class="settings-height-selects"><select name="max_height_ft" aria-label="Maximum height feet"><option value="">Any</option><?php for ($ft = 4; $ft <= 8; $ft++): ?><option value="<?= $ft; ?>" <?= (string)$matching_max_height_ft === (string)$ft ? 'selected' : ''; ?>><?= $ft; ?> ft</option><?php endfor; ?></select><select name="max_height_in" aria-label="Maximum height inches"><option value="">Any</option><?php for ($inch = 0; $inch <= 11; $inch++): ?><option value="<?= $inch; ?>" <?= (string)$matching_max_height_in === (string)$inch ? 'selected' : ''; ?>><?= $inch; ?> in</option><?php endfor; ?></select></div></label>
                            <label class="settings-field"><span>Minimum weight (kg)</span><input type="number" name="min_weight_kg" min="1" max="500" step="0.1" value="<?= htmlspecialchars((string)($matching_pref['min_weight_kg'] ?? '')); ?>" placeholder="Any"></label>
                            <label class="settings-field"><span>Maximum weight (kg)</span><input type="number" name="max_weight_kg" min="1" max="500" step="0.1" value="<?= htmlspecialchars((string)($matching_pref['max_weight_kg'] ?? '')); ?>" placeholder="Any"></label>
                            <label class="settings-field"><span>Skin colour</span><select name="complexion"><option value="">Any skin colour</option><?php foreach ($complexions as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['complexion'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field"><span>Marital status</span><select name="marital_status"><option value="">Any marital status</option><?php foreach ($marital_statuses as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['marital_status'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field"><span>Family status</span><select name="family_status"><option value="">Any family status</option><?php foreach ($family_statuses as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['family_status'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                        </div>
                    </div>

                    <div class="settings-match-section">
                        <div class="settings-match-heading"><span><i class="fa-solid fa-book-open"></i></span><div><strong>Religion & values</strong><small>Islamic-specific fields become relevant when Islam is selected.</small></div></div>
                        <div class="settings-form-grid settings-matching-grid">
                            <label class="settings-field"><span>Religion</span><select name="religion" id="settingsMatchingReligion"><option value="">Any religion</option><?php foreach ($religions as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['religion'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field settings-islamic-field"><span>Madhhab</span><select name="madhhab"><option value="">Any madhhab</option><?php foreach ($madhhabs as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['madhhab'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field settings-islamic-field"><span>Prayer status</span><select name="prayer_status"><option value="">Any prayer practice</option><?php foreach ($prayer_statuses as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['prayer_status'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field settings-islamic-field"><span>Halal lifestyle</span><select name="halal_lifestyle"><option value="">Any</option><?php foreach ($halal_lifestyle_options as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['halal_lifestyle'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field settings-islamic-field"><span>Maḥram maintained</span><select name="mahram_maintained"><option value="">No preference</option><option value="1" <?= (string)($matching_pref['mahram_maintained'] ?? '') === '1' ? 'selected' : ''; ?>>Yes</option><option value="0" <?= (string)($matching_pref['mahram_maintained'] ?? '') === '0' ? 'selected' : ''; ?>>No</option></select></label>
                            <label class="settings-field settings-islamic-field"><span>Islamic knowledge</span><select name="islamic_knowledge"><option value="">Any level</option><?php foreach ($islamic_knowledge_levels as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['islamic_knowledge'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field settings-islamic-field"><span>Hijab status</span><select name="hijab_status"><option value="">Any</option><?php foreach ($hijab_statuses as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['hijab_status'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field settings-islamic-field"><span>Beard status</span><select name="beard_status"><option value="">Any</option><?php foreach ($beard_statuses as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['beard_status'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                        </div>
                    </div>

                    <div class="settings-match-section">
                        <div class="settings-match-heading"><span><i class="fa-solid fa-graduation-cap"></i></span><div><strong>Education, career & personality</strong><small>Optional criteria for education, profession and personal style.</small></div></div>
                        <div class="settings-form-grid settings-matching-grid">
                            <label class="settings-field"><span>Education</span><select name="education"><option value="">Any education level</option><?php foreach ($education_levels as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['education'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field"><span>Profession</span><select name="profession"><option value="">Any profession</option><?php foreach ($user_professions as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['profession'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field"><span>Minimum monthly income (BDT)</span><input type="number" name="min_monthly_income" min="0" max="99999999.99" step="1000" value="<?= htmlspecialchars((string)($matching_pref['min_monthly_income'] ?? '')); ?>" placeholder="No minimum"></label>
                            <label class="settings-field"><span>Personality type</span><select name="personality_type"><option value="">Any personality</option><?php foreach ($personality_types as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['personality_type'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                        </div>
                    </div>

                    <div class="settings-match-section">
                        <div class="settings-match-heading"><span><i class="fa-solid fa-location-dot"></i></span><div><strong>Preferred location</strong><small>Leave a location level empty to keep it open.</small></div></div>
                        <div class="settings-form-grid settings-matching-grid">
                            <label class="settings-field"><span>Division</span><select id="settings_matching_division" name="division_id" data-selected="<?= htmlspecialchars((string)($matching_pref['division_id'] ?? '')); ?>"><option value="">Any division</option><?php foreach ($divisions as $division): ?><option value="<?= (int)$division['id']; ?>" <?= (string)($matching_pref['division_id'] ?? '') === (string)$division['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($division['name_en']); ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field"><span>District</span><select id="settings_matching_district" name="district_id" data-selected="<?= htmlspecialchars((string)($matching_pref['district_id'] ?? '')); ?>" <?= empty($matching_pref['division_id']) ? 'disabled' : ''; ?>><option value="">Any district</option></select></label>
                            <label class="settings-field"><span>Upazila</span><select id="settings_matching_upazila" name="upazila_id" data-selected="<?= htmlspecialchars((string)($matching_pref['upazila_id'] ?? '')); ?>" <?= empty($matching_pref['district_id']) ? 'disabled' : ''; ?>><option value="">Any upazila</option></select></label>
                        </div>
                    </div>

                    <div class="settings-match-section">
                        <div class="settings-match-heading"><span><i class="fa-solid fa-heart-pulse"></i></span><div><strong>Lifestyle & health</strong><small>These controls are intentionally preference-based; “No preference” keeps the criterion open.</small></div></div>
                        <div class="settings-form-grid settings-matching-grid">
                            <label class="settings-field"><span>Smoking</span><select name="accept_smoker"><option value="1" <?= ($matching_pref['accept_smoker'] ?? '1') === '1' ? 'selected' : ''; ?>>No preference</option><option value="0" <?= ($matching_pref['accept_smoker'] ?? '1') === '0' ? 'selected' : ''; ?>>Prefer non-smoker</option></select></label>
                            <label class="settings-field"><span>Alcohol</span><select name="accept_alcohol"><option value="1" <?= ($matching_pref['accept_alcohol'] ?? '1') === '1' ? 'selected' : ''; ?>>No preference</option><option value="0" <?= ($matching_pref['accept_alcohol'] ?? '1') === '0' ? 'selected' : ''; ?>>Prefer non-drinker</option></select></label>
                            <label class="settings-field"><span>Disability</span><select name="accept_disability"><option value="1" <?= ($matching_pref['accept_disability'] ?? '1') === '1' ? 'selected' : ''; ?>>No preference</option><option value="0" <?= ($matching_pref['accept_disability'] ?? '1') === '0' ? 'selected' : ''; ?>>Prefer without disability</option></select></label>
                            <label class="settings-field"><span>Chronic disease</span><select name="accept_chronic_disease"><option value="1" <?= ($matching_pref['accept_chronic_disease'] ?? '1') === '1' ? 'selected' : ''; ?>>No preference</option><option value="0" <?= ($matching_pref['accept_chronic_disease'] ?? '1') === '0' ? 'selected' : ''; ?>>Prefer without chronic disease</option></select></label>
                            <label class="settings-field"><span>Blood group</span><select name="blood_group"><option value="">Any blood group</option><?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $item): ?><option value="<?= $item; ?>" <?= ($matching_pref['blood_group'] ?? '') === $item ? 'selected' : ''; ?>><?= $item; ?></option><?php endforeach; ?></select></label>
                        </div>
                        <label class="settings-field settings-field-full"><span>Additional preferences</span><textarea name="additional_preferences" maxlength="5000" rows="4" placeholder="Add any extra partner preferences you want to remember."><?= htmlspecialchars((string)($matching_pref['additional_preferences'] ?? '')); ?></textarea></label>
                    </div>

                    <div class="settings-form-footer">
                        <span><i class="fa-solid fa-lock"></i> Matching preferences are protected by CSRF validation and saved to your existing preference record.</span>
                        <button type="submit" class="settings-save-button"><i class="fa-solid fa-floppy-disk"></i><span>Save Matching Preferences</span></button>
                    </div>
                </form>
            </section>

            <section class="settings-detail-panel settings-matching-weights-panel" id="matching-weights-panel" hidden aria-labelledby="matching-weights-panel-title">
                <div class="settings-detail-header">
                    <div>
                        <span class="settings-kicker">MATCHING WEIGHTS</span>
                        <h2 id="matching-weights-panel-title">Set your match priorities</h2>
                        <p>Move each slider independently. Every factor can be set from 0% to 100%. The total must be exactly 100% before saving.</p>
                    </div>
                    <button type="button" class="settings-detail-close" data-settings-toggle="matching-weights-panel" aria-label="Close matching weights"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <form method="post" class="settings-matching-weights-form" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>#matching-weights-panel" id="matchingWeightsForm">
                    <input type="hidden" name="settings_action" value="update_matching_weights">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($settings_csrf); ?>">
                    <?php if ($matching_weights_message): ?><div class="settings-alert settings-alert-success"><i class="fa-solid fa-circle-check"></i><span><?= htmlspecialchars($matching_weights_message); ?></span></div><?php endif; ?>
                    <?php if ($matching_weights_error): ?><div class="settings-alert settings-alert-error"><i class="fa-solid fa-circle-exclamation"></i><span><?= htmlspecialchars($matching_weights_error); ?></span></div><?php endif; ?>
                    <div class="matching-weight-total" id="matchingWeightTotal" data-total="<?= array_sum($matching_weights); ?>">
                        <div><span>Current total</span><strong id="matchingWeightTotalValue"><?= array_sum($matching_weights); ?>%</strong></div>
                        <span class="matching-weight-status" id="matchingWeightStatus"></span>
                    </div>
                    <div class="matching-weight-list">
                    <?php
                    $weight_items = [
                        ['age_weight','Age','Age preference match','fa-cake-candles'],
                        ['height_weight','Height','Height preference match','fa-ruler-vertical'],
                        ['religion_weight','Religion','Religion preference match','fa-mosque'],
                        ['islamic_practice_weight','Islamic Practice','Madhhab, prayer and related Islamic preferences','fa-star-and-crescent'],
                        ['marital_status_weight','Marital Status','Marital-status preference match','fa-ring'],
                        ['education_weight','Education','Education preference match','fa-graduation-cap'],
                        ['profession_weight','Profession','Profession preference match','fa-briefcase'],
                        ['location_weight','Location','Location preference match','fa-location-dot'],
                        ['lifestyle_weight','Lifestyle','Personality and lifestyle preference match','fa-person-running'],
                        ['qna_weight','Q&A','Trait-question compatibility','fa-circle-question'],
                        ['skin_colour_weight','Skin Colour','Skin-colour preference match','fa-palette'],
                        ['weight_weight','Weight','Weight-range preference match','fa-weight-scale'],
                        ['family_status_weight','Family Status','Family-status preference match','fa-house-user'],
                    ];
                    foreach ($weight_items as [$field,$label,$desc,$icon]): $value=(int)($matching_weights[$field]??0); ?>
                        <label class="matching-weight-row">
                            <span class="matching-weight-icon"><i class="fa-solid <?= $icon; ?>"></i></span>
                            <span class="matching-weight-copy"><strong><?= htmlspecialchars($label); ?></strong><small><?= htmlspecialchars($desc); ?></small></span>
                            <span class="matching-weight-slider-wrap"><input class="matching-weight-slider" type="range" name="<?= htmlspecialchars($field); ?>" min="0" max="100" step="1" value="<?= $value; ?>" data-weight-slider><span class="matching-weight-scale"><span>0%</span><span>100%</span></span></span>
                            <output class="matching-weight-value" data-weight-value><?= $value; ?>%</output>
                        </label>
                    <?php endforeach; ?>
                    </div>
                    <div class="matching-weight-note"><i class="fa-solid fa-circle-info"></i><span>These percentages control the importance of the existing matching factors. Your age, height, location and other partner preferences remain separate and are still used by the existing matching logic.</span></div>
                    <div class="settings-form-footer"><span><i class="fa-solid fa-lock"></i> The server validates the total again before saving.</span><button type="submit" class="settings-save-button" id="matchingWeightsSave" disabled><i class="fa-solid fa-floppy-disk"></i><span>Save Matching Weights</span></button></div>
                </form>
            </section>

            <section class="settings-detail-panel settings-profile-media-panel" id="profile-media-panel" hidden aria-labelledby="profile-media-panel-title">
                <div class="settings-detail-header">
                    <div>
                        <span class="settings-kicker">PROFILE &amp; MEDIA</span>
                        <h2 id="profile-media-panel-title">Profile presentation &amp; introductions</h2>
                        <p>Review your profile photo and manage access to your active voice and video introductions.</p>
                    </div>
                    <button type="button" class="settings-detail-close" data-settings-toggle="profile-media-panel" aria-label="Close profile and media settings">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <?php if ($profile_media_message !== ''): ?>
                    <div class="settings-alert settings-alert-success" role="status"><i class="fa-solid fa-circle-check"></i><span><?= htmlspecialchars($profile_media_message); ?></span></div>
                <?php endif; ?>
                <?php if ($profile_media_error !== ''): ?>
                    <div class="settings-alert settings-alert-error" role="alert"><i class="fa-solid fa-circle-exclamation"></i><span><?= htmlspecialchars($profile_media_error); ?></span></div>
                <?php endif; ?>

                <div class="settings-media-overview">
                    <div class="settings-media-photo-card">
                        <div class="settings-media-photo-wrap">
                            <?php if (!empty($current_user['photo'])): ?>
                                <img src="<?= BASE_URL; ?>uploads/profile/<?= htmlspecialchars($current_user['photo']); ?>" alt="Your profile photo">
                            <?php else: ?>
                                <div class="settings-media-photo-placeholder"><i class="fa-solid fa-user"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="settings-media-photo-copy">
                            <strong>Profile photo</strong>
                            <span><?= !empty($current_user['photo']) ? 'Photo uploaded' : 'No profile photo uploaded'; ?></span>
                            <small>Visibility: <?= htmlspecialchars((string) ($current_user['photo_visibility'] ?? 'Verified Users')); ?></small>
                        </div>
                        <a class="settings-secondary-button" href="profile/step5.php"><i class="fa-solid fa-camera"></i><span>Manage photo &amp; recordings</span></a>
                    </div>

                    <div class="settings-media-status-grid">
                        <?php foreach (['Voice Introduction' => 'fa-microphone', 'Video Introduction' => 'fa-video'] as $media_type => $icon): ?>
                            <?php $media = $profile_media_records[$media_type]; ?>
                            <article class="settings-media-status-card">
                                <div class="settings-media-status-icon"><i class="fa-solid <?= $icon; ?>"></i></div>
                                <div class="settings-media-status-copy">
                                    <strong><?= htmlspecialchars($media_type); ?></strong>
                                    <span class="settings-media-status-pill <?= $media && $media['status'] === 'Active' ? 'is-active' : ''; ?>">
                                        <?= $media ? htmlspecialchars((string) $media['status']) : 'Not uploaded'; ?>
                                    </span>
                                    <?php if ($media && !empty($media['duration_seconds'])): ?><small><?= (int) $media['duration_seconds']; ?> sec</small><?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>

                <form method="post" class="settings-form" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>#profile-media-panel">
                    <input type="hidden" name="settings_action" value="update_profile_media">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($settings_csrf); ?>">

                    <div class="settings-media-visibility-section">
                        <div class="settings-match-heading">
                            <span><i class="fa-solid fa-eye"></i></span>
                            <div><strong>Introduction media visibility</strong><small>These controls apply to the currently active voice/video introductions. Upload or replace media from Profile Step 5.</small></div>
                        </div>
                        <div class="settings-form-grid settings-matching-grid">
                            <?php
                            $voice_display_visibility = (($profile_media_records['Voice Introduction']['visibility'] ?? 'Verified Users') === 'Private') ? 'Hidden' : ($profile_media_records['Voice Introduction']['visibility'] ?? 'Verified Users');
                            $video_display_visibility = (($profile_media_records['Video Introduction']['visibility'] ?? 'Verified Users') === 'Private') ? 'Hidden' : ($profile_media_records['Video Introduction']['visibility'] ?? 'Verified Users');
                            ?>
                            <label class="settings-field">
                                <span>Voice introduction visibility</span>
                                <select name="voice_visibility">
                                    <?php foreach (['Everyone', 'Verified Users', 'Matched Users', 'Hidden'] as $option): ?>
                                        <option value="<?= $option; ?>" <?= $voice_display_visibility === $option ? 'selected' : ''; ?>><?= $option; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($profile_media_records['Voice Introduction'])): ?><small>No active voice introduction yet.</small><?php endif; ?>
                            </label>
                            <label class="settings-field">
                                <span>Video introduction visibility</span>
                                <select name="video_visibility">
                                    <?php foreach (['Everyone', 'Verified Users', 'Matched Users', 'Hidden'] as $option): ?>
                                        <option value="<?= $option; ?>" <?= $video_display_visibility === $option ? 'selected' : ''; ?>><?= $option; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($profile_media_records['Video Introduction'])): ?><small>No active video introduction yet.</small><?php endif; ?>
                            </label>
                        </div>
                    </div>

                    <div class="settings-media-note">
                        <i class="fa-solid fa-circle-info"></i>
                        <div><strong>Media management stays in Profile Step 5</strong><p>This Settings section does not duplicate the recording/upload workflow. Use the existing Profile Step 5 page to record, replace or remove your voice/video introductions.</p></div>
                    </div>

                    <div class="settings-form-footer">
                        <span><i class="fa-solid fa-lock"></i> Media visibility changes are protected by CSRF validation.</span>
                        <button type="submit" class="settings-save-button"><i class="fa-solid fa-floppy-disk"></i><span>Save Media Visibility</span></button>
                    </div>
                </form>
            </section>

            <section class="settings-detail-panel settings-blocking-panel" id="blocking-panel" hidden aria-labelledby="blocking-panel-title">
                <div class="settings-detail-header">
                    <div>
                        <span class="settings-kicker">SAFETY</span>
                        <h2 id="blocking-panel-title">Blocking &amp; Reports</h2>
                    </div>
                    <button type="button" class="settings-detail-close" data-settings-toggle="blocking-panel" aria-label="Close blocking and reports settings">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <?php if ($blocking_message !== ''): ?>
                    <div class="settings-alert settings-alert-success" role="status"><i class="fa-solid fa-circle-check"></i><span><?= htmlspecialchars($blocking_message); ?></span></div>
                <?php endif; ?>
                <?php if ($blocking_error !== ''): ?>
                    <div class="settings-alert settings-alert-error" role="alert"><i class="fa-solid fa-circle-exclamation"></i><span><?= htmlspecialchars($blocking_error); ?></span></div>
                <?php endif; ?>

                <div class="settings-safety-actions">
                    <form method="post" class="settings-safety-card settings-block-user-form" id="blockUserForm" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>#blocking-panel">
                        <input type="hidden" name="settings_action" value="block_user">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($settings_csrf); ?>">
                        <input type="hidden" name="target_user_id" id="blockTargetUserId" value="">
                        <label class="settings-field" for="blockUserSearch">
                            <span>Search user</span>
                            <div class="settings-user-search">
                                <input type="text" id="blockUserSearch" placeholder="SM-000027 or User ID" autocomplete="off" aria-describedby="blockSearchMessage">
                                <button type="button" class="settings-user-search-button" id="blockUserSearchButton" aria-label="Search user"><i class="fa-solid fa-magnifying-glass"></i></button>
                            </div>
                        </label>
                        <div class="settings-user-search-message" id="blockSearchMessage" role="status"></div>
                        <div class="settings-user-result" id="blockUserResult" hidden></div>
                        <button type="submit" class="settings-save-button" id="blockUserButton" disabled><i class="fa-solid fa-user-slash"></i><span>Block</span></button>
                    </form>

                    <form method="post" class="settings-safety-card" id="reportUserForm" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>#blocking-panel">
                        <input type="hidden" name="settings_action" value="report_user">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($settings_csrf); ?>">
                        <input type="hidden" name="target_user_id" id="reportTargetUserId" value="">
                        <div class="settings-report-search-row">
                            <label class="settings-field" for="reportUserSearch">
                                <span>Search user</span>
                                <div class="settings-user-search">
                                    <input type="text" id="reportUserSearch" placeholder="SM-000027 or User ID" autocomplete="off" aria-describedby="reportSearchMessage">
                                    <button type="button" class="settings-user-search-button" id="reportUserSearchButton" aria-label="Search user"><i class="fa-solid fa-magnifying-glass"></i></button>
                                </div>
                            </label>
                            <label class="settings-field">
                                <span>Reason</span>
                                <select name="report_reason" id="reportReason" required disabled>
                                    <option value="">Select</option>
                                    <?php foreach (['Fake profile', 'Harassment', 'Inappropriate content', 'Scam or fraud', 'Impersonation', 'Other'] as $reason): ?>
                                        <option value="<?= htmlspecialchars($reason); ?>"><?= htmlspecialchars($reason); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <div class="settings-user-search-message" id="reportSearchMessage" role="status"></div>
                        <div class="settings-user-result" id="reportUserResult" hidden></div>
                        <label class="settings-field">
                            <span>Details</span>
                            <textarea name="report_details" rows="3" maxlength="1000" id="reportDetails" disabled></textarea>
                        </label>
                        <button type="submit" class="settings-save-button" id="reportUserButton" disabled><i class="fa-solid fa-flag"></i><span>Report</span></button>
                    </form>
                </div>

                <div class="settings-safety-list">
                    <div class="settings-safety-list-head"><h3>Blocked users</h3></div>
                    <?php if ($blocked_users): ?>
                        <?php foreach ($blocked_users as $blocked): ?>
                            <div class="settings-safety-row">
                                <div>
                                    <strong><?= htmlspecialchars(trim(($blocked['first_name'] ?? '') . ' ' . ($blocked['last_name'] ?? ''))); ?></strong>
                                    <span><?= htmlspecialchars(settings_public_id($blocked['blocked_user_id'])); ?></span>
                                </div>
                                <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>#blocking-panel">
                                    <input type="hidden" name="settings_action" value="unblock_user">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($settings_csrf); ?>">
                                    <input type="hidden" name="blocked_user_id" value="<?= (int) $blocked['blocked_user_id']; ?>">
                                    <button type="submit" class="settings-secondary-button"><i class="fa-solid fa-user-check"></i><span>Unblock</span></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="settings-safety-empty">No blocked users.</p>
                    <?php endif; ?>
                </div>

                <div class="settings-safety-list">
                    <div class="settings-safety-list-head"><h3>Reports</h3></div>
                    <?php if ($user_reports): ?>
                        <?php foreach ($user_reports as $report): ?>
                            <div class="settings-safety-report">
                                <div class="settings-safety-report-top">
                                    <strong><?= htmlspecialchars(settings_public_id($report['reported_user_id'])); ?> · <?= htmlspecialchars($report['reason']); ?></strong>
                                    <span><?= htmlspecialchars($report['status']); ?></span>
                                </div>
                                <?php if (trim((string) ($report['details'] ?? '')) !== ''): ?><p><?= nl2br(htmlspecialchars($report['details'])); ?></p><?php endif; ?>
                                <small><?= htmlspecialchars(date('d M Y, H:i', strtotime($report['created_at']))); ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="settings-safety-empty">No reports.</p>
                    <?php endif; ?>
                    <?php if ($reports_total_pages > 1): ?>
                        <div class="settings-safety-pagination" aria-label="Reports pagination">
                            <?php if ($reports_page > 1): ?>
                                <a href="?report_page=<?= $reports_page - 1; ?>#blocking-panel" class="settings-secondary-button">Previous</a>
                            <?php endif; ?>
                            <span><?= $reports_page; ?> / <?= $reports_total_pages; ?></span>
                            <?php if ($reports_page < $reports_total_pages): ?>
                                <a href="?report_page=<?= $reports_page + 1; ?>#blocking-panel" class="settings-secondary-button">Next</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="settings-detail-panel settings-preferences-panel" id="preferences-panel" hidden aria-labelledby="preferences-panel-title">
                <div class="settings-detail-header">
                    <div>
                        <span class="settings-kicker">PREFERENCES</span>
                        <h2 id="preferences-panel-title">Partner Preferences</h2>
                        <p>Manage the same partner preferences you set in Profile Step 6. Questions are kept out of this Settings section.</p>
                    </div>
                    <button type="button" class="settings-detail-close" data-settings-toggle="preferences-panel" aria-label="Close preferences">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <?php if ($preferences_message !== ''): ?>
                    <div class="settings-alert settings-alert-success" role="status"><i class="fa-solid fa-circle-check"></i><span><?= htmlspecialchars($preferences_message); ?></span></div>
                <?php endif; ?>
                <?php if ($preferences_error !== ''): ?>
                    <div class="settings-alert settings-alert-error" role="alert"><i class="fa-solid fa-circle-exclamation"></i><span><?= htmlspecialchars($preferences_error); ?></span></div>
                <?php endif; ?>

                <form method="post" class="settings-form" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>#preferences-panel">
                    <input type="hidden" name="settings_action" value="update_matching">
                    <input type="hidden" name="settings_return_section" value="preferences">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($settings_csrf); ?>">
                    <input type="hidden" name="preferred_gender" value="<?= htmlspecialchars((string)($matching_pref['preferred_gender'] ?? '')); ?>">

                    <div class="settings-match-section">
                        <div class="settings-match-heading"><span><i class="fa-solid fa-sliders"></i></span><div><strong>Basic Partner Preferences</strong><small>Choose the broad range you prefer. Empty fields mean no specific limit.</small></div></div>
                        <div class="settings-form-grid settings-matching-grid">
                            <label class="settings-field"><span>Preferred gender</span><select disabled><option value="Male" <?= ($matching_pref['preferred_gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option><option value="Female" <?= ($matching_pref['preferred_gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option></select><small>Automatically follows your account gender.</small></label>
                            <div></div>
                            <label class="settings-field"><span>Minimum age</span><input type="number" name="min_age" min="15" max="80" value="<?= htmlspecialchars((string)($matching_pref['min_age'] ?? '')); ?>" placeholder="Any"></label>
                            <label class="settings-field"><span>Maximum age</span><input type="number" name="max_age" min="15" max="80" value="<?= htmlspecialchars((string)($matching_pref['max_age'] ?? '')); ?>" placeholder="Any"></label>
                            <label class="settings-field"><span>Minimum height</span><div class="settings-height-selects"><select name="min_height_ft" aria-label="Minimum height feet"><option value="">Any</option><?php for ($ft = 4; $ft <= 8; $ft++): ?><option value="<?= $ft; ?>" <?= (string)$matching_min_height_ft === (string)$ft ? 'selected' : ''; ?>><?= $ft; ?> ft</option><?php endfor; ?></select><select name="min_height_in" aria-label="Minimum height inches"><option value="">Any</option><?php for ($inch = 0; $inch <= 11; $inch++): ?><option value="<?= $inch; ?>" <?= (string)$matching_min_height_in === (string)$inch ? 'selected' : ''; ?>><?= $inch; ?> in</option><?php endfor; ?></select></div></label>
                            <label class="settings-field"><span>Maximum height</span><div class="settings-height-selects"><select name="max_height_ft" aria-label="Maximum height feet"><option value="">Any</option><?php for ($ft = 4; $ft <= 8; $ft++): ?><option value="<?= $ft; ?>" <?= (string)$matching_max_height_ft === (string)$ft ? 'selected' : ''; ?>><?= $ft; ?> ft</option><?php endfor; ?></select><select name="max_height_in" aria-label="Maximum height inches"><option value="">Any</option><?php for ($inch = 0; $inch <= 11; $inch++): ?><option value="<?= $inch; ?>" <?= (string)$matching_max_height_in === (string)$inch ? 'selected' : ''; ?>><?= $inch; ?> in</option><?php endfor; ?></select></div></label>
                            <label class="settings-field"><span>Minimum weight (kg)</span><input type="number" name="min_weight_kg" min="1" max="500" step="0.1" value="<?= htmlspecialchars((string)($matching_pref['min_weight_kg'] ?? '')); ?>" placeholder="Any"></label>
                            <label class="settings-field"><span>Maximum weight (kg)</span><input type="number" name="max_weight_kg" min="1" max="500" step="0.1" value="<?= htmlspecialchars((string)($matching_pref['max_weight_kg'] ?? '')); ?>" placeholder="Any"></label>
                            <label class="settings-field"><span>Skin colour</span><select name="complexion"><option value="">Any skin colour</option><?php foreach ($complexions as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['complexion'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field"><span>Marital status</span><select name="marital_status"><option value="">Any marital status</option><?php foreach ($marital_statuses as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['marital_status'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field"><span>Family status</span><select name="family_status"><option value="">Any family status</option><?php foreach ($family_statuses as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['family_status'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                        </div>
                    </div>

                    <div class="settings-match-section">
                        <div class="settings-match-heading"><span><i class="fa-solid fa-book-open"></i></span><div><strong>Religion &amp; values</strong><small>Islamic-specific fields become relevant when Islam is selected.</small></div></div>
                        <div class="settings-form-grid settings-matching-grid">
                            <label class="settings-field"><span>Religion</span><select name="religion" id="settingsPreferencesReligion"><option value="">Any religion</option><?php foreach ($religions as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['religion'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field settings-preferences-islamic-field"><span>Madhhab</span><select name="madhhab"><option value="">Any madhhab</option><?php foreach ($madhhabs as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['madhhab'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field settings-preferences-islamic-field"><span>Prayer status</span><select name="prayer_status"><option value="">Any prayer practice</option><?php foreach ($prayer_statuses as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['prayer_status'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field settings-preferences-islamic-field"><span>Halal lifestyle</span><select name="halal_lifestyle"><option value="">Any</option><?php foreach ($halal_lifestyle_options as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['halal_lifestyle'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field settings-preferences-islamic-field"><span>Maḥram maintained</span><select name="mahram_maintained"><option value="">No preference</option><option value="1" <?= (string)($matching_pref['mahram_maintained'] ?? '') === '1' ? 'selected' : ''; ?>>Yes</option><option value="0" <?= (string)($matching_pref['mahram_maintained'] ?? '') === '0' ? 'selected' : ''; ?>>No</option></select></label>
                            <label class="settings-field settings-preferences-islamic-field"><span>Islamic knowledge</span><select name="islamic_knowledge"><option value="">Any level</option><?php foreach ($islamic_knowledge_levels as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['islamic_knowledge'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field settings-preferences-islamic-field"><span>Hijab status</span><select name="hijab_status"><option value="">Any</option><?php foreach ($hijab_statuses as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['hijab_status'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field settings-preferences-islamic-field"><span>Beard status</span><select name="beard_status"><option value="">Any</option><?php foreach ($beard_statuses as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['beard_status'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                        </div>
                    </div>

                    <div class="settings-match-section">
                        <div class="settings-match-heading"><span><i class="fa-solid fa-graduation-cap"></i></span><div><strong>Education, career &amp; personality</strong><small>Optional criteria for education, profession and personal style.</small></div></div>
                        <div class="settings-form-grid settings-matching-grid">
                            <label class="settings-field"><span>Education</span><select name="education"><option value="">Any education level</option><?php foreach ($education_levels as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['education'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field"><span>Profession</span><select name="profession"><option value="">Any profession</option><?php foreach ($user_professions as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['profession'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field"><span>Minimum monthly income (BDT)</span><input type="number" name="min_monthly_income" min="0" max="99999999.99" step="1000" value="<?= htmlspecialchars((string)($matching_pref['min_monthly_income'] ?? '')); ?>" placeholder="No minimum"></label>
                            <label class="settings-field"><span>Personality type</span><select name="personality_type"><option value="">Any personality</option><?php foreach ($personality_types as $item): ?><option value="<?= htmlspecialchars($item); ?>" <?= ($matching_pref['personality_type'] ?? '') === $item ? 'selected' : ''; ?>><?= htmlspecialchars($item); ?></option><?php endforeach; ?></select></label>
                        </div>
                    </div>

                    <div class="settings-match-section">
                        <div class="settings-match-heading"><span><i class="fa-solid fa-location-dot"></i></span><div><strong>Preferred location</strong><small>Leave a location level empty to keep it open.</small></div></div>
                        <div class="settings-form-grid settings-matching-grid">
                            <label class="settings-field"><span>Division</span><select id="settings_preferences_division" name="division_id" data-selected="<?= htmlspecialchars((string)($matching_pref['division_id'] ?? '')); ?>"><option value="">Any division</option><?php foreach ($divisions as $division): ?><option value="<?= (int)$division['id']; ?>" <?= (string)($matching_pref['division_id'] ?? '') === (string)$division['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($division['name_en']); ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field"><span>District</span><select id="settings_preferences_district" name="district_id" data-selected="<?= htmlspecialchars((string)($matching_pref['district_id'] ?? '')); ?>" <?= empty($matching_pref['division_id']) ? 'disabled' : ''; ?>><option value="">Any district</option></select></label>
                            <label class="settings-field"><span>Upazila</span><select id="settings_preferences_upazila" name="upazila_id" data-selected="<?= htmlspecialchars((string)($matching_pref['upazila_id'] ?? '')); ?>" <?= empty($matching_pref['district_id']) ? 'disabled' : ''; ?>><option value="">Any upazila</option></select></label>
                        </div>
                    </div>

                    <div class="settings-match-section">
                        <div class="settings-match-heading"><span><i class="fa-solid fa-heart-pulse"></i></span><div><strong>Lifestyle &amp; health</strong><small>“No preference” keeps the criterion open.</small></div></div>
                        <div class="settings-form-grid settings-matching-grid">
                            <label class="settings-field"><span>Smoking</span><select name="accept_smoker"><option value="1" <?= ($matching_pref['accept_smoker'] ?? '1') === '1' ? 'selected' : ''; ?>>No preference</option><option value="0" <?= ($matching_pref['accept_smoker'] ?? '1') === '0' ? 'selected' : ''; ?>>Prefer non-smoker</option></select></label>
                            <label class="settings-field"><span>Alcohol</span><select name="accept_alcohol"><option value="1" <?= ($matching_pref['accept_alcohol'] ?? '1') === '1' ? 'selected' : ''; ?>>No preference</option><option value="0" <?= ($matching_pref['accept_alcohol'] ?? '1') === '0' ? 'selected' : ''; ?>>Prefer non-drinker</option></select></label>
                            <label class="settings-field"><span>Disability</span><select name="accept_disability"><option value="1" <?= ($matching_pref['accept_disability'] ?? '1') === '1' ? 'selected' : ''; ?>>No preference</option><option value="0" <?= ($matching_pref['accept_disability'] ?? '1') === '0' ? 'selected' : ''; ?>>Prefer without disability</option></select></label>
                            <label class="settings-field"><span>Chronic disease</span><select name="accept_chronic_disease"><option value="1" <?= ($matching_pref['accept_chronic_disease'] ?? '1') === '1' ? 'selected' : ''; ?>>No preference</option><option value="0" <?= ($matching_pref['accept_chronic_disease'] ?? '1') === '0' ? 'selected' : ''; ?>>Prefer without chronic disease</option></select></label>
                            <label class="settings-field"><span>Blood group</span><select name="blood_group"><option value="">Any blood group</option><?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $item): ?><option value="<?= $item; ?>" <?= ($matching_pref['blood_group'] ?? '') === $item ? 'selected' : ''; ?>><?= $item; ?></option><?php endforeach; ?></select></label>
                        </div>
                        <label class="settings-field settings-field-full"><span>Additional preferences</span><textarea name="additional_preferences" maxlength="5000" rows="4" placeholder="Add any extra partner preferences you want to remember."><?= htmlspecialchars((string)($matching_pref['additional_preferences'] ?? '')); ?></textarea></label>
                    </div>

                    <div class="settings-form-footer">
                        <span><i class="fa-solid fa-lock"></i> These are the same partner preferences used by Profile Step 6.</span>
                        <button type="submit" class="settings-save-button"><i class="fa-solid fa-floppy-disk"></i><span>Save Preferences</span></button>
                    </div>
                </form>
            </section>

            <section class="settings-detail-panel settings-account-control-panel" id="account-control-panel" hidden aria-labelledby="account-control-panel-title">
                <div class="settings-detail-header">
                    <div>
                        <span class="settings-kicker">ACCOUNT</span>
                        <h2 id="account-control-panel-title">Account Control</h2>
                    </div>
                    <button type="button" class="settings-detail-close" data-settings-toggle="account-control-panel" aria-label="Close account control">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <?php if ($account_error !== ''): ?>
                    <div class="settings-alert settings-alert-error" role="alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($account_error); ?></span>
                    </div>
                <?php endif; ?>

                <div class="settings-account-control-grid">
                    <section class="settings-account-control-card">
                        <div class="settings-account-control-icon"><i class="fa-solid fa-pause"></i></div>
                        <div class="settings-account-control-body">
                            <h3>Deactivate account</h3>
                            <span>Current status: <?= htmlspecialchars($current_user['account_status'] ?? 'Active'); ?></span>
                            <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>#account-control-panel" class="settings-account-control-form" data-account-action="deactivate">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($settings_csrf); ?>">
                                <input type="hidden" name="settings_action" value="deactivate_account">
                                <input type="password" name="control_password" autocomplete="current-password" placeholder="Current password" required>
                                <button type="submit" class="settings-account-control-button settings-account-control-button-secondary">
                                    <i class="fa-solid fa-pause"></i>
                                    <span>Deactivate</span>
                                </button>
                            </form>
                        </div>
                    </section>

                    <section class="settings-account-control-card settings-account-control-danger">
                        <div class="settings-account-control-icon"><i class="fa-solid fa-trash"></i></div>
                        <div class="settings-account-control-body">
                            <h3>Delete account</h3>
                            <span>Permanent</span>
                            <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>#account-control-panel" class="settings-account-control-form" data-account-action="delete">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($settings_csrf); ?>">
                                <input type="hidden" name="settings_action" value="delete_account">
                                <input type="password" name="control_password" autocomplete="current-password" placeholder="Current password" required>
                                <label class="settings-account-confirm">
                                    <input type="checkbox" name="confirm_delete" value="1" required>
                                    <span>I want to permanently delete my account</span>
                                </label>
                                <button type="submit" class="settings-account-control-button settings-account-control-button-danger">
                                    <i class="fa-solid fa-trash"></i>
                                    <span>Delete account</span>
                                </button>
                            </form>
                        </div>
                    </section>
                </div>
            </section>

            <section class="settings-detail-panel" id="privacy-panel" hidden aria-labelledby="privacy-panel-title">
                <div class="settings-detail-header">
                    <div>
                        <span class="settings-kicker">PRIVACY</span>
                        <h2 id="privacy-panel-title">Profile visibility & photo privacy</h2>
                        <p>Control whether your profile appears to other users and who can see your profile photo.</p>
                    </div>
                    <button type="button" class="settings-detail-close" data-settings-toggle="privacy-panel" aria-label="Close privacy settings">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <?php if ($privacy_message !== ''): ?>
                    <div class="settings-alert settings-alert-success" role="status">
                        <i class="fa-solid fa-circle-check"></i>
                        <span><?= htmlspecialchars($privacy_message); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($privacy_error !== ''): ?>
                    <div class="settings-alert settings-alert-error" role="alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($privacy_error); ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" class="settings-form" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>#privacy-panel">
                    <input type="hidden" name="settings_action" value="update_privacy">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($settings_csrf); ?>">

                    <div class="settings-form-grid">
                        <label class="settings-field">
                            <span>Profile visibility</span>
                            <select name="profile_visibility" required>
                                <option value="Public" <?= ($current_user['profile_visibility'] ?? 'Public') === 'Public' ? 'selected' : ''; ?>>Public — visible in profile discovery</option>
                                <option value="Hidden" <?= ($current_user['profile_visibility'] ?? '') === 'Hidden' ? 'selected' : ''; ?>>Hidden — hide profile from discovery</option>
                            </select>
                        </label>
                        <label class="settings-field">
                            <span>Profile photo visibility</span>
                            <select name="photo_visibility" required>
                                <option value="Everyone" <?= ($current_user['photo_visibility'] ?? 'Verified Users') === 'Everyone' ? 'selected' : ''; ?>>Everyone</option>
                                <option value="Verified Users" <?= ($current_user['photo_visibility'] ?? 'Verified Users') === 'Verified Users' ? 'selected' : ''; ?>>Verified Users</option>
                                <option value="Matched Users" <?= ($current_user['photo_visibility'] ?? '') === 'Matched Users' ? 'selected' : ''; ?>>Matched Users</option>
                                <option value="Hidden" <?= ($current_user['photo_visibility'] ?? '') === 'Hidden' ? 'selected' : ''; ?>>Hidden</option>
                            </select>
                        </label>
                    </div>

                    <div class="settings-privacy-note">
                        <i class="fa-solid fa-user-shield"></i>
                        <div>
                            <strong>Privacy controls</strong>
                            <p>These settings only control the visibility fields available in your current profile database. They do not change your account status or verification status.</p>
                        </div>
                    </div>

                    <div class="settings-form-footer">
                        <span><i class="fa-solid fa-lock"></i> Privacy changes are protected by CSRF validation.</span>
                        <button type="submit" class="settings-save-button">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span>Save Privacy Settings</span>
                        </button>
                    </div>
                </form>
            </section>


        </div>
    </main>
</div>

<script>
(function () {
    const toggleButtons = document.querySelectorAll('[data-settings-toggle]');
    if (!toggleButtons.length) return;

    function getPanel(button) {
        const id = button.getAttribute('data-settings-toggle');
        return id ? document.getElementById(id) : null;
    }

    function setPanel(panel, open, moveFocus) {
        if (!panel) return;
        panel.hidden = !open;
        toggleButtons.forEach(function (button) {
            if (button.getAttribute('data-settings-toggle') === panel.id && button.hasAttribute('aria-expanded')) {
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
        });
        if (open && moveFocus) {
            const field = panel.querySelector('input:not([type="hidden"])');
            if (field) field.focus();
        }
    }

    toggleButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const panel = getPanel(button);
            if (!panel) return;
            const open = panel.hidden;
            setPanel(panel, open, true);
            if (open) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });


    const blockUserForm = document.getElementById('blockUserForm');
    const blockUserSearch = document.getElementById('blockUserSearch');
    const blockUserSearchButton = document.getElementById('blockUserSearchButton');
    const blockUserResult = document.getElementById('blockUserResult');
    const blockUserMessage = document.getElementById('blockSearchMessage');
    const blockTargetUserId = document.getElementById('blockTargetUserId');
    const blockUserButton = document.getElementById('blockUserButton');

    function clearBlockUserSelection() {
        if (blockTargetUserId) blockTargetUserId.value = '';
        if (blockUserButton) blockUserButton.disabled = true;
        if (blockUserResult) {
            blockUserResult.hidden = true;
            blockUserResult.innerHTML = '';
            blockUserResult.classList.remove('is-selected');
        }
    }

    function unselectBlockUser() {
        if (blockTargetUserId) blockTargetUserId.value = '';
        if (blockUserButton) blockUserButton.disabled = true;
        if (blockUserResult) {
            blockUserResult.classList.remove('is-selected');
            const button = blockUserResult.querySelector('.settings-user-select-button');
            if (button) button.textContent = 'Select';
        }
    }

    async function searchBlockUser() {
        if (!blockUserSearch || !blockUserResult || !blockUserMessage) return;
        const query = blockUserSearch.value.trim();
        clearBlockUserSelection();
        blockUserMessage.textContent = '';
        if (!query) {
            blockUserMessage.textContent = 'Enter a User ID.';
            return;
        }

        blockUserSearchButton.disabled = true;
        blockUserMessage.textContent = 'Searching…';
        try {
            const url = 'settings.php?settings_lookup_user=1&user_id=' + encodeURIComponent(query);
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            const data = await response.json();
            if (!response.ok || !data.success || !data.user) {
                blockUserMessage.textContent = data.message || 'User not found.';
                return;
            }

            const user = data.user;
            const photo = user.photo ? document.createElement('img') : document.createElement('div');
            photo.className = 'settings-user-result-photo';
            if (user.photo) {
                photo.src = user.photo;
                photo.alt = user.name || user.public_id;
            } else {
                photo.classList.add('settings-user-result-placeholder');
                photo.innerHTML = '<i class="fa-solid fa-user"></i>';
            }

            const info = document.createElement('div');
            info.className = 'settings-user-result-name';
            const name = document.createElement('strong');
            name.textContent = user.name || 'Unnamed user';
            const publicId = document.createElement('span');
            publicId.textContent = user.public_id;
            info.appendChild(name);
            info.appendChild(publicId);

            const selectButton = document.createElement('button');
            selectButton.type = 'button';
            selectButton.className = 'settings-user-select-button';
            selectButton.textContent = 'Select';
            selectButton.addEventListener('click', function () {
                const isSelected = blockTargetUserId.value === String(user.id);
                if (isSelected) {
                    unselectBlockUser();
                    blockUserMessage.textContent = user.public_id + ' unselected.';
                    return;
                }
                blockTargetUserId.value = String(user.id);
                blockUserButton.disabled = false;
                blockUserResult.classList.add('is-selected');
                selectButton.textContent = 'Unselect';
                blockUserMessage.textContent = user.public_id + ' selected.';
            });

            blockUserResult.appendChild(photo);
            blockUserResult.appendChild(info);
            blockUserResult.appendChild(selectButton);
            blockUserResult.hidden = false;
            blockUserMessage.textContent = '';
        } catch (error) {
            blockUserMessage.textContent = 'Unable to search right now.';
        } finally {
            blockUserSearchButton.disabled = false;
        }
    }

    if (blockUserSearchButton) blockUserSearchButton.addEventListener('click', searchBlockUser);
    if (blockUserSearch) {
        blockUserSearch.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                searchBlockUser();
            }
        });
        blockUserSearch.addEventListener('input', clearBlockUserSelection);
    }
    if (blockUserForm) {
        blockUserForm.addEventListener('submit', function (event) {
            if (!blockTargetUserId || !blockTargetUserId.value) {
                event.preventDefault();
                if (blockUserMessage) blockUserMessage.textContent = 'Search and select a user first.';
            }
        });
    }

    const reportUserForm = document.getElementById('reportUserForm');
    const reportUserSearch = document.getElementById('reportUserSearch');
    const reportUserSearchButton = document.getElementById('reportUserSearchButton');
    const reportUserResult = document.getElementById('reportUserResult');
    const reportUserMessage = document.getElementById('reportSearchMessage');
    const reportTargetUserId = document.getElementById('reportTargetUserId');
    const reportReason = document.getElementById('reportReason');
    const reportDetails = document.getElementById('reportDetails');
    const reportUserButton = document.getElementById('reportUserButton');

    function updateReportButtonState() {
        if (!reportUserButton) return;
        reportUserButton.disabled = !(reportTargetUserId && reportTargetUserId.value && reportReason && reportReason.value);
    }

    function clearReportUserSelection() {
        if (reportTargetUserId) reportTargetUserId.value = '';
        if (reportUserResult) {
            reportUserResult.hidden = true;
            reportUserResult.innerHTML = '';
            reportUserResult.classList.remove('is-selected');
        }
        if (reportReason) {
            reportReason.value = '';
            reportReason.disabled = true;
        }
        if (reportDetails) {
            reportDetails.value = '';
            reportDetails.disabled = true;
        }
        updateReportButtonState();
    }

    function unselectReportUser() {
        if (reportTargetUserId) reportTargetUserId.value = '';
        if (reportUserResult) {
            reportUserResult.classList.remove('is-selected');
            const button = reportUserResult.querySelector('.settings-user-select-button');
            if (button) button.textContent = 'Select';
        }
        if (reportReason) {
            reportReason.value = '';
            reportReason.disabled = true;
        }
        if (reportDetails) {
            reportDetails.value = '';
            reportDetails.disabled = true;
        }
        updateReportButtonState();
    }

    async function searchReportUser() {
        if (!reportUserSearch || !reportUserResult || !reportUserMessage) return;
        const query = reportUserSearch.value.trim();
        clearReportUserSelection();
        reportUserMessage.textContent = '';
        if (!query) {
            reportUserMessage.textContent = 'Enter a User ID.';
            return;
        }

        reportUserSearchButton.disabled = true;
        reportUserMessage.textContent = 'Searching…';
        try {
            const url = 'settings.php?settings_lookup_user=1&user_id=' + encodeURIComponent(query);
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            const data = await response.json();
            if (!response.ok || !data.success || !data.user) {
                reportUserMessage.textContent = data.message || 'User not found.';
                return;
            }

            const user = data.user;
            const photo = user.photo ? document.createElement('img') : document.createElement('div');
            photo.className = 'settings-user-result-photo';
            if (user.photo) {
                photo.src = user.photo;
                photo.alt = user.name || user.public_id;
            } else {
                photo.classList.add('settings-user-result-placeholder');
                photo.innerHTML = '<i class="fa-solid fa-user"></i>';
            }

            const info = document.createElement('div');
            info.className = 'settings-user-result-name';
            const name = document.createElement('strong');
            name.textContent = user.name || 'Unnamed user';
            const publicId = document.createElement('span');
            publicId.textContent = user.public_id;
            info.appendChild(name);
            info.appendChild(publicId);

            const selectButton = document.createElement('button');
            selectButton.type = 'button';
            selectButton.className = 'settings-user-select-button';
            selectButton.textContent = 'Select';
            selectButton.addEventListener('click', function () {
                const isSelected = reportTargetUserId.value === String(user.id);
                if (isSelected) {
                    unselectReportUser();
                    reportUserMessage.textContent = user.public_id + ' unselected.';
                    return;
                }
                reportTargetUserId.value = String(user.id);
                reportUserResult.classList.add('is-selected');
                selectButton.textContent = 'Unselect';
                reportReason.disabled = false;
                reportDetails.disabled = false;
                reportUserMessage.textContent = user.public_id + ' selected.';
                updateReportButtonState();
            });

            reportUserResult.appendChild(photo);
            reportUserResult.appendChild(info);
            reportUserResult.appendChild(selectButton);
            reportUserResult.hidden = false;
            reportUserMessage.textContent = '';
        } catch (error) {
            reportUserMessage.textContent = 'Unable to search right now.';
        } finally {
            reportUserSearchButton.disabled = false;
        }
    }

    if (reportUserSearchButton) reportUserSearchButton.addEventListener('click', searchReportUser);
    if (reportUserSearch) {
        reportUserSearch.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                searchReportUser();
            }
        });
        reportUserSearch.addEventListener('input', clearReportUserSelection);
    }
    if (reportReason) reportReason.addEventListener('change', updateReportButtonState);
    if (reportUserForm) {
        reportUserForm.addEventListener('submit', function (event) {
            if (!reportTargetUserId || !reportTargetUserId.value || !reportReason || !reportReason.value) {
                event.preventDefault();
                if (reportUserMessage) reportUserMessage.textContent = 'Search and select a user, then select a reason.';
            }
        });
    }

    const notificationSwitch = document.getElementById('notificationsSwitch');
    const notificationsEnabledInput = document.getElementById('notificationsEnabledInput');
    const notificationsStatusText = document.getElementById('notificationsStatusText');
    const notificationsSwitchLabel = document.getElementById('notificationsSwitchLabel');
    if (notificationSwitch && notificationsEnabledInput) {
        notificationSwitch.addEventListener('click', function () {
            const enabled = notificationsEnabledInput.value !== '1';
            notificationsEnabledInput.value = enabled ? '1' : '0';
            notificationSwitch.classList.toggle('is-on', enabled);
            notificationSwitch.setAttribute('aria-checked', enabled ? 'true' : 'false');
            if (notificationsSwitchLabel) notificationsSwitchLabel.textContent = enabled ? 'ON' : 'OFF';
            if (notificationsStatusText) notificationsStatusText.textContent = enabled ? 'ON — notifications are enabled for everyone.' : 'OFF — notifications are disabled for everyone.';
        });
    }

    const newPassword = document.querySelector('input[name="new_password"]');
    const confirmPassword = document.querySelector('input[name="confirm_password"]');
    const conditionItems = document.querySelectorAll('.password-condition');
    const passwordMatch = document.getElementById('passwordMatch');

    function updatePasswordChecks() {
        if (!newPassword) return;
        const value = newPassword.value;
        const checks = {
            length: value.length >= 8,
            uppercase: /[A-Z]/.test(value),
            lowercase: /[a-z]/.test(value),
            number: /[0-9]/.test(value)
        };
        conditionItems.forEach(function (item) {
            const key = item.getAttribute('data-condition');
            const ok = !!checks[key];
            item.classList.toggle('is-valid', ok);
            const icon = item.querySelector('i');
            if (icon) icon.className = ok ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-xmark';
        });
        if (passwordMatch && confirmPassword) {
            if (!confirmPassword.value) {
                passwordMatch.textContent = '';
                passwordMatch.className = 'settings-password-match';
            } else if (value === confirmPassword.value) {
                passwordMatch.textContent = 'Passwords match.';
                passwordMatch.className = 'settings-password-match is-valid';
            } else {
                passwordMatch.textContent = 'Passwords do not match.';
                passwordMatch.className = 'settings-password-match is-invalid';
            }
        }
    }

    if (newPassword) newPassword.addEventListener('input', updatePasswordChecks);
    if (confirmPassword) confirmPassword.addEventListener('input', updatePasswordChecks);
    updatePasswordChecks();

    const matchingReligion = document.getElementById('settingsMatchingReligion');
    function updateIslamicPreferenceVisibility() {
        const isIslam = matchingReligion && matchingReligion.value === 'Islam';
        document.querySelectorAll('.settings-islamic-field').forEach(function (field) {
            field.hidden = !isIslam;
            field.querySelectorAll('select, input').forEach(function (control) {
                control.disabled = !isIslam;
            });
        });
    }
    if (matchingReligion) {
        matchingReligion.addEventListener('change', updateIslamicPreferenceVisibility);
        updateIslamicPreferenceVisibility();
    }

    const matchingDivision = document.getElementById('settings_matching_division');
    const matchingDistrict = document.getElementById('settings_matching_district');
    const matchingUpazila = document.getElementById('settings_matching_upazila');
    const matchingAjaxBase = 'ajax/';
    async function loadMatchingDistricts(divisionId, selectedId) {
        if (!matchingDistrict || !matchingUpazila) return;
        matchingDistrict.innerHTML = '<option value="">Any district</option>';
        matchingUpazila.innerHTML = '<option value="">Any upazila</option>';
        matchingDistrict.disabled = !divisionId;
        matchingUpazila.disabled = true;
        if (!divisionId) return;
        try {
            const response = await fetch(matchingAjaxBase + 'get_districts.php?division_id=' + encodeURIComponent(divisionId), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const html = await response.text();
            const parsed = document.createElement('select');
            parsed.innerHTML = html;
            Array.from(parsed.options).forEach(function (sourceOption, index) {
                if (index === 0) return;
                const option = document.createElement('option');
                option.value = sourceOption.value;
                option.textContent = sourceOption.textContent;
                if (String(sourceOption.value) === String(selectedId || '')) option.selected = true;
                matchingDistrict.appendChild(option);
            });
            matchingDistrict.disabled = false;
            if (matchingDistrict.value) await loadMatchingUpazilas(matchingDistrict.value, matchingUpazila.dataset.selected || '');
        } catch (error) { matchingDistrict.disabled = false; }
    }
    async function loadMatchingUpazilas(districtId, selectedId) {
        if (!matchingUpazila) return;
        matchingUpazila.innerHTML = '<option value="">Any upazila</option>';
        matchingUpazila.disabled = !districtId;
        if (!districtId) return;
        try {
            const response = await fetch(matchingAjaxBase + 'get_upazilas.php?district_id=' + encodeURIComponent(districtId), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const html = await response.text();
            const parsed = document.createElement('select');
            parsed.innerHTML = html;
            Array.from(parsed.options).forEach(function (sourceOption, index) {
                if (index === 0) return;
                const option = document.createElement('option');
                option.value = sourceOption.value;
                option.textContent = sourceOption.textContent;
                if (String(sourceOption.value) === String(selectedId || '')) option.selected = true;
                matchingUpazila.appendChild(option);
            });
            matchingUpazila.disabled = false;
        } catch (error) { matchingUpazila.disabled = false; }
    }
    if (matchingDivision) {
        matchingDivision.addEventListener('change', function () { loadMatchingDistricts(matchingDivision.value, ''); });
        if (matchingDivision.value) loadMatchingDistricts(matchingDivision.value, matchingDistrict ? matchingDistrict.dataset.selected : '');
    }
    if (matchingDistrict) {
        matchingDistrict.addEventListener('change', function () { loadMatchingUpazilas(matchingDistrict.value, ''); });
    }

    const preferencesReligion = document.getElementById('settingsPreferencesReligion');
    function updatePreferencesIslamicVisibility() {
        const isIslam = preferencesReligion && preferencesReligion.value === 'Islam';
        document.querySelectorAll('.settings-preferences-islamic-field').forEach(function (field) {
            field.hidden = !isIslam;
            field.querySelectorAll('select, input').forEach(function (control) {
                control.disabled = !isIslam;
            });
        });
    }
    if (preferencesReligion) {
        preferencesReligion.addEventListener('change', updatePreferencesIslamicVisibility);
        updatePreferencesIslamicVisibility();
    }

    const preferencesDivision = document.getElementById('settings_preferences_division');
    const preferencesDistrict = document.getElementById('settings_preferences_district');
    const preferencesUpazila = document.getElementById('settings_preferences_upazila');
    async function loadPreferenceDistricts(divisionId, selectedId) {
        if (!preferencesDistrict || !preferencesUpazila) return;
        preferencesDistrict.innerHTML = '<option value="">Any district</option>';
        preferencesUpazila.innerHTML = '<option value="">Any upazila</option>';
        preferencesDistrict.disabled = !divisionId;
        preferencesUpazila.disabled = true;
        if (!divisionId) return;
        try {
            const response = await fetch('ajax/get_districts.php?division_id=' + encodeURIComponent(divisionId), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const html = await response.text();
            const parsed = document.createElement('select');
            parsed.innerHTML = html;
            Array.from(parsed.options).forEach(function (sourceOption, index) {
                if (index === 0) return;
                const option = document.createElement('option');
                option.value = sourceOption.value;
                option.textContent = sourceOption.textContent;
                if (String(sourceOption.value) === String(selectedId || '')) option.selected = true;
                preferencesDistrict.appendChild(option);
            });
            preferencesDistrict.disabled = false;
            if (preferencesDistrict.value) await loadPreferenceUpazilas(preferencesDistrict.value, preferencesUpazila.dataset.selected || '');
        } catch (error) { preferencesDistrict.disabled = false; }
    }
    async function loadPreferenceUpazilas(districtId, selectedId) {
        if (!preferencesUpazila) return;
        preferencesUpazila.innerHTML = '<option value="">Any upazila</option>';
        preferencesUpazila.disabled = !districtId;
        if (!districtId) return;
        try {
            const response = await fetch('ajax/get_upazilas.php?district_id=' + encodeURIComponent(districtId), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const html = await response.text();
            const parsed = document.createElement('select');
            parsed.innerHTML = html;
            Array.from(parsed.options).forEach(function (sourceOption, index) {
                if (index === 0) return;
                const option = document.createElement('option');
                option.value = sourceOption.value;
                option.textContent = sourceOption.textContent;
                if (String(sourceOption.value) === String(selectedId || '')) option.selected = true;
                preferencesUpazila.appendChild(option);
            });
            preferencesUpazila.disabled = false;
        } catch (error) { preferencesUpazila.disabled = false; }
    }
    if (preferencesDivision) {
        preferencesDivision.addEventListener('change', function () { loadPreferenceDistricts(preferencesDivision.value, ''); });
        if (preferencesDivision.value) loadPreferenceDistricts(preferencesDivision.value, preferencesDistrict ? preferencesDistrict.dataset.selected : '');
    }
    if (preferencesDistrict) {
        preferencesDistrict.addEventListener('change', function () { loadPreferenceUpazilas(preferencesDistrict.value, ''); });
    }

    const matchingWeightForm = document.getElementById('matchingWeightsForm');
    if (matchingWeightForm) {
        const sliders = Array.from(matchingWeightForm.querySelectorAll('[data-weight-slider]'));
        const totalValue = document.getElementById('matchingWeightTotalValue');
        const status = document.getElementById('matchingWeightStatus');
        const save = document.getElementById('matchingWeightsSave');
        const totalBox = document.getElementById('matchingWeightTotal');
        function updateWeightTotal() {
            let total = 0;
            sliders.forEach(function (slider) {
                const value = Math.max(0, Math.min(100, parseInt(slider.value || '0', 10)));
                slider.value = value;
                const output = slider.closest('.matching-weight-row')?.querySelector('[data-weight-value]');
                if (output) output.textContent = value + '%';
                total += value;
            });
            if (totalValue) totalValue.textContent = total + '%';
            if (totalBox) totalBox.dataset.total = String(total);
            const ready = total === 100;
            if (status) {
                status.textContent = ready ? 'Ready to save' : (total < 100 ? 'Add ' + (100-total) + '% more' : 'Remove ' + (total-100) + '%');
                status.classList.toggle('is-ready', ready);
                status.classList.toggle('is-over', total > 100);
            }
            if (save) save.disabled = !ready;
        }
        sliders.forEach(function (slider) { slider.addEventListener('input', updateWeightTotal); });
        matchingWeightForm.addEventListener('submit', function (event) {
            let total = 0; sliders.forEach(function (slider) { total += parseInt(slider.value || '0', 10); });
            if (total !== 100) { event.preventDefault(); updateWeightTotal(); }
        });
        updateWeightTotal();
    }

    document.querySelectorAll('.settings-account-control-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const action = form.dataset.accountAction || '';
            if (action === 'deactivate') {
                if (!window.confirm('Deactivate your account?')) event.preventDefault();
            } else if (action === 'delete') {
                const confirmBox = form.querySelector('input[name="confirm_delete"]');
                if (!confirmBox || !confirmBox.checked || !window.confirm('Delete your account permanently? This cannot be undone.')) {
                    event.preventDefault();
                }
            }
        });
    });

    const hash = window.location.hash;
    if (hash === '#account-panel' || hash === '#security-panel' || hash === '#privacy-panel' || hash === '#notifications-panel' || hash === '#matching-panel' || hash === '#profile-media-panel' || hash === '#blocking-panel' || hash === '#preferences-panel' || hash === '#account-control-panel' || document.querySelector('.settings-alert')) {
        let panel = hash ? document.querySelector(hash) : null;
        if (!panel) {
            const alert = document.querySelector('.settings-alert');
            panel = alert ? alert.closest('.settings-detail-panel') : null;
        }
        if (panel) {
            setPanel(panel, true, false);
            if (hash) {
                window.requestAnimationFrame(function () {
                    panel.scrollIntoView({ block: 'start' });
                });
            }
        }
    }
})();
</script>

<?php include 'includes/footer.php'; ?>
