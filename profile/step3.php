<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';
require_once '../includes/dropdowns.php';
require_once '../includes/profile_completion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$error = '';

/* Display-only Bengali labels for selected Step 3 options; stored values stay unchanged. */
function sm_step3_option_label($value) {
    $labels = [
        'Early sleeper' => 'রাতে তাড়াতাড়ি ঘুমান',
        'Night owl' => 'রাতে দেরি করে ঘুমান',
        'Normal' => 'স্বাভাবিক',
        'Prefer small circle' => 'ছোট পরিসরে মেলামেশা পছন্দ',
        'Private' => 'ব্যক্তিগত থাকতে পছন্দ করেন',
        'Late sleeper' => 'রাতে দেরিতে ঘুমান',
        'Regular sleeper' => 'নিয়মিত সময়ে ঘুমান',
        'Moderately social' => 'মাঝামাঝি মিশুক',
        'Very social' => 'খুব মিশুক',
        'Introvert' => 'অন্তর্মুখী',
        'Extrovert' => 'বহির্মুখী',
        'Ambivert' => 'উভয়মুখী',
        'Reading' => 'বই পড়া',
        'Travelling' => 'ভ্রমণ',
        'Sports' => 'খেলাধুলা',
        'Photography' => 'ছবি তোলা',
        'Cooking' => 'রান্না করা',
        'Gaming' => 'গেম খেলা',
        'Writing' => 'লেখালেখি',
        'Gardening' => 'বাগান করা',
        'Volunteering' => 'স্বেচ্ছাসেবা',
        'Islamic learning' => 'ইসলামি জ্ঞানচর্চা',
        'Offline Chatting' => 'সরাসরি আড্ডা',
        'Riding' => 'সাইকেল/মোটরসাইকেল চালানো',
        'Housework' => 'ঘরের কাজ',
        'Social Media' => 'সামাজিক যোগাযোগমাধ্যম',
        'Sleeping' => 'ঘুমানো',
        'No diseases' => 'কোনো রোগ নেই',
        'Hijab (Hand, Foot, Face Covered)' => 'হিজাব (হাত, পা ও মুখ আবৃত)',
    ];
    return sm_text($value, $labels[$value] ?? $value);
}

/* Load profile */
$profileStmt = mysqli_prepare($conn, 'SELECT * FROM user_profiles WHERE user_id=? LIMIT 1');
mysqli_stmt_bind_param($profileStmt, 'i', $user_id);
mysqli_stmt_execute($profileStmt);
$profileResult = mysqli_stmt_get_result($profileStmt);
$user = mysqli_fetch_assoc($profileResult);

if (!$user) {
    $user = [
    'smoking_status' => '',
    'prayer_status' => '',
    'fasting_status' => '',
    'religious_practice' => '',
    'quran_reading' => '',
    'halal_lifestyle' => '',
    'mahram_maintained' => '',
    'islamic_knowledge' => '',
    'islamic_activities' => '',
    'hijab_status' => '',
    'hijab_details' => '',
    'beard_status' => '',
    'diet_preference' => '',
    'tea_coffee' => '',
    'sleep_pattern' => '',
    'social_nature' => '',
    'spending_style' => '',
    'travel_interest' => '',
    'pets' => '',
    'free_time_interests' => '',
    'personality_type' => '',
    'gender' => '',
    ];
}

/* Load health profile */
$health = [];
$healthStmt = mysqli_prepare($conn, 'SELECT blood_group, disability_status, medical_notes FROM health_profiles WHERE user_id=? LIMIT 1');
mysqli_stmt_bind_param($healthStmt, 'i', $user_id);
mysqli_stmt_execute($healthStmt);
$healthResult = mysqli_stmt_get_result($healthStmt);
if ($healthResult && mysqli_num_rows($healthResult) > 0) {
    $health = mysqli_fetch_assoc($healthResult);
}

/* Existing multi-select value */
$selected_interests = [];
if (!empty($user['free_time_interests'])) {
    $decoded = json_decode($user['free_time_interests'], true);
    if (is_array($decoded)) {
        $selected_interests = $decoded;
    } else {
        $selected_interests = array_filter(array_map('trim', explode(',', $user['free_time_interests'])));
    }
}

/* Save Step 3 */
if (isset($_POST['save_step3'])) {
    $smoking_status       = clean_input($_POST['smoking_status'] ?? '');
    $prayer_status        = clean_input($_POST['prayer_status'] ?? '');
    $beard_status         = clean_input($_POST['beard_status'] ?? 'Not Applicable');
    $hijab_status         = clean_input($_POST['hijab_status'] ?? 'Not Applicable');
    $hijab_details        = clean_input($_POST['hijab_details'] ?? '');
    $mahram_input         = clean_input($_POST['mahram_maintained'] ?? '');
    $quran_reading        = clean_input($_POST['quran_reading'] ?? '');
    $fasting_status       = clean_input($_POST['fasting_status'] ?? '');
    $religious_practice   = clean_input($_POST['religious_practice'] ?? '');
    $islamic_knowledge    = clean_input($_POST['islamic_knowledge'] ?? '');
    $halal_lifestyle      = clean_input($_POST['halal_lifestyle'] ?? '');
    $islamic_activities   = clean_input($_POST['islamic_activities'] ?? '');
    $favorite_scholars = clean_input($_POST['favorite_scholars'] ?? '');
    $favorite_books = clean_input($_POST['favorite_books'] ?? '');
    $religious_knowledge_sources = clean_input($_POST['religious_knowledge_sources'] ?? '');
    $bidah_shrine_stance = clean_input($_POST['bidah_shrine_stance'] ?? '');
    $tea_coffee           = clean_input($_POST['tea_coffee'] ?? '');
    $diet_preference      = clean_input($_POST['diet_preference'] ?? '');
    $sleep_pattern        = clean_input($_POST['sleep_pattern'] ?? '');
    $personality_type     = clean_input($_POST['personality_type'] ?? '');
    $social_nature        = clean_input($_POST['social_nature'] ?? '');
    $travel_interest      = clean_input($_POST['travel_interest'] ?? '');
    $spending_style       = clean_input($_POST['spending_style'] ?? '');
    $pets                  = clean_input($_POST['pets'] ?? '');
    $blood_group           = clean_input($_POST['blood_group'] ?? '');
    $physical_disability   = clean_input($_POST['physical_disability'] ?? '');
    $health_notes          = clean_input($_POST['health_notes'] ?? '');

    $interests_post = $_POST['free_time_interests'] ?? [];
    if (!is_array($interests_post)) {
        $interests_post = [];
    }

    /*
     * Validate every dropdown value against the central dropdown source.
     * This keeps Step 3 safe even if someone manually alters the POST data.
     */
    $dropdown_rules = [
        'smoking_status'      => $smoking_statuses,
        'prayer_status'       => $prayer_statuses,
        'quran_reading'       => $quran_reading_options,
        'fasting_status'      => $fasting_options,
        'religious_practice'  => $religious_practice_options,
        'islamic_knowledge'   => $islamic_knowledge_levels,
        'halal_lifestyle'     => $halal_lifestyle_options,
        'tea_coffee'          => $tea_coffee_options,
        'diet_preference'     => $diet_preferences,
        'sleep_pattern'       => $sleep_patterns,
        'personality_type'    => $personality_types,
        'social_nature'       => $social_natures,
        'travel_interest'     => $travel_interest_options,
        'spending_style'      => $spending_style_options,
        'pets'                => $pet_options,
        'blood_group'         => $blood_groups,
        'physical_disability' => $physical_disabilities,
    ];

    $invalid_dropdown = false;
    foreach ($dropdown_rules as $field => $allowed_values) {
        $value = ${$field};
        if ($value !== '' && !in_array($value, array_map('strval', $allowed_values), true)) {
            $invalid_dropdown = true;
            break;
        }
    }

    $allowed_interests = array_map('strval', $free_time_interest_options);
    $submitted_interests = array_values(array_unique(array_map('strval', $interests_post)));
    $invalid_interest = (bool) array_diff($submitted_interests, $allowed_interests);
    $invalid_mahram = ($mahram_input !== '' && !in_array($mahram_input, array_map('strval', $mahram_options), true));
    $selected_interests = array_values(array_intersect($allowed_interests, $submitted_interests));
    $free_time_interests = $selected_interests ? json_encode($selected_interests, JSON_UNESCAPED_UNICODE) : null;

    $required_missing = (
        $smoking_status === '' ||
        $prayer_status === '' ||
        $islamic_knowledge === '' ||
        $halal_lifestyle === '' ||
        $sleep_pattern === '' ||
        $personality_type === '' ||
        $social_nature === ''
    );

    /* Keep the existing gender-specific fields consistent. */
    if (($user['gender'] ?? '') === 'Male') {
        $hijab_status = 'Not Applicable';
    } elseif (($user['gender'] ?? '') === 'Female') {
        $beard_status = 'Not Applicable';
    } else {
        $beard_status = 'Not Applicable';
        $hijab_status = 'Not Applicable';
    }

    if ($mahram_input === 'Yes') {
        $mahram_maintained = 1;
    } elseif ($mahram_input === 'No') {
        $mahram_maintained = 0;
    } else {
        $mahram_maintained = null;
    }

    if ($invalid_dropdown || $invalid_interest || $invalid_mahram) {
        $error = 'Please select valid options from the available lists.';
    } elseif ($required_missing) {
        $error = 'Please fill all required fields marked with *.';
    } else {
        mysqli_begin_transaction($conn);

        try {
            $update = mysqli_prepare($conn, '
                UPDATE user_profiles SET
                    smoking_status=?,
                    prayer_status=?,
                    beard_status=?,
                    hijab_status=?,
                    hijab_details=?,
                    mahram_maintained=?,
                    quran_reading=?,
                    fasting_status=?,
                    religious_practice=?,
                    islamic_knowledge=?,
                    halal_lifestyle=?,
                    islamic_activities=?,
                    favorite_scholars=?,
                    favorite_books=?,
                    religious_knowledge_sources=?,
                    bidah_shrine_stance=?,
                    tea_coffee=?,
                    diet_preference=?,
                    sleep_pattern=?,
                    personality_type=?,
                    social_nature=?,
                    free_time_interests=?,
                    travel_interest=?,
                    spending_style=?,
                    pets=?
                WHERE user_id=?
            ');

            mysqli_stmt_bind_param(
                $update,
                'sssssisssssssssssssssssssi',
                $smoking_status,
                $prayer_status,
                $beard_status,
                $hijab_status,
                $hijab_details,
                $mahram_maintained,
                $quran_reading,
                $fasting_status,
                $religious_practice,
                $islamic_knowledge,
                $halal_lifestyle,
                $islamic_activities,
                $favorite_scholars,
                $favorite_books,
                $religious_knowledge_sources,
                $bidah_shrine_stance,
                $tea_coffee,
                $diet_preference,
                $sleep_pattern,
                $personality_type,
                $social_nature,
                $free_time_interests,
                $travel_interest,
                $spending_style,
                $pets,
                $user_id
            );

            if (!mysqli_stmt_execute($update)) {
                throw new Exception('Profile update failed.');
            }

            $healthCheck = mysqli_prepare($conn, 'SELECT health_profile_id FROM health_profiles WHERE user_id=? LIMIT 1');
            mysqli_stmt_bind_param($healthCheck, 'i', $user_id);
            mysqli_stmt_execute($healthCheck);
            $healthCheckResult = mysqli_stmt_get_result($healthCheck);

            if ($healthCheckResult && mysqli_num_rows($healthCheckResult) > 0) {
                $healthUpdate = mysqli_prepare($conn, '
                    UPDATE health_profiles SET
                        blood_group=?,
                        disability_status=?,
                        medical_notes=?
                    WHERE user_id=?
                ');
                mysqli_stmt_bind_param($healthUpdate, 'sssi', $blood_group, $physical_disability, $health_notes, $user_id);
                if (!mysqli_stmt_execute($healthUpdate)) {
                    throw new Exception('Health information update failed.');
                }
            } else {
                $healthInsert = mysqli_prepare($conn, '
                    INSERT INTO health_profiles (user_id, blood_group, disability_status, medical_notes)
                    VALUES (?, ?, ?, ?)
                ');
                mysqli_stmt_bind_param($healthInsert, 'isss', $user_id, $blood_group, $physical_disability, $health_notes);
                if (!mysqli_stmt_execute($healthInsert)) {
                    throw new Exception('Health information save failed.');
                }
            }

            mysqli_commit($conn);
            header('Location: step4.php');
            exit();
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $error = 'Unable to save Step 3 right now. Please try again.';
        }
    }

    /* Keep the form populated after validation errors. */
    $user['smoking_status'] = $smoking_status;
    $user['prayer_status'] = $prayer_status;
    $user['beard_status'] = $beard_status;
    $user['hijab_status'] = $hijab_status;
    $user['hijab_details'] = $hijab_details;
    $user['mahram_maintained'] = $mahram_maintained;
    $user['quran_reading'] = $quran_reading;
    $user['fasting_status'] = $fasting_status;
    $user['religious_practice'] = $religious_practice;
    $user['islamic_knowledge'] = $islamic_knowledge;
    $user['halal_lifestyle'] = $halal_lifestyle;
    $user['islamic_activities'] = $islamic_activities;
    $user['favorite_scholars'] = $favorite_scholars;
    $user['favorite_books'] = $favorite_books;
    $user['religious_knowledge_sources'] = $religious_knowledge_sources;
    $user['bidah_shrine_stance'] = $bidah_shrine_stance;
    $user['tea_coffee'] = $tea_coffee;
    $user['diet_preference'] = $diet_preference;
    $user['sleep_pattern'] = $sleep_pattern;
    $user['personality_type'] = $personality_type;
    $user['social_nature'] = $social_nature;
    $user['free_time_interests'] = $free_time_interests;
    $user['travel_interest'] = $travel_interest;
    $user['spending_style'] = $spending_style;
    $user['pets'] = $pets;
    $health['blood_group'] = $blood_group;
    $health['disability_status'] = $physical_disability;
    $health['medical_notes'] = $health_notes;
}

$page_css = 'assets/css/profile-step3.css';
include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container py-4 py-lg-5 profile-step3-page">
    <div class="row justify-content-center">
        <div class="col-12 col-xxl-10">
            <div class="step3-shell">
                <?php
                $currentStep = 3;
                $pageTitle = sm_text('Lifestyle & Health', 'জীবনযাপন ও স্বাস্থ্য');
                $pageDescription = '';
                $steps = [
                    1 => ['title' => sm_text('Personal', 'ব্যক্তিগত তথ্য'), 'icon' => 'bi-person-circle', 'url' => 'step1.php'],
                    2 => ['title' => sm_text('Family', 'পারিবারিক তথ্য'), 'icon' => 'bi-people', 'url' => 'step2.php'],
                    3 => ['title' => sm_text('Lifestyle', 'জীবনযাপন'), 'icon' => 'bi-heart', 'url' => 'step3.php'],
                    4 => ['title' => sm_text('Location', 'ঠিকানা'), 'icon' => 'bi-geo-alt', 'url' => 'step4.php'],
                    5 => ['title' => sm_text('Privacy', 'গোপনীয়তা'), 'icon' => 'bi-shield-lock', 'url' => 'step5.php'],
                    6 => ['title' => sm_text('PP & QnA', 'সঙ্গীর পছন্দ ও প্রশ্নোত্তর'), 'icon' => 'bi-patch-question', 'url' => 'step6.php']
                ];
                $completion = sm_get_profile_completion($conn, $user_id);
                $progress = $completion['percentage'];
                ?>

                <header class="step3-wizard-head">
                    <div class="step3-heading-row">
                        <div>
                            <span class="step3-kicker"><?= sm_text('PROFILE BUILDER', 'প্রোফাইল তৈরি') ?></span>
                            <h1><?= sm_text('Step', 'ধাপ') ?> <?= (($GLOBALS['current_language'] ?? 'bn') === 'en' ? $currentStep : strtr((string)$currentStep, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯'])) ?></h1>
                            <p><?= htmlspecialchars($pageTitle) ?></p>
                        </div>
                        <a class="step3-logo" href="../dashboard.php" aria-label="Back to dashboard">
                            <img src="<?= BASE_URL ?>assets/images/logo/logo.png" alt="Smart Matrimony">
                        </a>
                    </div>

                    <nav class="step3-step-nav" aria-label="Profile steps">
                        <?php foreach ($steps as $stepNo => $step): ?>
                            <a class="step3-step <?= $stepNo === $currentStep ? 'is-current' : '' ?> <?= !empty($completion['steps'][$stepNo]) ? 'is-completed' : '' ?>"
                               href="<?= htmlspecialchars($step['url']) ?>"
                               aria-current="<?= $stepNo === $currentStep ? 'step' : 'false' ?>">
                                <span class="step3-step-icon"><i class="bi <?= $step['icon'] ?>"></i></span>
                                <span class="step3-step-label"><?= htmlspecialchars($step['title']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>

                    <div class="step3-progress-wrap">
                        <div class="step3-progress-track" role="progressbar" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Profile completion">
                            <div class="step3-progress-fill" style="width: <?= $progress ?>%"></div>
                        </div>
                        <div class="step3-progress-meta">
                            <span><?= sm_text('Step', 'ধাপ') ?> <?= (($GLOBALS['current_language'] ?? 'bn') === 'en' ? $currentStep : strtr((string)$currentStep, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯'])) ?> <?= sm_text('of', 'এর মধ্যে') ?> <?= count($steps) ?></span>
                            <strong><?= $progress ?><?= sm_text('% complete', '% সম্পন্ন') ?></strong>
                        </div>
                    </div>
                </header>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger step3-alert" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="step3-form" novalidate>

                    <!-- Existing lifestyle fields -->
                    <section class="step3-section">
                        <div class="section-heading">
                            <div class="section-icon"><i class="bi bi-stars"></i></div>
                            <div>
                                <h2><?= sm_text('Islamic Lifestyle', 'ইসলামি জীবনযাপন') ?></h2>
                                <p><?= sm_text('Your regular practices and Islamic lifestyle.', 'আপনার নিয়মিত আমল ও ইসলামি জীবনযাপনের তথ্য দিন।') ?></p>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label"><?= sm_text('Smoking Status', 'ধূমপানের অভ্যাস') ?> <span class="required">*</span></label>
                                <select name="smoking_status" class="form-select" required>
                                    <option value=""><?= sm_text('Select smoking status', 'ধূমপানের অবস্থা নির্বাচন করুন') ?></option>
                                    <?php foreach ($smoking_statuses as $status): ?>
                                        <option value="<?= htmlspecialchars($status) ?>" <?= (($user['smoking_status'] ?? '') === $status) ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($status)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label"><?= sm_text('Prayer Status', 'নামাজের অভ্যাস') ?> <span class="required">*</span></label>
                                <select name="prayer_status" class="form-select" required>
                                    <option value=""><?= sm_text('Select prayer status', 'নামাজের অবস্থা নির্বাচন করুন') ?></option>
                                    <?php foreach ($prayer_statuses as $status): ?>
                                        <option value="<?= htmlspecialchars($status) ?>" <?= (($user['prayer_status'] ?? '') === $status) ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($status)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label"><?= sm_text('Quran Reading', 'কোরআন তিলাওয়াত') ?></label>
                                <select name="quran_reading" class="form-select">
                                    <option value=""><?= sm_text('Select Quran reading', 'কোরআন তিলাওয়াতের অভ্যাস নির্বাচন করুন') ?></option>
                                    <?php foreach ($quran_reading_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['quran_reading'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($option)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label"><?= sm_text('Fasting', 'রোজা') ?></label>
                                <select name="fasting_status" class="form-select">
                                    <option value=""><?= sm_text('Select fasting', 'রোজা রাখার অভ্যাস নির্বাচন করুন') ?></option>
                                    <?php foreach ($fasting_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['fasting_status'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($option)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label"><?= sm_text('Religious Practice', 'ধর্মীয় অনুশীলন') ?></label>
                                <select name="religious_practice" class="form-select">
                                    <option value=""><?= sm_text('Select religious practice', 'ধর্মীয় অনুশীলনের মাত্রা নির্বাচন করুন') ?></option>
                                    <?php foreach ($religious_practice_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['religious_practice'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($option)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label"><?= sm_text('Islamic Knowledge', 'ইসলামি জ্ঞান') ?> <span class="required">*</span></label>
                                <select name="islamic_knowledge" class="form-select" required>
                                    <option value=""><?= sm_text('Select Islamic knowledge', 'ইসলামি জ্ঞানের স্তর নির্বাচন করুন') ?></option>
                                    <?php foreach ($islamic_knowledge_levels as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['islamic_knowledge'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($option)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label"><?= sm_text('Halal Lifestyle', 'হালাল জীবনযাপন') ?> <span class="required">*</span></label>
                                <select name="halal_lifestyle" class="form-select" required>
                                    <option value=""><?= sm_text('Select halal lifestyle', 'হালাল জীবনযাপনের ধরন নির্বাচন করুন') ?></option>
                                    <?php foreach ($halal_lifestyle_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['halal_lifestyle'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($option)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label"><?= sm_text('Mahram Maintained', 'মাহরাম মেনে চলেন কি না') ?></label>
                                <select name="mahram_maintained" class="form-select">
                                    <option value=""><?= sm_text('Select mahram practice', 'মাহরাম মেনে চলার অবস্থা নির্বাচন করুন') ?></option>
                                    <?php foreach ($mahram_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['mahram_maintained'] ?? null) === ($option === 'Yes' ? 1 : 0)) ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($option)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label"><?= sm_text('Islamic Activities', 'ইসলামি কার্যক্রম') ?></label>
                                <textarea name="islamic_activities" rows="3" maxlength="500" class="form-control" placeholder="<?= sm_text('Mention mosque visits, Islamic lectures, study circles or other activities.', 'মসজিদে যাওয়া, ইসলামি আলোচনা, পাঠচক্র বা অন্যান্য কার্যক্রমের কথা লিখুন।') ?>"><?= htmlspecialchars($user['islamic_activities'] ?? '') ?></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label"><?= sm_text('Favorite Islamic Scholars (Optional)', 'প্রিয় ইসলামি আলেম (ঐচ্ছিক)') ?></label>
                                <textarea name="favorite_scholars" rows="2" maxlength="1000" class="form-control" placeholder="<?= sm_text('Names of scholars you prefer to learn from.', 'যেসব আলেমের কাছ থেকে শিখতে পছন্দ করেন, তাঁদের নাম লিখুন।') ?>"><?= htmlspecialchars($user['favorite_scholars'] ?? '') ?></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label"><?= sm_text('Favorite Books (Optional)', 'প্রিয় বই (ঐচ্ছিক)') ?></label>
                                <textarea name="favorite_books" rows="2" maxlength="1000" class="form-control" placeholder="<?= sm_text('Names of Islamic books you like.', 'আপনার পছন্দের ইসলামি বইগুলোর নাম লিখুন।') ?>"><?= htmlspecialchars($user['favorite_books'] ?? '') ?></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label"><?= sm_text('Religious Learning Sources (Optional)', 'ধর্মীয় জ্ঞান অর্জনের উৎস (ঐচ্ছিক)') ?></label>
                                <textarea name="religious_knowledge_sources" rows="2" maxlength="1500" class="form-control" placeholder="<?= sm_text('Where you practice or learn about Islam, such as classes, mosque, books or online lessons.', 'ক্লাস, মসজিদ, বই বা অনলাইন পাঠের মতো যেসব মাধ্যমে ইসলাম সম্পর্কে শেখেন, তা লিখুন।') ?>"><?= htmlspecialchars($user['religious_knowledge_sources'] ?? '') ?></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label"><?= sm_text('Your View on Bid\'ah and Shrine Worship (Optional)', 'বিদআত ও মাজারকেন্দ্রিক উপাসনা সম্পর্কে আপনার মতামত (ঐচ্ছিক)') ?></label>
                                <textarea name="bidah_shrine_stance" rows="2" maxlength="500" class="form-control" placeholder="<?= sm_text('Briefly describe your views.', 'এ বিষয়ে আপনার মতামত সংক্ষেপে লিখুন।') ?>"><?= htmlspecialchars($user['bidah_shrine_stance'] ?? '') ?></textarea>
                            </div>


                            <?php if (($user['gender'] ?? '') === 'Male'): ?>
                                <div class="col-md-6">
                                    <label class="form-label"><?= sm_text('Beard Status', 'দাড়ির অবস্থা') ?></label>
                                    <select name="beard_status" class="form-select">
                                        <option value=""><?= sm_text('Select beard status', 'দাড়ির অবস্থা নির্বাচন করুন') ?></option>
                                        <?php foreach ($beard_statuses as $status): ?>
                                            <option value="<?= htmlspecialchars($status) ?>" <?= (($user['beard_status'] ?? '') === $status) ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($status)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <div class="col-md-6">
                                    <label class="form-label"><?= sm_text('Hijab Status', 'হিজাবের অবস্থা') ?></label>
                                    <select name="hijab_status" class="form-select">
                                        <option value=""><?= sm_text('Select hijab status', 'হিজাবের অবস্থা নির্বাচন করুন') ?></option>
                                        <?php foreach ($hijab_statuses as $status): ?>
                                            <option value="<?= htmlspecialchars($status) ?>" <?= (($user['hijab_status'] ?? '') === $status) ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($status)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label"><?= sm_text('Hijab Details', 'হিজাব সম্পর্কে বিস্তারিত') ?></label>
                                    <textarea name="hijab_details" rows="3" maxlength="500" class="form-control" placeholder="<?= sm_text('Add any helpful details about your hijab practice.', 'আপনার হিজাব পালনের বিষয়ে প্রয়োজনীয় তথ্য লিখুন।') ?>"><?= htmlspecialchars($user['hijab_details'] ?? '') ?></textarea>
                                </div>
                            <?php endif; ?>

                        </div>
                    </section>

                    <!-- Daily lifestyle -->
                    <section class="step3-section">
                        <div class="section-heading">
                            <div class="section-icon"><i class="bi bi-sunrise"></i></div>
                            <div>
                                <h2><?= sm_text('Daily Lifestyle', 'দৈনন্দিন জীবনযাপন') ?></h2>
                                <p><?= sm_text('Simple everyday preferences that help describe your lifestyle.', 'আপনার দৈনন্দিন জীবনযাপনের ধরন বোঝাতে এই পছন্দগুলো নির্বাচন করুন।') ?></p>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label"><?= sm_text('Diet Preference', 'খাদ্যাভ্যাস') ?></label>
                                <select name="diet_preference" class="form-select">
                                    <option value=""><?= sm_text('Select diet preference', 'খাদ্যাভ্যাস নির্বাচন করুন') ?></option>
                                    <?php foreach ($diet_preferences as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['diet_preference'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($option)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label"><?= sm_text('Sleep Pattern', 'ঘুমের অভ্যাস') ?> <span class="required">*</span></label>
                                <select name="sleep_pattern" class="form-select" required>
                                    <option value=""><?= sm_text('Select sleep pattern', 'ঘুমের ধরন নির্বাচন করুন') ?></option>
                                    <?php foreach ($sleep_patterns as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['sleep_pattern'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars(sm_step3_option_label($option)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label"><?= sm_text('Tea / Coffee Habit', 'চা-কফি পানের অভ্যাস') ?></label>
                                <select name="tea_coffee" class="form-select">
                                    <option value=""><?= sm_text('Select tea / coffee habit', 'চা বা কফি পানের অভ্যাস নির্বাচন করুন') ?></option>
                                    <?php foreach ($tea_coffee_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['tea_coffee'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($option)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </section>

                    <!-- Personality & social -->
                    <section class="step3-section">
                        <div class="section-heading">
                            <div class="section-icon"><i class="bi bi-people"></i></div>
                            <div>
                                <h2><?= sm_text('Personality & Social', 'ব্যক্তিত্ব ও সামাজিকতা') ?></h2>
                                <p><?= sm_text('Tell others a little more about how you naturally interact.', 'আপনি সাধারণত অন্যদের সঙ্গে কীভাবে মেলামেশা করেন, তা জানান।') ?></p>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label"><?= sm_text('Personality Type', 'ব্যক্তিত্বের ধরন') ?> <span class="required">*</span></label>
                                <select name="personality_type" class="form-select" required>
                                    <option value=""><?= sm_text('Select personality type', 'ব্যক্তিত্বের ধরন নির্বাচন করুন') ?></option>
                                    <?php foreach ($personality_types as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['personality_type'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($option)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label"><?= sm_text('Social Nature', 'সামাজিক স্বভাব') ?> <span class="required">*</span></label>
                                <select name="social_nature" class="form-select" required>
                                    <option value=""><?= sm_text('Select social nature', 'সামাজিক স্বভাব নির্বাচন করুন') ?></option>
                                    <?php foreach ($social_natures as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['social_nature'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars(sm_step3_option_label($option)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label"><?= sm_text('Free-time Interests', 'অবসর সময়ের আগ্রহ') ?></label>
                                <div class="interest-grid">
                                    <?php foreach ($free_time_interest_options as $interest): ?>
                                        <label class="interest-option">
                                            <input type="checkbox" name="free_time_interests[]" value="<?= htmlspecialchars($interest) ?>" <?= in_array($interest, $selected_interests, true) ? 'checked' : '' ?>>
                                            <span><?= htmlspecialchars(sm_step3_option_label($interest)) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="field-help"><?= sm_text('Choose any interests that describe how you enjoy your free time.', 'অবসর সময়ে যেসব কাজ করতে ভালোবাসেন, সেগুলো নির্বাচন করুন।') ?></div>
                            </div>
                        </div>
                    </section>

                    <!-- Preferences -->
                    <section class="step3-section">
                        <div class="section-heading">
                            <div class="section-icon"><i class="bi bi-compass"></i></div>
                            <div>
                                <h2><?= sm_text('Lifestyle Preferences', 'জীবনযাপনের পছন্দ') ?></h2>
                                <p><?= sm_text('Optional details that make your profile more informative.', 'ঐচ্ছিক এই তথ্যগুলো আপনার প্রোফাইলকে আরও পূর্ণাঙ্গ করবে।') ?></p>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-4">
                                <label class="form-label"><?= sm_text('Travel Interest', 'ভ্রমণের আগ্রহ') ?></label>
                                <select name="travel_interest" class="form-select">
                                    <option value=""><?= sm_text('Select travel interest', 'ভ্রমণের আগ্রহ নির্বাচন করুন') ?></option>
                                    <?php foreach ($travel_interest_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['travel_interest'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($option)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label"><?= sm_text('Spending Style', 'খরচের ধরন') ?></label>
                                <select name="spending_style" class="form-select">
                                    <option value=""><?= sm_text('Select spending style', 'খরচের ধরন নির্বাচন করুন') ?></option>
                                    <?php foreach ($spending_style_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['spending_style'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($option)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label"><?= sm_text('Pets', 'পোষা প্রাণী') ?></label>
                                <select name="pets" class="form-select">
                                    <option value=""><?= sm_text('Select pet preference', 'পোষা প্রাণী সম্পর্কে পছন্দ নির্বাচন করুন') ?></option>
                                    <?php foreach ($pet_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['pets'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($option)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </section>

                    <!-- Existing health information -->
                    <section class="step3-section">
                        <div class="section-heading">
                            <div class="section-icon"><i class="bi bi-heart-pulse"></i></div>
                            <div>
                                <h2><?= sm_text('Health Information', 'স্বাস্থ্যসংক্রান্ত তথ্য') ?></h2>
                                <p><?= sm_text('Basic health details for a more complete profile.', 'প্রোফাইল পূর্ণাঙ্গ করতে স্বাস্থ্যসংক্রান্ত প্রাথমিক তথ্য দিন।') ?></p>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label"><?= sm_text('Blood Group', 'রক্তের গ্রুপ') ?></label>
                                <select name="blood_group" class="form-select">
                                    <option value=""><?= sm_text('Select blood group', 'রক্তের গ্রুপ নির্বাচন করুন') ?></option>
                                    <?php foreach ($blood_groups as $blood): ?>
                                        <option value="<?= htmlspecialchars($blood) ?>" <?= (($health['blood_group'] ?? '') === $blood) ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($blood)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label"><?= sm_text('Physical Disability', 'শারীরিক প্রতিবন্ধকতা') ?></label>
                                <select name="physical_disability" class="form-select">
                                    <option value=""><?= sm_text('Select physical disability', 'শারীরিক প্রতিবন্ধকতার অবস্থা নির্বাচন করুন') ?></option>
                                    <?php foreach ($physical_disabilities as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($health['disability_status'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($option)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label"><?= sm_text('Medical Notes', 'স্বাস্থ্যসংক্রান্ত তথ্য') ?> <span class="optional"><?= sm_text('(Optional)', '(ঐচ্ছিক)') ?></span></label>
                                <textarea name="health_notes" rows="3" maxlength="500" class="form-control" placeholder="<?= sm_text('Mention chronic diseases, allergies or important medical information.', 'দীর্ঘমেয়াদি রোগ, অ্যালার্জি বা গুরুত্বপূর্ণ স্বাস্থ্যতথ্য লিখুন।') ?>"><?= htmlspecialchars($health['medical_notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </section>

                    <div class="step3-actions">
                        <a href="view_profile.php?user_id=<?= (int)$user_id ?>" class="btn btn-outline-secondary step3-back">
                            <i class="bi bi-arrow-left-circle"></i> <?= sm_text('Back to Profile', 'প্রোফাইলে ফিরে যান') ?>
                        </a>
                        <button type="submit" name="save_step3" class="btn btn-success step3-save">
                            <?= sm_text('Save & Continue', 'সংরক্ষণ করে এগিয়ে যান') ?> <i class="bi bi-arrow-right-circle"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/profile-step3.js" defer></script>
<?php include '../includes/footer.php'; ?>
