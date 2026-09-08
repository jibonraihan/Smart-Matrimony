<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
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

/* Load profile */
$profileStmt = mysqli_prepare($conn, 'SELECT * FROM user_profiles WHERE user_id=? LIMIT 1');
mysqli_stmt_bind_param($profileStmt, 'i', $user_id);
mysqli_stmt_execute($profileStmt);
$profileResult = mysqli_stmt_get_result($profileStmt);
$user = mysqli_fetch_assoc($profileResult);

if (!$user) {
    header('Location: step1.php');
    exit();
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
    } elseif (strcasecmp((string)($user['gender'] ?? ''), 'Female') === 0) {
        $beard_status = 'Not Applicable';
    } else {
        $beard_status = 'Not Applicable';
        $hijab_status = 'Not Applicable';
    }

    /* Hijab is stored as VARCHAR, so it must not be tied to the current
     * dropdown list for server-side validation. This keeps existing saved
     * values valid even if an option is renamed/removed, while still
     * validating the submitted value as safe text. The dropdown itself is
     * generated from includes/dropdowns.php, so its current options remain
     * the source of the UI choices.
     */
    $invalid_hijab = (strlen($hijab_status) > 100);

    if ($mahram_input === 'Yes') {
        $mahram_maintained = 1;
    } elseif ($mahram_input === 'No') {
        $mahram_maintained = 0;
    } else {
        $mahram_maintained = null;
    }

    if ($invalid_dropdown || $invalid_interest || $invalid_mahram || $invalid_hijab) {
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
                'sssssisssssssssssssssi',
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

            /* Targeted safeguard: persist the selected female hijab status explicitly. */
            if (strcasecmp((string)($user['gender'] ?? ''), 'Female') === 0) {
                $hijabUpdate = mysqli_prepare($conn, 'UPDATE user_profiles SET hijab_status=? WHERE user_id=?');
                if (!$hijabUpdate) {
                    throw new Exception('Hijab status update preparation failed.');
                }
                mysqli_stmt_bind_param($hijabUpdate, 'si', $hijab_status, $user_id);
                if (!mysqli_stmt_execute($hijabUpdate)) {
                    mysqli_stmt_close($hijabUpdate);
                    throw new Exception('Hijab status update failed.');
                }
                mysqli_stmt_close($hijabUpdate);
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
                $pageTitle = 'Lifestyle & Health';
                $pageDescription = '';
                $steps = [
                    1 => ['title' => 'Personal', 'icon' => 'bi-person-circle', 'url' => 'step1.php'],
                    2 => ['title' => 'Family', 'icon' => 'bi-people', 'url' => 'step2.php'],
                    3 => ['title' => 'Lifestyle', 'icon' => 'bi-heart', 'url' => 'step3.php'],
                    4 => ['title' => 'Location', 'icon' => 'bi-geo-alt', 'url' => 'step4.php'],
                    5 => ['title' => 'Privacy', 'icon' => 'bi-shield-lock', 'url' => 'step5.php'],
                    6 => ['title' => 'PP & QnA', 'icon' => 'bi-patch-question', 'url' => 'step6.php']
                ];
                $completion = sm_get_profile_completion($conn, $user_id);
                $progress = $completion['percentage'];
                ?>

                <header class="step3-wizard-head">
                    <div class="step3-heading-row">
                        <div>
                            <span class="step3-kicker">PROFILE BUILDER</span>
                            <h1>Step <?= $currentStep ?></h1>
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
                            <span>Step <?= $currentStep ?> of <?= count($steps) ?></span>
                            <strong><?= $progress ?>% complete</strong>
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
                                <h2>Islamic Lifestyle</h2>
                                <p>Your regular practices and Islamic lifestyle.</p>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Smoking Status <span class="required">*</span></label>
                                <select name="smoking_status" class="form-select" required>
                                    <option value="">Select smoking status</option>
                                    <?php foreach ($smoking_statuses as $status): ?>
                                        <option value="<?= htmlspecialchars($status) ?>" <?= (($user['smoking_status'] ?? '') === $status) ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Prayer Status <span class="required">*</span></label>
                                <select name="prayer_status" class="form-select" required>
                                    <option value="">Select prayer status</option>
                                    <?php foreach ($prayer_statuses as $status): ?>
                                        <option value="<?= htmlspecialchars($status) ?>" <?= (($user['prayer_status'] ?? '') === $status) ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Quran Reading</label>
                                <select name="quran_reading" class="form-select">
                                    <option value="">Select Quran reading</option>
                                    <?php foreach ($quran_reading_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['quran_reading'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Fasting</label>
                                <select name="fasting_status" class="form-select">
                                    <option value="">Select fasting</option>
                                    <?php foreach ($fasting_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['fasting_status'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Religious Practice</label>
                                <select name="religious_practice" class="form-select">
                                    <option value="">Select religious practice</option>
                                    <?php foreach ($religious_practice_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['religious_practice'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Islamic Knowledge <span class="required">*</span></label>
                                <select name="islamic_knowledge" class="form-select" required>
                                    <option value="">Select Islamic knowledge</option>
                                    <?php foreach ($islamic_knowledge_levels as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['islamic_knowledge'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Halal Lifestyle <span class="required">*</span></label>
                                <select name="halal_lifestyle" class="form-select" required>
                                    <option value="">Select halal lifestyle</option>
                                    <?php foreach ($halal_lifestyle_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['halal_lifestyle'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Mahram Maintained</label>
                                <select name="mahram_maintained" class="form-select">
                                    <option value="">Select mahram practice</option>
                                    <?php foreach ($mahram_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['mahram_maintained'] ?? null) === ($option === 'Yes' ? 1 : 0)) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Islamic Activities</label>
                                <textarea name="islamic_activities" rows="3" maxlength="500" class="form-control" placeholder="Mention mosque visits, Islamic lectures, study circles or other activities."><?= htmlspecialchars($user['islamic_activities'] ?? '') ?></textarea>
                            </div>


                            <?php if (($user['gender'] ?? '') === 'Male'): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Beard Status</label>
                                    <select name="beard_status" class="form-select">
                                        <option value="">Select beard status</option>
                                        <?php foreach ($beard_statuses as $status): ?>
                                            <option value="<?= htmlspecialchars($status) ?>" <?= (($user['beard_status'] ?? '') === $status) ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <div class="col-md-6">
                                    <label class="form-label">Hijab Status</label>
                                    <select name="hijab_status" class="form-select">
                                        <option value="">Select hijab status</option>
                                        <?php foreach ($hijab_statuses as $status): ?>
                                            <option value="<?= htmlspecialchars($status) ?>" <?= (($user['hijab_status'] ?? '') === $status) ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Hijab Details</label>
                                    <textarea name="hijab_details" rows="3" maxlength="500" class="form-control" placeholder="Add any helpful details about your hijab practice."><?= htmlspecialchars($user['hijab_details'] ?? '') ?></textarea>
                                </div>
                            <?php endif; ?>

                        </div>
                    </section>

                    <!-- Daily lifestyle -->
                    <section class="step3-section">
                        <div class="section-heading">
                            <div class="section-icon"><i class="bi bi-sunrise"></i></div>
                            <div>
                                <h2>Daily Lifestyle</h2>
                                <p>Simple everyday preferences that help describe your lifestyle.</p>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Diet Preference</label>
                                <select name="diet_preference" class="form-select">
                                    <option value="">Select diet preference</option>
                                    <?php foreach ($diet_preferences as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['diet_preference'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Sleep Pattern <span class="required">*</span></label>
                                <select name="sleep_pattern" class="form-select" required>
                                    <option value="">Select sleep pattern</option>
                                    <?php foreach ($sleep_patterns as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['sleep_pattern'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Tea / Coffee Habit</label>
                                <select name="tea_coffee" class="form-select">
                                    <option value="">Select tea / coffee habit</option>
                                    <?php foreach ($tea_coffee_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['tea_coffee'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
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
                                <h2>Personality & Social</h2>
                                <p>Tell others a little more about how you naturally interact.</p>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Personality Type <span class="required">*</span></label>
                                <select name="personality_type" class="form-select" required>
                                    <option value="">Select personality type</option>
                                    <?php foreach ($personality_types as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['personality_type'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Social Nature <span class="required">*</span></label>
                                <select name="social_nature" class="form-select" required>
                                    <option value="">Select social nature</option>
                                    <?php foreach ($social_natures as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['social_nature'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Free-time Interests</label>
                                <div class="interest-grid">
                                    <?php foreach ($free_time_interest_options as $interest): ?>
                                        <label class="interest-option">
                                            <input type="checkbox" name="free_time_interests[]" value="<?= htmlspecialchars($interest) ?>" <?= in_array($interest, $selected_interests, true) ? 'checked' : '' ?>>
                                            <span><?= htmlspecialchars($interest) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="field-help">Choose any interests that describe how you enjoy your free time.</div>
                            </div>
                        </div>
                    </section>

                    <!-- Preferences -->
                    <section class="step3-section">
                        <div class="section-heading">
                            <div class="section-icon"><i class="bi bi-compass"></i></div>
                            <div>
                                <h2>Lifestyle Preferences</h2>
                                <p>Optional details that make your profile more informative.</p>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-4">
                                <label class="form-label">Travel Interest</label>
                                <select name="travel_interest" class="form-select">
                                    <option value="">Select travel interest</option>
                                    <?php foreach ($travel_interest_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['travel_interest'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Spending Style</label>
                                <select name="spending_style" class="form-select">
                                    <option value="">Select spending style</option>
                                    <?php foreach ($spending_style_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['spending_style'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Pets</label>
                                <select name="pets" class="form-select">
                                    <option value="">Select pet preference</option>
                                    <?php foreach ($pet_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['pets'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
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
                                <h2>Health Information</h2>
                                <p>Basic health details for a more complete profile.</p>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Blood Group</label>
                                <select name="blood_group" class="form-select">
                                    <option value="">Select blood group</option>
                                    <?php foreach ($blood_groups as $blood): ?>
                                        <option value="<?= htmlspecialchars($blood) ?>" <?= (($health['blood_group'] ?? '') === $blood) ? 'selected' : '' ?>><?= htmlspecialchars($blood) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Physical Disability</label>
                                <select name="physical_disability" class="form-select">
                                    <option value="">Select physical disability</option>
                                    <?php foreach ($physical_disabilities as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($health['disability_status'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Medical Notes <span class="optional">(Optional)</span></label>
                                <textarea name="health_notes" rows="3" maxlength="500" class="form-control" placeholder="Mention chronic diseases, allergies or important medical information."><?= htmlspecialchars($health['medical_notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </section>

                    <div class="step3-actions">
                        <a href="view_profile.php" class="btn btn-outline-secondary step3-back">
                            <i class="bi bi-arrow-left-circle"></i> Back to Profile
                        </a>
                        <button type="submit" name="save_step3" class="btn btn-success step3-save">
                            Save & Continue <i class="bi bi-arrow-right-circle"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/profile-step3.js" defer></script>
<?php include '../includes/footer.php'; ?>
