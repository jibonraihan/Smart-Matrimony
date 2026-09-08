<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/dropdowns.php';
require_once '../includes/profile_completion.php';

/* Step 4 location dropdown options. Keep these aligned with user_profiles enum values. */
$area_type_options = ['Urban', 'Rural', 'Semi-Urban'];
$residence_type_options = ['Own House', 'Family House', 'Rented', 'Other'];

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$error = '';

/* Load existing location data. */
$stmt = mysqli_prepare($conn, '
    SELECT
        country,
        division_id,
        district_id,
        upazila_id,
        area,
        area_type,
        residence_type,
        post_office,
        postal_code,
        permanent_hometown
    FROM user_profiles
    WHERE user_id=?
    LIMIT 1
');
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = $result ? mysqli_fetch_assoc($result) : null;

/* Load saved location names so the preview can show an existing address immediately. */
$existing_location_names = [
    'division' => '',
    'district' => '',
    'upazila' => ''
];
if ($user && !empty($user['division_id']) && !empty($user['district_id']) && !empty($user['upazila_id'])) {
    $location_names_stmt = mysqli_prepare($conn, '
        SELECT
            d.name_en AS division_name,
            ds.name_en AS district_name,
            u.name_en AS upazila_name
        FROM divisions d
        INNER JOIN districts ds ON ds.id=? AND ds.division_id=d.id
        INNER JOIN upazilas u ON u.id=? AND u.district_id=ds.id
        WHERE d.id=?
        LIMIT 1
    ');
    mysqli_stmt_bind_param(
        $location_names_stmt,
        'iii',
        $user['district_id'],
        $user['upazila_id'],
        $user['division_id']
    );
    mysqli_stmt_execute($location_names_stmt);
    $location_names_result = mysqli_stmt_get_result($location_names_stmt);
    if ($location_names_result && ($location_names_row = mysqli_fetch_assoc($location_names_result))) {
        $existing_location_names = [
            'division' => (string) $location_names_row['division_name'],
            'district' => (string) $location_names_row['district_name'],
            'upazila' => (string) $location_names_row['upazila_name']
        ];
    }
}

if (!$user) {
    header('Location: step1.php');
    exit();
}

if (isset($_POST['save_step4'])) {
    $country = clean_input($_POST['country'] ?? 'Bangladesh');
    $division_id = (int) ($_POST['division_id'] ?? 0);
    $district_id = (int) ($_POST['district_id'] ?? 0);
    $upazila_id = (int) ($_POST['upazila_id'] ?? 0);
    $area = clean_input($_POST['area'] ?? '');
    $area_type = clean_input($_POST['area_type'] ?? '');
    $residence_type = clean_input($_POST['residence_type'] ?? '');
    $post_office = clean_input($_POST['post_office'] ?? '');
    $postal_code = clean_input($_POST['postal_code'] ?? '');
    $permanent_hometown = clean_input($_POST['permanent_hometown'] ?? '');

    $valid_area_type = in_array($area_type, array_map('strval', $area_type_options), true);
    $valid_residence_type = ($residence_type === '' || in_array($residence_type, array_map('strval', $residence_type_options), true));

    $location_valid = false;
    if ($division_id > 0 && $district_id > 0 && $upazila_id > 0) {
        $location_check = mysqli_prepare($conn, '
            SELECT d.id AS district_id, u.id AS upazila_id
            FROM districts d
            INNER JOIN upazilas u ON u.district_id=d.id
            WHERE d.id=? AND d.division_id=? AND u.id=?
            LIMIT 1
        ');
        mysqli_stmt_bind_param($location_check, 'iii', $district_id, $division_id, $upazila_id);
        mysqli_stmt_execute($location_check);
        $location_result = mysqli_stmt_get_result($location_check);
        $location_valid = $location_result && mysqli_num_rows($location_result) === 1;
    }

    if ($division_id <= 0 || $district_id <= 0 || $upazila_id <= 0 || $area === '' || !$valid_area_type) {
        $error = 'Please complete all required location fields marked with *.';
    } elseif (!$valid_residence_type) {
        $error = 'Please select a valid residence type.';
    } elseif (!$location_valid) {
        $error = 'Please select a valid Division, District and Upazila combination.';
    } else {
        $update = mysqli_prepare($conn, '
            UPDATE user_profiles SET
                country=?,
                division_id=?,
                district_id=?,
                upazila_id=?,
                area=?,
                area_type=?,
                residence_type=?,
                post_office=?,
                postal_code=?,
                permanent_hometown=?
            WHERE user_id=?
        ');

        mysqli_stmt_bind_param(
            $update,
            'siiissssssi',
            $country,
            $division_id,
            $district_id,
            $upazila_id,
            $area,
            $area_type,
            $residence_type,
            $post_office,
            $postal_code,
            $permanent_hometown,
            $user_id
        );

        if (mysqli_stmt_execute($update)) {
            header('Location: step5.php');
            exit();
        }

        $error = 'Unable to save Step 4 right now. Please try again.';
    }

    /* Preserve submitted values after validation errors. */
    $user['country'] = $country;
    $user['division_id'] = $division_id;
    $user['district_id'] = $district_id;
    $user['upazila_id'] = $upazila_id;
    $user['area'] = $area;
    $user['area_type'] = $area_type;
    $user['residence_type'] = $residence_type;
    $user['post_office'] = $post_office;
    $user['postal_code'] = $postal_code;
    $user['permanent_hometown'] = $permanent_hometown;
}

$page_css = 'assets/css/profile-step4.css';
include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container py-4 py-lg-5 profile-step4-page">
    <div class="row justify-content-center">
        <div class="col-12 col-xxl-10">
            <div class="step4-shell">
                <?php
                $currentStep = 4;
                $pageTitle = 'Location Information';
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

                <header class="step4-wizard-head">
                    <div class="step4-heading-row">
                        <div>
                            <span class="step4-kicker">PROFILE BUILDER</span>
                            <h1>Step <?= $currentStep ?></h1>
                            <p><?= htmlspecialchars($pageTitle) ?></p>
                        </div>
                        <a class="step4-logo" href="../dashboard.php" aria-label="Back to dashboard">
                            <img src="<?= BASE_URL ?>assets/images/logo/logo.png" alt="Smart Matrimony">
                        </a>
                    </div>

                    <nav class="step4-step-nav" aria-label="Profile steps">
                        <?php foreach ($steps as $stepNo => $step): ?>
                            <a class="step4-step <?= $stepNo === $currentStep ? 'is-current' : '' ?> <?= !empty($completion['steps'][$stepNo]) ? 'is-completed' : '' ?>"
                               href="<?= htmlspecialchars($step['url']) ?>"
                               aria-current="<?= $stepNo === $currentStep ? 'step' : 'false' ?>">
                                <span class="step4-step-icon"><i class="bi <?= $step['icon'] ?>"></i></span>
                                <span class="step4-step-label"><?= htmlspecialchars($step['title']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>

                    <div class="step4-progress-wrap">
                        <div class="step4-progress-track" role="progressbar" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Profile completion">
                            <div class="step4-progress-fill" style="width: <?= $progress ?>%"></div>
                        </div>
                        <div class="step4-progress-meta">
                            <span>Step <?= $currentStep ?> of <?= count($steps) ?></span>
                            <strong><?= $progress ?>% complete</strong>
                        </div>
                    </div>
                </header>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger step4-alert" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="step4-form" novalidate>
                    <section class="step4-section">
                        <div class="section-heading">
                            <span class="section-icon"><i class="bi bi-globe2"></i></span>
                            <div>
                                <h2>Location Information</h2>
                                <p>Tell us where you currently live.</p>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="country">Country <span class="required">*</span></label>
                                <input type="text" id="country" name="country" class="form-control" value="Bangladesh" readonly>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" for="division">Division <span class="required">*</span></label>
                                <select name="division_id" id="division" class="form-select location-select" required>
                                    <option value="">Select division</option>
                                    <?php
                                    $divisions = mysqli_query($conn, 'SELECT id, name_bn, name_en FROM divisions ORDER BY name_en');
                                    while ($row = mysqli_fetch_assoc($divisions)):
                                    ?>
                                        <option value="<?= (int) $row['id'] ?>" <?= ((int)($user['division_id'] ?? 0) === (int)$row['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($row['name_bn']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" for="district">District <span class="required">*</span></label>
                                <select name="district_id" id="district" class="form-select location-select" required disabled>
                                    <option value="">Select district</option>
                                </select>
                                <small class="step4-field-hint">Districts are loaded from the selected division.</small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" for="upazila">Upazila <span class="required">*</span></label>
                                <select name="upazila_id" id="upazila" class="form-select location-select" required disabled>
                                    <option value="">Select upazila</option>
                                </select>
                                <small class="step4-field-hint">Upazilas are loaded from the selected district.</small>
                            </div>
                        </div>
                    </section>

                    <section class="step4-section">
                        <div class="section-heading">
                            <span class="section-icon"><i class="bi bi-house-heart"></i></span>
                            <div>
                                <h2>Residential Details</h2>
                                <p>Add a little more context about your home area.</p>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-lg-6">
                                <label class="form-label" for="area">Area / Village <span class="required">*</span></label>
                                <input type="text" id="area" name="area" class="form-control" value="<?= htmlspecialchars($user['area'] ?? '') ?>" placeholder="Enter area or village" maxlength="120" autocomplete="address-level4" required>
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label" for="area_type">Area Type <span class="required">*</span></label>
                                <select name="area_type" id="area_type" class="form-select" required>
                                    <option value="">Select area type</option>
                                    <?php foreach ($area_type_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['area_type'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label" for="residence_type">Residence Type <span class="optional">(Optional)</span></label>
                                <select name="residence_type" id="residence_type" class="form-select">
                                    <option value="">Select residence type</option>
                                    <?php foreach ($residence_type_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($user['residence_type'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label" for="permanent_hometown">Permanent Home / Hometown <span class="optional">(Optional)</span></label>
                                <input type="text" id="permanent_hometown" name="permanent_hometown" class="form-control" value="<?= htmlspecialchars($user['permanent_hometown'] ?? '') ?>" placeholder="Enter permanent hometown" maxlength="120">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="post_office">Post Office <span class="optional">(Optional)</span></label>
                                <input type="text" id="post_office" name="post_office" class="form-control" value="<?= htmlspecialchars($user['post_office'] ?? '') ?>" placeholder="Enter post office" maxlength="100">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="postal_code">Postal Code <span class="optional">(Optional)</span></label>
                                <input type="text" id="postal_code" name="postal_code" class="form-control" value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>" placeholder="Enter postal code" inputmode="numeric" maxlength="4">
                            </div>
                        </div>
                    </section>

                    <div class="step4-location-preview" id="locationPreview" aria-live="polite">
                        <div class="step4-location-preview-icon"><i class="bi bi-pin-map-fill"></i></div>
                        <div class="step4-location-preview-content">
                            <span class="step4-location-preview-kicker">LOCATION PREVIEW</span>
                            <strong id="locationPreviewText">Select your Division, District and Upazila to preview your location.</strong>
                        </div>
                    </div>

                    <div class="step4-privacy-note">
                        <i class="bi bi-shield-check"></i>
                        <div>
                            <strong>Your exact location stays under your control.</strong>
                            <span>Location visibility settings can be managed in the Privacy step.</span>
                        </div>
                    </div>

                    <div class="step4-actions">
                        <a href="../profile/view_profile.php" class="btn step4-back">
                            <i class="bi bi-arrow-left-circle"></i> Back to Profile
                        </a>
                        <button type="submit" name="save_step4" class="btn step4-save">
                            Save &amp; Continue <i class="bi bi-arrow-right-circle"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const form = document.querySelector('.step4-form');
    const division = document.getElementById('division');
    const district = document.getElementById('district');
    const upazila = document.getElementById('upazila');
    const postalCode = document.getElementById('postal_code');
    const selectedDistrict = <?= json_encode((string)($user['district_id'] ?? '')) ?>;
    const selectedUpazila = <?= json_encode((string)($user['upazila_id'] ?? '')) ?>;
    const existingLocation = <?= json_encode($existing_location_names, JSON_UNESCAPED_UNICODE) ?>;
    const locationPreviewText = document.getElementById('locationPreviewText');

    function updateLocationPreview() {
        const divisionText = division.options[division.selectedIndex]?.textContent.trim() || '';
        const districtText = district.options[district.selectedIndex]?.textContent.trim() || '';
        const upazilaText = upazila.options[upazila.selectedIndex]?.textContent.trim() || '';

        if (division.value && district.value && upazila.value && districtText && upazilaText) {
            locationPreviewText.textContent = upazilaText + ', ' + districtText + ', ' + divisionText + ', Bangladesh';
        } else if (existingLocation.division && existingLocation.district && existingLocation.upazila && !division.dataset.changed) {
            locationPreviewText.textContent = existingLocation.upazila + ', ' + existingLocation.district + ', ' + existingLocation.division + ', Bangladesh';
        } else if (division.value) {
            locationPreviewText.textContent = 'Continue selecting District and Upazila to complete your location preview.';
        } else {
            locationPreviewText.textContent = 'Select your Division, District and Upazila to preview your location.';
        }
    }

    function setLoading(select, loading) {
        select.classList.toggle('is-loading', loading);
        select.disabled = loading;
    }

    function resetSelect(select, placeholder) {
        select.innerHTML = '<option value="">' + placeholder + '</option>';
        select.disabled = true;
        select.classList.remove('is-loading', 'is-invalid');
    }

    function loadDistrict(selected = '') {
        const divisionId = division.value;
        resetSelect(district, 'Select district');
        resetSelect(upazila, 'Select upazila');
        if (!divisionId) return;

        setLoading(district, true);
        district.innerHTML = '<option value="">Loading districts...</option>';

        fetch('../ajax/get_districts.php?division_id=' + encodeURIComponent(divisionId))
            .then(response => {
                if (!response.ok) throw new Error('District request failed');
                return response.text();
            })
            .then(html => {
                district.innerHTML = html;
                district.disabled = false;
                district.classList.remove('is-loading');
                if (selected) {
                    district.value = selected;
                    if (district.value === selected) loadUpazila(selectedUpazila);
                }
            })
            .catch(() => {
                district.innerHTML = '<option value="">Unable to load districts</option>';
                district.disabled = false;
                district.classList.remove('is-loading');
            });
    }

    function loadUpazila(selected = '') {
        const districtId = district.value;
        resetSelect(upazila, 'Select upazila');
        if (!districtId) return;

        setLoading(upazila, true);
        upazila.innerHTML = '<option value="">Loading upazilas...</option>';

        fetch('../ajax/get_upazilas.php?district_id=' + encodeURIComponent(districtId))
            .then(response => {
                if (!response.ok) throw new Error('Upazila request failed');
                return response.text();
            })
            .then(html => {
                upazila.innerHTML = html;
                upazila.disabled = false;
                upazila.classList.remove('is-loading');
                if (selected) upazila.value = selected;
            })
            .catch(() => {
                upazila.innerHTML = '<option value="">Unable to load upazilas</option>';
                upazila.disabled = false;
                upazila.classList.remove('is-loading');
            });
    }

    division.addEventListener('change', function () {
        division.dataset.changed = '1';
        loadDistrict();
        updateLocationPreview();
    });

    district.addEventListener('change', function () {
        loadUpazila();
        updateLocationPreview();
    });

    form.addEventListener('keydown', function (event) {
        const target = event.target;
        if (event.key === 'Enter' && target.matches('input[type="text"]')) {
            event.preventDefault();
        }
    });

    form.addEventListener('submit', function (event) {
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        const required = form.querySelectorAll('[required]');
        let firstInvalid = null;

        required.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                if (!firstInvalid) firstInvalid = field;
            }
        });

        if (postalCode.value && !/^\d{4}$/.test(postalCode.value.trim())) {
            postalCode.classList.add('is-invalid');
            if (!firstInvalid) firstInvalid = postalCode;
        }

        if (firstInvalid) {
            event.preventDefault();
            firstInvalid.focus();
        }
    });

    postalCode.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 4);
        this.classList.remove('is-invalid');
    });

    if (division.value) {
        loadDistrict(selectedDistrict);
    }

    upazila.addEventListener('change', updateLocationPreview);
    updateLocationPreview();
})();
</script>

<?php include '../includes/footer.php'; ?>
