<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

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
        up.complexion
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
    'bio' => $user['bio'] ?? ''
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
        'bio' => $bio
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
                    complexion = ?
                WHERE user_id = ?
            ");

            mysqli_stmt_bind_param(
                $save,
                "ssssssssdsidsi",
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
                    complexion
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            mysqli_stmt_bind_param(
                $save,
                "isssssssssssdsids",
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
                $complexion
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

<main class="step1-page">
    <div class="step1-shell">

        <header class="step1-wizard-head">
            <div class="step1-heading-row">
                <div>
                    <span class="step1-kicker">PROFILE BUILDER</span>
                    <h1>Step 1</h1>
                    <p>Personal Information</p>
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
                    <span>Step 1 of 6</span>
                    <strong><?= $progress ?>% complete</strong>
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
                    <span class="step1-section-kicker">ACCOUNT DETAILS</span>
                    <h2>Basic Information</h2>
                </div>
            </div>

            <div class="step1-basic-grid">
                <div><span>Full Name</span><strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong></div>
                <div><span>Gender</span><strong><?= htmlspecialchars($user['gender']) ?></strong></div>
                <div><span>Email</span><strong><?= htmlspecialchars($user['email']) ?></strong></div>
                <div><span>Mobile</span><strong><?= htmlspecialchars($user['mobile']) ?></strong></div>
            </div>

            <div class="step1-locked-note">
                <i class="bi bi-lock-fill"></i>
                <span>Name, Gender, Email and Mobile are fixed from your registration and cannot be changed here.</span>
            </div>
        </section>

        <form method="POST" class="step1-form" novalidate>
            <section class="step1-form-card">
                <div class="step1-section-heading">
                    <span class="step1-section-icon"><i class="bi bi-pencil-square"></i></span>
                    <div>
                        <span class="step1-section-kicker">YOUR INFORMATION</span>
                        <h2>Personal Details</h2>
                    </div>
                </div>

                <div class="step1-field-grid step1-personal-grid">
                    <div class="step1-field">
                        <label for="date_of_birth">Date of Birth <em>*</em></label>
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
                            ?>" placeholder="dd/mm/yyyy" inputmode="numeric" autocomplete="bday" maxlength="10" required>
                            <button type="button" class="step1-calendar-btn" id="open_dob_calendar" aria-label="Open date of birth calendar"><i class="bi bi-calendar3"></i></button>
                            <input type="date" id="dob_calendar" class="step1-hidden-date" max="<?= date('Y-m-d') ?>" tabindex="-1" aria-hidden="true">
                        </div>
                        <small>Use dd/mm/yyyy or choose your date from the calendar.</small>
                    </div>

                    <div class="step1-field">
                        <label for="age">Age</label>
                        <input type="text" id="age" value="" placeholder="Auto calculated" readonly aria-readonly="true">
                        <small>Age is calculated automatically from your date of birth.</small>
                    </div>

                    <div class="step1-field step1-nid-field">
                        <label for="nid_number">NID or Birth Certificate Number</label>
                        <input type="text" id="nid_number" name="nid_number" value="<?= htmlspecialchars((string) $old['nid_number']) ?>" maxlength="30" placeholder="Optional">
                        <small>Optional · Enter your NID or Birth Certificate Number.</small>
                    </div>

                    <div class="step1-field">
                        <label for="religion">Religion <em>*</em></label>
                        <select id="religion" name="religion" required>
                            <option value="" disabled <?= $old['religion'] === '' ? 'selected' : '' ?>>Select religion</option>
                            <?php foreach ($religions as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $old['religion'] === $item ? 'selected' : '' ?>><?= htmlspecialchars($item) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="step1-field">
                        <label for="madhhab">Madhhab <em>*</em></label>
                        <select id="madhhab" name="madhhab" required>
                            <option value="" disabled <?= $old['madhhab'] === '' ? 'selected' : '' ?>>Select madhhab</option>
                            <?php foreach ($madhhabs as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $old['madhhab'] === $item ? 'selected' : '' ?>><?= htmlspecialchars($item) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="step1-field">
                        <label for="marital_status">Marital Status <em>*</em></label>
                        <select id="marital_status" name="marital_status" required>
                            <option value="" disabled <?= $old['marital_status'] === '' ? 'selected' : '' ?>>Select marital status</option>
                            <?php foreach ($marital_statuses as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $old['marital_status'] === $item ? 'selected' : '' ?>><?= htmlspecialchars($item) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="step1-field step1-height-field">
                        <label>Height <em>*</em></label>
                        <div class="step1-height-grid">
                            <select id="feet" aria-label="Select height in feet" required>
                                <option value="" disabled selected>foot</option>
                                <?php for ($i = 4; $i <= 7; $i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?> foot</option>
                                <?php endfor; ?>
                            </select>
                            <select id="inch" aria-label="Select height in inches" required>
                                <option value="" disabled selected>inch</option>
                                <?php for ($i = 0; $i <= 11; $i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?> inch</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <input type="hidden" id="height_cm" name="height_cm" value="<?= htmlspecialchars((string) $old['height_cm']) ?>">
                    </div>

                    <div class="step1-field">
                        <label for="weight_kg">Weight (kg) <em>*</em></label>
                        <input type="number" id="weight_kg" name="weight_kg" value="<?= htmlspecialchars((string) $old['weight_kg']) ?>" placeholder="Enter weight" min="30" max="200" step="1" required>
                        <small>Use the arrows or type your weight.</small>
                    </div>

                    <div class="step1-field">
                        <label for="complexion">Skin Colour <em>*</em></label>
                        <select id="complexion" name="complexion" required>
                            <option value="" disabled <?= $old['complexion'] === '' ? 'selected' : '' ?>>Select Skin Colour</option>
                            <?php foreach ($complexions as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $old['complexion'] === $item ? 'selected' : '' ?>><?= htmlspecialchars($item) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="step1-field step1-bio-field">
                        <label for="bio">Short Bio</label>
                        <textarea id="bio" name="bio" maxlength="500" rows="5" placeholder="Write something about your personal life" ><?= htmlspecialchars($old['bio']) ?></textarea>
                        <small>Optional · Maximum 500 characters.</small>
                    </div>
                </div>
            </section>

            <section class="step1-form-card step1-career-card">
                <div class="step1-section-heading">
                    <span class="step1-section-icon"><i class="bi bi-briefcase-fill"></i></span>
                    <div>
                        <span class="step1-section-kicker">CAREER &amp; FINANCIAL INFORMATION</span>
                        <h2>Career &amp; Financial Information</h2>
                    </div>
                </div>

                <div class="step1-field-grid">
                    <div class="step1-field">
                        <label for="education">Education <em>*</em></label>
                        <select id="education" name="education" required>
                            <option value="" disabled <?= $old['education'] === '' ? 'selected' : '' ?>>Select education</option>
                            <?php foreach ($education_levels as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $old['education'] === $item ? 'selected' : '' ?>><?= htmlspecialchars($item) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="step1-field">
                        <label for="profession">Profession <em>*</em></label>
                        <select id="profession" name="profession" required>
                            <option value="" disabled <?= $old['profession'] === '' ? 'selected' : '' ?>>Select profession</option>
                            <?php foreach ($user_professions as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $old['profession'] === $item ? 'selected' : '' ?>><?= htmlspecialchars($item) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small id="profession-help">Select your current profession or occupation.</small>
                    </div>

                    <div class="step1-field">
                        <label for="occupation_details">Occupation Details</label>
                        <input type="text" id="occupation_details" name="occupation_details" value="<?= htmlspecialchars((string) $old['occupation_details']) ?>" maxlength="150" placeholder="e.g. Software Engineer at a private company">
                        <small>Optional · Add a little more detail about your work.</small>
                    </div>

                    <div class="step1-field">
                        <label for="monthly_income">Monthly Income (BDT) <span id="income-required-mark"><em>*</em></span></label>
                        <div class="step1-income-control">
                            <span class="step1-income-prefix">৳</span>
                            <input type="number" id="monthly_income" name="monthly_income" value="<?= htmlspecialchars((string) $old['monthly_income']) ?>" min="0" max="99999999.99" step="100" placeholder="Optional">
                        </div>
                        <small id="income-help">Required for most income-earning professions · Optional for Student, Unemployed and Retired.</small>
                    </div>
                </div>
            </section>

            <div class="step1-actions">
                <a href="view_profile.php" class="step1-btn step1-btn-secondary">
                    <i class="bi bi-arrow-left"></i>
                    <span>Back to Profile</span>
                </a>
                <button type="submit" name="save_step1" class="step1-btn step1-btn-primary">
                    <span>Save &amp; Continue</span>
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

    calculateAge();
    restoreHeight();
})();
</script>

<?php include '../includes/footer.php'; ?>
