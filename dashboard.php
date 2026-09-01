<?php

$page_css = 'assets/css/dashboard.css';

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

/* ---------------------------------------------------------
   Profile completion
   This is calculated from the fields already present in
   user_profiles; no database schema change is required.
--------------------------------------------------------- */

$completion_fields = [
    'date_of_birth',
    'religion',
    'highest_education',
    'profession',
    'bio',
    'photo',
    'height_cm',
    'weight_kg',
    'complexion',
    'family_type',
    'family_status',
    'division_id',
    'district_id',
    'upazila_id',
    'smoking_status',
    'prayer_status',
    'mahram_maintained'
];

$filled = 0;
foreach ($completion_fields as $field) {
    if (array_key_exists($field, $current_user) && $current_user[$field] !== null && $current_user[$field] !== '') {
        $filled++;
    }
}

$profile_completion = (int) round(($filled / count($completion_fields)) * 100);
$profile_completion = max(0, min(100, $profile_completion));
$profile_complete = $profile_completion >= 100;

$profile_link = 'profile/create_profile.php';
$profile_cta_text = $profile_complete ? 'View / Update Profile' : 'Update Profile Details';

/* ---------------------------------------------------------
   Search filters
--------------------------------------------------------- */

$search_submitted = isset($_GET['search']);

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
        "up.gender = ?"
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
            up.gender,
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

include 'includes/header.php';
?>

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
            <a href="#profile-section"><i class="fa-solid fa-user-circle"></i><span>My Profile</span></a>
            <a href="#partner-search"><i class="fa-solid fa-magnifying-glass"></i><span>Find Partner</span></a>
            <a href="#wedding-services"><i class="fa-solid fa-ring"></i><span>Wedding Services</span></a>
            <a href="<?= BASE_URL; ?>cart.php"><i class="fa-solid fa-cart-shopping"></i><span>My Service Cart</span></a>
            <a href="#"><i class="fa-solid fa-heart"></i><span>My Matches</span><span class="menu-soon">Soon</span></a>
            <a href="#"><i class="fa-solid fa-bookmark"></i><span>Bookmarks</span><span class="menu-soon">Soon</span></a>
            <a href="#"><i class="fa-solid fa-comments"></i><span>Messages</span><span class="menu-soon">Soon</span></a>

            <?php if (($current_user['role'] ?? '') === 'Admin'): ?>
                <a href="#"><i class="fa-solid fa-user-shield"></i><span>Admin Login</span></a>
            <?php else: ?>
                <a href="#" class="disabled-link"><i class="fa-solid fa-user-shield"></i><span>Admin Login</span><span class="menu-soon">Restricted</span></a>
            <?php endif; ?>

            <?php if (($current_user['role'] ?? '') === 'Manager'): ?>
                <a href="#"><i class="fa-solid fa-calendar-check"></i><span>Event Manager</span></a>
            <?php else: ?>
                <a href="#" class="disabled-link"><i class="fa-solid fa-calendar-check"></i><span>Event Manager</span><span class="menu-soon">Restricted</span></a>
            <?php endif; ?>
        </nav>

        <div class="menu-footer">
            <a href="<?= BASE_URL; ?>logout.php" class="menu-logout">
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </a>
        </div>
    </aside>

    <main>

        <!-- ===================== WELCOME ===================== -->
        <section class="dashboard-hero">
            <div class="dashboard-container hero-grid">

                <div class="welcome-copy">
                    <span class="section-kicker"><i class="fa-solid fa-sparkles"></i> YOUR MATRIMONY SPACE</span>
                    <h1>Welcome back, <span><?= htmlspecialchars($current_user['first_name'] ?? 'Member'); ?></span> 👋</h1>
                    <p>
                        Manage your profile, discover compatible partners and explore wedding services — all from one place.
                    </p>

                    <div class="hero-mini-stats">
                        <div><strong><?= number_format($total_results); ?></strong><span>Current Matches</span></div>
                        <div><strong><?= $profile_completion; ?>%</strong><span>Profile Complete</span></div>
                        <div><strong><?= count($services); ?></strong><span>Service Categories</span></div>
                    </div>
                </div>

                <div class="profile-summary" id="profile-section">
                    <div class="profile-summary-top">
                        <div class="profile-avatar">
                            <?php if (!empty($current_user['photo'])): ?>
                                <img src="<?= BASE_URL; ?>uploads/profile/<?= htmlspecialchars($current_user['photo']); ?>" alt="Profile photo">
                            <?php else: ?>
                                <i class="fa-solid fa-user"></i>
                            <?php endif; ?>
                        </div>

                        <div class="profile-summary-info">
                            <span class="profile-label">YOUR PROFILE</span>
                            <h2><?= htmlspecialchars($display_name); ?></h2>
                            <p><?= htmlspecialchars($current_user['email'] ?? ''); ?></p>
                        </div>

                        <a href="<?= BASE_URL . $profile_link; ?>" class="profile-icon-btn" title="View / Update Profile">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>
                    </div>

                    <div class="completion-row">
                        <div>
                            <span>Profile completion</span>
                            <strong><?= $profile_completion; ?>%</strong>
                        </div>
                        <div class="completion-track">
                            <span style="width: <?= $profile_completion; ?>%"></span>
                        </div>
                    </div>

                    <?php if (!$profile_complete): ?>
                        <div class="profile-alert">
                            <div class="profile-alert-icon"><i class="fa-solid fa-user-pen"></i></div>
                            <div>
                                <strong>Your profile needs a little more detail.</strong>
                                <p>A complete profile helps other members understand you better and improves matching.</p>
                            </div>
                            <a href="<?= BASE_URL . $profile_link; ?>" class="profile-cta">
                                <?= htmlspecialchars($profile_cta_text); ?>
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="profile-complete-note">
                            <i class="fa-solid fa-circle-check"></i>
                            Your profile is complete. You can update it anytime.
                            <a href="<?= BASE_URL . $profile_link; ?>">Profile</a>
                        </div>
                    <?php endif; ?>
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

                <form method="GET" class="search-panel" autocomplete="off">
                    <input type="hidden" name="search" value="1">

                    <div class="gender-choice-wrap">
                        <label class="filter-label required-label">Looking for</label>
                        <div class="gender-choice-grid">
                            <label class="gender-option <?= $target_gender === 'Female' ? 'selected' : ''; ?>">
                                <input type="radio" name="gender" value="Female" <?= $target_gender === 'Female' ? 'checked' : ''; ?> required>
                                <span class="gender-icon"><i class="fa-solid fa-venus"></i></span>
                                <span><strong>Bride</strong><small>Search female profiles</small></span>
                            </label>

                            <label class="gender-option <?= $target_gender === 'Male' ? 'selected' : ''; ?>">
                                <input type="radio" name="gender" value="Male" <?= $target_gender === 'Male' ? 'checked' : ''; ?> required>
                                <span class="gender-icon"><i class="fa-solid fa-mars"></i></span>
                                <span><strong>Groom</strong><small>Search male profiles</small></span>
                            </label>
                        </div>
                    </div>

                    <div class="filter-grid">
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

                        <div class="filter-group" id="beardFilterGroup">
                            <label class="filter-label">Beard</label>
                            <select name="beard_status" class="filter-control">
                                <option value="">Any</option>
                                <?php foreach (['Yes', 'No', 'Not Applicable'] as $value): ?>
                                    <option value="<?= htmlspecialchars($value); ?>" <?= $beard_status === $value ? 'selected' : ''; ?>><?= htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group" id="hijabFilterGroup">
                            <label class="filter-label">Hijab</label>
                            <select name="hijab_status" class="filter-control">
                                <option value="">Any</option>
                                <?php foreach (['Yes', 'No', 'Not Applicable'] as $value): ?>
                                    <option value="<?= htmlspecialchars($value); ?>" <?= $hijab_status === $value ? 'selected' : ''; ?>><?= htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="search-actions">
                        <a href="<?= BASE_URL; ?>dashboard.php#partner-search" class="clear-search">
                            <i class="fa-solid fa-rotate-left"></i> Clear filters
                        </a>
                        <button type="submit" class="search-button">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            Search <?= $target_gender === 'Male' ? 'Groom' : ($target_gender === 'Female' ? 'Bride' : 'Profiles'); ?>
                        </button>
                    </div>
                </form>

                <!-- Results -->
                <div class="results-header">
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
                    <div class="empty-results">
                        <div class="empty-results-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                        <h4>Start with Bride or Groom</h4>
                        <p>Pick the mandatory search type above, then use as many optional filters as you need.</p>
                    </div>
                <?php elseif ($search_submitted && $target_gender === ''): ?>
                    <div class="empty-results warning-empty">
                        <div class="empty-results-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
                        <h4>Please choose Bride or Groom</h4>
                        <p>The search target is mandatory before other filters can be applied.</p>
                    </div>
                <?php elseif (empty($results)): ?>
                    <div class="empty-results">
                        <div class="empty-results-icon"><i class="fa-regular fa-face-frown"></i></div>
                        <h4>No matching profiles yet</h4>
                        <p>Try removing one or two filters to broaden your search.</p>
                    </div>
                <?php else: ?>
                    <div class="profile-results-grid">
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
                            ?>
                            <article class="profile-card">
                                <div class="profile-card-photo">
                                    <?php if ($photo !== '' && ($profile['photo_visibility'] ?? '') === 'Everyone'): ?>
                                        <img src="<?= BASE_URL; ?>uploads/profile/<?= htmlspecialchars($photo); ?>" alt="Profile photo">
                                    <?php elseif ($photo !== ''): ?>
                                        <div class="profile-photo-placeholder private-photo"><i class="fa-solid fa-lock"></i></div>
                                    <?php else: ?>
                                        <div class="profile-photo-placeholder"><i class="fa-solid fa-user"></i></div>
                                    <?php endif; ?>

                                    <?php if (($profile['verification_status'] ?? '') === 'Verified'): ?>
                                        <span class="verified-badge"><i class="fa-solid fa-circle-check"></i> Verified</span>
                                    <?php endif; ?>
                                </div>

                                <div class="profile-card-body">
                                    <div class="profile-card-name-row">
                                        <div>
                                            <h4><?= htmlspecialchars($name); ?></h4>
                                            <p>
                                                <?= $age !== '' ? (int) $age . ' yrs' : 'Age not provided'; ?>
                                                <?php if (!empty($profile['religion'])): ?> · <?= htmlspecialchars($profile['religion']); ?><?php endif; ?>
                                            </p>
                                        </div>
                                        <button type="button" class="bookmark-button" title="Bookmark">
                                            <i class="fa-regular fa-bookmark"></i>
                                        </button>
                                    </div>

                                    <div class="profile-facts">
                                        <?php if (!empty($profile['profession'])): ?><span><i class="fa-solid fa-briefcase"></i><?= htmlspecialchars($profile['profession']); ?></span><?php endif; ?>
                                        <?php if (!empty($profile['highest_education'])): ?><span><i class="fa-solid fa-graduation-cap"></i><?= htmlspecialchars($profile['highest_education']); ?></span><?php endif; ?>
                                        <?php if (!empty($profile['height_cm']) || !empty($profile['weight_kg'])): ?>
                                            <span><i class="fa-solid fa-ruler-combined"></i><?= !empty($profile['height_cm']) ? htmlspecialchars($profile['height_cm']) . ' cm' : 'Height —'; ?><?= !empty($profile['weight_kg']) ? ' · ' . htmlspecialchars($profile['weight_kg']) . ' kg' : ''; ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($profile['complexion'])): ?><span><i class="fa-solid fa-user"></i><?= htmlspecialchars($profile['complexion']); ?></span><?php endif; ?>
                                        <?php if (!empty($profile['family_type']) || !empty($profile['family_status'])): ?><span><i class="fa-solid fa-house-user"></i><?= htmlspecialchars(trim(($profile['family_type'] ?? '') . ' · ' . ($profile['family_status'] ?? ''), ' ·')); ?></span><?php endif; ?>
                                        <?php if (!empty($profile['prayer_status'])): ?><span><i class="fa-solid fa-mosque"></i><?= htmlspecialchars($profile['prayer_status']); ?></span><?php endif; ?>
                                        <?php if (!empty($profile['smoking_status'])): ?><span><i class="fa-solid fa-ban-smoking"></i><?= htmlspecialchars($profile['smoking_status']); ?></span><?php endif; ?>
                                        <?php if (!empty($profile['blood_group'])): ?><span><i class="fa-solid fa-droplet"></i><?= htmlspecialchars($profile['blood_group']); ?></span><?php endif; ?>
                                        <?php if (!empty($profile['district_name']) || !empty($profile['district'])): ?><span><i class="fa-solid fa-location-dot"></i><?= htmlspecialchars($profile['district_name'] ?? $profile['district'] ?? ''); ?></span><?php endif; ?>
                                    </div>

                                    <a class="view-profile-button" href="profile/view_profile.php?user_id=<?= (int) $profile['user_id']; ?>">
                                        View Details Profile
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </a>
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
                <div class="section-heading">
                    <div>
                        <span class="section-kicker">WEDDING PLANNING</span>
                        <h2>Wedding Services</h2>
                        <p>Choose a service to explore its available packages, providers, prices and booking options.</p>
                    </div>
                    <span class="service-phase-note"><i class="fa-solid fa-database"></i> Database connected</span>
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
                                <?= (int) $service['provider_count']; ?> <?= (int) $service['provider_count'] === 1 ? 'option' : 'options'; ?>
                            </span>
                            <i class="service-arrow fa-solid fa-arrow-right"></i>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="service-roadmap">
                    <div class="roadmap-icon"><i class="fa-solid fa-circle-info"></i></div>
                    <div>
                        <strong>Next service-management phase</strong>
                        <p>Click any category to compare available options. You can add selected packages to your cart and continue to booking.</p>
                    </div>
                </div>
            </div>
        </section>

    </main>
</div>

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

    function updateGenderSpecificFilters() {
        const selected = document.querySelector('input[name="gender"]:checked');
        const gender = selected ? selected.value : '';
        const beardGroup = document.getElementById('beardFilterGroup');
        const hijabGroup = document.getElementById('hijabFilterGroup');

        beardGroup.style.display = gender === 'Female' ? 'none' : '';
        hijabGroup.style.display = gender === 'Male' ? 'none' : '';
    }

    document.querySelectorAll('input[name="gender"]').forEach(function (radio) {
        radio.addEventListener('change', updateGenderSpecificFilters);
    });

    updateGenderSpecificFilters();
})();
</script>

<?php include 'includes/footer.php'; ?>
