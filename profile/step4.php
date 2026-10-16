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

/* Residential Details dropdown options. */
$area_type_options = [
    'Urban',
    'Rural',
    'Semi-Urban'
];

$residence_type_options = [
    'Own House',
    'Family House',
    'Rented',
    'Other'
];

/* Load current and permanent address data. */
$address_columns = 'country, division_id, district_id, upazila_id, area, area_type, residence_type, post_office, postal_code, permanent_hometown,
    permanent_country, permanent_division_id, permanent_district_id, permanent_upazila_id, permanent_area, permanent_area_type, permanent_residence_type, permanent_post_office, permanent_postal_code';
$stmt = mysqli_prepare($conn, "SELECT $address_columns FROM user_profiles WHERE user_id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = $result ? mysqli_fetch_assoc($result) : null;
if (!$user) {
    $user = array_fill_keys(array_map('trim', explode(',', $address_columns)), '');
}
$same_address = 'Yes';
if (!empty($user['permanent_division_id']) || !empty($user['permanent_district_id']) || !empty($user['permanent_upazila_id']) || !empty($user['permanent_area'])) {
    $same_address = ((string)$user['division_id'] === (string)$user['permanent_division_id'] &&
        (string)$user['district_id'] === (string)$user['permanent_district_id'] &&
        (string)$user['upazila_id'] === (string)$user['permanent_upazila_id'] &&
        (string)$user['area'] === (string)$user['permanent_area'] &&
        (string)$user['area_type'] === (string)$user['permanent_area_type'] &&
        (string)$user['residence_type'] === (string)$user['permanent_residence_type'] &&
        (string)$user['post_office'] === (string)$user['permanent_post_office'] &&
        (string)$user['postal_code'] === (string)$user['permanent_postal_code']) ? 'Yes' : 'No';
}

function sm_step4_location_names($conn, $divisionId, $districtId, $upazilaId) {
    $names = ['division' => '', 'district' => '', 'upazila' => ''];
    if ((int)$divisionId > 0 && (int)$districtId > 0 && (int)$upazilaId > 0) {
        $q = mysqli_prepare($conn, 'SELECT d.name_en AS division_name, ds.name_en AS district_name, u.name_en AS upazila_name FROM divisions d INNER JOIN districts ds ON ds.id=? AND ds.division_id=d.id INNER JOIN upazilas u ON u.id=? AND u.district_id=ds.id WHERE d.id=? LIMIT 1');
        mysqli_stmt_bind_param($q, 'iii', $districtId, $upazilaId, $divisionId);
        mysqli_stmt_execute($q);
        $r = mysqli_stmt_get_result($q);
        if ($r && ($row = mysqli_fetch_assoc($r))) $names = ['division'=>$row['division_name'], 'district'=>$row['district_name'], 'upazila'=>$row['upazila_name']];
    }
    return $names;
}
$existing_location_names = sm_step4_location_names($conn, $user['division_id'], $user['district_id'], $user['upazila_id']);
$existing_permanent_location_names = sm_step4_location_names($conn, $user['permanent_division_id'], $user['permanent_district_id'], $user['permanent_upazila_id']);

if (isset($_POST['save_step4'])) {
    $same_address = (($_POST['same_address'] ?? 'Yes') === 'Yes') ? 'Yes' : 'No';
    $country = clean_input($_POST['country'] ?? 'Bangladesh');
    $division_id = (int)($_POST['division_id'] ?? 0);
    $district_id = (int)($_POST['district_id'] ?? 0);
    $upazila_id = (int)($_POST['upazila_id'] ?? 0);
    $area = clean_input($_POST['area'] ?? '');
    $area_type = clean_input($_POST['area_type'] ?? '');
    $residence_type = clean_input($_POST['residence_type'] ?? '');
    $post_office = clean_input($_POST['post_office'] ?? '');
    $postal_code = clean_input($_POST['postal_code'] ?? '');
    $permanent_hometown = clean_input($_POST['permanent_hometown'] ?? ($user['permanent_hometown'] ?? ''));

    $permanent_country = clean_input($_POST['permanent_country'] ?? 'Bangladesh');
    $permanent_division_id = (int)($_POST['permanent_division_id'] ?? 0);
    $permanent_district_id = (int)($_POST['permanent_district_id'] ?? 0);
    $permanent_upazila_id = (int)($_POST['permanent_upazila_id'] ?? 0);
    $permanent_area = clean_input($_POST['permanent_area'] ?? '');
    $permanent_area_type = clean_input($_POST['permanent_area_type'] ?? '');
    $permanent_residence_type = clean_input($_POST['permanent_residence_type'] ?? '');
    $permanent_post_office = clean_input($_POST['permanent_post_office'] ?? '');
    $permanent_postal_code = clean_input($_POST['permanent_postal_code'] ?? '');

    if ($same_address === 'Yes') {
        $permanent_country = $country; $permanent_division_id = $division_id; $permanent_district_id = $district_id; $permanent_upazila_id = $upazila_id;
        $permanent_area = $area; $permanent_area_type = $area_type; $permanent_residence_type = $residence_type; $permanent_post_office = $post_office; $permanent_postal_code = $postal_code;
    }
    $valid_area_type = in_array($area_type, $area_type_options, true);
    $valid_residence_type = ($residence_type === '' || in_array($residence_type, $residence_type_options, true));
    $permanent_valid_area_type = ($same_address === 'Yes') || in_array($permanent_area_type, $area_type_options, true);
    $permanent_valid_residence_type = ($same_address === 'Yes') || $permanent_residence_type === '' || in_array($permanent_residence_type, $residence_type_options, true);

    $check_location = function($division, $district, $upazila) use ($conn) {
        if ($division <= 0 || $district <= 0 || $upazila <= 0) return false;
        $q = mysqli_prepare($conn, 'SELECT d.id FROM districts d INNER JOIN upazilas u ON u.district_id=d.id WHERE d.id=? AND d.division_id=? AND u.id=? LIMIT 1');
        mysqli_stmt_bind_param($q, 'iii', $district, $division, $upazila); mysqli_stmt_execute($q); $r = mysqli_stmt_get_result($q);
        return $r && mysqli_num_rows($r) === 1;
    };
    $current_location_valid = $check_location($division_id, $district_id, $upazila_id);
    $permanent_location_valid = ($same_address === 'Yes') || $check_location($permanent_division_id, $permanent_district_id, $permanent_upazila_id);

    if ($division_id <= 0 || $district_id <= 0 || $upazila_id <= 0 || $area === '' || !$valid_area_type) {
        $error = 'Please complete all required current address fields marked with *.';
    } elseif (!$valid_residence_type || !$permanent_valid_area_type || !$permanent_valid_residence_type) {
        $error = 'Please select a valid area or residence type.';
    } elseif (!$current_location_valid || !$permanent_location_valid) {
        $error = 'Please select a valid Division, District and Upazila combination for each address.';
    } elseif ($same_address === 'No' && ($permanent_area === '' || $permanent_area_type === '')) {
        $error = 'Please complete the required permanent residential details.';
    } else {
        $update = mysqli_prepare($conn, 'UPDATE user_profiles SET country=?, division_id=?, district_id=?, upazila_id=?, area=?, area_type=?, residence_type=?, post_office=?, postal_code=?, permanent_hometown=?, permanent_country=?, permanent_division_id=?, permanent_district_id=?, permanent_upazila_id=?, permanent_area=?, permanent_area_type=?, permanent_residence_type=?, permanent_post_office=?, permanent_postal_code=? WHERE user_id=?');
        mysqli_stmt_bind_param($update, 'siiisssssssiiisssssi', $country, $division_id, $district_id, $upazila_id, $area, $area_type, $residence_type, $post_office, $postal_code, $permanent_hometown, $permanent_country, $permanent_division_id, $permanent_district_id, $permanent_upazila_id, $permanent_area, $permanent_area_type, $permanent_residence_type, $permanent_post_office, $permanent_postal_code, $user_id);
        if (mysqli_stmt_execute($update)) { header('Location: step5.php'); exit(); }
        $error = 'Unable to save Step 4 right now. Please try again.';
    }
    foreach (['country','division_id','district_id','upazila_id','area','area_type','residence_type','post_office','postal_code','permanent_hometown','permanent_country','permanent_division_id','permanent_district_id','permanent_upazila_id','permanent_area','permanent_area_type','permanent_residence_type','permanent_post_office','permanent_postal_code'] as $field) {
        if (isset($$field)) $user[$field] = $$field;
    }
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
                        <div class="section-heading"><span class="section-icon"><i class="bi bi-geo-alt"></i></span><div><h2>Current Address</h2><p>Enter the address where you currently live.</p></div></div>
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label" for="country">Country <span class="required">*</span></label><input type="text" id="country" name="country" class="form-control" value="<?= htmlspecialchars($user['country'] ?: 'Bangladesh') ?>" readonly></div>
                            <div class="col-md-4"><label class="form-label" for="division">Division <span class="required">*</span></label><select name="division_id" id="division" class="form-select location-select" required><option value="">Select division</option><?php $divisions = mysqli_query($conn, 'SELECT id, name_bn, name_en FROM divisions ORDER BY name_en'); while ($row = mysqli_fetch_assoc($divisions)): ?><option value="<?= (int)$row['id'] ?>" <?= ((int)($user['division_id'] ?? 0) === (int)$row['id']) ? 'selected' : '' ?>><?= htmlspecialchars($row['name_en']) ?></option><?php endwhile; ?></select></div>
                            <div class="col-md-4"><label class="form-label" for="district">District <span class="required">*</span></label><select name="district_id" id="district" class="form-select location-select" required disabled><option value="">Select district</option></select><small class="step4-field-hint">Districts load from the selected division.</small></div>
                            <div class="col-md-4"><label class="form-label" for="upazila">Upazila <span class="required">*</span></label><select name="upazila_id" id="upazila" class="form-select location-select" required disabled><option value="">Select upazila</option></select><small class="step4-field-hint">Upazilas load from the selected district.</small></div>
                        </div>
                    </section>
                    <section class="step4-section">
                        <div class="section-heading"><span class="section-icon"><i class="bi bi-house-heart"></i></span><div><h2>Current Residential Details</h2><p>More details about your current home area.</p></div></div>
                        <div class="row g-3">
                            <div class="col-lg-6"><label class="form-label" for="area">Area / Village <span class="required">*</span></label><input type="text" id="area" name="area" class="form-control" value="<?= htmlspecialchars($user['area'] ?? '') ?>" placeholder="Enter area or village" maxlength="120" autocomplete="address-level4" required></div>
                            <div class="col-lg-6"><label class="form-label" for="area_type">Area Type <span class="required">*</span></label><select name="area_type" id="area_type" class="form-select" required><option value="">Select area type</option><?php foreach ($area_type_options as $option): ?><option value="<?= htmlspecialchars($option) ?>" <?= (($user['area_type'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option><?php endforeach; ?></select></div>
                            <div class="col-lg-6"><label class="form-label" for="residence_type">Residence Type <span class="optional">(Optional)</span></label><select name="residence_type" id="residence_type" class="form-select"><option value="">Select residence type</option><?php foreach ($residence_type_options as $option): ?><option value="<?= htmlspecialchars($option) ?>" <?= (($user['residence_type'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-6"><label class="form-label" for="post_office">Post Office <span class="optional">(Optional)</span></label><input type="text" id="post_office" name="post_office" class="form-control" value="<?= htmlspecialchars($user['post_office'] ?? '') ?>" placeholder="Enter post office" maxlength="100"></div>
                            <div class="col-md-6"><label class="form-label" for="postal_code">Postal Code <span class="optional">(Optional)</span></label><input type="text" id="postal_code" name="postal_code" class="form-control" value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>" placeholder="Enter postal code" inputmode="numeric" maxlength="4"></div>
                        </div>
                    </section>
                    <section class="step4-section">
                        <div class="section-heading"><span class="section-icon"><i class="bi bi-check2-square"></i></span><div><h2>Permanent Address</h2><p>Is your permanent address the same as your current address?</p></div></div>
                        <div class="form-check mb-3"><input class="form-check-input" type="radio" name="same_address" id="same_address_yes" value="Yes" <?= $same_address === 'Yes' ? 'checked' : '' ?>><label class="form-check-label" for="same_address_yes">Yes, both addresses are the same</label></div>
                        <div class="form-check mb-4"><input class="form-check-input" type="radio" name="same_address" id="same_address_no" value="No" <?= $same_address === 'No' ? 'checked' : '' ?>><label class="form-check-label" for="same_address_no">No, my permanent address is different</label></div>
                        <div id="permanentAddressFields" <?= $same_address === 'Yes' ? 'hidden' : '' ?>>
                            <div class="row g-3">
                                <div class="col-12"><label class="form-label" for="permanent_country">Country <span class="required">*</span></label><input type="text" id="permanent_country" name="permanent_country" class="form-control" value="<?= htmlspecialchars($user['permanent_country'] ?: 'Bangladesh') ?>" readonly></div>
                                <div class="col-md-4"><label class="form-label" for="permanent_division">Division <span class="required">*</span></label><select name="permanent_division_id" id="permanent_division" class="form-select location-select"><option value="">Select division</option><?php $divisions = mysqli_query($conn, 'SELECT id, name_bn, name_en FROM divisions ORDER BY name_en'); while ($row = mysqli_fetch_assoc($divisions)): ?><option value="<?= (int)$row['id'] ?>" <?= ((int)($user['permanent_division_id'] ?? 0) === (int)$row['id']) ? 'selected' : '' ?>><?= htmlspecialchars($row['name_en']) ?></option><?php endwhile; ?></select></div>
                                <div class="col-md-4"><label class="form-label" for="permanent_district">District <span class="required">*</span></label><select name="permanent_district_id" id="permanent_district" class="form-select location-select" disabled><option value="">Select district</option></select></div>
                                <div class="col-md-4"><label class="form-label" for="permanent_upazila">Upazila <span class="required">*</span></label><select name="permanent_upazila_id" id="permanent_upazila" class="form-select location-select" disabled><option value="">Select upazila</option></select></div>
                                <div class="col-lg-6"><label class="form-label" for="permanent_area">Area / Village <span class="required">*</span></label><input type="text" id="permanent_area" name="permanent_area" class="form-control" value="<?= htmlspecialchars($user['permanent_area'] ?? '') ?>" placeholder="Enter permanent area or village" maxlength="120"></div>
                                <div class="col-lg-6"><label class="form-label" for="permanent_area_type">Area Type <span class="required">*</span></label><select name="permanent_area_type" id="permanent_area_type" class="form-select"><option value="">Select area type</option><?php foreach ($area_type_options as $option): ?><option value="<?= htmlspecialchars($option) ?>" <?= (($user['permanent_area_type'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option><?php endforeach; ?></select></div>
                                <div class="col-lg-6"><label class="form-label" for="permanent_residence_type">Residence Type <span class="optional">(Optional)</span></label><select name="permanent_residence_type" id="permanent_residence_type" class="form-select"><option value="">Select residence type</option><?php foreach ($residence_type_options as $option): ?><option value="<?= htmlspecialchars($option) ?>" <?= (($user['permanent_residence_type'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-6"><label class="form-label" for="permanent_post_office">Post Office <span class="optional">(Optional)</span></label><input type="text" id="permanent_post_office" name="permanent_post_office" class="form-control" value="<?= htmlspecialchars($user['permanent_post_office'] ?? '') ?>" placeholder="Enter post office" maxlength="100"></div>
                                <div class="col-md-6"><label class="form-label" for="permanent_postal_code">Postal Code <span class="optional">(Optional)</span></label><input type="text" id="permanent_postal_code" name="permanent_postal_code" class="form-control" value="<?= htmlspecialchars($user['permanent_postal_code'] ?? '') ?>" placeholder="Enter postal code" inputmode="numeric" maxlength="4"></div>
                                <div class="col-md-6"><label class="form-label" for="permanent_hometown">Permanent Home / Hometown <span class="optional">(Optional)</span></label><input type="text" id="permanent_hometown" name="permanent_hometown" class="form-control" value="<?= htmlspecialchars($user['permanent_hometown'] ?? '') ?>" placeholder="Enter permanent hometown" maxlength="120"></div>
                            </div>
                        </div>
                    </section>

                    <div class="step4-location-preview" id="currentLocationPreview" aria-live="polite">
                        <div class="step4-location-preview-icon"><i class="bi bi-pin-map-fill"></i></div>
                        <div class="step4-location-preview-content">
                            <span class="step4-location-preview-kicker">CURRENT LOCATION PREVIEW</span>
                            <strong id="currentLocationPreviewText">Select your Division, District and Upazila to preview your current location.</strong>
                        </div>
                    </div>
                    <div class="step4-location-preview" id="permanentLocationPreview" aria-live="polite">
                        <div class="step4-location-preview-icon"><i class="bi bi-house-check-fill"></i></div>
                        <div class="step4-location-preview-content">
                            <span class="step4-location-preview-kicker">PERMANENT LOCATION PREVIEW</span>
                            <strong id="permanentLocationPreviewText">Select your Division, District and Upazila to preview your permanent location.</strong>
                        </div>
                    </div>


                    <div class="step4-actions">
                        <a href="../profile/view_profile.php?user_id=<?= $user_id ?>" class="btn step4-back">
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
    const postalIds = ['postal_code', 'permanent_postal_code'];
    const sameYes = document.getElementById('same_address_yes');
    const sameNo = document.getElementById('same_address_no');
    const permanentFields = document.getElementById('permanentAddressFields');
    const selected = {
        currentDistrict: <?= json_encode((string)($user['district_id'] ?? '')) ?>,
        currentUpazila: <?= json_encode((string)($user['upazila_id'] ?? '')) ?>,
        permanentDistrict: <?= json_encode((string)($user['permanent_district_id'] ?? '')) ?>,
        permanentUpazila: <?= json_encode((string)($user['permanent_upazila_id'] ?? '')) ?>
    };
    const existingLocation = <?= json_encode($existing_location_names, JSON_UNESCAPED_UNICODE) ?>;
    const existingPermanentLocation = <?= json_encode($existing_permanent_location_names, JSON_UNESCAPED_UNICODE) ?>;
    const currentPreview = document.getElementById('currentLocationPreviewText');
    const permanentPreview = document.getElementById('permanentLocationPreviewText');

    function togglePermanent() {
        const show = sameNo.checked;
        permanentFields.hidden = !show;
        permanentFields.querySelectorAll('select,input').forEach(el => {
            if (el.id !== 'permanent_country') el.disabled = !show;
            if (['permanent_division','permanent_area','permanent_area_type'].includes(el.id)) el.required = show;
        });
    }
    function resetSelect(el, placeholder) { el.innerHTML = '<option value="">' + placeholder + '</option>'; el.disabled = true; }
    function loadPair(prefix, districtValue = '', upazilaValue = '') {
        const division = document.getElementById(prefix + 'division');
        const district = document.getElementById(prefix + 'district');
        const upazila = document.getElementById(prefix + 'upazila');
        if (!division || !district || !upazila) return;
        resetSelect(district, 'Select district'); resetSelect(upazila, 'Select upazila');
        if (!division.value) return;
        fetch('../ajax/get_districts.php?division_id=' + encodeURIComponent(division.value)).then(r => { if (!r.ok) throw new Error(); return r.text(); }).then(html => {
            district.innerHTML = html; district.disabled = false;
            if (districtValue) { district.value = districtValue; if (district.value === districtValue) loadUpazila(prefix, upazilaValue); } updateLocationPreviews();
        }).catch(() => { district.innerHTML = '<option value="">Unable to load districts</option>'; district.disabled = false; });
    }
    function loadUpazila(prefix, selectedValue = '') {
        const district = document.getElementById(prefix + 'district'); const upazila = document.getElementById(prefix + 'upazila');
        resetSelect(upazila, 'Select upazila'); if (!district.value) return;
        fetch('../ajax/get_upazilas.php?district_id=' + encodeURIComponent(district.value)).then(r => { if (!r.ok) throw new Error(); return r.text(); }).then(html => { upazila.innerHTML = html; upazila.disabled = false; if (selectedValue) upazila.value = selectedValue; updateLocationPreviews(); }).catch(() => { upazila.innerHTML = '<option value="">Unable to load upazilas</option>'; upazila.disabled = false; });
    }
    function getLocationPreview(prefix, existingNames, isCurrent) {
        const d = document.getElementById(prefix + 'division');
        const ds = document.getElementById(prefix + 'district');
        const u = document.getElementById(prefix + 'upazila');
        const dt = d?.options[d.selectedIndex]?.textContent.trim() || '';
        const dst = ds?.options[ds.selectedIndex]?.textContent.trim() || '';
        const ut = u?.options[u.selectedIndex]?.textContent.trim() || '';
        if (d?.value && ds?.value && u?.value && dst && ut) return ut + ', ' + dst + ', ' + dt + ', Bangladesh';
        if (existingNames.division && existingNames.district && existingNames.upazila && !d?.dataset.changed) {
            return existingNames.upazila + ', ' + existingNames.district + ', ' + existingNames.division + ', Bangladesh';
        }
        if (d?.value) return 'Continue selecting District and Upazila to complete your ' + (isCurrent ? 'current' : 'permanent') + ' location preview.';
        return 'Select your Division, District and Upazila to preview your ' + (isCurrent ? 'current' : 'permanent') + ' location.';
    }
    function updateLocationPreviews() {
        const currentText = getLocationPreview('', existingLocation, true);
        currentPreview.textContent = currentText;
        permanentPreview.textContent = sameYes.checked
            ? currentText
            : getLocationPreview('permanent_', existingPermanentLocation, false);
    }
    [['','currentDistrict','currentUpazila'],['permanent_','permanentDistrict','permanentUpazila']].forEach(([prefix, districtKey, upazilaKey]) => {
        const d = document.getElementById(prefix + 'division'), ds = document.getElementById(prefix + 'district'), u = document.getElementById(prefix + 'upazila');
        d.addEventListener('change', () => { d.dataset.changed = '1'; loadPair(prefix); updateLocationPreviews(); });
        ds.addEventListener('change', () => { loadUpazila(prefix); updateLocationPreviews(); });
        u.addEventListener('change', updateLocationPreviews);
        if (d.value && (!prefix || sameNo.checked)) loadPair(prefix, selected[districtKey], selected[upazilaKey]);
    });
    [sameYes, sameNo].forEach(el => el.addEventListener('change', () => { togglePermanent(); if (sameNo.checked) loadPair('permanent_', selected.permanentDistrict, selected.permanentUpazila); updateLocationPreviews(); }));
    togglePermanent();
    form.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.matches('input[type="text"]')) e.preventDefault(); });
    form.addEventListener('submit', e => {
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        let first = null;
        form.querySelectorAll('[required]').forEach(el => { if (!el.disabled && !el.value.trim()) { el.classList.add('is-invalid'); if (!first) first = el; } });
        postalIds.forEach(id => { const el = document.getElementById(id); if (el && !el.disabled && el.value && !/^\d{4}$/.test(el.value.trim())) { el.classList.add('is-invalid'); if (!first) first = el; } });
        if (first) { e.preventDefault(); first.focus(); }
    });
    postalIds.forEach(id => { const el = document.getElementById(id); if (el) el.addEventListener('input', function () { this.value = this.value.replace(/\D/g, '').slice(0, 4); this.classList.remove('is-invalid'); }); });
    updateLocationPreviews();
})();
</script>

<?php include '../includes/footer.php'; ?>
