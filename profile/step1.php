<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';
require_once '../includes/dropdowns.php';
require_once '../includes/profile_completion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$error = '';
$success = '';

$stmt = mysqli_prepare($conn, "
    SELECT
        u.first_name,
        u.last_name,
        u.gender,
        u.email,
        u.mobile,
        up.date_of_birth,
        up.nid_number,
        up.religion,
        up.madhhab,
        up.marital_status,
        up.highest_education,
        up.profession,
        up.occupation_details,
        up.monthly_income,
        up.bio,
        up.height_cm,
        up.weight_kg,
        up.complexion,
        up.biodata_created_by,
        up.guardian_consent
    FROM users u
    LEFT JOIN user_profiles up ON up.user_id = u.user_id
    WHERE u.user_id = ?
    LIMIT 1
");

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($stmt);

if (!$user) {
    header("Location: ../dashboard.php");
    exit;
}

$old = [
    'date_of_birth' => $user['date_of_birth'] ?? '',
    'nid_number' => $user['nid_number'] ?? '',
    'religion' => $user['religion'] ?? '',
    'madhhab' => $user['madhhab'] ?? '',
    'marital_status' => $user['marital_status'] ?? '',
    'education' => $user['highest_education'] ?? '',
    'profession' => $user['profession'] ?? '',
    'occupation_details' => $user['occupation_details'] ?? '',
    'monthly_income' => $user['monthly_income'] ?? '',
    'height_cm' => $user['height_cm'] ?? '',
    'weight_kg' => $user['weight_kg'] ?? '',
    'complexion' => $user['complexion'] ?? '',
    'bio' => $user['bio'] ?? '',
    'biodata_created_by' => $user['biodata_created_by'] ?? '',
    'guardian_consent' => $user['guardian_consent'] ?? null
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_step1'])) {
    $date_of_birth_input = trim($_POST['date_of_birth'] ?? '');
    $date_of_birth = '';
    $nid_number = trim($_POST['nid_number'] ?? '');

    // Accept the user-facing dd/mm/yyyy format and convert it to MySQL Y-m-d.
    $dob_parts = preg_split('/\//', $date_of_birth_input);
    if (count($dob_parts) === 3 && checkdate((int)$dob_parts[1], (int)$dob_parts[0], (int)$dob_parts[2])) {
        $date_of_birth = sprintf('%04d-%02d-%02d', (int)$dob_parts[2], (int)$dob_parts[1], (int)$dob_parts[0]);
    }
    $religion = trim($_POST['religion'] ?? '');
    $madhhab = trim($_POST['madhhab'] ?? '');
    $marital_status = trim($_POST['marital_status'] ?? '');
    $education = trim($_POST['education'] ?? '');
    $profession = trim($_POST['profession'] ?? '');
    $occupation_details = trim($_POST['occupation_details'] ?? '');
    $monthly_income_input = trim($_POST['monthly_income'] ?? '');
    $monthly_income = ($monthly_income_input === '') ? null : (float)$monthly_income_input;
    $height_cm = (int) ($_POST['height_cm'] ?? 0);
    $weight_kg = (float) ($_POST['weight_kg'] ?? 0);
    $complexion = trim($_POST['complexion'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $biodata_created_by = trim($_POST['biodata_created_by'] ?? '');
    $allowed_biodata_creators = ['Self', 'Father', 'Mother', 'Brother', 'Sister', 'Uncle', 'Aunt'];
    $guardian_consent_input = trim($_POST['guardian_consent'] ?? '');
    $guardian_consent = ($biodata_created_by === 'Self' && in_array($guardian_consent_input, ['Yes', 'No'], true))
        ? ($guardian_consent_input === 'Yes' ? 1 : 0)
        : null;

    $old = [
        'date_of_birth' => $date_of_birth,
        'nid_number' => $nid_number,
        'religion' => $religion,
        'madhhab' => $madhhab,
        'marital_status' => $marital_status,
        'education' => $education,
        'profession' => $profession,
        'occupation_details' => $occupation_details,
        'monthly_income' => $monthly_income_input,
        'height_cm' => $height_cm,
        'weight_kg' => $weight_kg,
        'complexion' => $complexion,
        'bio' => $bio,
        'biodata_created_by' => $biodata_created_by,
        'guardian_consent' => $guardian_consent
    ];

    $allowed_religions = $religions;
    $allowed_madhhabs = $madhhabs;
    $allowed_marital = $marital_statuses;
    $allowed_education = $education_levels;
    $allowed_professions = $user_professions;
    $allowed_complexions = $complexions;

    $today = new DateTimeImmutable('today');
    $dob = DateTimeImmutable::createFromFormat('Y-m-d', $date_of_birth);
    $dob_errors = DateTimeImmutable::getLastErrors();
    $valid_dob = $dob && ($dob_errors === false || ($dob_errors['warning_count'] === 0 && $dob_errors['error_count'] === 0)) && $dob->format('Y-m-d') === $date_of_birth;

    if ($date_of_birth === '' || !$valid_dob) {
        $error = 'Please select a valid date of birth.';
    } elseif ($dob > $today) {
        $error = 'Date of birth cannot be in the future.';
    } elseif (mb_strlen($nid_number) > 30) {
        $error = 'NID or Birth Certificate Number must be 30 characters or fewer.';
    } elseif (!in_array($religion, $allowed_religions, true)) {
        $error = 'Please select a valid religion.';
    } elseif (!in_array($madhhab, $allowed_madhhabs, true)) {
        $error = 'Please select a valid madhhab.';
    } elseif (!in_array($marital_status, $allowed_marital, true)) {
        $error = 'Please select a valid marital status.';
    } elseif (!in_array($education, $allowed_education, true)) {
        $error = 'Please select a valid education level.';
    } elseif (!in_array($profession, $allowed_professions, true)) {
        $error = 'Please select a valid profession.';
    } elseif (mb_strlen($occupation_details) > 150) {
        $error = 'Occupation details must be 150 characters or fewer.';
    } elseif ($monthly_income !== null && ($monthly_income < 0 || $monthly_income > 99999999.99)) {
        $error = 'Please enter a valid monthly income.';
    } elseif ($monthly_income === null && !in_array($profession, ['Student', 'Unemployed', 'Retired'], true)) {
        $error = 'Monthly income is required for this profession.';
    } elseif ($height_cm < 120 || $height_cm > 230) {
        $error = 'Please select a valid height in feet and inches.';
    } elseif ($weight_kg < 30 || $weight_kg > 200) {
        $error = 'Please enter a valid weight between 30 and 200 kg.';
    } elseif (!in_array($complexion, $allowed_complexions, true)) {
        $error = 'Please select a valid complexion.';
    } elseif (mb_strlen($bio) > 500) {
        $error = 'Short bio must be 500 characters or fewer.';
    } elseif (!in_array($biodata_created_by, $allowed_biodata_creators, true)) {
        $error = 'Please select who created this biodata.';
    } elseif ($biodata_created_by === 'Self' && !in_array($guardian_consent_input, ['Yes', 'No'], true)) {
        $error = 'Please indicate whether your guardian consents to or is aware of this biodata.';
    }

    if ($error === '') {
        $check = mysqli_prepare($conn, "SELECT profile_id FROM user_profiles WHERE user_id = ? LIMIT 1");
        mysqli_stmt_bind_param($check, "i", $user_id);
        mysqli_stmt_execute($check);
        $check_result = mysqli_stmt_get_result($check);
        $profile_exists = $check_result && mysqli_num_rows($check_result) > 0;
        mysqli_stmt_close($check);

        if ($profile_exists) {
            $save = mysqli_prepare($conn, "
                UPDATE user_profiles
                SET date_of_birth = ?,
                    nid_number = ?,
                    religion = ?,
                    madhhab = ?,
                    marital_status = ?,
                    highest_education = ?,
                    profession = ?,
                    occupation_details = ?,
                    monthly_income = ?,
                    bio = ?,
                    height_cm = ?,
                    weight_kg = ?,
                    complexion = ?,
                    biodata_created_by = ?,
                    guardian_consent = ?
                WHERE user_id = ?
            ");

            mysqli_stmt_bind_param(
                $save,
                "ssssssssdsidssii",
                $date_of_birth,
                $nid_number,
                $religion,
                $madhhab,
                $marital_status,
                $education,
                $profession,
                $occupation_details,
                $monthly_income,
                $bio,
                $height_cm,
                $weight_kg,
                $complexion,
                $biodata_created_by,
                $guardian_consent,
                $user_id
            );
        } else {
            $save = mysqli_prepare($conn, "
                INSERT INTO user_profiles
                (
                    user_id,
                    first_name,
                    last_name,
                    gender,
                    date_of_birth,
                    nid_number,
                    religion,
                    madhhab,
                    marital_status,
                    highest_education,
                    profession,
                    occupation_details,
                    monthly_income,
                    bio,
                    height_cm,
                    weight_kg,
                    complexion,
                    biodata_created_by,
                    guardian_consent
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            mysqli_stmt_bind_param(
                $save,
                "isssssssssssdsidssi",
                $user_id,
                $user['first_name'],
                $user['last_name'],
                $user['gender'],
                $date_of_birth,
                $nid_number,
                $religion,
                $madhhab,
                $marital_status,
                $education,
                $profession,
                $occupation_details,
                $monthly_income,
                $bio,
                $height_cm,
                $weight_kg,
                $complexion,
                $biodata_created_by,
                $guardian_consent
            );
        }

        if ($save && mysqli_stmt_execute($save)) {
            mysqli_stmt_close($save);
            header("Location: step2.php");
            exit;
        }

        if ($save) {
            mysqli_stmt_close($save);
        }
        $error = 'Unable to save your information right now. Please try again.';
    }
}

$page_css = 'assets/css/profile-step1.css';
include '../includes/header.php';
include '../includes/navbar.php';

$steps = [
    1 => ['title' => sm_text('Personal', 'ব্যক্তিগত তথ্য'), 'icon' => 'bi-person-circle', 'url' => 'step1.php'],
    2 => ['title' => sm_text('Family', 'পারিবারিক তথ্য'), 'icon' => 'bi-people', 'url' => 'step2.php'],
    3 => ['title' => sm_text('Lifestyle', 'জীবনযাপন'), 'icon' => 'bi-heart', 'url' => 'step3.php'],
    4 => ['title' => sm_text('Location', 'ঠিকানা'), 'icon' => 'bi-geo-alt', 'url' => 'step4.php'],
    5 => ['title' => sm_text('Privacy', 'গোপনীয়তা'), 'icon' => 'bi-shield-lock', 'url' => 'step5.php'],
    6 => ['title' => sm_text('PP & QnA', 'জীবনসঙ্গীর পছন্দ ও প্রশ্নোত্তর'), 'icon' => 'bi-patch-question', 'url' => 'step6.php']
];
$completion = sm_get_profile_completion($conn, $user_id);
$progress = $completion['percentage'];
?>
<style>
    /* Keep guardian Yes/No controls compact and isolated from global form styles. */
    #guardian-consent-field .step1-guardian-choice {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
        margin-top: 8px;
    }
    #guardian-consent-field .step1-guardian-option {
        display: inline-flex;
        align-items: center;
        justify-content: flex-start;
        gap: 7px;
        width: auto;
        min-width: 0;
        margin: 0;
        padding: 7px 12px;
        border: 1px solid #d8e4e8;
        border-radius: 8px;
        cursor: pointer;
        line-height: 1.2;
    }
    #guardian-consent-field .step1-guardian-option input[type="radio"] {
        appearance: auto;
        -webkit-appearance: radio;
        display: inline-block;
        flex: 0 0 16px;
        width: 16px !important;
        min-width: 16px;
        max-width: 16px;
        height: 16px !important;
        min-height: 16px;
        margin: 0;
        padding: 0;
        box-shadow: none;
        accent-color: #1677ff;
        cursor: pointer;
    }
    #guardian-consent-field .step1-guardian-option span {
        display: inline;
        margin: 0;
        font-size: 14px;
        font-weight: 600;
    }
</style>

<main class="step1-page">
    <div class="step1-shell">

        <header class="step1-wizard-head">
            <div class="step1-heading-row">
                <div>
                    <span class="step1-kicker"><?= sm_text('PROFILE BUILDER', 'প্রোফাইল তৈরি') ?></span>
                    <h1><?= sm_text('Step 1', 'ধাপ ১') ?></h1>
                    <p><?= sm_text('Personal Information', 'ব্যক্তিগত তথ্য') ?></p>
                </div>
                <a class="step1-logo" href="../dashboard.php" aria-label="Back to dashboard">
                    <img src="<?= BASE_URL ?>assets/images/logo/logo.png" alt="Smart Matrimony">
                </a>
            </div>

            <nav class="step1-step-nav" aria-label="Profile steps">
                <?php foreach ($steps as $stepNo => $step): ?>
                    <a class="step1-step <?= $stepNo === 1 ? 'is-current' : '' ?> <?= !empty($completion['steps'][$stepNo]) ? 'is-completed' : '' ?>"
                       href="<?= htmlspecialchars($step['url']) ?>"
                       aria-current="<?= $stepNo === 1 ? 'step' : 'false' ?>">
                        <span class="step1-step-icon"><i class="bi <?= $step['icon'] ?>"></i></span>
                        <span class="step1-step-label"><?= htmlspecialchars($step['title']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="step1-progress-wrap">
                <div class="step1-progress-track" role="progressbar" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="step1-progress-fill" style="width: <?= $progress ?>%"></div>
                </div>
                <div class="step1-progress-meta">
                    <span><?= sm_text('Step 1 of 6', 'ধাপ ১ / ৬') ?></span>
                    <strong><?= $progress ?><?= sm_text('% complete', '% সম্পন্ন') ?></strong>
                </div>
            </div>
        </header>

        <?php if ($error !== ''): ?>
            <div class="step1-alert step1-alert-error" role="alert">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <section class="step1-basic-card">
            <div class="step1-section-heading">
                <span class="step1-section-icon"><i class="bi bi-person-vcard-fill"></i></span>
                <div>
                    <span class="step1-section-kicker"><?= sm_text('ACCOUNT DETAILS', 'অ্যাকাউন্টের তথ্য') ?></span>
                    <h2><?= sm_text('Basic Information', 'মৌলিক তথ্য') ?></h2>
                </div>
            </div>

            <div class="step1-basic-grid">
                <div><span><?= sm_text('Full Name', 'পূর্ণ নাম') ?></span><strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong></div>
                <div><span><?= sm_text('Gender', 'লিঙ্গ') ?></span><strong><?= htmlspecialchars($user['gender']) ?></strong></div>
                <div><span><?= sm_text('Email', 'ইমেইল') ?></span><strong><?= htmlspecialchars($user['email']) ?></strong></div>
                <div><span><?= sm_text('Mobile', 'মোবাইল') ?></span><strong><?= htmlspecialchars($user['mobile']) ?></strong></div>
            </div>

            <div class="step1-locked-note">
                <i class="bi bi-lock-fill"></i>
                <span><?= sm_text('Name, Gender, Email and Mobile are fixed from your registration and cannot be changed here.', 'নাম, লিঙ্গ, ইমেইল ও মোবাইল নম্বর নিবন্ধনের সময় নির্ধারিত হয়েছে; এখান থেকে এগুলো পরিবর্তন করা যাবে না।') ?></span>
            </div>
        </section>

        <form method="POST" class="step1-form" novalidate>
            <section class="step1-form-card">
                <div class="step1-section-heading">
                    <span class="step1-section-icon"><i class="bi bi-pencil-square"></i></span>
                    <div>
                        <span class="step1-section-kicker"><?= sm_text('YOUR INFORMATION', 'আপনার তথ্য') ?></span>
                        <h2><?= sm_text('Personal Details', 'ব্যক্তিগত বিবরণ') ?></h2>
                    </div>
                </div>

                <div class="step1-field-grid step1-personal-grid">
                    <div class="step1-field">
                        <label for="date_of_birth"><?= sm_text('Date of Birth', 'জন্মতারিখ') ?> <em>*</em></label>
                        <div class="step1-date-control">
                            <input type="text" id="date_of_birth" name="date_of_birth" value="<?php
                                $dob_display = '';
                                if (!empty($old['date_of_birth'])) {
                                    $stored_dob = DateTimeImmutable::createFromFormat('Y-m-d', $old['date_of_birth']);
                                    if ($stored_dob) {
                                        $dob_display = $stored_dob->format('d/m/Y');
                                    }
                                }
                                echo htmlspecialchars($dob_display);
                            ?>" placeholder="<?= sm_text('dd/mm/yyyy', 'দিন/মাস/বছর') ?>" inputmode="numeric" autocomplete="bday" maxlength="10" required>
                            <button type="button" class="step1-calendar-btn" id="open_dob_calendar" aria-label="Open date of birth calendar"><i class="bi bi-calendar3"></i></button>
                            <input type="date" id="dob_calendar" class="step1-hidden-date" max="<?= date('Y-m-d') ?>" tabindex="-1" aria-hidden="true">
                        </div>
                        <small><?= sm_text('Use dd/mm/yyyy or choose your date from the calendar.', 'দিন/মাস/বছর লিখুন অথবা ক্যালেন্ডার থেকে তারিখ বেছে নিন।') ?></small>
                    </div>

                    <div class="step1-field">
                        <label for="age"><?= sm_text('Age', 'বয়স') ?></label>
                        <input type="text" id="age" value="" placeholder="Auto calculated" readonly aria-readonly="true">
                        <small><?= sm_text('Calculated automatically from your date of birth.', 'জন্মতারিখ থেকে বয়স স্বয়ংক্রিয়ভাবে হিসাব করা হবে।') ?></small>
                    </div>

                    <div class="step1-field step1-nid-field">
                        <label for="nid_number"><?= sm_text('NID or Birth Certificate Number', 'জাতীয় পরিচয়পত্র বা জন্মনিবন্ধন নম্বর') ?></label>
                        <input type="text" id="nid_number" name="nid_number" value="<?= htmlspecialchars((string) $old['nid_number']) ?>" maxlength="30" placeholder="<?= sm_text('Optional', 'ঐচ্ছিক') ?>">
                        
                    </div>

                    <div class="step1-field">
                        <label for="religion"><?= sm_text('Religion', 'ধর্ম') ?> <em>*</em></label>
                        <select id="religion" name="religion" required>
                            <option value="" disabled <?= $old['religion'] === '' ? 'selected' : '' ?>><?= sm_text('Select religion', 'ধর্ম নির্বাচন করুন') ?></option>
                            <?php foreach ($religions as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $old['religion'] === $item ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($item)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="step1-field">
                        <label for="madhhab"><?= sm_text('Madhhab', 'মাযহাব') ?> <em>*</em></label>
                        <select id="madhhab" name="madhhab" required>
                            <option value="" disabled <?= $old['madhhab'] === '' ? 'selected' : '' ?>><?= sm_text('Select madhhab', 'মাযহাব নির্বাচন করুন') ?></option>
                            <?php foreach ($madhhabs as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $old['madhhab'] === $item ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($item)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="step1-field">
                        <label for="marital_status"><?= sm_text('Marital Status', 'বৈবাহিক অবস্থা') ?> <em>*</em></label>
                        <select id="marital_status" name="marital_status" required>
                            <option value="" disabled <?= $old['marital_status'] === '' ? 'selected' : '' ?>><?= sm_text('Select marital status', 'বৈবাহিক অবস্থা নির্বাচন করুন') ?></option>
                            <?php foreach ($marital_statuses as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $old['marital_status'] === $item ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($item)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="step1-field step1-height-field">
                        <label><?= sm_text('Height', 'উচ্চতা') ?> <em>*</em></label>
                        <div class="step1-height-grid">
                            <select id="feet" aria-label="Select height in feet" required>
                                <option value="" disabled selected><?= sm_text('foot', 'ফুট') ?></option>
                                <?php for ($i = 4; $i <= 7; $i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?> <?= sm_text('foot', 'ফুট') ?></option>
                                <?php endfor; ?>
                            </select>
                            <select id="inch" aria-label="Select height in inches" required>
                                <option value="" disabled selected><?= sm_text('inch', 'ইঞ্চি') ?></option>
                                <?php for ($i = 0; $i <= 11; $i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?> <?= sm_text('inch', 'ইঞ্চি') ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <input type="hidden" id="height_cm" name="height_cm" value="<?= htmlspecialchars((string) $old['height_cm']) ?>">
                    </div>

                    <div class="step1-field">
                        <label for="weight_kg"><?= sm_text('Weight (kg)', 'ওজন (কেজি)') ?> <em>*</em></label>
                        <input type="number" id="weight_kg" name="weight_kg" value="<?= htmlspecialchars((string) $old['weight_kg']) ?>" placeholder="<?= sm_text('Enter weight', 'ওজন লিখুন') ?>" min="30" max="200" step="1" required>
                        
                    </div>

                    <div class="step1-field">
                        <label for="complexion"><?= sm_text('Skin Colour', 'গায়ের রং') ?> <em>*</em></label>
                        <select id="complexion" name="complexion" required>
                            <option value="" disabled <?= $old['complexion'] === '' ? 'selected' : '' ?>><?= sm_text('Select Skin Colour', 'গায়ের রং নির্বাচন করুন') ?></option>
                            <?php foreach ($complexions as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $old['complexion'] === $item ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($item)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="step1-field step1-bio-field">
                        <label for="bio"><?= sm_text('Short Bio', 'সংক্ষিপ্ত পরিচিতি') ?></label>
                        <textarea id="bio" name="bio" maxlength="500" rows="5" placeholder="<?= sm_text('Write something about your personal life', 'নিজের সম্পর্কে সংক্ষেপে লিখুন') ?>" ><?= htmlspecialchars($old['bio']) ?></textarea>
                        <small><?= sm_text('Maximum 500 characters.', 'সর্বোচ্চ ৫০০ অক্ষর লিখতে পারবেন।') ?></small>
                    </div>

                    <div class="step1-field">
                        <label for="biodata_created_by"><?= sm_text('Who is creating this biodata?', 'এই বায়োডাটা কে তৈরি করছেন?') ?> <em>*</em></label>
                        <select id="biodata_created_by" name="biodata_created_by" required>
                            <option value="" disabled <?= $old['biodata_created_by'] === '' ? 'selected' : '' ?>><?= sm_text('Select who is creating', 'কে বায়োডাটা তৈরি করছেন নির্বাচন করুন') ?></option>
                            <?php foreach (['Self', 'Father', 'Mother', 'Brother', 'Sister', 'Uncle', 'Aunt'] as $creator): ?>
                                <option value="<?= htmlspecialchars($creator) ?>" <?= $old['biodata_created_by'] === $creator ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($creator)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="step1-field" id="guardian-consent-field" <?= $old['biodata_created_by'] !== 'Self' ? 'hidden' : '' ?>>
                        <label><?= sm_text('Does your guardian consent to creating this biodata?', 'বায়োডাটা তৈরিতে আপনার অভিভাবকের সম্মতি আছে কি?') ?> <em>*</em></label>
                        <div class="step1-guardian-choice" role="radiogroup" aria-label="Guardian consent" >
                            <label class="step1-guardian-option" for="guardian_consent_yes">
                                <input type="radio" id="guardian_consent_yes" name="guardian_consent" value="Yes" <?= (string)$old['guardian_consent'] === '1' ? 'checked' : '' ?> <?= $old['biodata_created_by'] === 'Self' ? 'required' : '' ?>>
                                <span><?= sm_text('Yes', 'হ্যাঁ') ?></span>
                            </label>
                            <label class="step1-guardian-option" for="guardian_consent_no">
                                <input type="radio" id="guardian_consent_no" name="guardian_consent" value="No" <?= (string)$old['guardian_consent'] === '0' && $old['guardian_consent'] !== null ? 'checked' : '' ?>>
                                <span><?= sm_text('No', 'না') ?></span>
                            </label>
                        </div>
                    </div>
                </div>
            </section>

            <section class="step1-form-card step1-career-card">
                <div class="step1-section-heading">
                    <span class="step1-section-icon"><i class="bi bi-briefcase-fill"></i></span>
                    <div>
                        <span class="step1-section-kicker"><?= sm_text('CAREER &amp; FINANCIAL INFORMATION', 'পেশা ও আর্থিক তথ্য') ?></span>
                        <h2><?= sm_text('Career &amp; Financial Information', 'পেশা ও আর্থিক তথ্য') ?></h2>
                    </div>
                </div>

                <div class="step1-field-grid">
                    <div class="step1-field">
                        <label for="education"><?= sm_text('Education', 'শিক্ষাগত যোগ্যতা') ?> <em>*</em></label>
                        <select id="education" name="education" required>
                            <option value="" disabled <?= $old['education'] === '' ? 'selected' : '' ?>><?= sm_text('Select education', 'শিক্ষাগত যোগ্যতা নির্বাচন করুন') ?></option>
                            <?php foreach ($education_levels as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $old['education'] === $item ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($item)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="step1-field">
                        <label for="profession"><?= sm_text('Profession', 'পেশা') ?> <em>*</em></label>
                        <select id="profession" name="profession" required>
                            <option value="" disabled <?= $old['profession'] === '' ? 'selected' : '' ?>><?= sm_text('Select profession', 'পেশা নির্বাচন করুন') ?></option>
                            <?php foreach ($user_professions as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $old['profession'] === $item ? 'selected' : '' ?>><?= htmlspecialchars(sm_dropdown_label($item)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                    </div>

                    <div class="step1-field">
                        <label for="occupation_details"><?= sm_text('Occupation Details', 'পেশার বিবরণ') ?></label>
                        <input type="text" id="occupation_details" name="occupation_details" value="<?= htmlspecialchars((string) $old['occupation_details']) ?>" maxlength="150" placeholder="<?= sm_text('e.g. Software Engineer at a private company', 'যেমন: একটি বেসরকারি প্রতিষ্ঠানে সফটওয়্যার ইঞ্জিনিয়ার') ?>">
                        <small><?= sm_text('Add a little more detail about your work.', 'আপনার কাজ সম্পর্কে আরও কিছু তথ্য দিন।') ?></small>
                    </div>

                    <div class="step1-field">
                        <label for="monthly_income"><?= sm_text('Monthly Income (BDT)', 'মাসিক আয় (টাকা)') ?> <span id="income-required-mark"><em>*</em></span></label>
                        <div class="step1-income-control">
                            <span class="step1-income-prefix">৳</span>
                            <input type="number" id="monthly_income" name="monthly_income" value="<?= htmlspecialchars((string) $old['monthly_income']) ?>" min="0" max="99999999.99" step="100" placeholder="<?= sm_text('Optional', 'ঐচ্ছিক') ?>">
                        </div>
                        <small id="income-help"><?= sm_text('Required for most income-earning professions · Optional for Student, Unemployed and Retired.', 'অধিকাংশ পেশার ক্ষেত্রে মাসিক আয় দিতে হবে। শিক্ষার্থী, বেকার ও অবসরপ্রাপ্তদের জন্য ঐচ্ছিক।') ?></small>
                    </div>
                </div>
            </section>

            <div class="step1-actions">
                <a href="view_profile.php?user_id=<?= (int)$user_id ?>" class="step1-btn step1-btn-secondary">
                    <i class="bi bi-arrow-left"></i>
                    <span><?= sm_text('Back to Profile', 'প্রোফাইলে ফিরে যান') ?></span>
                </a>
                <button type="submit" name="save_step1" class="step1-btn step1-btn-primary">
                    <span><?= sm_text('Save &amp; Continue', 'সংরক্ষণ করে এগিয়ে যান') ?></span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            </div>
        </form>
    </div>
</main>

<script>
(function () {
    const dobInput = document.getElementById('date_of_birth');
    const ageInput = document.getElementById('age');
    const feetInput = document.getElementById('feet');
    const inchInput = document.getElementById('inch');
    const heightCmInput = document.getElementById('height_cm');

    function parseDisplayDob(value) {
        const match = value.trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (!match) return null;

        const day = Number(match[1]);
        const month = Number(match[2]);
        const year = Number(match[3]);
        const dob = new Date(year, month - 1, day);

        if (dob.getFullYear() !== year || dob.getMonth() !== month - 1 || dob.getDate() !== day) {
            return null;
        }
        return dob;
    }

    function formatDob(date) {
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        return `${day}/${month}/${year}`;
    }

    function calculateAge() {
        const dob = parseDisplayDob(dobInput.value);
        if (!dob) {
            ageInput.value = '';
            return;
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        dob.setHours(0, 0, 0, 0);

        if (dob > today) {
            ageInput.value = '';
            return;
        }

        let age = today.getFullYear() - dob.getFullYear();
        const monthDiff = today.getMonth() - dob.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
            age--;
        }

        ageInput.value = age >= 0 ? age + ' years' : '';
    }

    function updateHeight() {
        const feet = parseInt(feetInput.value, 10);
        const inch = parseInt(inchInput.value, 10);

        if (Number.isNaN(feet) || Number.isNaN(inch)) {
            heightCmInput.value = '';
            return;
        }

        const cm = Math.round(((feet * 12) + inch) * 2.54);
        heightCmInput.value = cm;
    }

    function restoreHeight() {
        const cm = parseInt(heightCmInput.value, 10);
        if (Number.isNaN(cm) || cm <= 0) return;

        const totalInches = Math.round(cm / 2.54);
        const feet = Math.floor(totalInches / 12);
        const inch = totalInches % 12;

        if (feet >= 4 && feet <= 7) feetInput.value = feet;
        if (inch >= 0 && inch <= 11) inchInput.value = inch;
    }

    dobInput.addEventListener('input', calculateAge);
    dobInput.addEventListener('blur', function () {
        const dob = parseDisplayDob(dobInput.value);
        if (dob) dobInput.value = formatDob(dob);
    });

    document.getElementById('open_dob_calendar').addEventListener('click', function () {
        const current = parseDisplayDob(dobInput.value);
        if (current) {
            const year = current.getFullYear();
            const month = String(current.getMonth() + 1).padStart(2, '0');
            const day = String(current.getDate()).padStart(2, '0');
            document.getElementById('dob_calendar').value = `${year}-${month}-${day}`;
        }

        const calendar = document.getElementById('dob_calendar');
        if (typeof calendar.showPicker === 'function') {
            calendar.showPicker();
        } else {
            calendar.focus();
            calendar.click();
        }
    });

    document.getElementById('dob_calendar').addEventListener('change', function () {
        if (!this.value) return;
        const [year, month, day] = this.value.split('-').map(Number);
        const selected = new Date(year, month - 1, day);
        dobInput.value = formatDob(selected);
        calculateAge();
    });

    document.querySelector('.step1-form').addEventListener('submit', function (event) {
        const dob = parseDisplayDob(dobInput.value);
        if (!dob) {
            event.preventDefault();
            dobInput.setCustomValidity('Please enter a valid date in dd/mm/yyyy format.');
            dobInput.reportValidity();
            return;
        }

        dobInput.setCustomValidity('');
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (dob > today) {
            event.preventDefault();
            dobInput.setCustomValidity('Date of birth cannot be in the future.');
            dobInput.reportValidity();
        }
    });
    feetInput.addEventListener('change', updateHeight);
    inchInput.addEventListener('change', updateHeight);

    function updateIncomeRequirement() {
        const profession = document.getElementById('profession');
        const incomeInput = document.getElementById('monthly_income');
        const mark = document.getElementById('income-required-mark');
        const help = document.getElementById('income-help');
        if (!profession || !incomeInput) return;

        const optionalProfessions = ['Student', 'Unemployed', 'Retired'];
        const isOptional = optionalProfessions.includes(profession.value);

        incomeInput.required = !isOptional;
        mark.style.visibility = isOptional ? 'hidden' : 'visible';
        incomeInput.placeholder = isOptional ? 'Optional' : 'Enter monthly income';
        help.textContent = isOptional
            ? 'Optional for this profession · Enter it if you receive a regular income.'
            : 'Required for this profession · Enter your average monthly income in BDT.';
    }

    document.getElementById('profession').addEventListener('change', updateIncomeRequirement);
    updateIncomeRequirement();

    const biodataCreator = document.getElementById('biodata_created_by');
    const guardianConsentField = document.getElementById('guardian-consent-field');
    const guardianConsentRadios = guardianConsentField.querySelectorAll('input[name="guardian_consent"]');
    function updateGuardianConsentVisibility() {
        const isSelf = biodataCreator.value === 'Self';
        guardianConsentField.hidden = !isSelf;
        guardianConsentRadios.forEach((radio) => {
            radio.required = isSelf && radio.value === 'Yes';
            if (!isSelf) radio.checked = false;
        });
    }
    biodataCreator.addEventListener('change', updateGuardianConsentVisibility);
    updateGuardianConsentVisibility();

    calculateAge();
    restoreHeight();
})();
</script>

<?php include '../includes/footer.php'; ?>
