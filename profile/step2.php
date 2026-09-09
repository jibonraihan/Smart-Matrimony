<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
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
$error = "";

/*
|--------------------------------------------------------------------------
| Determine Wali requirement before rendering the page
|--------------------------------------------------------------------------
*/
$gender = '';
$religion = '';
$wali_required = false;

/*
|--------------------------------------------------------------------------
| Load existing profile data
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare($conn, "SELECT * FROM user_profiles WHERE user_id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = $result ? (mysqli_fetch_assoc($result) ?: []) : [];

$gender = $user['gender'] ?? '';
$religion = $user['religion'] ?? '';
$wali_required = ($gender === 'Female' && $religion === 'Islam');

/*
|--------------------------------------------------------------------------
| Save Step 2
|--------------------------------------------------------------------------
*/
if (isset($_POST['save_step2'])) {

    $father_name       = clean_input($_POST['father_name'] ?? '');
    $father_profession = clean_input($_POST['father_profession'] ?? '');
    $mother_name       = clean_input($_POST['mother_name'] ?? '');
    $mother_profession = clean_input($_POST['mother_profession'] ?? '');

    $guardian_name     = clean_input($_POST['guardian_name'] ?? '');
    $guardian_relation = clean_input($_POST['guardian_relation'] ?? '');
    $guardian_contact  = clean_input($_POST['guardian_contact'] ?? '');

    $brothers          = max(0, min(50, (int) ($_POST['brothers'] ?? 0)));
    $sisters           = max(0, min(50, (int) ($_POST['sisters'] ?? 0)));
    $family_type       = clean_input($_POST['family_type'] ?? '');
    $family_status     = clean_input($_POST['family_status'] ?? '');
    $living_with_family = clean_input($_POST['living_with_family'] ?? '');

    $paternal_uncles_raw = trim((string) ($_POST['paternal_uncles'] ?? ''));
    $paternal_aunts_raw = trim((string) ($_POST['paternal_aunts'] ?? ''));
    $paternal_married = clean_input($_POST['paternal_married'] ?? '');
    $family_purdah_environment = clean_input($_POST['family_purdah_environment'] ?? '');
    $family_religious_lifestyle = clean_input($_POST['family_religious_lifestyle'] ?? '');

    $paternal_uncles = $paternal_uncles_raw === '' ? null : max(0, min(50, (int) $paternal_uncles_raw));
    $paternal_aunts = $paternal_aunts_raw === '' ? null : max(0, min(50, (int) $paternal_aunts_raw));

    // Keep validation in sync with the centralized dropdown options.
    $valid_family_religious_lifestyles = $family_religious_lifestyle_options;

    if ($father_name === '' || $father_profession === '' || $mother_name === '' || $mother_profession === '') {
        $error = "Please complete all required parents' information.";
    } elseif ($wali_required && ($guardian_name === '' || $guardian_relation === '' || $guardian_contact === '')) {
        $error = "Marriage Guardian (Wali) information is required for Muslim female profiles.";
    } elseif ($family_type === '' || $family_status === '' || $living_with_family === '') {
        $error = "Please complete all required family details.";
    } elseif ($paternal_uncles === null || $paternal_aunts === null || $family_purdah_environment === '' || $family_religious_lifestyle === '') {
        $error = "Please complete all required extended family details.";
    } elseif (!in_array($family_religious_lifestyle, $valid_family_religious_lifestyles, true)) {
        $error = "Please select a valid family religious lifestyle.";
    } else {

        // Keep the existing siblings column as the total for compatibility, while storing both counts separately.
        $siblings = $brothers + $sisters;

        $sql = "UPDATE user_profiles
                SET father_name=?,
                    father_profession=?,
                    mother_name=?,
                    mother_profession=?,
                    guardian_name=?,
                    guardian_relation=?,
                    guardian_contact=?,
                    family_type=?,
                    family_status=?,
                    brothers_count=?,
                    sisters_count=?,
                    siblings=?,
                    living_with_family=?,
                    paternal_uncles_count=?,
                    paternal_aunts_count=?,
                    paternal_siblings_married=?,
                    family_purdah_environment=?,
                    family_religious_lifestyle=?
                WHERE user_id=?";

        $update = mysqli_prepare($conn, $sql);

        if ($update) {
            mysqli_stmt_bind_param(
                $update,
                "sssssssssiiisiiissi",
                $father_name,
                $father_profession,
                $mother_name,
                $mother_profession,
                $guardian_name,
                $guardian_relation,
                $guardian_contact,
                $family_type,
                $family_status,
                $brothers,
                $sisters,
                $siblings,
                $living_with_family,
                $paternal_uncles,
                $paternal_aunts,
                $paternal_married,
                $family_purdah_environment,
                $family_religious_lifestyle,
                $user_id
            );

            if (mysqli_stmt_execute($update)) {
                header("Location: step3.php");
                exit;
            }

            $error = "Database update failed. Please try again.";
        } else {
            $error = "Unable to prepare the profile update.";
        }
    }

    // Preserve submitted values if validation fails.
    $user['father_name'] = $father_name;
    $user['father_profession'] = $father_profession;
    $user['mother_name'] = $mother_name;
    $user['mother_profession'] = $mother_profession;
    $user['guardian_name'] = $guardian_name;
    $user['guardian_relation'] = $guardian_relation;
    $user['guardian_contact'] = $guardian_contact;
    $user['brothers_count'] = $brothers;
    $user['sisters_count'] = $sisters;
    $user['siblings'] = $siblings ?? ($user['siblings'] ?? 0);
    $user['family_type'] = $family_type;
    $user['family_status'] = $family_status;
    $user['living_with_family'] = $living_with_family;
    $user['paternal_uncles_count'] = $paternal_uncles;
    $user['paternal_aunts_count'] = $paternal_aunts;
    $user['paternal_siblings_married'] = $paternal_married;
    $user['family_purdah_environment'] = $family_purdah_environment;
    $user['family_religious_lifestyle'] = $family_religious_lifestyle;
}

$currentStep = 2;
$pageTitle = "Family Information";
$pageDescription = "";
$page_css = 'assets/css/profile-step2.css';

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="profile-step-page">
    <div class="profile-step-shell">

        <?php
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

<header class="step2-wizard-head">
    <div class="step2-heading-row">
        <div>
            <span class="step2-kicker">PROFILE BUILDER</span>
            <h1>Step <?= $currentStep ?></h1>
            <p><?= htmlspecialchars($pageTitle) ?></p>
        </div>
        <a class="step2-logo" href="../dashboard.php" aria-label="Back to dashboard">
            <img src="<?= BASE_URL ?>assets/images/logo/logo.png" alt="Smart Matrimony">
        </a>
    </div>

    <nav class="step2-step-nav" aria-label="Profile steps">
        <?php foreach ($steps as $stepNo => $step): ?>
            <a class="step2-step <?= $stepNo === $currentStep ? 'is-current' : '' ?> <?= !empty($completion['steps'][$stepNo]) ? 'is-completed' : '' ?>"
               href="<?= htmlspecialchars($step['url']) ?>"
               aria-current="<?= $stepNo === $currentStep ? 'step' : 'false' ?>">
                <span class="step2-step-icon"><i class="bi <?= $step['icon'] ?>"></i></span>
                <span class="step2-step-label"><?= htmlspecialchars($step['title']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="step2-progress-wrap">
        <div class="step2-progress-track" role="progressbar" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Profile completion">
            <div class="step2-progress-fill" style="width: <?= $progress ?>%"></div>
        </div>
        <div class="step2-progress-meta">
            <span>Step <?= $currentStep ?> of <?= count($steps) ?></span>
            <strong><?= $progress ?>% complete</strong>
        </div>
    </div>
</header>

        <?php if ($error !== ''): ?>
            <div class="step2-alert" role="alert">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="step2-form" novalidate>

            <section class="step2-section">
                <div class="step2-section-heading">
                    <span class="step2-heading-icon"><i class="bi bi-people-fill"></i></span>
                    <div>
                        <h3>Parents' Information</h3>
                        <p>Tell us about your parents.</p>
                    </div>
                </div>

                <div class="step2-grid step2-grid-2">
                    <div class="step2-field">
                        <label for="father_name">Father's Name <span class="required">*</span></label>
                        <input
                            type="text"
                            id="father_name"
                            name="father_name"
                            value="<?= htmlspecialchars($user['father_name'] ?? '') ?>"
                            placeholder="Enter father's name"
                            required>
                    </div>

                    <div class="step2-field">
                        <label for="father_profession">Father's Profession <span class="required">*</span></label>
                        <select id="father_profession" name="father_profession" required>
                            <option value="">Select father's profession</option>
                            <?php foreach ($father_professions as $profession): ?>
                                <option value="<?= htmlspecialchars($profession) ?>" <?= (($user['father_profession'] ?? '') === $profession) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($profession) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="step2-field">
                        <label for="mother_name">Mother's Name <span class="required">*</span></label>
                        <input
                            type="text"
                            id="mother_name"
                            name="mother_name"
                            value="<?= htmlspecialchars($user['mother_name'] ?? '') ?>"
                            placeholder="Enter mother's name"
                            required>
                    </div>

                    <div class="step2-field">
                        <label for="mother_profession">Mother's Profession <span class="required">*</span></label>
                        <select id="mother_profession" name="mother_profession" required>
                            <option value="">Select mother's profession</option>
                            <?php foreach ($mother_professions as $profession): ?>
                                <option value="<?= htmlspecialchars($profession) ?>" <?= (($user['mother_profession'] ?? '') === $profession) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($profession) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </section>

            <section class="step2-section wali-section <?= $wali_required ? 'wali-required' : 'wali-optional' ?>" id="wali-section">
                <div class="step2-section-heading">
                    <span class="step2-heading-icon"><i class="bi bi-shield-check"></i></span>
                    <div>
                        <h3>Marriage Guardian (Wali) <span class="wali-required-mark" <?= $wali_required ? '' : 'hidden' ?>>*</span></h3>
                        <p id="wali-help">
                            <?= $wali_required
                                ? 'Required for Muslim female profiles.'
                                : 'Optional for your current profile.' ?>
                        </p>
                    </div>
                    <span class="wali-badge" id="wali-badge">
                        <?= $wali_required ? 'Required' : 'Optional' ?>
                    </span>
                </div>

                <div class="step2-grid step2-grid-3">
                    <div class="step2-field">
                        <label for="guardian_name">Guardian Name <span class="wali-required-mark" <?= $wali_required ? '' : 'hidden' ?>>*</span></label>
                        <input
                            type="text"
                            id="guardian_name"
                            name="guardian_name"
                            value="<?= htmlspecialchars($user['guardian_name'] ?? '') ?>"
                            placeholder="Enter guardian's name"
                            <?= $wali_required ? 'required' : '' ?>>
                    </div>

                    <div class="step2-field">
                        <label for="guardian_relation">Relationship <span class="wali-required-mark" <?= $wali_required ? '' : 'hidden' ?>>*</span></label>
                        <select id="guardian_relation" name="guardian_relation" <?= $wali_required ? 'required' : '' ?>>
                            <option value="">Select relationship</option>
                            <?php foreach ($guardian_relations as $relation): ?>
                                <option value="<?= htmlspecialchars($relation) ?>" <?= (($user['guardian_relation'] ?? '') === $relation) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($relation) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="step2-field">
                        <label for="guardian_contact">Guardian Mobile <span class="wali-required-mark" <?= $wali_required ? '' : 'hidden' ?>>*</span></label>
                        <input
                            type="tel"
                            id="guardian_contact"
                            name="guardian_contact"
                            maxlength="15"
                            value="<?= htmlspecialchars($user['guardian_contact'] ?? '') ?>"
                            placeholder="01XXXXXXXXX"
                            <?= $wali_required ? 'required' : '' ?>>
                    </div>
                </div>
            </section>

            <section class="step2-section">
                <div class="step2-section-heading">
                    <span class="step2-heading-icon"><i class="bi bi-house-heart-fill"></i></span>
                    <div>
                        <h3>Family Details</h3>
                        <p>A few simple details about your family.</p>
                    </div>
                </div>

                <div class="step2-grid step2-grid-5">
                    <div class="step2-field">
                        <label for="brothers">Brothers <span class="required">*</span></label>
                        <input
                            type="number"
                            id="brothers"
                            name="brothers"
                            min="0"
                            max="50"
                            step="1"
                            value="<?= htmlspecialchars((string) ($user['brothers_count'] ?? 0)) ?>"
                            placeholder="Enter number"
                            required>
                        <small>Use the arrows or type a number.</small>
                    </div>

                    <div class="step2-field">
                        <label for="sisters">Sisters <span class="required">*</span></label>
                        <input
                            type="number"
                            id="sisters"
                            name="sisters"
                            min="0"
                            max="50"
                            step="1"
                            value="<?= htmlspecialchars((string) ($user['sisters_count'] ?? 0)) ?>"
                            placeholder="Enter number"
                            required>
                        <small>Use the arrows or type a number.</small>
                    </div>

                    <div class="step2-field">
                        <label for="family_type">Family Type <span class="required">*</span></label>
                        <select id="family_type" name="family_type" required>
                            <option value="">Family type</option>
                            <?php foreach ($family_types as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>" <?= (($user['family_type'] ?? '') === $type) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="step2-field">
                        <label for="family_status">Family Status <span class="required">*</span></label>
                        <select id="family_status" name="family_status" required>
                            <option value="">Family status</option>
                            <?php foreach ($family_statuses as $status): ?>
                                <option value="<?= htmlspecialchars($status) ?>" <?= (($user['family_status'] ?? '') === $status) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="step2-field">
                        <label for="living_with_family">Lives with Family <span class="required">*</span></label>
                        <select id="living_with_family" name="living_with_family" required>
                            <option value="">Living status</option>
                            <?php foreach ($living_with_family_options as $option): ?>
                                <option value="<?= htmlspecialchars($option) ?>" <?= (($user['living_with_family'] ?? '') === $option) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="step2-grid step2-grid-3 step2-family-extra-grid">
                    <div class="step2-field">
                        <label for="paternal_uncles">Father's Brothers <span class="required">*</span></label>
                        <input type="number" id="paternal_uncles" name="paternal_uncles" min="0" max="50" step="1" value="<?= htmlspecialchars((string) ($user['paternal_uncles_count'] ?? '')) ?>" placeholder="Number of paternal uncles" required>
                    </div>

                    <div class="step2-field">
                        <label for="paternal_aunts">Father's Sisters <span class="required">*</span></label>
                        <input type="number" id="paternal_aunts" name="paternal_aunts" min="0" max="50" step="1" value="<?= htmlspecialchars((string) ($user['paternal_aunts_count'] ?? '')) ?>" placeholder="Number of paternal aunts" required>
                    </div>

                    <div class="step2-field">
                        <label for="paternal_married">How many are married? <span class="optional">(Optional)</span></label>
                        <input type="text" id="paternal_married" name="paternal_married" maxlength="255" value="<?= htmlspecialchars($user['paternal_siblings_married'] ?? '') ?>" placeholder="e.g. 2 uncles, 1 aunt">
                    </div>

                    <div class="step2-field">
                        <label for="family_purdah_environment">Pordah Environment in Family <span class="required">*</span></label>
                        <textarea id="family_purdah_environment" name="family_purdah_environment" rows="3" maxlength="2000" placeholder="Describe the purdah environment in your family" required><?= htmlspecialchars($user['family_purdah_environment'] ?? '') ?></textarea>
                    </div>

                    <div class="step2-field">
                        <label for="family_religious_lifestyle">Overall Family Religious Lifestyle <span class="required">*</span></label>
                        <select id="family_religious_lifestyle" name="family_religious_lifestyle" required>
                            <option value="">Select family religious lifestyle</option>
                            <?php foreach ($family_religious_lifestyle_options as $lifestyle): ?>
                                <option value="<?= htmlspecialchars($lifestyle) ?>" <?= (($user['family_religious_lifestyle'] ?? '') === $lifestyle) ? 'selected' : '' ?>><?= htmlspecialchars($lifestyle) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </section>

            <div class="step2-actions">
                <a href="view_profile.php" class="step2-btn step2-btn-back">
                    <i class="bi bi-arrow-left-circle"></i>
                    <span>Back to Profile</span>
                </a>

                <button type="submit" name="save_step2" class="step2-btn step2-btn-primary">
                    <span>Save &amp; Continue</span>
                    <i class="bi bi-arrow-right-circle"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const gender = <?= json_encode($user['gender'] ?? '') ?>;
    const religion = <?= json_encode($user['religion'] ?? '') ?>;
    const waliRequired = gender === 'Female' && religion === 'Islam';

    const section = document.getElementById('wali-section');
    const badge = document.getElementById('wali-badge');
    const help = document.getElementById('wali-help');
    const marks = document.querySelectorAll('.wali-required-mark');
    const fields = [
        document.getElementById('guardian_name'),
        document.getElementById('guardian_relation'),
        document.getElementById('guardian_contact')
    ];

    function syncWaliState() {
        section.classList.toggle('wali-required', waliRequired);
        section.classList.toggle('wali-optional', !waliRequired);
        badge.textContent = waliRequired ? 'Required' : 'Optional';
        help.textContent = waliRequired
            ? 'Required for Muslim female profiles.'
            : 'Optional for your current profile.';

        marks.forEach(mark => {
            mark.hidden = !waliRequired;
        });

        fields.forEach(field => {
            if (field) field.required = waliRequired;
        });
    }

    syncWaliState();

    // Keep sibling counters integer-only even when users type manually.
    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('input', function () {
            if (this.value !== '') {
                this.value = Math.max(0, Math.floor(Number(this.value) || 0));
            }
        });
    });
})();
</script>

<?php include '../includes/footer.php'; ?>
