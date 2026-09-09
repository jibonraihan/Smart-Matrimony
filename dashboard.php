<?php

$page_css = 'assets/css/dashboard.css';


require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/dropdowns.php';
require_once 'includes/profile_completion.php';
require_once 'includes/match_score.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

if (empty($_SESSION['matching_csrf'])) {
    $_SESSION['matching_csrf'] = bin2hex(random_bytes(32));
}
$matching_csrf = $_SESSION['matching_csrf'];

/* Dashboard bookmark action: normal POST + redirect, no AJAX endpoint. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dashboard_bookmark_action'])) {
    $posted_csrf = (string) ($_POST['csrf_token'] ?? '');
    $target_id = (int) ($_POST['bookmark_user_id'] ?? 0);
    $bookmark_action = (string) ($_POST['bookmark_action'] ?? '');
    $return_query = (string) ($_POST['return_query'] ?? '');

    $redirect_url = BASE_URL . 'dashboard.php';
    if ($return_query !== '') $redirect_url .= '?' . ltrim($return_query, '?');
    $redirect_url .= '#matching-profiles';

    if (!hash_equals($matching_csrf, $posted_csrf)) {
        $_SESSION['dashboard_bookmark_flash'] = 'Your session expired. Please refresh the page and try again.';
        header('Location: ' . $redirect_url); exit;
    }
    if ($target_id <= 0 || $target_id === $user_id || !in_array($bookmark_action, ['add','remove'], true)) {
        $_SESSION['dashboard_bookmark_flash'] = 'This bookmark action is not available.';
        header('Location: ' . $redirect_url); exit;
    }

    $target_stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_id=? AND role='User' AND account_status='Active' LIMIT 1");
    $target_ok = false;
    if ($target_stmt) {
        mysqli_stmt_bind_param($target_stmt, 'i', $target_id);
        mysqli_stmt_execute($target_stmt);
        $target_ok = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($target_stmt));
        mysqli_stmt_close($target_stmt);
    }
    if (!$target_ok) {
        $_SESSION['dashboard_bookmark_flash'] = 'This profile is not available.';
        header('Location: ' . $redirect_url); exit;
    }

    if ($bookmark_action === 'add') {
        $stmt_b = mysqli_prepare($conn, "INSERT IGNORE INTO bookmarks (user_id, bookmarked_user_id) VALUES (?, ?)");
        $ok = false;
        if ($stmt_b) {
            mysqli_stmt_bind_param($stmt_b, 'ii', $user_id, $target_id);
            $ok = mysqli_stmt_execute($stmt_b);
            mysqli_stmt_close($stmt_b);
        }
        $_SESSION['dashboard_bookmark_flash'] = $ok ? 'Profile bookmarked.' : 'Unable to save the bookmark right now.';
    } else {
        $stmt_b = mysqli_prepare($conn, "DELETE FROM bookmarks WHERE user_id=? AND bookmarked_user_id=?");
        $ok = false;
        if ($stmt_b) {
            mysqli_stmt_bind_param($stmt_b, 'ii', $user_id, $target_id);
            $ok = mysqli_stmt_execute($stmt_b);
            mysqli_stmt_close($stmt_b);
        }
        $_SESSION['dashboard_bookmark_flash'] = $ok ? 'Bookmark removed.' : 'Unable to remove the bookmark right now.';
    }
    header('Location: ' . $redirect_url); exit;
}

$dashboard_bookmark_flash = $_SESSION['dashboard_bookmark_flash'] ?? '';
unset($_SESSION['dashboard_bookmark_flash']);

/* ---------------------------------------------------------
   Logged-in user + profile
--------------------------------------------------------- */

$stmt = mysqli_prepare($conn, "
    SELECT
        u.user_id,
        u.first_name,
        u.last_name,
        u.gender,
        u.email,
        u.mobile,
        u.role,
        up.profile_id,
        up.date_of_birth,
        up.religion,
        up.highest_education,
        up.profession,
        up.photo,
        up.height_cm,
        up.weight_kg,
        up.complexion,
        up.division_id,
        up.district_id,
        up.upazila_id,
        up.family_type,
        up.family_status,
        up.smoking_status,
        up.prayer_status,
        up.beard_status,
        up.hijab_status,
        up.mahram_maintained,
        up.bio,
        up.profile_visibility,
        up.verification_status
    FROM users u
    LEFT JOIN user_profiles up ON up.user_id = u.user_id
    WHERE u.user_id = ?
    LIMIT 1
");

mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$current_user = mysqli_fetch_assoc($user_result);
mysqli_stmt_close($stmt);

if (!$current_user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$display_name = trim(($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? ''));
$display_name = $display_name !== '' ? $display_name : 'Member';
$profile_public_id = 'SM-' . str_pad((string) $user_id, 6, '0', STR_PAD_LEFT);
$current_gender = $current_user['gender'] ?? '';

/* ---------------------------------------------------------
   Service cart + booking summary
--------------------------------------------------------- */
$cart_count = 0;
$booking_count = 0;
$bookmark_count = 0;

$cart_stmt = mysqli_prepare($conn, 'SELECT COALESCE(SUM(quantity),0) AS total FROM service_cart_items WHERE user_id = ?');
mysqli_stmt_bind_param($cart_stmt, 'i', $user_id);
mysqli_stmt_execute($cart_stmt);
$cart_row = mysqli_fetch_assoc(mysqli_stmt_get_result($cart_stmt));
$cart_count = (int) ($cart_row['total'] ?? 0);
mysqli_stmt_close($cart_stmt);

$booking_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM bookings WHERE user_id = ? AND booking_status <> 'Cancelled'");
mysqli_stmt_bind_param($booking_stmt, 'i', $user_id);
mysqli_stmt_execute($booking_stmt);
$booking_row = mysqli_fetch_assoc(mysqli_stmt_get_result($booking_stmt));
$booking_count = (int) ($booking_row['total'] ?? 0);
mysqli_stmt_close($booking_stmt);

$bookmark_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM bookmarks WHERE user_id = ?");
mysqli_stmt_bind_param($bookmark_stmt, 'i', $user_id);
mysqli_stmt_execute($bookmark_stmt);
$bookmark_row = mysqli_fetch_assoc(mysqli_stmt_get_result($bookmark_stmt));
$bookmark_count = (int) ($bookmark_row['total'] ?? 0);
mysqli_stmt_close($bookmark_stmt);

$booking_package_count = 0;
$booking_package_stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(bd.quantity), 0) AS total FROM bookings b INNER JOIN booking_details bd ON bd.booking_id = b.booking_id WHERE b.user_id = ? AND b.booking_status <> 'Cancelled'");
mysqli_stmt_bind_param($booking_package_stmt, 'i', $user_id);
mysqli_stmt_execute($booking_package_stmt);
$booking_package_row = mysqli_fetch_assoc(mysqli_stmt_get_result($booking_package_stmt));
$booking_package_count = (int) ($booking_package_row['total'] ?? 0);
mysqli_stmt_close($booking_package_stmt);

/* Current active matches */
$current_matches_count = 0;
$match_count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM matches WHERE (sender_user_id = ? OR receiver_user_id = ?) AND status = 'Accepted' AND relationship_active = 1");
if ($match_count_stmt) {
    mysqli_stmt_bind_param($match_count_stmt, 'ii', $user_id, $user_id);
    mysqli_stmt_execute($match_count_stmt);
    $match_count_row = mysqli_fetch_assoc(mysqli_stmt_get_result($match_count_stmt));
    $current_matches_count = (int) ($match_count_row['total'] ?? 0);
    mysqli_stmt_close($match_count_stmt);
}


/* ---------------------------------------------------------
   Profile completion
   Use the same centralized completion calculator as Profile View.
--------------------------------------------------------- */
$profile_completion_data = sm_get_profile_completion($conn, $user_id);
$profile_completion = (int) ($profile_completion_data['percentage'] ?? 0);
$profile_complete = $profile_completion >= 100;

$profile_link = 'profile/create_profile.php';
$own_profile_link = 'profile/view_profile.php?user_id=' . $user_id;
$profile_cta_text = $profile_complete ? 'View / Update Profile' : 'Update Profile Details';

/* Search state must be resolved before building the return URL. */
$search_submitted = isset($_GET['search']);
$profile_return_url = $search_submitted
    ? BASE_URL . 'dashboard.php?' . http_build_query($_GET) . '#matching-profiles'
    : BASE_URL . 'dashboard.php#partner-search';

/* ---------------------------------------------------------
   Search filters
--------------------------------------------------------- */


$target_gender = trim($_GET['gender'] ?? '');
$division_id = (int) ($_GET['division_id'] ?? 0);
$district_id = (int) ($_GET['district_id'] ?? 0);
$upazila_id = (int) ($_GET['upazila_id'] ?? 0);
$religion = trim($_GET['religion'] ?? '');
$profession = trim($_GET['profession'] ?? '');
$family_type = trim($_GET['family_type'] ?? '');
$family_status = trim($_GET['family_status'] ?? '');
$complexion = trim($_GET['complexion'] ?? '');
$beard_status = trim($_GET['beard_status'] ?? '');
$hijab_status = trim($_GET['hijab_status'] ?? '');
$prayer_status = trim($_GET['prayer_status'] ?? '');
$smoking_status = trim($_GET['smoking_status'] ?? '');
$mahram_maintained = trim($_GET['mahram_maintained'] ?? '');
$marital_status = trim($_GET['marital_status'] ?? '');
$education = trim($_GET['education'] ?? '');
$madhhab = trim($_GET['madhhab'] ?? '');
$blood_group = trim($_GET['blood_group'] ?? '');
$father_profession = trim($_GET['father_profession'] ?? '');
$mother_profession = trim($_GET['mother_profession'] ?? '');

$min_salary = isset($_GET['min_salary']) && $_GET['min_salary'] !== '' ? (float) $_GET['min_salary'] : null;
$max_salary = isset($_GET['max_salary']) && $_GET['max_salary'] !== '' ? (float) $_GET['max_salary'] : null;
$min_height = isset($_GET['min_height']) && $_GET['min_height'] !== '' ? (float) $_GET['min_height'] : null;
$max_height = isset($_GET['max_height']) && $_GET['max_height'] !== '' ? (float) $_GET['max_height'] : null;
$min_weight = isset($_GET['min_weight']) && $_GET['min_weight'] !== '' ? (float) $_GET['min_weight'] : null;
$max_weight = isset($_GET['max_weight']) && $_GET['max_weight'] !== '' ? (float) $_GET['max_weight'] : null;

$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 8;

$results = [];
$total_results = 0;
$total_pages = 0;

/* ---------------------------------------------------------
   Run search only after mandatory Bride/Groom selection
--------------------------------------------------------- */

if ($search_submitted && in_array($target_gender, ['Male', 'Female'], true)) {

    $where = [
        "up.user_id <> ?",
        "u.account_status = 'Active'",
        "up.profile_visibility = 'Public'",
        "u.gender = ?"
    ];

    $types = 'is';
    $params = [$user_id, $target_gender];

    $add_string = function ($condition, $value) use (&$where, &$types, &$params) {
        if ($value !== '') {
            $where[] = $condition;
            $types .= 's';
            $params[] = $value;
        }
    };

    $add_int = function ($condition, $value) use (&$where, &$types, &$params) {
        if ((int) $value > 0) {
            $where[] = $condition;
            $types .= 'i';
            $params[] = (int) $value;
        }
    };

    $add_float = function ($condition, $value) use (&$where, &$types, &$params) {
        if ($value !== null) {
            $where[] = $condition;
            $types .= 'd';
            $params[] = (float) $value;
        }
    };

    $add_int('up.division_id = ?', $division_id);
    $add_int('up.district_id = ?', $district_id);
    $add_int('up.upazila_id = ?', $upazila_id);
    $add_string('up.religion = ?', $religion);
    $add_string('up.profession = ?', $profession);
    $add_string('up.family_type = ?', $family_type);
    $add_string('up.family_status = ?', $family_status);
    $add_string('up.marital_status = ?', $marital_status);
    $add_string('up.highest_education = ?', $education);
    $add_string('up.madhhab = ?', $madhhab);
    if ($blood_group !== '') {
        $where[] = 'hp.blood_group = ?';
        $types .= 's';
        $params[] = $blood_group;
    }
    $add_string('up.complexion = ?', $complexion);
    $add_string('up.beard_status = ?', $beard_status);
    $add_string('up.hijab_status = ?', $hijab_status);
    $add_string('up.prayer_status = ?', $prayer_status);
    $add_string('up.smoking_status = ?', $smoking_status);
    $add_string('up.father_profession = ?', $father_profession);
    $add_string('up.mother_profession = ?', $mother_profession);

    if ($mahram_maintained !== '') {
        $where[] = 'up.mahram_maintained = ?';
        $types .= 'i';
        $params[] = (int) $mahram_maintained;
    }

    $add_float('up.monthly_income >= ?', $min_salary);
    $add_float('up.monthly_income <= ?', $max_salary);
    $add_float('up.height_cm >= ?', $min_height);
    $add_float('up.height_cm <= ?', $max_height);
    $add_float('up.weight_kg >= ?', $min_weight);
    $add_float('up.weight_kg <= ?', $max_weight);

    $where_sql = implode(' AND ', $where);

    /* Count first */
    $count_sql = "
        SELECT COUNT(*) AS total
        FROM users u
        INNER JOIN user_profiles up ON up.user_id = u.user_id
        LEFT JOIN health_profiles hp ON hp.user_id = up.user_id
        LEFT JOIN districts d ON d.id = up.district_id
        LEFT JOIN upazilas uz ON uz.id = up.upazila_id
        LEFT JOIN divisions dv ON dv.id = up.division_id
        WHERE {$where_sql}
    ";

    $count_stmt = mysqli_prepare($conn, $count_sql);
    $count_params = $params;
    $count_types = $types;
    $count_refs = [];
    foreach ($count_params as $key => $value) {
        $count_refs[$key] = &$count_params[$key];
    }
    mysqli_stmt_bind_param($count_stmt, $count_types, ...$count_refs);
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $total_results = (int) (mysqli_fetch_assoc($count_result)['total'] ?? 0);
    mysqli_stmt_close($count_stmt);

    $total_pages = max(1, (int) ceil($total_results / $per_page));
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $per_page;

    $search_sql = "
        SELECT
            up.profile_id,
            up.user_id,
            up.first_name,
            up.last_name,
            u.gender,
            up.date_of_birth,
            up.religion,
            up.madhhab,
            up.marital_status,
            up.highest_education,
            hp.blood_group,
            up.profession,
            up.photo,
            up.height_cm,
            up.weight_kg,
            up.complexion,
            up.family_type,
            up.family_status,
            up.prayer_status,
            up.smoking_status,
            up.beard_status,
            up.hijab_status,
            up.division,
            up.district,
            up.upazila,
            up.verification_status,
            up.photo_visibility,
            up.halal_lifestyle,
            up.mahram_maintained,
            up.islamic_knowledge,
            up.monthly_income,
            up.district_id,
            up.upazila_id,
            up.division_id,
            d.name_bn AS district_name,
            uz.name_bn AS upazila_name,
            dv.name_bn AS division_name
        FROM users u
        INNER JOIN user_profiles up ON up.user_id = u.user_id
        LEFT JOIN health_profiles hp ON hp.user_id = up.user_id
        LEFT JOIN districts d ON d.id = up.district_id
        LEFT JOIN upazilas uz ON uz.id = up.upazila_id
        LEFT JOIN divisions dv ON dv.id = up.division_id
        WHERE {$where_sql}
        ORDER BY
            CASE WHEN up.verification_status = 'Verified' THEN 0 ELSE 1 END,
            up.updated_at DESC,
            up.profile_id DESC
        LIMIT ? OFFSET ?
    ";

    $search_types = $types . 'ii';
    $search_params = $params;
    $search_params[] = $per_page;
    $search_params[] = $offset;
    $search_refs = [];
    foreach ($search_params as $key => $value) {
        $search_refs[$key] = &$search_params[$key];
    }

    $search_stmt = mysqli_prepare($conn, $search_sql);
    mysqli_stmt_bind_param($search_stmt, $search_types, ...$search_refs);
    mysqli_stmt_execute($search_stmt);
    $search_result = mysqli_stmt_get_result($search_stmt);

    while ($row = mysqli_fetch_assoc($search_result)) {
        $results[] = $row;
    }

    mysqli_stmt_close($search_stmt);
}

/* ---------------------------------------------------------
   Mutual compatibility score preparation
   This is a read-only layer over the existing search results.
   Existing search filtering/pagination remains unchanged.
--------------------------------------------------------- */
$dashboard_match_scores = [];
$dashboard_match_details = [];
if (!empty($results)) {
    $viewer_profile_stmt = mysqli_prepare($conn, "SELECT * FROM user_profiles WHERE user_id = ? LIMIT 1");
    $viewer_profile = [];
    if ($viewer_profile_stmt) {
        mysqli_stmt_bind_param($viewer_profile_stmt, 'i', $user_id);
        mysqli_stmt_execute($viewer_profile_stmt);
        $viewer_profile = mysqli_fetch_assoc(mysqli_stmt_get_result($viewer_profile_stmt)) ?: [];
        mysqli_stmt_close($viewer_profile_stmt);
    }

    $viewer_preferences = [];
    $viewer_pref_stmt = mysqli_prepare($conn, "SELECT * FROM search_preferences WHERE user_id = ? LIMIT 1");
    if ($viewer_pref_stmt) {
        mysqli_stmt_bind_param($viewer_pref_stmt, 'i', $user_id);
        mysqli_stmt_execute($viewer_pref_stmt);
        $viewer_preferences = mysqli_fetch_assoc(mysqli_stmt_get_result($viewer_pref_stmt)) ?: [];
        mysqli_stmt_close($viewer_pref_stmt);
    }

    $candidate_ids = [];
    foreach ($results as $candidate_row) {
        $candidate_ids[] = (int) $candidate_row['user_id'];
    }
    $candidate_ids = array_values(array_unique(array_filter($candidate_ids)));

    $candidate_preferences = [];
    $candidate_trait_preferences = [];
    $candidate_trait_answers = [];
    if ($candidate_ids) {
        $placeholders = implode(',', array_fill(0, count($candidate_ids), '?'));
        $types = str_repeat('i', count($candidate_ids));

        $pref_stmt = mysqli_prepare($conn, "SELECT * FROM search_preferences WHERE user_id IN ($placeholders)");
        if ($pref_stmt) {
            $refs = [];
            foreach ($candidate_ids as $key => $value) $refs[$key] = &$candidate_ids[$key];
            mysqli_stmt_bind_param($pref_stmt, $types, ...$refs);
            mysqli_stmt_execute($pref_stmt);
            $pref_result = mysqli_stmt_get_result($pref_stmt);
            while ($pref_row = mysqli_fetch_assoc($pref_result)) {
                $candidate_preferences[(int) $pref_row['user_id']] = $pref_row;
            }
            mysqli_stmt_close($pref_stmt);
        }

        $trait_answer_stmt = mysqli_prepare($conn, "SELECT uta.user_id, uta.question_id, uta.answer FROM user_trait_answers uta INNER JOIN trait_questions tq ON tq.question_id = uta.question_id WHERE uta.user_id IN ($placeholders) AND tq.gender = 'Both'");
        if ($trait_answer_stmt) {
            $ids_for_answers = $candidate_ids;
            $refs = [];
            foreach ($ids_for_answers as $key => $value) $refs[$key] = &$ids_for_answers[$key];
            mysqli_stmt_bind_param($trait_answer_stmt, $types, ...$refs);
            mysqli_stmt_execute($trait_answer_stmt);
            $answer_result = mysqli_stmt_get_result($trait_answer_stmt);
            while ($answer_row = mysqli_fetch_assoc($answer_result)) {
                $candidate_trait_answers[(int) $answer_row['user_id']][(int) $answer_row['question_id']] = $answer_row['answer'];
            }
            mysqli_stmt_close($trait_answer_stmt);
        }
    }

    $viewer_trait_answers = [];
    $viewer_answer_stmt = mysqli_prepare($conn, "SELECT uta.question_id, uta.answer FROM user_trait_answers uta INNER JOIN trait_questions tq ON tq.question_id = uta.question_id WHERE uta.user_id = ? AND tq.gender = 'Both'");
    if ($viewer_answer_stmt) {
        mysqli_stmt_bind_param($viewer_answer_stmt, 'i', $user_id);
        mysqli_stmt_execute($viewer_answer_stmt);
        $viewer_answer_result = mysqli_stmt_get_result($viewer_answer_stmt);
        while ($row = mysqli_fetch_assoc($viewer_answer_result)) {
            $viewer_trait_answers[(int) $row['question_id']] = $row['answer'];
        }
        mysqli_stmt_close($viewer_answer_stmt);
    }

    // Q&A matching uses only questions marked as common to both genders.
    // Each user's own answer is compared directly with the other user's answer.
    $viewer_trait_preferences = $viewer_trait_answers;
    foreach ($candidate_trait_answers as $candidate_id => $answers) {
        $candidate_trait_preferences[$candidate_id] = $answers;
    }

    foreach ($results as $candidate_row) {
        $candidate_id = (int) $candidate_row['user_id'];
        $dashboard_match_details[$candidate_id] = sm_calculate_mutual_match_breakdown(
            $viewer_preferences,
            $viewer_profile,
            $candidate_preferences[$candidate_id] ?? [],
            $candidate_row,
            $viewer_trait_preferences,
            $viewer_trait_answers,
            $candidate_trait_preferences[$candidate_id] ?? [],
            $candidate_trait_answers[$candidate_id] ?? []
        );
        $dashboard_match_scores[$candidate_id] = $dashboard_match_details[$candidate_id]['score'] !== null
            ? (int) round($dashboard_match_details[$candidate_id]['score'])
            : null;
    }
}

/* ---------------------------------------------------------
   Dashboard photo privacy helpers
   - Everyone: visible to everyone
   - Verified Users: visible only to verified viewers
   - Matched Users: visible only to an active accepted match
   - Hidden: never visible
--------------------------------------------------------- */
$dashboard_matched_user_ids = [];
if (!empty($results)) {
    $matched_stmt = mysqli_prepare($conn, "SELECT CASE WHEN sender_user_id = ? THEN receiver_user_id ELSE sender_user_id END AS matched_user_id FROM matches WHERE (sender_user_id = ? OR receiver_user_id = ?) AND status = 'Accepted' AND relationship_active = 1");
    if ($matched_stmt) {
        mysqli_stmt_bind_param($matched_stmt, 'iii', $user_id, $user_id, $user_id);
        mysqli_stmt_execute($matched_stmt);
        $matched_result = mysqli_stmt_get_result($matched_stmt);
        while ($matched_row = mysqli_fetch_assoc($matched_result)) {
            $dashboard_matched_user_ids[(int) $matched_row['matched_user_id']] = true;
        }
        mysqli_stmt_close($matched_stmt);
    }
}

/* ---------------------------------------------------------
   Divisions
--------------------------------------------------------- */

$divisions = [];
$division_result = mysqli_query($conn, "SELECT id, name_bn, name_en FROM divisions ORDER BY name_bn");
if ($division_result) {
    while ($row = mysqli_fetch_assoc($division_result)) {
        $divisions[] = $row;
    }
}

/* ---------------------------------------------------------
   Service categories available in current schema
--------------------------------------------------------- */

$services = [];
$service_result = mysqli_query($conn, "
    SELECT s.service_id, s.service_name, s.description,
           COUNT(sp.provider_id) AS provider_count
    FROM services s
    LEFT JOIN service_providers sp
        ON sp.service_id = s.service_id
       AND sp.status = 'Active'
    GROUP BY s.service_id, s.service_name, s.description
    ORDER BY s.service_id
");
if ($service_result) {
    while ($row = mysqli_fetch_assoc($service_result)) {
        $services[] = $row;
    }
}
$active_provider_count = 0;
foreach ($services as $service) {
    $active_provider_count += (int) ($service['provider_count'] ?? 0);
}

/* Total active packages represented by the current service-provider catalog. */
$total_service_packages = 0;
$package_count_result = mysqli_query($conn, "
    SELECT COUNT(*) AS total_packages
    FROM service_providers
    WHERE status = 'Active'
      AND package_name IS NOT NULL
      AND TRIM(package_name) <> ''
");
if ($package_count_result) {
    $package_count_row = mysqli_fetch_assoc($package_count_result);
    $total_service_packages = (int) ($package_count_row['total_packages'] ?? 0);
}

include 'includes/header.php';
?>
<link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/dashboard-hero-responsive.css?v=1">

<div class="dashboard-page">

    <!-- ===================== TOP BAR ===================== -->
    <header class="dashboard-topbar">
        <div class="dashboard-container topbar-inner">

            <a href="<?= BASE_URL; ?>dashboard.php" class="dashboard-brand">
                <img src="<?= BASE_URL; ?>assets/images/logo/logo.png" alt="Smart Matrimony">
                <span>Smart Matrimony</span>
            </a>

            <div class="topbar-actions">
                <a href="<?= BASE_URL; ?>index.php" class="topbar-home">
                    <i class="fa-solid fa-house"></i>
                    <span>Public Home</span>
                </a>

                <button class="menu-toggle" type="button" id="dashboardMenuToggle" aria-label="Open menu" aria-expanded="false">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </header>

    <!-- ===================== SLIDE MENU ===================== -->
    <div class="menu-overlay" id="menuOverlay"></div>

    <aside class="dashboard-menu" id="dashboardMenu" aria-hidden="true">
        <div class="menu-header">
            <div>
                <span class="menu-kicker">ACCOUNT MENU</span>
                <h3>Quick Navigation</h3>
            </div>
            <button type="button" class="menu-close" id="menuClose" aria-label="Close menu">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="menu-user-card">
            <div class="menu-user-avatar">
                <?php if (!empty($current_user['photo'])): ?>
                    <img src="<?= BASE_URL; ?>uploads/profile/<?= htmlspecialchars($current_user['photo']); ?>" alt="Profile photo">
                <?php else: ?>
                    <i class="fa-solid fa-user"></i>
                <?php endif; ?>
            </div>
            <div>
                <strong><?= htmlspecialchars($display_name); ?></strong>
                <span><?= htmlspecialchars($current_user['role'] ?? 'User'); ?></span>
            </div>
        </div>

        <nav class="menu-links">
            <a class="active" href="<?= BASE_URL; ?>dashboard.php"><i class="fa-solid fa-grid-2"></i><span>Dashboard</span></a>
            <a href="<?= BASE_URL; ?><?= htmlspecialchars($own_profile_link); ?>"><i class="fa-solid fa-user-circle"></i><span>My Profile</span></a>
            <a href="#partner-search"><i class="fa-solid fa-magnifying-glass"></i><span>Find Partner</span></a>
            <a href="#wedding-services"><i class="fa-solid fa-ring"></i><span>Wedding Services</span></a>
            <a href="<?= BASE_URL; ?>cart.php"><i class="fa-solid fa-cart-shopping"></i><span>My Service Cart</span><?php if ($cart_count > 0): ?><span class="menu-count"><?= $cart_count; ?></span><?php endif; ?></a>
            <a href="<?= BASE_URL; ?>my_bookings.php"><i class="fa-solid fa-calendar-check"></i><span>My Bookings</span><?php if ($booking_count > 0): ?><span class="menu-count"><?= $booking_count; ?></span><?php endif; ?></a>
            <a href="<?= BASE_URL; ?>matching/my_matches.php"><i class="fa-solid fa-heart"></i><span>My Matches</span></a>
            <a href="<?= BASE_URL; ?>matching/bookmarks.php"><i class="fa-solid fa-bookmark"></i><span>Bookmarks</span></a>
            <a href="<?= BASE_URL; ?>matching/chat_requests.php"><i class="fa-solid fa-comments"></i><span>Messages</span></a>

            <?php if (($current_user['role'] ?? '') === 'Admin'): ?>
                <a href="<?= BASE_URL; ?>admin/login.php" target="_blank" rel="noopener"><i class="fa-solid fa-user-shield"></i><span>Admin Login</span></a>
            <?php endif; ?>

            <?php if (($current_user['role'] ?? '') === 'Manager'): ?>
                <a href="<?= BASE_URL; ?>manager/login.php" target="_blank" rel="noopener"><i class="fa-solid fa-calendar-check"></i><span>Event Manager</span></a>
            <?php else: ?>
                <a href="<?= BASE_URL; ?>manager/login.php" target="_blank" rel="noopener"><i class="fa-solid fa-calendar-check"></i><span>Event Manager Login</span></a>
            <?php endif; ?>

            <a href="<?= BASE_URL; ?>authenticator/login.php" target="_blank" rel="noopener"><i class="fa-solid fa-user-check"></i><span>Authenticator Login</span></a>
        </nav>

        <div class="menu-footer">
            <a href="<?= BASE_URL; ?>logout.php" class="menu-logout">
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </a>
        </div>
    </aside>

    <main>

        <!-- ===================== PROFILE COMMAND CENTER HERO ===================== -->
        <section class="dashboard-hero" id="profile-hero">
            <div class="dashboard-container hero-card">
                <div class="hero-profile-panel">
                    <div class="hero-identity">
                        <div class="profile-avatar hero-profile-avatar">
                            <?php if (!empty($current_user['photo'])): ?>
                                <img src="<?= BASE_URL; ?>uploads/profile/<?= htmlspecialchars($current_user['photo']); ?>" alt="Profile photo">
                            <?php else: ?>
                                <i class="fa-solid <?= ($current_user['gender'] ?? '') === 'Female' ? 'fa-user-large' : 'fa-user'; ?>"></i>
                            <?php endif; ?>
                        </div>

                        <div class="hero-identity-copy">
                            <span class="hero-profile-label">MY PROFILE</span>
                            <div class="hero-name-row">
                                <h1><?= htmlspecialchars($display_name); ?></h1>
                                <?php
                                    $hero_verification = (string) ($current_user['verification_status'] ?? 'Pending');
                                    $hero_verification_class = strtolower($hero_verification);
                                ?>
                                <span class="hero-verification-badge <?= htmlspecialchars($hero_verification_class); ?>">
                                    <i class="fa-solid <?= $hero_verification === 'Verified' ? 'fa-circle-check' : ($hero_verification === 'Rejected' ? 'fa-circle-xmark' : 'fa-clock') ; ?>"></i>
                                    <?= htmlspecialchars($hero_verification); ?>
                                </span>
                            </div>
                            <div style="margin-top: 6px; color: #6f8582; font-size: .82rem; line-height: 1.4; overflow-wrap: anywhere;">
                                <i class="fa-solid fa-envelope" style="margin-right: 5px;"></i><?= htmlspecialchars($current_user['email'] ?? ''); ?>
                            </div>
                            <span class="profile-id-badge"><i class="fa-solid fa-id-card"></i> <?= htmlspecialchars($profile_public_id); ?></span>
                        </div>
                    </div>

                    <div class="hero-info-grid">
                        <div class="hero-info-item"><i class="fa-solid fa-venus-mars"></i><span><small>Gender</small><strong><?= htmlspecialchars($current_user['gender'] ?? '—'); ?></strong></span></div>
                        <div class="hero-info-item"><i class="fa-solid fa-mosque"></i><span><small>Religion</small><strong><?= htmlspecialchars($current_user['religion'] ?? '—'); ?></strong></span></div>
                        <div class="hero-info-item"><i class="fa-solid fa-graduation-cap"></i><span><small>Education</small><strong><?= htmlspecialchars($current_user['highest_education'] ?? '—'); ?></strong></span></div>
                        <div class="hero-info-item"><i class="fa-solid fa-briefcase"></i><span><small>Profession</small><strong><?= htmlspecialchars($current_user['profession'] ?? '—'); ?></strong></span></div>
                    </div>

                    <div class="hero-action-row">
                        <a href="<?= BASE_URL . $own_profile_link; ?>" class="hero-action primary"><i class="fa-solid fa-user"></i> View Profile</a>
                        <a href="<?= BASE_URL . $profile_link; ?>" class="hero-action secondary"><i class="fa-solid fa-pen-to-square"></i> Update Profile</a>
                    </div>
                </div>

                <div class="hero-overview-panel">
                    <div class="hero-panel-heading">
                        <div>
                            <span class="hero-profile-label">PROFILE OVERVIEW</span>
                            <h2>Your Matrimony Dashboard</h2>
                        </div>
                        <span class="hero-status-dot"><i class="fa-solid fa-shield-heart"></i> Account Active</span>
                    </div>

                    <div class="hero-stat-grid">
                        <div class="hero-stat-card"><span class="hero-stat-icon"><i class="fa-solid fa-heart"></i></span><div><strong><?= number_format($current_matches_count); ?></strong><small>Current Matches</small></div></div>
                        <div class="hero-stat-card"><span class="hero-stat-icon"><i class="fa-solid fa-chart-line"></i></span><div><strong><?= $profile_completion; ?>%</strong><small>Profile Complete</small></div></div>
                        <div class="hero-stat-card"><span class="hero-stat-icon"><i class="fa-solid fa-bookmark"></i></span><div><strong><?= number_format($bookmark_count); ?></strong><small>Bookmark Profile</small></div></div>
                        <div class="hero-stat-card"><span class="hero-stat-icon"><i class="fa-solid fa-box-open"></i></span><div><strong><?= number_format($booking_package_count); ?></strong><small>Booking Packages</small></div></div>
                    </div>

                    <div class="hero-completion">
                        <div class="hero-completion-head"><span>Profile completion</span><strong><?= $profile_completion; ?>%</strong></div>
                        <div class="completion-track"><span style="width: <?= $profile_completion; ?>%"></span></div>
                        <div class="hero-completion-note">
                            <?php if ($profile_complete): ?>
                                <i class="fa-solid fa-circle-check"></i><span>Your profile is complete and ready for matching.</span>
                            <?php else: ?>
                                <i class="fa-solid fa-circle-info"></i><span>Complete your profile to improve visibility and matching.</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="hero-quick-actions">
                        <span class="hero-quick-label">QUICK ACTIONS</span>
                        <div class="hero-quick-grid">
                            <a href="<?= BASE_URL; ?>matching/my_matches.php"><i class="fa-solid fa-heart"></i><span>My Matches</span></a>
                            <a href="<?= BASE_URL; ?>matching/bookmarks.php"><i class="fa-solid fa-bookmark"></i><span>Bookmarks</span></a>
                            <a href="<?= BASE_URL; ?>matching/chat_requests.php"><i class="fa-solid fa-comments"></i><span>Messages</span></a>
                            <a href="#wedding-services"><i class="fa-solid fa-ring"></i><span>Wedding Services</span></a>
                        </div>
                    </div>
                </div>
            </div>
        </section>


        <!-- ===================== ACCOUNT OVERVIEW / QUICK ACTIONS ===================== -->
        <section class="dashboard-account-overview" aria-label="Account overview and quick actions">
            <div class="dashboard-container">
                <div class="account-overview-head">
                    <div>
                        <span class="section-kicker">ACCOUNT OVERVIEW</span>
                        <h2>Quick Actions</h2>
                        <p>Jump directly to the parts of your matrimony account you use most.</p>
                    </div>
                </div>

                <div class="account-overview-grid">
                    <a class="account-action-card" href="<?= BASE_URL; ?><?= htmlspecialchars($own_profile_link); ?>">
                        <span class="account-action-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="account-action-copy">
                            <strong>My Profile</strong>
                            <small>View your profile</small>
                        </span>
                        <i class="fa-solid fa-arrow-right account-action-arrow"></i>
                    </a>

                    <a class="account-action-card" href="#partner-search">
                        <span class="account-action-icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <span class="account-action-copy">
                            <strong>Find Partner</strong>
                            <small>Search suitable profiles</small>
                        </span>
                        <i class="fa-solid fa-arrow-right account-action-arrow"></i>
                    </a>

                    <a class="account-action-card" href="<?= BASE_URL; ?>matching/my_matches.php">
                        <span class="account-action-icon"><i class="fa-solid fa-heart"></i></span>
                        <span class="account-action-copy">
                            <strong>My Matches</strong>
                            <small><?= number_format($current_matches_count); ?> current matches</small>
                        </span>
                        <i class="fa-solid fa-arrow-right account-action-arrow"></i>
                    </a>

                    <a class="account-action-card" href="<?= BASE_URL; ?>matching/bookmarks.php">
                        <span class="account-action-icon"><i class="fa-solid fa-bookmark"></i></span>
                        <span class="account-action-copy">
                            <strong>Bookmarks</strong>
                            <small>Saved profiles</small>
                        </span>
                        <i class="fa-solid fa-arrow-right account-action-arrow"></i>
                    </a>

                    <a class="account-action-card" href="<?= BASE_URL; ?>matching/chat_requests.php">
                        <span class="account-action-icon"><i class="fa-solid fa-comments"></i></span>
                        <span class="account-action-copy">
                            <strong>Messages</strong>
                            <small>Interests &amp; conversations</small>
                        </span>
                        <i class="fa-solid fa-arrow-right account-action-arrow"></i>
                    </a>

                    <a class="account-action-card" href="#wedding-services">
                        <span class="account-action-icon"><i class="fa-solid fa-ring"></i></span>
                        <span class="account-action-copy">
                            <strong>Wedding Services</strong>
                            <small><?= count($services); ?> service categories</small>
                        </span>
                        <i class="fa-solid fa-arrow-right account-action-arrow"></i>
                    </a>

                    <a class="account-action-card" href="<?= BASE_URL; ?>cart.php">
                        <span class="account-action-icon"><i class="fa-solid fa-cart-shopping"></i></span>
                        <span class="account-action-copy">
                            <strong>Service Cart</strong>
                            <small><?= number_format($cart_count); ?> saved item<?= $cart_count === 1 ? '' : 's'; ?></small>
                        </span>
                        <i class="fa-solid fa-arrow-right account-action-arrow"></i>
                    </a>

                    <a class="account-action-card" href="<?= BASE_URL; ?>my_bookings.php">
                        <span class="account-action-icon"><i class="fa-solid fa-calendar-check"></i></span>
                        <span class="account-action-copy">
                            <strong>My Bookings</strong>
                            <small><?= number_format($booking_count); ?> booking<?= $booking_count === 1 ? '' : 's'; ?></small>
                        </span>
                        <i class="fa-solid fa-arrow-right account-action-arrow"></i>
                    </a>
                </div>
            </div>
        </section>

        <!-- ===================== PARTNER SEARCH ===================== -->
        <section class="dashboard-section" id="partner-search">
            <div class="dashboard-container">

                <div class="section-heading">
                    <div>
                        <span class="section-kicker">SMART MATCHMAKING</span>
                        <h2>Find Your Life Partner</h2>
                        <p>Choose Bride or Groom first, then refine your search with the filters you need.</p>
                    </div>
                    <div class="result-badge">
                        <i class="fa-solid fa-users"></i>
                        <?= number_format($total_results); ?> profiles
                    </div>
                </div>

                <form method="GET" class="search-panel" id="partnerSearchForm" autocomplete="off">
                    <input type="hidden" name="search" value="1">

                    <div class="gender-choice-wrap">
                        <label class="filter-label required-label">Looking for</label>
                        <div class="gender-choice-grid">
                            <label class="gender-option <?= $target_gender === 'Female' ? 'selected' : ''; ?>">
                                <input type="radio" name="gender" value="Female" <?= $target_gender === 'Female' ? 'checked' : ''; ?> required>
                                <span class="gender-icon"><i class="fa-solid fa-venus"></i></span>
                                <span class="gender-copy"><strong>Bride</strong><small>Search female profiles</small></span>
                                <span class="gender-selected-badge"><i class="fa-solid fa-check"></i> Selected</span>
                            </label>

                            <label class="gender-option <?= $target_gender === 'Male' ? 'selected' : ''; ?>">
                                <input type="radio" name="gender" value="Male" <?= $target_gender === 'Male' ? 'checked' : ''; ?> required>
                                <span class="gender-icon"><i class="fa-solid fa-mars"></i></span>
                                <span class="gender-copy"><strong>Groom</strong><small>Search male profiles</small></span>
                                <span class="gender-selected-badge"><i class="fa-solid fa-check"></i> Selected</span>
                            </label>
                        </div>
                    </div>

                    <div class="filter-section filter-essential">
                        <div class="filter-section-heading">
                            <div>
                                <span class="filter-section-kicker">ESSENTIAL FILTERS</span>
                                <strong>Refine the basics</strong>
                            </div>
                            <span class="filter-section-note">All optional</span>
                        </div>
                        <div class="filter-grid filter-grid-essential">
                        <div class="filter-group">
                            <label class="filter-label">Division</label>
                            <select name="division_id" id="searchDivision" class="filter-control">
                                <option value="">Any Division</option>
                                <?php foreach ($divisions as $division): ?>
                                    <option value="<?= (int) $division['id']; ?>" <?= $division_id === (int) $division['id'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($division['name_bn']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">District</label>
                            <select name="district_id" id="searchDistrict" class="filter-control" data-selected="<?= $district_id; ?>">
                                <option value="">Any District</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Upazila</label>
                            <select name="upazila_id" id="searchUpazila" class="filter-control" data-selected="<?= $upazila_id; ?>">
                                <option value="">Any Upazila</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Religion</label>
                            <select name="religion" class="filter-control">
                                <option value="">Any Religion</option>
                                <?php foreach (['Islam', 'Hinduism', 'Christianity', 'Buddhism', 'Other'] as $value): ?>
                                    <option value="<?= htmlspecialchars($value); ?>" <?= $religion === $value ? 'selected' : ''; ?>><?= htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Profession</label>
                            <select name="profession" class="filter-control">
                                <option value="">Any Profession</option>
                                <?php foreach ($user_professions as $value): ?>
                                    <?php if (in_array($value, ['Government Service', 'Private Service', 'Lecturer', 'Professor', 'Engineer', 'Software Engineer', 'IT Professional', 'Dentist', 'BDS', 'DVM'], true) || $value !== ''): ?>
                                        <option value="<?= htmlspecialchars($value); ?>" <?= $profession === $value ? 'selected' : ''; ?>><?= htmlspecialchars($value); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        </div>
                    </div>

                    <div class="filter-advanced-wrap">
                        <button type="button" class="more-filters-toggle" id="moreFiltersToggle"
                                aria-expanded="false" aria-controls="advancedSearchFilters">
                            <span><i class="fa-solid fa-sliders"></i> More Filters</span>
                            <i class="fa-solid fa-chevron-down more-filters-chevron"></i>
                        </button>

                        <div class="filter-section filter-advanced" id="advancedSearchFilters" hidden>
                            <div class="filter-section-heading">
                                <div>
                                    <span class="filter-section-kicker">MORE FILTERS</span>
                                    <strong>Fine-tune your preferences</strong>
                                </div>
                            </div>
                            <div class="filter-grid filter-grid-advanced">
                        <div class="filter-group">
                            <label class="filter-label">Family Type</label>
                            <select name="family_type" class="filter-control">
                                <option value="">Any</option>
                                <?php foreach ($family_types as $value): ?>
                                    <option value="<?= htmlspecialchars($value); ?>" <?= $family_type === $value ? 'selected' : ''; ?>><?= htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Family Status</label>
                            <select name="family_status" class="filter-control">
                                <option value="">Any</option>
                                <?php foreach ($family_statuses as $value): ?>
                                    <option value="<?= htmlspecialchars($value); ?>" <?= $family_status === $value ? 'selected' : ''; ?>><?= htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Complexion</label>
                            <select name="complexion" class="filter-control">
                                <option value="">Any</option>
                                <?php foreach ($complexions as $value): ?>
                                    <option value="<?= htmlspecialchars($value); ?>" <?= $complexion === $value ? 'selected' : ''; ?>><?= htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group range-pair">
                            <label class="filter-label">Height (cm)</label>
                            <div class="range-inputs">
                                <input type="number" name="min_height" min="120" max="250" step="1" value="<?= $min_height !== null ? htmlspecialchars($min_height) : ''; ?>" placeholder="Min">
                                <span>–</span>
                                <input type="number" name="max_height" min="120" max="250" step="1" value="<?= $max_height !== null ? htmlspecialchars($max_height) : ''; ?>" placeholder="Max">
                            </div>
                        </div>
                        <div class="filter-group range-pair">
                            <label class="filter-label">Weight (kg)</label>
                            <div class="range-inputs">
                                <input type="number" name="min_weight" min="25" max="250" step="1" value="<?= $min_weight !== null ? htmlspecialchars($min_weight) : ''; ?>" placeholder="Min">
                                <span>–</span>
                                <input type="number" name="max_weight" min="25" max="250" step="1" value="<?= $max_weight !== null ? htmlspecialchars($max_weight) : ''; ?>" placeholder="Max">
                            </div>
                        </div>
                        <div class="filter-group range-pair">
                            <label class="filter-label">Monthly Income</label>
                            <div class="range-inputs">
                                <input type="number" name="min_salary" min="0" step="1000" value="<?= $min_salary !== null ? htmlspecialchars($min_salary) : ''; ?>" placeholder="Min">
                                <span>–</span>
                                <input type="number" name="max_salary" min="0" step="1000" value="<?= $max_salary !== null ? htmlspecialchars($max_salary) : ''; ?>" placeholder="Max">
                            </div>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Prayer</label>
                            <select name="prayer_status" class="filter-control">
                                <option value="">Any</option>
                                <?php foreach ($prayer_statuses as $value): ?>
                                    <option value="<?= htmlspecialchars($value); ?>" <?= $prayer_status === $value ? 'selected' : ''; ?>><?= htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Smoking</label>
                            <select name="smoking_status" class="filter-control">
                                <option value="">Any</option>
                                <?php foreach ($smoking_statuses as $value): ?>
                                    <option value="<?= htmlspecialchars($value); ?>" <?= $smoking_status === $value ? 'selected' : ''; ?>><?= htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Father's Profession</label>
                            <select name="father_profession" class="filter-control">
                                <option value="">Any</option>
                                <?php foreach ($father_professions as $value): ?>
                                    <option value="<?= htmlspecialchars($value); ?>" <?= $father_profession === $value ? 'selected' : ''; ?>><?= htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Mother's Profession</label>
                            <select name="mother_profession" class="filter-control">
                                <option value="">Any</option>
                                <?php foreach ($mother_professions as $value): ?>
                                    <option value="<?= htmlspecialchars($value); ?>" <?= $mother_profession === $value ? 'selected' : ''; ?>><?= htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Mahram Maintenance</label>
                            <select name="mahram_maintained" class="filter-control">
                                <option value="">Any</option>
                                <option value="1" <?= $mahram_maintained === '1' ? 'selected' : ''; ?>>Maintained</option>
                                <option value="0" <?= $mahram_maintained === '0' ? 'selected' : ''; ?>>Not Maintained</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Marital Status</label>
                            <select name="marital_status" class="filter-control">
                                <option value="">Any Marital Status</option>
                                <?php foreach ($marital_statuses as $value): ?>
                                    <option value="<?= htmlspecialchars($value); ?>" <?= $marital_status === $value ? 'selected' : ''; ?>><?= htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Education</label>
                            <select name="education" class="filter-control">
                                <option value="">Any Education</option>
                                <?php foreach ($education_levels as $value): ?>
                                    <option value="<?= htmlspecialchars($value); ?>" <?= $education === $value ? 'selected' : ''; ?>><?= htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Madhhab / Manhaj</label>
                            <select name="madhhab" class="filter-control">
                                <option value="">Any</option>
                                <?php foreach ($madhhabs as $value): ?>
                                    <option value="<?= htmlspecialchars($value); ?>" <?= $madhhab === $value ? 'selected' : ''; ?>><?= htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Blood Group</label>
                            <select name="blood_group" class="filter-control">
                                <option value="">Any</option>
                                <?php foreach ($blood_groups as $value): ?>
                                    <option value="<?= htmlspecialchars($value); ?>" <?= $blood_group === $value ? 'selected' : ''; ?>><?= htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                            </div>
                        </div>
                    </div>

                    <div class="search-actions">
                        <button type="button" class="clear-search" id="clearSearchFilters">
                            <i class="fa-solid fa-rotate-left"></i> Clear filters
                        </button>
                        <button type="submit" class="search-button">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            Search Profile
                        </button>
                    </div>
                </form>

                <!-- Results -->
                <div class="results-header" id="matching-profiles">
                    <div>
                        <span class="section-kicker">MATCHING PROFILES</span>
                        <h3>
                            <?php if ($search_submitted && $target_gender !== ''): ?>
                                <?= number_format($total_results); ?> <?= $target_gender === 'Female' ? 'Bride' : 'Groom'; ?> profiles found
                            <?php else: ?>
                                Choose a search target to discover profiles
                            <?php endif; ?>
                        </h3>
                    </div>
                </div>

                <?php if (!$search_submitted): ?>
                    <div class="empty-results" id="matching-results-grid">
                        <div class="empty-results-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                        <h4>Start with Bride or Groom</h4>
                        <p>Pick the mandatory search type above, then use as many optional filters as you need.</p>
                    </div>
                <?php elseif ($search_submitted && $target_gender === ''): ?>
                    <div class="empty-results warning-empty" id="matching-results-grid">
                        <div class="empty-results-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
                        <h4>Please choose Bride or Groom</h4>
                        <p>The search target is mandatory before other filters can be applied.</p>
                    </div>
                <?php elseif (empty($results)): ?>
                    <div class="empty-results" id="matching-results-grid">
                        <div class="empty-results-icon"><i class="fa-regular fa-face-frown"></i></div>
                        <h4>No matching profiles yet</h4>
                        <p>Try removing one or two filters to broaden your search.</p>
                    </div>
                <?php else: ?>
                    <div class="profile-results-grid" id="matching-results-grid">
                        <?php foreach ($results as $profile): ?>
                            <?php
                                $name = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
                                $name = $name !== '' ? $name : 'Smart Matrimony Member';
                                $age = '';
                                if (!empty($profile['date_of_birth'])) {
                                    try {
                                        $dob = new DateTime($profile['date_of_birth']);
                                        $age = $dob->diff(new DateTime('today'))->y;
                                    } catch (Exception $e) {
                                        $age = '';
                                    }
                                }
                                $photo = $profile['photo'] ?? '';
                                $photo_visibility = (string) ($profile['photo_visibility'] ?? 'Hidden');
                                $target_verification = (string) ($profile['verification_status'] ?? 'Pending');
                                $viewer_is_verified = (string) ($current_user['verification_status'] ?? 'Pending') === 'Verified';
                                $target_is_matched = isset($dashboard_matched_user_ids[(int) ($profile['user_id'] ?? 0)]);
                                $photo_visible = $photo !== '' && (
                                    $photo_visibility === 'Everyone'
                                    || ($photo_visibility === 'Verified Users' && $viewer_is_verified)
                                    || ($photo_visibility === 'Matched Users' && $target_is_matched)
                                );
                                $target_gender_for_placeholder = (string) ($profile['gender'] ?? '');
                                $gender_icon = $target_gender_for_placeholder === 'Female' ? 'fa-person-dress' : ($target_gender_for_placeholder === 'Male' ? 'fa-person' : 'fa-user');
                            ?>
                            <article class="profile-card">
                                <div class="profile-card-photo">
                                    <?php if ($photo_visible): ?>
                                        <img src="<?= BASE_URL; ?>uploads/profile/<?= htmlspecialchars($photo); ?>" alt="Profile photo">
                                    <?php elseif ($photo !== ''): ?>
                                        <div class="profile-photo-placeholder private-photo gender-placeholder">
                                            <i class="fa-solid <?= $gender_icon; ?>"></i>
                                            <span class="privacy-lock-indicator"><i class="fa-solid fa-lock"></i></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="profile-photo-placeholder gender-placeholder"><i class="fa-solid <?= $gender_icon; ?>"></i></div>
                                    <?php endif; ?>

                                    <?php if (($profile['verification_status'] ?? '') === 'Verified'): ?>
                                        <span class="verified-badge"><i class="fa-solid fa-circle-check"></i> Verified</span>
                                    <?php endif; ?>
                                </div>

                                <div class="profile-card-body">
                                    <?php
                                        $target_id = (int) $profile['user_id'];
                                        $target_public_id = 'SM-' . str_pad((string) $target_id, 6, '0', STR_PAD_LEFT);
                                        // Gender is canonical in users.gender. A profile interaction
                                        // must always be opposite-gender, regardless of any old match row.
                                        $target_gender_value = $profile['gender'] ?? '';
                                        $same_gender = in_array($current_gender, ['Male', 'Female'], true)
                                            && in_array($target_gender_value, ['Male', 'Female'], true)
                                            && $current_gender === $target_gender_value;
                                        $can_send_interest = in_array($current_gender, ['Male', 'Female'], true)
                                            && in_array($target_gender_value, ['Male', 'Female'], true)
                                            && !$same_gender;
                                        $is_bookmarked = false;
                                        $interaction_status = '';
                                        $interaction_id = 0;
                                        $interaction_direction = '';
                                        $interaction_stmt = mysqli_prepare($conn, "SELECT 1 FROM bookmarks WHERE user_id=? AND bookmarked_user_id=? LIMIT 1");
                                        mysqli_stmt_bind_param($interaction_stmt, 'ii', $user_id, $target_id);
                                        mysqli_stmt_execute($interaction_stmt);
                                        $is_bookmarked = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($interaction_stmt));
                                        mysqli_stmt_close($interaction_stmt);
                                        $interaction_stmt = mysqli_prepare($conn, "SELECT match_id, status, relationship_active, sender_user_id FROM matches WHERE ((sender_user_id=? AND receiver_user_id=?) OR (sender_user_id=? AND receiver_user_id=?)) ORDER BY match_id DESC LIMIT 1");
                                        mysqli_stmt_bind_param($interaction_stmt, 'iiii', $user_id, $target_id, $target_id, $user_id);
                                        mysqli_stmt_execute($interaction_stmt);
                                        $interaction = mysqli_fetch_assoc(mysqli_stmt_get_result($interaction_stmt));
                                        mysqli_stmt_close($interaction_stmt);
                                        if ($interaction) {
                                            $interaction_status = $interaction['status'];
                                            $interaction_id = (int) $interaction['match_id'];
                                            $interaction_direction = ((int) $interaction['sender_user_id'] === $user_id) ? 'sent' : 'received';
                                        }
                                    ?>
                                    <div class="profile-card-name-row">
                                        <div>
                                            <h4><?= htmlspecialchars($name); ?></h4>
                                            <span class="profile-card-id"><i class="fa-solid fa-id-card"></i> <?= htmlspecialchars($target_public_id); ?></span>
                                            <p>
                                                <?= $age !== '' ? (int) $age . ' yrs' : 'Age not provided'; ?>
                                                <?php if (!empty($profile['religion'])): ?> · <?= htmlspecialchars($profile['religion']); ?><?php endif; ?>
                                            </p>
                                            <?php $match_score = $dashboard_match_scores[$target_id] ?? null; ?>
                                            <?php if ($match_score !== null): ?>
                                                <div class="profile-match-wrap">
                                                    <span class="profile-match-badge" title="Mutual compatibility score">
                                                        <i class="fa-solid fa-heart"></i> <?= (int) $match_score; ?>% Match
                                                    </span>
                                                    <button type="button" class="profile-match-details-button" data-match-details="<?= htmlspecialchars(json_encode($dashboard_match_details[$target_id] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>" aria-label="View match details">
                                                        View Match Details
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span class="profile-match-badge is-unavailable" title="Add partner preferences to calculate a compatibility score">
                                                    <i class="fa-regular fa-heart"></i> Match —
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <form method="post" action="<?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>" class="bookmark-inline-form dashboard-bookmark-form">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($matching_csrf); ?>">
                                            <input type="hidden" name="dashboard_bookmark_action" value="1">
                                            <input type="hidden" name="bookmark_user_id" value="<?= $target_id; ?>">
                                            <input type="hidden" name="bookmark_action" value="<?= $is_bookmarked ? 'remove' : 'add'; ?>">
                                            <input type="hidden" name="return_query" value="<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="bookmark-button <?= $is_bookmarked ? 'is-bookmarked' : ''; ?>" title="<?= $is_bookmarked ? 'Remove bookmark' : 'Bookmark profile'; ?>">
                                                <i class="fa-<?= $is_bookmarked ? 'solid' : 'regular'; ?> fa-bookmark"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <div class="profile-facts">
                                        <?php if (!empty($profile['profession'])): ?><span><i class="fa-solid fa-briefcase"></i><?= htmlspecialchars($profile['profession']); ?></span><?php endif; ?>
                                        <?php if (!empty($profile['highest_education'])): ?><span><i class="fa-solid fa-graduation-cap"></i><?= htmlspecialchars($profile['highest_education']); ?></span><?php endif; ?>
                                        <?php if (!empty($profile['height_cm']) || !empty($profile['weight_kg'])): ?>
                                            <?php
                                                $height_ft = null;
                                                $height_in = null;
                                                if (!empty($profile['height_cm'])) {
                                                    $height_total_inches = (float) $profile['height_cm'] / 2.54;
                                                    $height_ft = (int) floor($height_total_inches / 12);
                                                    $height_in = (int) round($height_total_inches - ($height_ft * 12));
                                                    if ($height_in >= 12) {
                                                        $height_ft++;
                                                        $height_in = 0;
                                                    }
                                                }
                                                $weight_display = null;
                                                if ($profile['weight_kg'] !== null && $profile['weight_kg'] !== '') {
                                                    $weight_display = rtrim(rtrim(number_format((float) $profile['weight_kg'], 2, '.', ''), '0'), '.');
                                                }
                                            ?>
                                            <span><i class="fa-solid fa-ruler-combined"></i><?= $height_ft !== null ? $height_ft . ' ft ' . $height_in . ' inch' : 'Height —'; ?><?= $weight_display !== null ? ' · ' . htmlspecialchars($weight_display) . ' kg' : ''; ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($profile['complexion'])): ?><span><i class="fa-solid fa-user"></i><?= htmlspecialchars($profile['complexion']); ?></span><?php endif; ?>
                                        <?php if (!empty($profile['family_type']) || !empty($profile['family_status'])): ?><span><i class="fa-solid fa-house-user"></i><?= htmlspecialchars(trim(($profile['family_type'] ?? '') . ' · ' . ($profile['family_status'] ?? ''), ' ·')); ?></span><?php endif; ?>
                                        <?php if (!empty($profile['prayer_status'])): ?><span><i class="fa-solid fa-mosque"></i><?= htmlspecialchars($profile['prayer_status']); ?></span><?php endif; ?>
                                        <?php if (!empty($profile['smoking_status'])): ?><span><i class="fa-solid fa-ban-smoking"></i><?= htmlspecialchars($profile['smoking_status']); ?></span><?php endif; ?>
                                        <?php if (!empty($profile['blood_group'])): ?><span><i class="fa-solid fa-droplet"></i><?= htmlspecialchars($profile['blood_group']); ?></span><?php endif; ?>
                                        <?php if (!empty($profile['district_name']) || !empty($profile['district'])): ?><span><i class="fa-solid fa-location-dot"></i><?= htmlspecialchars($profile['district_name'] ?? $profile['district'] ?? ''); ?></span><?php endif; ?>
                                    </div>

                                    <div class="profile-card-actions">
                                        <a class="view-profile-button" href="profile/view_profile.php?<?= http_build_query(['user_id' => $target_id, 'return_url' => $profile_return_url]); ?>">
                                            View Details Profile
                                            <i class="fa-solid fa-arrow-right"></i>
                                        </a>
                                        <?php if ($same_gender): ?>
                                            <span class="interaction-state unavailable" title="Interest can only be sent to the opposite gender"><i class="fa-solid fa-ban"></i> Interest Unavailable</span>
                                        <?php elseif ($interaction_status === 'Accepted' && $interaction_direction && $interaction_id > 0): ?>
                                            <a class="interaction-state accepted" href="<?= BASE_URL; ?>matching/my_matches.php"><i class="fa-solid fa-heart-circle-check"></i> Matched</a>
                                        <?php elseif ($interaction_status === 'Pending' && $interaction_direction === 'sent'): ?>
                                            <a class="interaction-state pending" href="<?= BASE_URL; ?>matching/my_matches.php"><i class="fa-solid fa-clock"></i> Interest Sent</a>
                                        <?php elseif ($interaction_status === 'Pending' && $interaction_direction === 'received'): ?>
                                            <a class="interaction-state received" href="<?= BASE_URL; ?>matching/my_matches.php"><i class="fa-solid fa-envelope"></i> Respond</a>
                                        <?php elseif ($can_send_interest): ?>
                                            <a class="interest-button" href="<?= BASE_URL; ?>matching/send_interest.php?user_id=<?= $target_id; ?>"><i class="fa-solid fa-heart"></i> Send Interest</a>
                                        <?php else: ?>
                                            <span class="interaction-state unavailable"><i class="fa-solid fa-ban"></i> Interest Unavailable</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <nav class="dashboard-pagination" aria-label="Profile pagination">
                            <?php
                                $query = $_GET;
                                unset($query['page']);
                                $build_page_url = function ($page_number) use ($query) {
                                    $query['page'] = $page_number;
                                    return '?' . http_build_query($query) . '#partner-search';
                                };
                            ?>

                            <?php if ($page > 1): ?>
                                <a href="<?= htmlspecialchars($build_page_url($page - 1)); ?>" class="page-arrow"><i class="fa-solid fa-chevron-left"></i></a>
                            <?php endif; ?>

                            <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                                <a href="<?= htmlspecialchars($build_page_url($p)); ?>" class="page-number <?= $p === $page ? 'active' : ''; ?>"><?= $p; ?></a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="<?= htmlspecialchars($build_page_url($page + 1)); ?>" class="page-arrow"><i class="fa-solid fa-chevron-right"></i></a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- ===================== SERVICE CATEGORIES ===================== -->
        <section class="dashboard-section services-section" id="wedding-services">
            <div class="dashboard-container">
                <div class="section-heading service-section-heading">
                    <div class="service-heading-copy">
                        <span class="section-kicker">WEDDING PLANNING</span>
                        <h2>Wedding Services</h2>
                        <p>Choose a service to explore its available packages, providers, prices and booking options.</p>
                    </div>
                    <div class="service-overview-stats" aria-label="Wedding service overview">
                        <div class="service-overview-stat">
                            <span class="service-overview-icon"><i class="fa-solid fa-layer-group"></i></span>
                            <span><strong><?= count($services); ?></strong><small>Total Services</small></span>
                        </div>
                        <div class="service-overview-stat">
                            <span class="service-overview-icon package-stat-icon"><i class="fa-solid fa-box-open"></i></span>
                            <span><strong><?= $total_service_packages; ?></strong><small>Total Packages</small></span>
                        </div>
                    </div>
                </div>

                <div class="service-category-grid">
                    <?php foreach ($services as $service): ?>
                        <?php
                            $icons = [
                                'Photography' => 'fa-camera-retro',
                                'Catering' => 'fa-utensils',
                                'Decoration' => 'fa-wand-magic-sparkles',
                                'Car Rental' => 'fa-car-side',
                                'Makeup Artist' => 'fa-wand-magic',
                                'Wedding Planner' => 'fa-calendar-check',
                                'Convention Center' => 'fa-building',
                                'Resort / Lawn' => 'fa-tree-city',
                                'Mehendi Artist' => 'fa-hand-sparkles',
                                'Wedding Flowers' => 'fa-seedling',
                                'Basor Ghor Decoration' => 'fa-bed',
                                'Wedding Dress' => 'fa-shirt',
                                'Groom Dress' => 'fa-user-tie',
                                'Bridal Accessories' => 'fa-gem',
                                'Sound & Lighting' => 'fa-lightbulb',
                                'Invitation & Printing' => 'fa-envelope-open-text'
                            ];
                        ?>
                        <a class="service-category-card" href="<?= BASE_URL; ?>services/service_details.php?service_id=<?= (int) $service['service_id']; ?>">
                            <div class="service-icon">
                                <i class="fa-solid <?= htmlspecialchars($icons[$service['service_name']] ?? 'fa-heart'); ?>"></i>
                            </div>
                            <div>
                                <h3><?= htmlspecialchars($service['service_name']); ?></h3>
                                <p><?= htmlspecialchars($service['description'] ?? 'Wedding-related service'); ?></p>
                            </div>
                            <span class="service-status">
                                <?= (int) $service['provider_count']; ?> <?= (int) $service['provider_count'] === 1 ? 'package' : 'packages'; ?>
                            </span>
                            <i class="service-arrow fa-solid fa-arrow-right"></i>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="service-roadmap">
                    <div class="roadmap-icon"><i class="fa-solid fa-circle-info"></i></div>
                    <div>
                        <strong>Plan your wedding services</strong>
                        <p>Explore packages, add your choices to the cart, request a booking, and track your booking status from My Bookings.</p>
                    </div>
                </div>
            </div>
        </section>

    </main>
</div>

<div class="match-details-modal" id="matchDetailsModal" aria-hidden="true">
    <div class="match-details-backdrop" data-match-close></div>
    <section class="match-details-dialog" role="dialog" aria-modal="true" aria-labelledby="matchDetailsTitle">
        <button type="button" class="match-details-close" data-match-close aria-label="Close match details"><i class="fa-solid fa-xmark"></i></button>
        <div class="match-details-head">
            <span class="match-details-icon"><i class="fa-solid fa-heart"></i></span>
            <div>
                <span class="section-kicker">COMPATIBILITY BREAKDOWN</span>
                <h3 id="matchDetailsTitle">Match Details</h3>
                <p>See how the compatibility score was calculated.</p>
            </div>
        </div>
        <div class="match-details-overview">
            <div><strong id="matchDetailsOverall">—%</strong><span>Overall Match</span></div>
            <div><strong id="matchDetailsForward">—%</strong><span>You → Them</span></div>
            <div><strong id="matchDetailsReverse">—%</strong><span>Them → You</span></div>
        </div>
        <div class="match-details-list" id="matchDetailsList"></div>
        <p class="match-details-note">Scores are calculated from the current mutual compatibility rules. Missing information is not treated as a mismatch.</p>
    </section>
</div>

<script src="<?= BASE_URL ?>assets/js/dashboard-match-details.js"></script>
<script>
(function () {
    const menu = document.getElementById('dashboardMenu');
    const overlay = document.getElementById('menuOverlay');
    const toggle = document.getElementById('dashboardMenuToggle');
    const close = document.getElementById('menuClose');

    function openMenu() {
        menu.classList.add('open');
        overlay.classList.add('show');
        toggle.setAttribute('aria-expanded', 'true');
        menu.setAttribute('aria-hidden', 'false');
        document.body.classList.add('menu-open');
    }

    function closeMenu() {
        menu.classList.remove('open');
        overlay.classList.remove('show');
        toggle.setAttribute('aria-expanded', 'false');
        menu.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('menu-open');
    }

    toggle.addEventListener('click', openMenu);
    close.addEventListener('click', closeMenu);
    overlay.addEventListener('click', closeMenu);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeMenu();
    });

    document.querySelectorAll('.menu-links a[href^="#"]').forEach(function (link) {
        link.addEventListener('click', function () {
            if (!link.classList.contains('disabled-link')) closeMenu();
        });
    });

    const division = document.getElementById('searchDivision');
    const district = document.getElementById('searchDistrict');
    const upazila = document.getElementById('searchUpazila');

    function loadDistricts(divisionId, selectedDistrict) {
        if (!divisionId) {
            district.innerHTML = '<option value="">Any District</option>';
            upazila.innerHTML = '<option value="">Any Upazila</option>';
            return Promise.resolve();
        }

        district.innerHTML = '<option value="">Loading districts...</option>';
        upazila.innerHTML = '<option value="">Any Upazila</option>';

        return fetch('ajax/get_districts.php?division_id=' + encodeURIComponent(divisionId))
            .then(response => response.text())
            .then(html => {
                district.innerHTML = html.replace('<option value="">Select District</option>', '<option value="">Any District</option>');
                if (selectedDistrict) {
                    district.value = String(selectedDistrict);
                }
            })
            .catch(() => {
                district.innerHTML = '<option value="">Could not load districts</option>';
            });
    }

    function loadUpazilas(districtId, selectedUpazila) {
        if (!districtId) {
            upazila.innerHTML = '<option value="">Any Upazila</option>';
            return;
        }

        upazila.innerHTML = '<option value="">Loading upazilas...</option>';

        fetch('ajax/get_upazilas.php?district_id=' + encodeURIComponent(districtId))
            .then(response => response.text())
            .then(html => {
                upazila.innerHTML = html.replace('<option value="">Select Upazila</option>', '<option value="">Any Upazila</option>');
                if (selectedUpazila) {
                    upazila.value = String(selectedUpazila);
                }
            })
            .catch(() => {
                upazila.innerHTML = '<option value="">Could not load upazilas</option>';
            });
    }

    division.addEventListener('change', function () {
        loadDistricts(this.value, '').then(function () {
            upazila.innerHTML = '<option value="">Any Upazila</option>';
        });
    });

    district.addEventListener('change', function () {
        loadUpazilas(this.value, '');
    });

    const selectedDistrict = district.dataset.selected || '';
    const selectedUpazila = upazila.dataset.selected || '';

    if (division.value) {
        loadDistricts(division.value, selectedDistrict).then(function () {
            if (district.value) loadUpazilas(district.value, selectedUpazila);
        });
    }


    const moreFiltersToggle = document.getElementById('moreFiltersToggle');
    const advancedSearchFilters = document.getElementById('advancedSearchFilters');

    if (moreFiltersToggle && advancedSearchFilters) {
        moreFiltersToggle.addEventListener('click', function () {
            const isOpen = moreFiltersToggle.getAttribute('aria-expanded') === 'true';
            moreFiltersToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            advancedSearchFilters.hidden = isOpen;
            moreFiltersToggle.classList.toggle('is-open', !isOpen);
        });

        // If an advanced filter is already active after a search, keep the section open.
        const advancedHasValue = Array.from(advancedSearchFilters.querySelectorAll('select, input'))
            .some(function (field) { return field.value !== ''; });
        if (advancedHasValue) {
            moreFiltersToggle.setAttribute('aria-expanded', 'true');
            advancedSearchFilters.hidden = false;
            moreFiltersToggle.classList.add('is-open');
        }
    }

    const partnerSearchForm = document.getElementById('partnerSearchForm');

    // Search UI helpers. These run entirely on the current page; no completed
    // dashboard sections are involved.
    function updateGenderSelectionUI() {
        document.querySelectorAll('.gender-option').forEach(function (option) {
            const radio = option.querySelector('input[name="gender"]');
            option.classList.toggle('selected', !!radio && radio.checked);
        });
    }

    function updateGenderSpecificFilters() {
        const selected = document.querySelector('input[name="gender"]:checked');
        const gender = selected ? selected.value : '';
        const beardGroup = document.getElementById('beardFilterGroup');
        const hijabGroup = document.getElementById('hijabFilterGroup');

        if (beardGroup) beardGroup.style.display = gender === 'Female' ? 'none' : '';
        if (hijabGroup) hijabGroup.style.display = gender === 'Male' ? 'none' : '';
    }

    if (partnerSearchForm) {
        partnerSearchForm.addEventListener('submit', function (event) {
            if (!partnerSearchForm.checkValidity()) return;

            event.preventDefault();

            // Build the exact GET query from the current filter values.
            // Use the results-grid fragment so the browser opens the new page
            // directly at the profile cards, instead of loading at the top and
            // visibly scrolling down afterward.
            const url = new URL(partnerSearchForm.getAttribute('action') || window.location.href, window.location.origin);
            url.search = new URLSearchParams(new FormData(partnerSearchForm)).toString();
            url.hash = 'matching-results-grid';

            window.location.href = url.toString();
        });
    }

    document.querySelectorAll('input[name="gender"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            updateGenderSpecificFilters();
            updateGenderSelectionUI();
        });
    });

    updateGenderSpecificFilters();
    updateGenderSelectionUI();

    // Bookmark: submit in the background so the current card position never reloads or jumps.
    // The existing PHP bookmark action remains unchanged; we only consume its redirected
    // response here and update the clicked card when the database action succeeds.
    document.querySelectorAll('.dashboard-bookmark-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            const button = form.querySelector('.bookmark-button');
            const actionField = form.querySelector('input[name="bookmark_action"]');
            if (!button || !actionField || form.dataset.bookmarkBusy === '1') return;

            const requestedAction = actionField.value;
            const currentScrollY = window.scrollY || window.pageYOffset || 0;
            const wasBookmarked = button.classList.contains('is-bookmarked');
            const icon = button.querySelector('i');
            form.dataset.bookmarkBusy = '1';
            button.disabled = true;

            // Update the visual state immediately. The existing server-side action
            // remains authoritative; if it fails, the previous state is restored.
            const optimisticAdded = requestedAction === 'add';
            button.classList.toggle('is-bookmarked', optimisticAdded);
            button.title = optimisticAdded ? 'Remove bookmark' : 'Bookmark profile';
            // Keep the next click in sync with the instant visual state.
            actionField.value = optimisticAdded ? 'remove' : 'add';
            if (icon) {
                icon.classList.toggle('fa-solid', optimisticAdded);
                icon.classList.toggle('fa-regular', !optimisticAdded);
            }

            // Capture the intended server action BEFORE changing the hidden field
            // for the next click's optimistic UI state.
            const requestData = new FormData(form);
            requestData.set('bookmark_action', requestedAction);

            fetch(form.action, {
                method: 'POST',
                body: requestData,
                credentials: 'same-origin',
                redirect: 'follow',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (response) {
                return response.text();
            })
            .then(function (html) {
                const parsed = new DOMParser().parseFromString(html, 'text/html');
                const serverToast = parsed.querySelector('.dashboard-bookmark-toast');
                const message = serverToast ? serverToast.textContent.trim() : '';

                if (message === 'Profile bookmarked.' || message === 'Bookmark removed.') {
                    const added = requestedAction === 'add';

                    const toast = document.createElement('div');
                    toast.className = 'dashboard-bookmark-toast';
                    toast.textContent = message;
                    document.body.appendChild(toast);
                    setTimeout(function () { toast.classList.add('show'); }, 20);
                    setTimeout(function () {
                        toast.classList.remove('show');
                        setTimeout(function () { toast.remove(); }, 250);
                    }, 2200);
                } else if (message) {
                    // Server rejected the action, so restore the exact previous visual state.
                    button.classList.toggle('is-bookmarked', wasBookmarked);
                    button.title = wasBookmarked ? 'Remove bookmark' : 'Bookmark profile';
                    actionField.value = wasBookmarked ? 'remove' : 'add';
                    if (icon) {
                        icon.classList.toggle('fa-solid', wasBookmarked);
                        icon.classList.toggle('fa-regular', !wasBookmarked);
                    }

                    const toast = document.createElement('div');
                    toast.className = 'dashboard-bookmark-toast';
                    toast.textContent = message;
                    document.body.appendChild(toast);
                    setTimeout(function () { toast.classList.add('show'); }, 20);
                    setTimeout(function () {
                        toast.classList.remove('show');
                        setTimeout(function () { toast.remove(); }, 250);
                    }, 2200);
                }
            })
            .catch(function () {
                // Network/server failure: roll back the optimistic visual change.
                button.classList.toggle('is-bookmarked', wasBookmarked);
                button.title = wasBookmarked ? 'Remove bookmark' : 'Bookmark profile';
                actionField.value = wasBookmarked ? 'remove' : 'add';
                if (icon) {
                    icon.classList.toggle('fa-solid', wasBookmarked);
                    icon.classList.toggle('fa-regular', !wasBookmarked);
                }

                const toast = document.createElement('div');
                toast.className = 'dashboard-bookmark-toast';
                toast.textContent = 'Unable to save the bookmark right now.';
                document.body.appendChild(toast);
                setTimeout(function () { toast.classList.add('show'); }, 20);
                setTimeout(function () {
                    toast.classList.remove('show');
                    setTimeout(function () { toast.remove(); }, 250);
                }, 2200);
            })
            .finally(function () {
                form.dataset.bookmarkBusy = '0';
                button.disabled = false;
                window.scrollTo(0, currentScrollY);
            });
        });
    });

    const clearSearchFilters = document.getElementById('clearSearchFilters');
    if (clearSearchFilters && partnerSearchForm) {
        clearSearchFilters.addEventListener('click', function (event) {
            event.preventDefault();

            // Reset the controls in-place. Do NOT navigate/reload, so the
            // current viewport position remains exactly where the user clicked.
            const clearScrollY = window.scrollY || window.pageYOffset || 0;

            // Explicitly clear every visible search control, including the
            // collapsed More Filters fields. Form.reset() can restore the
            // values that were present when the current search page loaded,
            // so clear each non-hidden field instead.
            partnerSearchForm.querySelectorAll('select, input:not([type="hidden"]), textarea').forEach(function (field) {
                if (field.type === 'radio' || field.type === 'checkbox') {
                    field.checked = false;
                } else {
                    field.value = '';
                }
            });

            if (district) district.innerHTML = '<option value="">Any District</option>';
            if (upazila) upazila.innerHTML = '<option value="">Any Upazila</option>';

            if (division) division.value = '';
            if (district) district.value = '';
            if (upazila) upazila.value = '';

            updateGenderSelectionUI();
            updateGenderSpecificFilters();

            // Remove old search parameters without changing scroll position.
            const clearUrl = new URL(window.location.href);
            clearUrl.search = '';
            clearUrl.hash = '';
            window.history.replaceState({}, '', clearUrl.toString());

            // reset()/replaceState() must not move the viewport. Restore the
            // exact position after the browser has finished processing the click.
            requestAnimationFrame(function () {
                window.scrollTo(0, clearScrollY);
            });
        });
    }

    // Clear any legacy bookmark scroll marker left by an older dashboard version.
    sessionStorage.removeItem('dashboard_bookmark_scroll');
    const bookmarkFlash = <?= json_encode($dashboard_bookmark_flash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    if (bookmarkFlash) {
        const toast = document.createElement('div');
        toast.className = 'dashboard-bookmark-toast';
        toast.textContent = bookmarkFlash;
        document.body.appendChild(toast);
        setTimeout(function () { toast.classList.add('show'); }, 20);
        setTimeout(function () { toast.classList.remove('show'); setTimeout(function () { toast.remove(); }, 250); }, 2200);
    }

})();
</script>

<?php include 'includes/footer.php'; ?>
