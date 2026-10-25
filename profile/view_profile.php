<?php

$page_css = 'assets/css/profile-view.css';

require_once '../config/db.php';
require_once '../includes/profile_completion.php';
require_once '../includes/language.php';
require_once '../includes/profile_value_translator.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$viewer_id = (int) $_SESSION['user_id'];
$matching_csrf = $_SESSION['matching_csrf'] ?? bin2hex(random_bytes(32));
$_SESSION['matching_csrf'] = $matching_csrf;
$privacy_csrf = $_SESSION['privacy_csrf'] ?? bin2hex(random_bytes(32));
$_SESSION['privacy_csrf'] = $privacy_csrf;
$profile_user_id = (int) ($_GET['user_id'] ?? 0);
$is_own_profile = $profile_user_id === $viewer_id;

$return_url = trim($_GET['return_url'] ?? '');
$return_path = parse_url($return_url, PHP_URL_PATH);
$return_basename = $return_path ? basename($return_path) : '';
if ($return_basename !== 'dashboard.php') {
    $return_url = BASE_URL . 'dashboard.php#partner-search';
} elseif (!preg_match('~^https?://~i', $return_url)) {
    // Relative dashboard URLs from /profile/ must point one level up.
    $query = parse_url($return_url, PHP_URL_QUERY);
    $fragment = parse_url($return_url, PHP_URL_FRAGMENT);
    $return_url = BASE_URL . 'dashboard.php'
        . ($query ? '?' . $query : '')
        . ($fragment ? '#' . $fragment : '#partner-search');
}

if ($profile_user_id <= 0) {
    header('Location: ../dashboard.php');
    exit;
}

$stmt = mysqli_prepare($conn, "
    SELECT
        u.user_id,
        u.email,
        u.mobile,
        up.profile_id,
        u.first_name,
        u.last_name,
        up.gender,
        up.date_of_birth,
        up.nid_number,
        up.religion,
        up.madhhab,
        up.marital_status,
        up.bio,
        up.biodata_created_by,
        up.guardian_consent,
        up.photo,
        up.height_cm,
        up.weight_kg,
        up.complexion,
        up.country,
        up.division_id,
        up.district_id,
        up.upazila_id,
        up.permanent_country,
        up.permanent_division_id,
        up.permanent_district_id,
        up.permanent_upazila_id,
        up.permanent_area,
        up.permanent_area_type,
        up.permanent_residence_type,
        up.permanent_post_office,
        up.permanent_postal_code,
        up.area,
        up.highest_education,
        up.profession,
        up.occupation_details,
        up.monthly_income,
        up.father_name,
        up.father_profession,
        up.father_alive,
        up.mother_name,
        up.mother_profession,
        up.mother_alive,
        up.parents_details,
        up.brothers_details,
        up.sisters_details,
        up.paternal_uncles_details,
        up.paternal_aunts_details,
        up.guardian_name,
        up.guardian_relation,
        up.guardian_contact,
        up.family_type,
        up.family_status,
        up.siblings,
        up.brothers_count,
        up.sisters_count,
        up.paternal_uncles_count,
        up.paternal_aunts_count,
        up.paternal_siblings_married,
        up.family_purdah_environment,
        up.family_religious_lifestyle,
        up.living_with_family,
        up.area_type,
        up.residence_type,
        up.post_office,
        up.postal_code,
        up.permanent_hometown,
        up.address_details,
        up.smoking_status,
        up.prayer_status,
        up.beard_status,
        up.hijab_details,
        up.quran_reading,
        up.fasting_status,
        up.religious_practice,
        up.islamic_knowledge,
        up.halal_lifestyle,
        up.islamic_activities,
        up.favorite_scholars,
        up.favorite_books,
        up.religious_knowledge_sources,
        up.bidah_shrine_stance,
        up.tea_coffee,
        up.diet_preference,
        up.sleep_pattern,
        up.personality_type,
        up.social_nature,
        up.travel_interest,
        up.spending_style,
        up.pets,
        up.free_time_interests,
        up.hijab_status,
        up.mahram_maintained,
        up.verification_status,
        up.profile_visibility,
        up.photo_visibility,
        d.name_en AS district_name,
        d.name_bn AS district_name_bn,
        dv.name_en AS division_name,
        dv.name_bn AS division_name_bn,
        uz.name_en AS upazila_name,
        uz.name_bn AS upazila_name_bn,
        pd.name_en AS permanent_district_name,
        pd.name_bn AS permanent_district_name_bn,
        pdv.name_en AS permanent_division_name,
        pdv.name_bn AS permanent_division_name_bn,
        puz.name_en AS permanent_upazila_name,
        puz.name_bn AS permanent_upazila_name_bn
    FROM users u
    INNER JOIN user_profiles up ON up.user_id = u.user_id
    LEFT JOIN divisions dv ON dv.id = up.division_id
    LEFT JOIN districts d ON d.id = up.district_id
    LEFT JOIN upazilas uz ON uz.id = up.upazila_id
    LEFT JOIN divisions pdv ON pdv.id = up.permanent_division_id
    LEFT JOIN districts pd ON pd.id = up.permanent_district_id
    LEFT JOIN upazilas puz ON puz.id = up.permanent_upazila_id
    WHERE u.user_id = ?
      AND u.account_status = 'Active'
      AND (up.profile_visibility = 'Public' OR u.user_id = ?)
    LIMIT 1
");

mysqli_stmt_bind_param($stmt, 'ii', $profile_user_id, $viewer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$profile = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$profile) {
    header('Location: ' . $return_url . (strpos($return_url, '?') === false ? '?' : '&') . 'profile=not-found#partner-search');
    exit;
}

// Public health information for profile view. Medication/notes are intentionally visible per profile-view requirements.
$health_profile = null;
$health_stmt = mysqli_prepare($conn, "SELECT blood_group, height_cm, weight_kg, allergies, disability_status, current_medications, medical_notes FROM health_profiles WHERE user_id=? LIMIT 1");
mysqli_stmt_bind_param($health_stmt, 'i', $profile_user_id);
mysqli_stmt_execute($health_stmt);
$health_profile = mysqli_fetch_assoc(mysqli_stmt_get_result($health_stmt)) ?: null;
mysqli_stmt_close($health_stmt);

$health_conditions = [];
$health_condition_stmt = mysqli_prepare($conn, "
    SELECT hc.condition_name, uhc.status, uhc.severity, uhc.treatment_status, uhc.diagnosed_date, uhc.remarks
    FROM user_health_conditions uhc
    INNER JOIN health_conditions hc ON hc.condition_id = uhc.condition_id
    WHERE uhc.user_id=?
    ORDER BY hc.condition_name ASC
");
mysqli_stmt_bind_param($health_condition_stmt, 'i', $profile_user_id);
mysqli_stmt_execute($health_condition_stmt);
$health_condition_result = mysqli_stmt_get_result($health_condition_stmt);
while ($condition_row = mysqli_fetch_assoc($health_condition_result)) {
    $health_conditions[] = $condition_row;
}
mysqli_stmt_close($health_condition_stmt);

$trait_answers = [];
$trait_stmt = mysqli_prepare($conn, "
    SELECT tq.question_id, tq.question_text, tq.gender, uta.answer
    FROM user_trait_answers uta
    INNER JOIN trait_questions tq ON tq.question_id = uta.question_id
    WHERE uta.user_id = ?
      AND tq.is_active = 1
      AND TRIM(uta.answer) <> ''
    ORDER BY tq.display_order ASC, tq.question_id ASC
");
if ($trait_stmt) {
    mysqli_stmt_bind_param($trait_stmt, 'i', $profile_user_id);
    mysqli_stmt_execute($trait_stmt);
    $trait_result_set = mysqli_stmt_get_result($trait_stmt);
    while ($trait_row = mysqli_fetch_assoc($trait_result_set)) {
        $trait_answers[] = $trait_row;
    }
    mysqli_stmt_close($trait_stmt);
}


/* Format partner height preferences from stored centimetres to feet/inches. */
function format_height_feet_inches($cm): string
{
    if ($cm === null || $cm === '' || !is_numeric($cm)) {
        return 'Any';
    }

    $inches = (float) $cm / 2.54;
    $feet = (int) floor($inches / 12);
    $remaining_inches = (int) round($inches - ($feet * 12));

    if ($remaining_inches === 12) {
        $feet++;
        $remaining_inches = 0;
    }

    return $feet . " ft " . $remaining_inches . " in";
}

/* Part 8: load the member's partner preferences from Step 6. */
$partner_preferences = null;
$partner_pref_stmt = mysqli_prepare($conn, "
    SELECT
        sp.*,
        dv.name_en AS preferred_division_name,
        dv.name_bn AS preferred_division_name_bn,
        d.name_en AS preferred_district_name,
        d.name_bn AS preferred_district_name_bn,
        uz.name_en AS preferred_upazila_name,
        uz.name_bn AS preferred_upazila_name_bn
    FROM search_preferences sp
    LEFT JOIN divisions dv ON dv.id = sp.division_id
    LEFT JOIN districts d ON d.id = sp.district_id
    LEFT JOIN upazilas uz ON uz.id = sp.upazila_id
    WHERE sp.user_id=?
    LIMIT 1
");
if ($partner_pref_stmt) {
    mysqli_stmt_bind_param($partner_pref_stmt, 'i', $profile_user_id);
    mysqli_stmt_execute($partner_pref_stmt);
    $partner_preferences = mysqli_fetch_assoc(mysqli_stmt_get_result($partner_pref_stmt)) ?: null;
    mysqli_stmt_close($partner_pref_stmt);
}

$partner_trait_preferences = [];
$partner_trait_stmt = mysqli_prepare($conn, "
    SELECT ptp.question_id, tq.question_text, tq.gender, ptp.preferred_answer
    FROM partner_trait_preferences ptp
    INNER JOIN trait_questions tq ON tq.question_id = ptp.question_id
    WHERE ptp.user_id=?
      AND tq.is_active=1
      AND TRIM(ptp.preferred_answer) <> ''
    ORDER BY tq.display_order ASC, tq.question_id ASC
");
if ($partner_trait_stmt) {
    mysqli_stmt_bind_param($partner_trait_stmt, 'i', $profile_user_id);
    mysqli_stmt_execute($partner_trait_stmt);
    $partner_trait_result = mysqli_stmt_get_result($partner_trait_stmt);
    while ($partner_trait_row = mysqli_fetch_assoc($partner_trait_result)) {
        $partner_trait_preferences[] = $partner_trait_row;
    }
    mysqli_stmt_close($partner_trait_stmt);
}


/* Part 7: load active profile media and keep the same visibility rules as media.php. */
$profile_media = [];
$privacy_voice_visibility = 'Verified Users';
$privacy_video_visibility = 'Verified Users';
$viewer_is_verified = false;

$interaction_stmt = mysqli_prepare($conn, "SELECT match_id, status, relationship_active, sender_user_id FROM matches WHERE ((sender_user_id=? AND receiver_user_id=?) OR (sender_user_id=? AND receiver_user_id=?)) ORDER BY match_id DESC LIMIT 1");
mysqli_stmt_bind_param($interaction_stmt, 'iiii', $viewer_id, $profile_user_id, $profile_user_id, $viewer_id);
mysqli_stmt_execute($interaction_stmt);
$interaction = mysqli_fetch_assoc(mysqli_stmt_get_result($interaction_stmt));
mysqli_stmt_close($interaction_stmt);
$interaction_status = $interaction['status'] ?? '';
$interaction_id = (int) ($interaction['match_id'] ?? 0);
$interaction_direction = $interaction ? ((int)$interaction['sender_user_id'] === $viewer_id ? 'sent' : 'received') : '';
$is_accepted_match = $interaction
    && ($interaction['status'] ?? '') === 'Accepted'
    && (int) ($interaction['relationship_active'] ?? 0) === 1;

$viewer_verify_stmt = mysqli_prepare($conn, "
    SELECT verification_status
    FROM user_profiles
    WHERE user_id=?
    LIMIT 1
");
if ($viewer_verify_stmt) {
    mysqli_stmt_bind_param($viewer_verify_stmt, 'i', $viewer_id);
    mysqli_stmt_execute($viewer_verify_stmt);
    $viewer_verify_row = mysqli_fetch_assoc(mysqli_stmt_get_result($viewer_verify_stmt));
    mysqli_stmt_close($viewer_verify_stmt);
    $viewer_is_verified = ($viewer_verify_row['verification_status'] ?? '') === 'Verified';
}

$media_stmt = mysqli_prepare($conn, "
    SELECT media_id, media_type, duration_seconds, visibility, status, created_at
    FROM profile_media
    WHERE user_id=?
      AND status='Active'
    ORDER BY FIELD(media_type, 'Profile Photo', 'Voice Introduction', 'Video Introduction'), created_at DESC, media_id DESC
");
if ($media_stmt) {
    mysqli_stmt_bind_param($media_stmt, 'i', $profile_user_id);
    mysqli_stmt_execute($media_stmt);
    $media_result = mysqli_stmt_get_result($media_stmt);
    while ($media_row = mysqli_fetch_assoc($media_result)) {
        $media_visibility = (string) ($media_row['visibility'] ?? 'Private');
        if ($is_own_profile && $media_row['media_type'] === 'Voice Introduction') {
            $privacy_voice_visibility = $media_visibility === 'Private' ? 'Hidden' : $media_visibility;
        } elseif ($is_own_profile && $media_row['media_type'] === 'Video Introduction') {
            $privacy_video_visibility = $media_visibility === 'Private' ? 'Hidden' : $media_visibility;
        }
        $media_allowed = $is_own_profile;

        if (!$media_allowed && ($profile['profile_visibility'] ?? '') === 'Public') {
            if ($media_visibility === 'Everyone') {
                $media_allowed = true;
            } elseif ($media_visibility === 'Verified Users') {
                $media_allowed = $viewer_is_verified;
            } elseif ($media_visibility === 'Matched Users') {
                $media_allowed = $is_accepted_match;
            }
        }

        if ($media_allowed) {
            $profile_media[] = $media_row;
        }
    }
    mysqli_stmt_close($media_stmt);
}

$viewer_stmt = mysqli_prepare($conn, "SELECT gender FROM users WHERE user_id=? LIMIT 1");
mysqli_stmt_bind_param($viewer_stmt, 'i', $viewer_id);
mysqli_stmt_execute($viewer_stmt);
$viewer_row = mysqli_fetch_assoc(mysqli_stmt_get_result($viewer_stmt));
mysqli_stmt_close($viewer_stmt);
$viewer_gender = $viewer_row['gender'] ?? '';
$profile_public_id = 'SM-' . str_pad((string) $profile_user_id, 6, '0', STR_PAD_LEFT);
$can_send_interest = in_array($viewer_gender, ['Male', 'Female'], true)
    && in_array(($profile['gender'] ?? ''), ['Male', 'Female'], true)
    && $viewer_gender !== ($profile['gender'] ?? '');

$bookmark_stmt = mysqli_prepare($conn, "SELECT 1 FROM bookmarks WHERE user_id=? AND bookmarked_user_id=? LIMIT 1");
mysqli_stmt_bind_param($bookmark_stmt, 'ii', $viewer_id, $profile_user_id);
mysqli_stmt_execute($bookmark_stmt);
$is_bookmarked = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($bookmark_stmt));
mysqli_stmt_close($bookmark_stmt);


$name = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
$name = $name !== '' ? $name : 'Smart Matrimony Member';

$age = null;
if (!empty($profile['date_of_birth'])) {
    try {
        $age = (new DateTime($profile['date_of_birth']))->diff(new DateTime('today'))->y;
    } catch (Exception $e) {
        $age = null;
    }
}

$verification_status = $profile['verification_status'] ?? 'Pending';
if (!in_array($verification_status, ['Verified', 'Pending', 'Rejected'], true)) {
    $verification_status = 'Pending';
}

$verification_badges = [
    'Verified' => [
        'class' => 'verification-verified',
        'icon' => 'fa-circle-check',
        'label' => 'Verified',
    ],
    'Pending' => [
        'class' => 'verification-pending',
        'icon' => 'fa-clock',
        'label' => 'Pending',
    ],
    'Rejected' => [
        'class' => 'verification-rejected',
        'icon' => 'fa-circle-xmark',
        'label' => 'Rejected',
    ],
];

$verification_badge = $verification_badges[$verification_status];
$is_verified_profile = $verification_status === 'Verified';

$can_show_photo = !empty($profile['photo']) && (
    $is_own_profile
    || ($profile['photo_visibility'] ?? '') === 'Everyone'
    || (($profile['photo_visibility'] ?? '') === 'Matched Users' && $is_accepted_match)
    || (($profile['photo_visibility'] ?? '') === 'Verified Users' && $is_verified_profile)
);

$profile_completion = sm_get_profile_completion($conn, $profile_user_id);
$profile_location_parts = array_values(array_filter([
    $profile['upazila_name'] ?? '',
    $profile['district_name'] ?? '',
    $profile['division_name'] ?? ''
], static fn($value) => trim((string) $value) !== ''));
$profile_location = $profile_location_parts ? implode(', ', $profile_location_parts) : '';
$profile_location_bn_parts = array_values(array_filter([
    $profile['upazila_name_bn'] ?? '',
    $profile['district_name_bn'] ?? '',
    $profile['division_name_bn'] ?? ''
], static fn($value) => trim((string) $value) !== ''));
$profile_location_bn = $profile_location_bn_parts ? implode(', ', $profile_location_bn_parts) : $profile_location;
$quick_info = [];
if (!empty($profile['gender'])) $quick_info[] = ['icon' => 'fa-venus-mars', 'label' => 'Gender', 'value' => $profile['gender']];
if (!empty($profile['religion'])) $quick_info[] = ['icon' => 'fa-mosque', 'label' => 'Religion', 'value' => $profile['religion']];
if (!empty($profile['marital_status'])) $quick_info[] = ['icon' => 'fa-heart', 'label' => 'Status', 'value' => $profile['marital_status']];
if ($profile_location !== '') $quick_info[] = ['icon' => 'fa-location-dot', 'label' => 'Location', 'value' => $profile_location];
if (!empty($profile['profession'])) $quick_info[] = ['icon' => 'fa-briefcase', 'label' => 'Profession', 'value' => $profile['profession']];
if (!empty($profile['highest_education'])) $quick_info[] = ['icon' => 'fa-graduation-cap', 'label' => 'Education', 'value' => $profile['highest_education']];

include '../includes/header.php';
?>

<div class="profile-view-page">
    <header class="profile-view-topbar">
        <div class="profile-view-container profile-view-nav">
            <a href="<?= BASE_URL; ?>dashboard.php" class="profile-view-brand" aria-label="Back to Smart Matrimony Dashboard">
                <span class="profile-view-brand-logo">
                    <img src="<?= BASE_URL; ?>assets/images/logo/logo.png" alt="Smart Matrimony Logo">
                </span>
                <span class="profile-view-brand-title">
                    <img src="<?= BASE_URL; ?>assets/images/logo/matrimony_title.png" alt="Smart Matrimony">
                </span>
            </a>

            <a href="<?= htmlspecialchars($return_url); ?>" class="back-dashboard">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>
    </header>

    <main class="profile-view-container profile-view-main">
        <section class="profile-view-hero-card">
            <div class="profile-view-photo-wrap">
                <div class="profile-view-photo">
                    <?php if ($can_show_photo): ?>
                        <img src="<?= BASE_URL; ?>uploads/profile/<?= htmlspecialchars($profile['photo']); ?>" alt="Profile photo of <?= htmlspecialchars($name); ?>">
                    <?php else: ?>
                        <div class="private-photo-large">
                            <i class="fa-solid fa-lock"></i>
                            <span>Photo private</span>
                        </div>
                    <?php endif; ?>
                </div>
                <span class="profile-photo-badge <?= htmlspecialchars($verification_badge['class']); ?>">
                    <i class="fa-solid <?= htmlspecialchars($verification_badge['icon']); ?>"></i>
                    <span class="profile-photo-badge-label"><?= htmlspecialchars($verification_badge['label']); ?></span><span class="profile-photo-badge-suffix">Profile</span>
                </span>
            </div>

            <div class="profile-view-intro">
                <div class="profile-view-header-top">
                    <span class="profile-view-id"><i class="fa-solid fa-id-card"></i> <?= htmlspecialchars($profile_public_id); ?></span>
                    <span class="profile-verified <?= htmlspecialchars($verification_badge['class']); ?>">
                        <i class="fa-solid <?= htmlspecialchars($verification_badge['icon']); ?>"></i>
                        <?= htmlspecialchars($verification_badge['label']); ?>
                    </span>
                </div>

                <div class="profile-view-title-row">
                    <div>
                        <span class="profile-view-kicker">MATRIMONY PROFILE</span>
                        <h1><?= htmlspecialchars($name); ?></h1>
                        <p class="profile-header-summary">
                            <?= $age !== null ? (int) $age . ' years old' : 'Age not provided'; ?>
                            <?php if (!empty($profile['religion'])): ?> · <?= htmlspecialchars($profile['religion']); ?><?php endif; ?>
                            <?php if (!empty($profile['marital_status'])): ?> · <?= htmlspecialchars($profile['marital_status']); ?><?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="profile-completion-mini">
                    <div class="profile-completion-mini-head">
                        <span><i class="fa-solid fa-chart-simple"></i> Profile Completion</span>
                        <strong><?= (int) $profile_completion['percentage']; ?>%</strong>
                    </div>
                    <div class="profile-completion-track" aria-label="Profile completion <?= (int) $profile_completion['percentage']; ?> percent">
                        <span style="width: <?= (int) $profile_completion['percentage']; ?>%"></span>
                    </div>
                    <small><?= (int) $profile_completion['completed_steps']; ?> of <?= (int) $profile_completion['total_steps']; ?> profile steps completed</small>
                </div>

                <?php if ($quick_info): ?>
                    <div class="profile-quick-info">
                        <?php foreach ($quick_info as $item): ?>
                            <div class="profile-quick-item">
                                <span class="profile-quick-icon"><i class="fa-solid <?= htmlspecialchars($item['icon']); ?>"></i></span>
                                <span class="profile-quick-copy">
                                    <small><?= htmlspecialchars($item['label']); ?></small>
                                    <strong<?= $item['label'] === 'Location' ? ' data-location-en="' . htmlspecialchars($profile_location, ENT_QUOTES) . '" data-location-bn="' . htmlspecialchars($profile_location_bn, ENT_QUOTES) . '"' : ''; ?>><?= htmlspecialchars($item['value']); ?></strong>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="profile-view-actions">
                    <a class="profile-action secondary" href="<?= BASE_URL; ?>profile/download_biodata.php?user_id=<?= $profile_user_id; ?>">
                        <i class="fa-solid fa-file-pdf"></i> Download Biodata PDF
                    </a>
                    <?php if ($is_own_profile): ?>
                        <a class="profile-action primary" href="<?= BASE_URL; ?>profile/create_profile.php">
                            <i class="fa-solid fa-user-pen"></i> Edit My Profile
                        </a>
                        <button type="button" class="profile-action privacy-control-trigger" id="privacyControlOpen">
                            <i class="fa-solid fa-shield-halved"></i> Privacy Control Center
                        </button>
                    <?php else: ?>
                        <form method="post" action="<?= BASE_URL; ?>matching/action.php" class="profile-bookmark-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($matching_csrf); ?>">
                            <input type="hidden" name="user_id" value="<?= $profile_user_id; ?>">
                            <input type="hidden" name="return_to" value="profile">
                            <button type="submit" name="action" value="<?= $is_bookmarked ? 'bookmark_remove' : 'bookmark_add'; ?>" class="profile-action secondary <?= $is_bookmarked ? 'bookmarked' : ''; ?>" id="profileBookmarkButton">
                                <i class="fa-<?= $is_bookmarked ? 'solid' : 'regular'; ?> fa-bookmark"></i> <span class="profile-bookmark-label"><?= $is_bookmarked ? 'Bookmarked' : 'Bookmark'; ?></span>
                            </button>
                        </form>
                        <?php if ($interaction_status === 'Accepted' && $interaction_id > 0): ?>
                            <a class="profile-action primary" href="<?= BASE_URL; ?>matching/my_matches.php"><i class="fa-solid fa-heart-circle-check"></i> Matched</a>
                        <?php elseif ($interaction_status === 'Pending' && $interaction_direction === 'sent'): ?>
                            <a class="profile-action state-pending" href="<?= BASE_URL; ?>matching/my_matches.php"><i class="fa-solid fa-clock"></i> Interest Sent</a>
                        <?php elseif ($interaction_status === 'Pending' && $interaction_direction === 'received'): ?>
                            <a class="profile-action state-received" href="<?= BASE_URL; ?>matching/my_matches.php"><i class="fa-solid fa-envelope"></i> Respond</a>
                        <?php elseif ($can_send_interest): ?>
                            <a class="profile-action primary" href="<?= BASE_URL; ?>matching/send_interest.php?user_id=<?= $profile_user_id; ?>&amp;return_url=<?= urlencode($return_url); ?>"><i class="fa-solid fa-heart"></i> Send Interest</a>
                        <?php else: ?>
                            <span class="profile-action unavailable"><i class="fa-solid fa-ban"></i> Interest Unavailable</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>


        <script>
        (function () {
            // Profile bookmark only: keep the existing matching/action.php backend.
            // Handle the button click directly and send the intended action explicitly.
            const profileBookmarkForm = document.querySelector('form.profile-bookmark-form');
            const profileBookmarkButton = document.querySelector('#profileBookmarkButton');

            if (!profileBookmarkForm || !profileBookmarkButton) return;

            profileBookmarkButton.addEventListener('click', function (event) {
                event.preventDefault();

                if (profileBookmarkForm.dataset.bookmarkBusy === '1') return;

                const action = profileBookmarkButton.value;
                if (action !== 'bookmark_add' && action !== 'bookmark_remove') return;

                const wasBookmarked = profileBookmarkButton.classList.contains('bookmarked');
                const icon = profileBookmarkButton.querySelector('i');
                const label = profileBookmarkButton.querySelector('.profile-bookmark-label');
                const nextBookmarked = action === 'bookmark_add';

                profileBookmarkForm.dataset.bookmarkBusy = '1';
                profileBookmarkButton.disabled = true;

                // Instant visual state; server remains authoritative.
                profileBookmarkButton.classList.toggle('bookmarked', nextBookmarked);
                profileBookmarkButton.value = nextBookmarked ? 'bookmark_remove' : 'bookmark_add';
                if (icon) {
                    icon.classList.toggle('fa-solid', nextBookmarked);
                    icon.classList.toggle('fa-regular', !nextBookmarked);
                }
                if (label) label.textContent = nextBookmarked ? 'Bookmarked' : 'Bookmark';

                const requestData = new URLSearchParams();
                requestData.set('csrf_token', profileBookmarkForm.querySelector('input[name="csrf_token"]').value);
                requestData.set('user_id', profileBookmarkForm.querySelector('input[name="user_id"]').value);
                requestData.set('return_to', 'profile');
                requestData.set('action', action);

                fetch(profileBookmarkForm.getAttribute('action'), {
                    method: 'POST',
                    body: requestData,
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(function (response) {
                    return response.text().then(function (text) {
                        let data = null;
                        try { data = JSON.parse(text); } catch (e) {}
                        if (!response.ok || !data) {
                            throw new Error('Bookmark request failed.');
                        }
                        return data;
                    });
                })
                .then(function (data) {
                    if (!data.success) throw new Error(data.message || 'Bookmark request failed.');

                    const finalBookmarked = typeof data.bookmarked === 'boolean' ? data.bookmarked : nextBookmarked;
                    profileBookmarkButton.classList.toggle('bookmarked', finalBookmarked);
                    profileBookmarkButton.value = finalBookmarked ? 'bookmark_remove' : 'bookmark_add';
                    if (icon) {
                        icon.classList.toggle('fa-solid', finalBookmarked);
                        icon.classList.toggle('fa-regular', !finalBookmarked);
                    }
                    if (label) label.textContent = finalBookmarked ? 'Bookmarked' : 'Bookmark';
                })
                .catch(function () {
                    // Restore the exact state that existed before the click.
                    profileBookmarkButton.classList.toggle('bookmarked', wasBookmarked);
                    profileBookmarkButton.value = wasBookmarked ? 'bookmark_remove' : 'bookmark_add';
                    if (icon) {
                        icon.classList.toggle('fa-solid', wasBookmarked);
                        icon.classList.toggle('fa-regular', !wasBookmarked);
                    }
                    if (label) label.textContent = wasBookmarked ? 'Bookmarked' : 'Bookmark';
                })
                .finally(function () {
                    profileBookmarkForm.dataset.bookmarkBusy = '0';
                    profileBookmarkButton.disabled = false;
                });
            });
        }());
        </script>

        <div class="profile-detail-grid profile-part3-grid">
            <section class="profile-detail-card profile-part3-card">
                <div class="detail-card-heading"><i class="fa-solid fa-user"></i><div><h2>Personal Information</h2><small>Basic identity &amp; background</small></div></div>
                <div class="detail-list">
                    <div><span>Gender</span><strong><?= htmlspecialchars($profile['gender'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Age</span><strong><?= $age !== null ? (int)$age . ' years' : 'Not provided'; ?></strong></div>
                    <div><span>NID / Birth Certificate No</span><strong><?= htmlspecialchars($profile['nid_number'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Marital Status</span><strong><?= htmlspecialchars($profile['marital_status'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Education</span><strong><?= htmlspecialchars($profile['highest_education'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Profession</span><strong><?= htmlspecialchars($profile['profession'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Occupation Details</span><strong><?= htmlspecialchars($profile['occupation_details'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Monthly Income</span><strong><?= trim((string)($profile['monthly_income'] ?? '')) !== '' && is_numeric($profile['monthly_income']) ? htmlspecialchars(number_format((float)$profile['monthly_income'], 0) . ' / month') : 'Not provided'; ?></strong></div>
                    <div><span>Biodata Created By</span><strong><?= htmlspecialchars(trim((string)($profile['biodata_created_by'] ?? '')) !== '' ? $profile['biodata_created_by'] : 'Not provided'); ?></strong></div>
                    <?php if (($profile['biodata_created_by'] ?? '') === 'Self'): ?>
                        <div><span>Guardian Consent for Biodata Creation</span><strong><?= array_key_exists('guardian_consent', $profile) && $profile['guardian_consent'] !== null ? ((int)$profile['guardian_consent'] === 1 ? 'Yes' : 'No') : 'Not provided'; ?></strong></div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="profile-detail-card profile-part3-card">
                <div class="detail-card-heading"><i class="fa-solid fa-ruler-combined"></i><div><h2>Physical &amp; Health Information</h2><small>Appearance &amp; personal details</small></div></div>
                <div class="detail-list">
                    <div><span>Height</span><strong><?php
                        $height_display = 'Not provided';
                        if (!empty($profile['height_cm']) && is_numeric($profile['height_cm'])) {
                            $total_inches = (float)$profile['height_cm'] / 2.54;
                            $feet = (int)floor($total_inches / 12);
                            $inches = (int)round($total_inches - ($feet * 12));
                            if ($inches >= 12) {
                                $feet++;
                                $inches = 0;
                            }
                            $height_display = $feet . ' ft ' . $inches . ' inch';
                        }
                        ?><?= htmlspecialchars($height_display); ?></strong></div>
                    <div><span>Weight</span><strong><?= !empty($profile['weight_kg']) ? htmlspecialchars($profile['weight_kg']) . ' kg' : 'Not provided'; ?></strong></div>
                    <div><span>Complexion</span><strong><?= htmlspecialchars($profile['complexion'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Smoking</span><strong><?= htmlspecialchars($profile['smoking_status'] ?? 'Not provided'); ?></strong></div>
                    <?php if (($profile['gender'] ?? '') === 'Male'): ?>
                        <div><span>Beard</span><strong><?= htmlspecialchars($profile['beard_status'] ?? 'Not provided'); ?></strong></div>
                    <?php endif; ?>
                </div>

                <?php if ($health_profile): ?>
                    <div class="health-summary-grid">
                        <div><span>Blood Group</span><strong><?= htmlspecialchars($health_profile['blood_group'] ?? 'Not provided'); ?></strong></div>
                        <div><span>Disability</span><strong><?= htmlspecialchars($health_profile['disability_status'] ?? 'Not provided'); ?></strong></div>
                        <div><span>Allergies</span><strong><?= htmlspecialchars($health_profile['allergies'] ?? 'None / Not provided'); ?></strong></div>
                        <div><span>Current Medications</span><strong><?= htmlspecialchars($health_profile['current_medications'] ?? 'None / Not provided'); ?></strong></div>
                        <div class="health-wide"><span>Medical Notes</span><strong><?= htmlspecialchars($health_profile['medical_notes'] ?? 'Not provided'); ?></strong></div>
                    </div>
                <?php else: ?>
                    <div class="profile-empty-state"><i class="fa-regular fa-circle-info"></i><span>No health profile information provided.</span></div>
                <?php endif; ?>

                <?php if ($health_conditions): ?>
                    <div class="health-conditions">
                        <div class="health-subheading"><i class="fa-solid fa-notes-medical"></i> Health Conditions</div>
                        <div class="health-condition-list">
                            <?php foreach ($health_conditions as $condition): ?>
                                <div class="health-condition-item">
                                    <div class="health-condition-main">
                                        <strong><?= htmlspecialchars($condition['condition_name']); ?></strong>
                                        <span><?= htmlspecialchars($condition['status'] ?? 'Unknown'); ?></span>
                                    </div>
                                    <div class="health-condition-meta">
                                        <?php if (!empty($condition['severity'])): ?><span>Severity: <?= htmlspecialchars($condition['severity']); ?></span><?php endif; ?>
                                        <?php if (!empty($condition['treatment_status'])): ?><span>Treatment: <?= htmlspecialchars($condition['treatment_status']); ?></span><?php endif; ?>
                                        <?php if (!empty($condition['remarks'])): ?><span><?= htmlspecialchars($condition['remarks']); ?></span><?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <section class="profile-detail-card profile-part3-card">
                <div class="detail-card-heading"><i class="fa-solid fa-mosque"></i><div><h2>Religion &amp; Practice</h2><small>Faith &amp; religious lifestyle</small></div></div>
                <div class="detail-list">
                    <div><span>Religion</span><strong><?= htmlspecialchars($profile['religion'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Madhhab</span><strong><?= htmlspecialchars($profile['madhhab'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Prayer</span><strong><?= htmlspecialchars($profile['prayer_status'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Mahram</span><strong><?= $profile['mahram_maintained'] === null ? 'Not provided' : ((int)$profile['mahram_maintained'] === 1 ? 'Maintained' : 'Not maintained'); ?></strong></div>
                    <?php if (($profile['gender'] ?? '') === 'Male'): ?>
                        <div><span>Beard</span><strong><?= htmlspecialchars($profile['beard_status'] ?? 'Not provided'); ?></strong></div>
                    <?php else: ?>
                        <div><span>Hijab</span><strong><?= htmlspecialchars($profile['hijab_status'] ?? 'Not provided'); ?></strong></div>
                    <?php endif; ?>
                    <?php if (trim((string)($profile['favorite_scholars'] ?? '')) !== ''): ?>
                        <div class="detail-wide"><span>Favorite Islamic Scholars</span><strong><?= nl2br(htmlspecialchars($profile['favorite_scholars'])); ?></strong></div>
                    <?php endif; ?>
                    <?php if (trim((string)($profile['favorite_books'] ?? '')) !== ''): ?>
                        <div class="detail-wide"><span>Favorite Islamic Books</span><strong><?= nl2br(htmlspecialchars($profile['favorite_books'])); ?></strong></div>
                    <?php endif; ?>
                    <?php if (trim((string)($profile['religious_knowledge_sources'] ?? '')) !== ''): ?>
                        <div class="detail-wide"><span>Religious Learning &amp; Practice Sources</span><strong><?= nl2br(htmlspecialchars($profile['religious_knowledge_sources'])); ?></strong></div>
                    <?php endif; ?>
                    <?php if (trim((string)($profile['bidah_shrine_stance'] ?? '')) !== ''): ?>
                        <div class="detail-wide"><span>Views on Bid'ah &amp; Shrine Worship</span><strong><?= nl2br(htmlspecialchars($profile['bidah_shrine_stance'])); ?></strong></div>
                    <?php endif; ?>
                </div>
            </section>

            <?php
                $currentAddressKeys = ['country','division_id','district_id','upazila_id','area','area_type','residence_type','post_office','postal_code'];
                $permanentAddressKeys = ['permanent_country','permanent_division_id','permanent_district_id','permanent_upazila_id','permanent_area','permanent_area_type','permanent_residence_type','permanent_post_office','permanent_postal_code'];
                $hasPermanentAddress = false;
                foreach ($permanentAddressKeys as $addressKey) { if (trim((string)($profile[$addressKey] ?? '')) !== '') { $hasPermanentAddress = true; break; } }
                $sameAsCurrent = !$hasPermanentAddress;
                if ($hasPermanentAddress) {
                    $sameAsCurrent = true;
                    foreach (array_combine($currentAddressKeys, $permanentAddressKeys) as $currentKey => $permanentKey) {
                        if (trim((string)($profile[$currentKey] ?? '')) !== trim((string)($profile[$permanentKey] ?? ''))) { $sameAsCurrent = false; break; }
                    }
                }
            ?>
            <section class="profile-detail-card profile-part4-card">
                <div class="detail-card-heading"><i class="fa-solid fa-location-dot"></i><div><h2>Location</h2><small>Current and permanent address details</small></div></div>
                <div class="detail-list">
                    <div class="detail-wide"><strong>Current Address</strong></div>
                    <div><span>Country</span><strong><?= htmlspecialchars($profile['country'] ?? 'Not provided'); ?></strong></div>
                    <div class="location-admin-row"><span>Division</span><strong><span data-location-en="<?= htmlspecialchars($profile['division_name'] ?? 'Not provided', ENT_QUOTES); ?>" data-location-bn="<?= htmlspecialchars($profile['division_name_bn'] ?? 'উল্লেখ করা হয়নি', ENT_QUOTES); ?>"><?= htmlspecialchars($profile['division_name'] ?? 'Not provided'); ?></span></strong></div>
                    <div class="location-admin-row"><span>District</span><strong><span data-location-en="<?= htmlspecialchars($profile['district_name'] ?? 'Not provided', ENT_QUOTES); ?>" data-location-bn="<?= htmlspecialchars($profile['district_name_bn'] ?? 'উল্লেখ করা হয়নি', ENT_QUOTES); ?>"><?= htmlspecialchars($profile['district_name'] ?? 'Not provided'); ?></span></strong></div>
                    <div class="location-admin-row"><span>Upazila</span><strong><span data-location-en="<?= htmlspecialchars($profile['upazila_name'] ?? 'Not provided', ENT_QUOTES); ?>" data-location-bn="<?= htmlspecialchars($profile['upazila_name_bn'] ?? 'উল্লেখ করা হয়নি', ENT_QUOTES); ?>"><?= htmlspecialchars($profile['upazila_name'] ?? 'Not provided'); ?></span></strong></div>
                    <div><span>Area</span><strong><?= htmlspecialchars($profile['area'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Area Type</span><strong><?= htmlspecialchars($profile['area_type'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Residential Details</span><strong><?= htmlspecialchars($profile['residence_type'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Post Office</span><strong><?= htmlspecialchars($profile['post_office'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Postal Code</span><strong><?= htmlspecialchars($profile['postal_code'] ?? 'Not provided'); ?></strong></div>
                    <div class="detail-wide"><strong>Permanent Address</strong></div>
                    <?php if ($sameAsCurrent): ?>
                        <div class="detail-wide"><span>Permanent Address</span><strong>Same as current location</strong></div>
                    <?php else: ?>
                        <div><span>Country</span><strong><?= htmlspecialchars($profile['permanent_country'] ?? 'Not provided'); ?></strong></div>
                        <div class="location-admin-row"><span>Division</span><strong><span data-location-en="<?= htmlspecialchars($profile['permanent_division_name'] ?? 'Not provided', ENT_QUOTES); ?>" data-location-bn="<?= htmlspecialchars($profile['permanent_division_name_bn'] ?? 'উল্লেখ করা হয়নি', ENT_QUOTES); ?>"><?= htmlspecialchars($profile['permanent_division_name'] ?? 'Not provided'); ?></span></strong></div>
                        <div class="location-admin-row"><span>District</span><strong><span data-location-en="<?= htmlspecialchars($profile['permanent_district_name'] ?? 'Not provided', ENT_QUOTES); ?>" data-location-bn="<?= htmlspecialchars($profile['permanent_district_name_bn'] ?? 'উল্লেখ করা হয়নি', ENT_QUOTES); ?>"><?= htmlspecialchars($profile['permanent_district_name'] ?? 'Not provided'); ?></span></strong></div>
                        <div class="location-admin-row"><span>Upazila</span><strong><span data-location-en="<?= htmlspecialchars($profile['permanent_upazila_name'] ?? 'Not provided', ENT_QUOTES); ?>" data-location-bn="<?= htmlspecialchars($profile['permanent_upazila_name_bn'] ?? 'উল্লেখ করা হয়নি', ENT_QUOTES); ?>"><?= htmlspecialchars($profile['permanent_upazila_name'] ?? 'Not provided'); ?></span></strong></div>
                        <div><span>Area</span><strong><?= htmlspecialchars($profile['permanent_area'] ?? 'Not provided'); ?></strong></div>
                        <div><span>Area Type</span><strong><?= htmlspecialchars($profile['permanent_area_type'] ?? 'Not provided'); ?></strong></div>
                        <div><span>Residential Details</span><strong><?= htmlspecialchars($profile['permanent_residence_type'] ?? 'Not provided'); ?></strong></div>
                        <div><span>Post Office</span><strong><?= htmlspecialchars($profile['permanent_post_office'] ?? 'Not provided'); ?></strong></div>
                        <div><span>Postal Code</span><strong><?= htmlspecialchars($profile['permanent_postal_code'] ?? 'Not provided'); ?></strong></div>
                    <?php endif; ?>
                    <div><span>Permanent Home / Hometown</span><strong><?= htmlspecialchars($profile['permanent_hometown'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Address Details</span><strong><?= htmlspecialchars($profile['address_details'] ?? 'Not provided'); ?></strong></div>
                </div>
            </section>

            <section class="profile-detail-card profile-part4-card">
                <div class="detail-card-heading"><i class="fa-solid fa-people-roof"></i><div><h2>Family Information</h2><small>Family background, relatives &amp; household</small></div></div>
                <div class="detail-list">
                    <div><span>Family Type</span><strong><?= htmlspecialchars($profile['family_type'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Family Status</span><strong><?= htmlspecialchars($profile['family_status'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Father</span><strong><?= htmlspecialchars($profile['father_name'] ?? 'Not provided'); ?></strong></div>
                    <?php if (!empty($profile['father_alive'])): ?><div><span>Father Alive?</span><strong><?= htmlspecialchars($profile['father_alive']); ?></strong></div><?php endif; ?>
                    <?php if (!empty($profile['father_profession'])): ?><div><span>Father's Profession</span><strong><?= htmlspecialchars($profile['father_profession']); ?></strong></div><?php endif; ?>
                    <div><span>Mother</span><strong><?= htmlspecialchars($profile['mother_name'] ?? 'Not provided'); ?></strong></div>
                    <?php if (!empty($profile['mother_alive'])): ?><div><span>Mother Alive?</span><strong><?= htmlspecialchars($profile['mother_alive']); ?></strong></div><?php endif; ?>
                    <?php if (!empty($profile['mother_profession'])): ?><div><span>Mother's Profession</span><strong><?= htmlspecialchars($profile['mother_profession']); ?></strong></div><?php endif; ?>
                    <?php if (!empty($profile['parents_details'])): ?><div><span>Additional Details About Parents</span><strong><?= nl2br(htmlspecialchars($profile['parents_details'])); ?></strong></div><?php endif; ?>
                    <div><span>Brothers</span><strong><?= $profile['brothers_count'] !== null ? (int)$profile['brothers_count'] : 'Not provided'; ?></strong></div>
                    <div><span>Sisters</span><strong><?= $profile['sisters_count'] !== null ? (int)$profile['sisters_count'] : 'Not provided'; ?></strong></div>
                    <div><span>Paternal Uncles</span><strong><?= $profile['paternal_uncles_count'] !== null ? (int)$profile['paternal_uncles_count'] : 'Not provided'; ?></strong></div>
                    <div><span>Paternal Aunts</span><strong><?= $profile['paternal_aunts_count'] !== null ? (int)$profile['paternal_aunts_count'] : 'Not provided'; ?></strong></div>
                    <?php if (!empty($profile['brothers_details'])): ?><div><span>Brothers' Details</span><strong><?= nl2br(htmlspecialchars($profile['brothers_details'])); ?></strong></div><?php endif; ?>
                    <?php if (!empty($profile['sisters_details'])): ?><div><span>Sisters' Details</span><strong><?= nl2br(htmlspecialchars($profile['sisters_details'])); ?></strong></div><?php endif; ?>
                    <?php if (!empty($profile['paternal_uncles_details'])): ?><div><span>Father's Brothers' Details</span><strong><?= nl2br(htmlspecialchars($profile['paternal_uncles_details'])); ?></strong></div><?php endif; ?>
                    <?php if (!empty($profile['paternal_aunts_details'])): ?><div><span>Father's Sisters' Details</span><strong><?= nl2br(htmlspecialchars($profile['paternal_aunts_details'])); ?></strong></div><?php endif; ?>
                    <div><span>Paternal Siblings Married</span><strong><?= htmlspecialchars($profile['paternal_siblings_married'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Family Purdah Environment</span><strong><?= htmlspecialchars($profile['family_purdah_environment'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Family Religious Lifestyle</span><strong><?= htmlspecialchars($profile['family_religious_lifestyle'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Living With Family</span><strong><?= htmlspecialchars($profile['living_with_family'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Guardian</span><strong>
                        <?php
                        $guardian_parts = array_filter([
                            trim((string)($profile['guardian_name'] ?? '')),
                            trim((string)($profile['guardian_relation'] ?? '')) !== '' ? '(' . trim((string)$profile['guardian_relation']) . ')' : ''
                        ]);
                        echo htmlspecialchars($guardian_parts ? implode(' ', $guardian_parts) : 'Not provided');
                        ?>
                    </strong></div>
                    <div><span>Guardian Contact</span><strong><?= !empty($profile['guardian_contact']) ? ($is_own_profile ? htmlspecialchars($profile['guardian_contact']) : 'Available') : 'Not provided'; ?></strong></div>
                </div>
            </section>

            <section class="profile-detail-card profile-part4-card profile-lifestyle-card">
                <div class="detail-card-heading"><i class="fa-solid fa-seedling"></i><div><h2>Lifestyle</h2><small>Daily habits &amp; personal nature</small></div></div>
                <div class="detail-list">
                    <div><span>Smoking</span><strong><?= htmlspecialchars($profile['smoking_status'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Halal Lifestyle</span><strong><?= htmlspecialchars($profile['halal_lifestyle'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Diet</span><strong><?= htmlspecialchars($profile['diet_preference'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Tea / Coffee</span><strong><?= htmlspecialchars($profile['tea_coffee'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Sleep Pattern</span><strong><?= htmlspecialchars($profile['sleep_pattern'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Personality</span><strong><?= htmlspecialchars($profile['personality_type'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Social Nature</span><strong><?= htmlspecialchars($profile['social_nature'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Travel</span><strong><?= htmlspecialchars($profile['travel_interest'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Spending Style</span><strong><?= htmlspecialchars($profile['spending_style'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Pets</span><strong><?= htmlspecialchars($profile['pets'] ?? 'Not provided'); ?></strong></div>
                </div>
                <?php
                $interests = $profile['free_time_interests'] ?? '';
                $interest_items = json_decode((string)$interests, true);
                if (!is_array($interest_items)) {
                    $interest_items = array_filter(array_map('trim', explode(',', (string)$interests)));
                }
                ?>
                <?php if ($interest_items): ?>
                    <div class="profile-interest-tags">
                        <span class="interest-label"><i class="fa-regular fa-clock"></i> Free Time</span>
                        <div class="interest-tags">
                            <?php foreach ($interest_items as $interest): ?>
                                <?php if (trim((string)$interest) !== ''): ?>
                                    <span><?= htmlspecialchars((string)$interest); ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>



            <section class="profile-detail-card profile-part6-card profile-about-card">
                <div class="detail-card-heading">
                    <i class="fa-solid fa-pen-nib"></i>
                    <div><h2>About Me</h2><small>A little about this person</small></div>
                </div>
                <?php if (!empty($profile['bio'])): ?>
                    <div class="profile-about-text"><?= nl2br(htmlspecialchars($profile['bio'])); ?></div>
                <?php else: ?>
                    <div class="profile-empty-state"><i class="fa-regular fa-comment-dots"></i><span>No introduction has been added yet.</span></div>
                <?php endif; ?>
            </section>

            <section class="profile-detail-card profile-part6-card profile-qa-section">
                <?php if ($trait_answers): ?>
                    <div class="profile-qa-list profile-qa-grid">
                        <?php $qa_counter = 1; ?>
                        <?php foreach ($trait_answers as $qa): ?>
                            <article class="profile-qa-item">
                                <div class="profile-qa-card-title">Personal Q&amp;A <?= $qa_counter++; ?></div>
                                <div class="profile-qa-question">
                                    <span class="profile-qa-number"><?= (int) $qa['question_id']; ?></span>
                                    <strong><?= htmlspecialchars($qa['question_text']); ?></strong>
                                </div>
                                <div class="profile-qa-answer">
                                    <?= nl2br(htmlspecialchars($qa['answer'])); ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="profile-empty-state"><i class="fa-regular fa-comments"></i><span>No questions have been answered yet.</span></div>
                <?php endif; ?>
            </section>

            <section class="profile-detail-card profile-part8-card profile-preferences-card">
                <div class="detail-card-heading">
                    <i class="fa-solid fa-heart-circle-check"></i>
                    <div><h2>What I'm Looking For</h2><small>Partner preferences shared by the member</small></div>
                </div>

                <?php if ($partner_preferences): ?>
                    <div class="preference-summary-grid">
                        <?php if (!empty($partner_preferences['preferred_gender'])): ?>
                            <div><span>Preferred Gender</span><strong><?= htmlspecialchars($partner_preferences['preferred_gender']); ?></strong></div>
                        <?php endif; ?>
                        <?php if ($partner_preferences['min_age'] !== null || $partner_preferences['max_age'] !== null): ?>
                            <div><span>Age Range</span><strong><?= $partner_preferences['min_age'] !== null ? (int)$partner_preferences['min_age'] : 'Any'; ?><?= $partner_preferences['max_age'] !== null ? ' – ' . (int)$partner_preferences['max_age'] : '+'; ?> years</strong></div>
                        <?php endif; ?>
                        <?php if ($partner_preferences['min_height_cm'] !== null || $partner_preferences['max_height_cm'] !== null): ?>
                            <div><span>Height Range</span><strong><?= htmlspecialchars(format_height_feet_inches($partner_preferences['min_height_cm'])); ?><?= $partner_preferences['max_height_cm'] !== null ? ' – ' . htmlspecialchars(format_height_feet_inches($partner_preferences['max_height_cm'])) : '+'; ?></strong></div>
                        <?php endif; ?>
                        <?php if ($partner_preferences['min_weight_kg'] !== null || $partner_preferences['max_weight_kg'] !== null): ?>
                            <div><span>Weight Range</span><strong><?= $partner_preferences['min_weight_kg'] !== null ? rtrim(rtrim(number_format((float)$partner_preferences['min_weight_kg'], 2, '.', ''), '0'), '.') : 'Any'; ?><?= $partner_preferences['max_weight_kg'] !== null ? ' – ' . rtrim(rtrim(number_format((float)$partner_preferences['max_weight_kg'], 2, '.', ''), '0'), '.') : '+'; ?> kg</strong></div>
                        <?php endif; ?>
                        <?php if (!empty($partner_preferences['complexion'])): ?>
                            <div><span>Complexion</span><strong><?= htmlspecialchars($partner_preferences['complexion']); ?></strong></div>
                        <?php endif; ?>
                        <?php if (!empty($partner_preferences['religion'])): ?><div><span>Religion</span><strong><?= htmlspecialchars($partner_preferences['religion']); ?></strong></div><?php endif; ?>
                        <?php if (!empty($partner_preferences['madhhab'])): ?><div><span>Madhhab</span><strong><?= htmlspecialchars($partner_preferences['madhhab']); ?></strong></div><?php endif; ?>
                        <?php if (!empty($partner_preferences['prayer_status'])): ?><div><span>Prayer</span><strong><?= htmlspecialchars($partner_preferences['prayer_status']); ?></strong></div><?php endif; ?>
                        <?php if (!empty($partner_preferences['halal_lifestyle'])): ?><div><span>Halal Lifestyle</span><strong><?= htmlspecialchars($partner_preferences['halal_lifestyle']); ?></strong></div><?php endif; ?>
                        <?php if (($partner_preferences['preferred_gender'] ?? '') === 'Female' && !empty($partner_preferences['hijab_status'])): ?><div><span>Hijab</span><strong><?= htmlspecialchars($partner_preferences['hijab_status']); ?></strong></div><?php endif; ?>
                        <?php if (($partner_preferences['preferred_gender'] ?? '') === 'Male' && !empty($partner_preferences['beard_status'])): ?><div><span>Beard</span><strong><?= htmlspecialchars($partner_preferences['beard_status']); ?></strong></div><?php endif; ?>
                        <?php if (!empty($partner_preferences['education'])): ?><div><span>Education</span><strong><?= htmlspecialchars($partner_preferences['education']); ?></strong></div><?php endif; ?>
                        <?php if (!empty($partner_preferences['profession'])): ?><div><span>Profession</span><strong><?= htmlspecialchars($partner_preferences['profession']); ?></strong></div><?php endif; ?>
                        <?php if ($partner_preferences['min_monthly_income'] !== null): ?><div><span>Minimum Income</span><strong><?= number_format((float)$partner_preferences['min_monthly_income'], 0); ?> / month</strong></div><?php endif; ?>
                        <?php if (!empty($partner_preferences['marital_status'])): ?><div><span>Marital Status</span><strong><?= htmlspecialchars($partner_preferences['marital_status']); ?></strong></div><?php endif; ?>
                        <?php if (!empty($partner_preferences['blood_group'])): ?><div><span>Blood Group</span><strong><?= htmlspecialchars($partner_preferences['blood_group']); ?></strong></div><?php endif; ?>
                    </div>

                    <?php if (!empty($partner_preferences['preferred_division_name']) || !empty($partner_preferences['preferred_district_name']) || !empty($partner_preferences['preferred_upazila_name'])): ?>
                        <div class="preference-block">
                            <div class="preference-block-title"><i class="fa-solid fa-location-dot"></i> Preferred Location</div>
                            <div class="preference-tags">
                                <?php if (!empty($partner_preferences['preferred_division_name'])): ?><span data-location-en="<?= htmlspecialchars($partner_preferences['preferred_division_name'], ENT_QUOTES, 'UTF-8'); ?>" data-location-bn="<?= htmlspecialchars($partner_preferences['preferred_division_name_bn'] ?? $partner_preferences['preferred_division_name'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($partner_preferences['preferred_division_name']); ?></span><?php endif; ?>
                                <?php if (!empty($partner_preferences['preferred_district_name'])): ?><span data-location-en="<?= htmlspecialchars($partner_preferences['preferred_district_name'], ENT_QUOTES, 'UTF-8'); ?>" data-location-bn="<?= htmlspecialchars($partner_preferences['preferred_district_name_bn'] ?? $partner_preferences['preferred_district_name'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($partner_preferences['preferred_district_name']); ?></span><?php endif; ?>
                                <?php if (!empty($partner_preferences['preferred_upazila_name'])): ?><span data-location-en="<?= htmlspecialchars($partner_preferences['preferred_upazila_name'], ENT_QUOTES, 'UTF-8'); ?>" data-location-bn="<?= htmlspecialchars($partner_preferences['preferred_upazila_name_bn'] ?? $partner_preferences['preferred_upazila_name'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($partner_preferences['preferred_upazila_name']); ?></span><?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($partner_preferences['personality_type'])): ?>
                        <div class="preference-block">
                            <div class="preference-block-title"><i class="fa-solid fa-user-group"></i> Lifestyle Expectations</div>
                            <div class="preference-tags"><span>Personality: <?= htmlspecialchars($partner_preferences['personality_type']); ?></span></div>
                        </div>
                    <?php endif; ?>

                    <?php
                        $acceptance_items = [];
                        if ((int)($partner_preferences['accept_smoker'] ?? 1) === 0) $acceptance_items[] = 'No smoker';
                        if ((int)($partner_preferences['accept_alcohol'] ?? 1) === 0) $acceptance_items[] = 'No alcohol';
                        if ((int)($partner_preferences['accept_disability'] ?? 1) === 0) $acceptance_items[] = 'No disability';
                        if ((int)($partner_preferences['accept_chronic_disease'] ?? 1) === 0) $acceptance_items[] = 'No chronic disease';
                    ?>
                    <?php if ($acceptance_items): ?>
                        <div class="preference-block">
                            <div class="preference-block-title"><i class="fa-solid fa-sliders"></i> Additional Filters</div>
                            <div class="preference-tags">
                                <?php foreach ($acceptance_items as $acceptance_item): ?><span><?= htmlspecialchars($acceptance_item); ?></span><?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($partner_preferences['additional_preferences'])): ?>
                        <div class="preference-additional">
                            <span>Additional Preferences</span>
                            <p><?= nl2br(htmlspecialchars($partner_preferences['additional_preferences'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($partner_preferences['partner_description'])): ?>
                        <div class="preference-additional">
                            <span>Detailed Description of Your Ideal Partner</span>
                            <p><?= nl2br(htmlspecialchars($partner_preferences['partner_description'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($partner_trait_preferences): ?>
                        <div class="preference-block preference-qa-block">
                            <div class="preference-block-title"><i class="fa-solid fa-list-check"></i> Question-based Preferences</div>
                            <div class="preference-qa-list">
                                <?php foreach ($partner_trait_preferences as $pref): ?>
                                    <div class="preference-qa-item">
                                        <span><?= htmlspecialchars($pref['question_text']); ?></span>
                                        <strong><?= htmlspecialchars($pref['preferred_answer']); ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="profile-empty-state"><i class="fa-regular fa-heart"></i><span>No partner preferences have been added yet.</span></div>
                <?php endif; ?>
            </section>

            <section class="profile-detail-card profile-part7-card profile-media-card">
                <div class="detail-card-heading">
                    <i class="fa-solid fa-photo-film"></i>
                    <div><h2>Media Gallery</h2><small>Photos, voice &amp; video introductions</small></div>
                </div>

                <?php if ($profile_media): ?>
                    <div class="profile-media-grid">
                        <?php foreach ($profile_media as $media): ?>
                            <?php
                                $media_id = (int) $media['media_id'];
                                $media_type = (string) $media['media_type'];
                                $media_url = 'media.php?id=' . $media_id;
                                $duration = (int) ($media['duration_seconds'] ?? 0);
                            ?>
                            <article class="profile-media-item profile-media-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $media_type))); ?>">
                                <?php if ($media_type === 'Profile Photo'): ?>
                                    <a class="profile-media-photo" href="<?= htmlspecialchars($media_url); ?>" target="_blank" rel="noopener">
                                        <img src="<?= htmlspecialchars($media_url); ?>" alt="Profile media" loading="lazy">
                                        <span class="profile-media-overlay"><i class="fa-solid fa-expand"></i></span>
                                    </a>
                                <?php elseif ($media_type === 'Voice Introduction'): ?>
                                    <div class="profile-media-audio-icon"><i class="fa-solid fa-microphone-lines"></i></div>
                                    <div class="profile-media-copy">
                                        <strong>Voice Introduction</strong>
                                        <span><?= $duration > 0 ? htmlspecialchars(gmdate('i:s', $duration)) . ' min' : 'Audio introduction'; ?></span>
                                    </div>
                                    <audio class="profile-media-audio" controls preload="none">
                                        <source src="<?= htmlspecialchars($media_url); ?>">
                                        Your browser does not support audio playback.
                                    </audio>
                                <?php else: ?>
                                    <div class="profile-media-video-wrap">
                                        <video class="profile-media-video" controls preload="metadata" playsinline>
                                            <source src="<?= htmlspecialchars($media_url); ?>">
                                            Your browser does not support video playback.
                                        </video>
                                    </div>
                                    <div class="profile-media-copy">
                                        <strong>Video Introduction</strong>
                                        <?php if ($duration > 0): ?><span><?= htmlspecialchars(gmdate('i:s', $duration)); ?></span><?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="profile-empty-state"><i class="fa-regular fa-images"></i><span>No media has been shared with you yet.</span></div>
                <?php endif; ?>
            </section>
        </div>

        <script>
        (function () {
            const grid = document.querySelector('.profile-detail-grid');
            if (!grid || grid.dataset.masonryReady === '1') return;

            const qaSection = Array.from(grid.children).find(function (el) {
                return el.classList.contains('profile-qa-section');
            });
            const qaItems = qaSection ? Array.from(qaSection.querySelectorAll('.profile-qa-item')) : [];
            const cards = Array.from(grid.children).filter(function (el) {
                return el.classList.contains('profile-detail-card') && !el.classList.contains('profile-qa-section');
            });
            if (!cards.length) return;

            function getColumnCount() {
                if (window.innerWidth <= 750) return 1;
                if (window.innerWidth <= 980) return 2;
                return 3;
            }

            function buildMasonry() {
                const count = getColumnCount();
                const fragment = document.createDocumentFragment();
                const columns = [];

                for (let i = 0; i < count; i++) {
                    const column = document.createElement('div');
                    column.className = 'profile-masonry-column';
                    columns.push(column);
                    fragment.appendChild(column);
                }

                grid.replaceChildren(fragment);

                cards.forEach(function (card) {
                    card.style.breakInside = 'avoid';
                    let target = columns[0];
                    for (let i = 1; i < columns.length; i++) {
                        if (columns[i].offsetHeight < target.offsetHeight) target = columns[i];
                    }
                    target.appendChild(card);
                });

                if (qaSection && qaItems.length) {
                    const qaTop = Math.min.apply(null, columns.map(function (column) { return column.offsetHeight; }));
                    grid.style.setProperty('--qa-flow-top', qaTop + 'px');
                    grid.classList.add('has-qa-flow');

                    const heading = qaSection.querySelector('.detail-card-heading');
                    if (heading) {
                        const headingWrap = document.createElement('div');
                        headingWrap.className = 'profile-qa-flow-heading';
                        headingWrap.appendChild(heading.cloneNode(true));
                        grid.appendChild(headingWrap);
                    }

                    qaItems.forEach(function (item) {
                        item.style.breakInside = 'avoid';
                        let target = columns[0];
                        for (let i = 1; i < columns.length; i++) {
                            if (columns[i].offsetHeight < target.offsetHeight) target = columns[i];
                        }
                        target.appendChild(item);
                    });
                }
            }

            let resizeTimer;
            function rebuild() {
                window.clearTimeout(resizeTimer);
                resizeTimer = window.setTimeout(buildMasonry, 120);
            }

            grid.dataset.masonryReady = '1';
            buildMasonry();
            window.addEventListener('resize', rebuild, { passive: true });
        }());
        </script>

        <?php if ($is_own_profile): ?>
            <aside class="privacy-drawer" id="privacyControlDrawer" aria-hidden="true" aria-labelledby="privacyDrawerTitle">
                <div class="privacy-drawer-backdrop" id="privacyControlBackdrop"></div>
                <div class="privacy-drawer-panel" role="dialog" aria-modal="true">
                    <div class="privacy-drawer-head">
                        <div>
                            <span class="privacy-drawer-kicker"><i class="fa-solid fa-lock"></i> Account Privacy</span>
                            <h2 id="privacyDrawerTitle">Privacy Control Center</h2>
                            <p>Manage how your profile and introductions are shared.</p>
                        </div>
                        <button type="button" class="privacy-drawer-close" id="privacyControlClose" aria-label="Close privacy control center">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <form id="privacyControlForm" class="privacy-control-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($privacy_csrf); ?>">

                        <div class="privacy-control-field">
                            <label for="privacyProfileVisibility">Profile Visibility</label>
                            <small>Whether your profile can appear to other members.</small>
                            <select id="privacyProfileVisibility" name="profile_visibility">
                                <option value="Public" <?= (($profile['profile_visibility'] ?? 'Public') === 'Public') ? 'selected' : ''; ?>>Public</option>
                                <option value="Hidden" <?= (($profile['profile_visibility'] ?? '') === 'Hidden') ? 'selected' : ''; ?>>Hidden</option>
                            </select>
                        </div>

                        <div class="privacy-control-field">
                            <label for="privacyPhotoVisibility">Profile Photo Visibility</label>
                            <small>Choose who can view your profile photo.</small>
                            <select id="privacyPhotoVisibility" name="photo_visibility">
                                <?php foreach (['Everyone', 'Verified Users', 'Matched Users', 'Hidden'] as $privacy_option): ?>
                                    <option value="<?= htmlspecialchars($privacy_option); ?>" <?= (($profile['photo_visibility'] ?? 'Verified Users') === $privacy_option) ? 'selected' : ''; ?>><?= htmlspecialchars($privacy_option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="privacy-control-field">
                            <label for="privacyVoiceVisibility">Voice Introduction Visibility</label>
                            <small>Control who can play your voice introduction.</small>
                            <select id="privacyVoiceVisibility" name="voice_visibility">
                                <?php foreach (['Everyone', 'Verified Users', 'Matched Users', 'Hidden'] as $privacy_option): ?>
                                    <option value="<?= htmlspecialchars($privacy_option); ?>" <?= ($privacy_voice_visibility === $privacy_option) ? 'selected' : ''; ?>><?= htmlspecialchars($privacy_option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="privacy-control-field">
                            <label for="privacyVideoVisibility">Video Introduction Visibility</label>
                            <small>Control who can watch your video introduction.</small>
                            <select id="privacyVideoVisibility" name="video_visibility">
                                <?php foreach (['Everyone', 'Verified Users', 'Matched Users', 'Hidden'] as $privacy_option): ?>
                                    <option value="<?= htmlspecialchars($privacy_option); ?>" <?= ($privacy_video_visibility === $privacy_option) ? 'selected' : ''; ?>><?= htmlspecialchars($privacy_option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="privacy-drawer-footer">
                            <span class="privacy-save-status" id="privacySaveStatus" aria-live="polite"></span>
                            <button type="submit" class="privacy-save-button" id="privacySaveButton"><i class="fa-solid fa-check"></i> Save Changes</button>
                        </div>
                    </form>
                </div>
            </aside>
        <?php endif; ?>

        <div class="profile-privacy-note">
            <i class="fa-solid fa-shield-halved"></i>
            <div>
                <strong>Privacy protected</strong>
                <p>Contact details and sensitive identity information are intentionally not displayed on public profile views.</p>
            </div>
        </div>
        <?php if ($is_own_profile): ?>
            <script>
            (function () {
                const drawer = document.getElementById('privacyControlDrawer');
                const openButton = document.getElementById('privacyControlOpen');
                const closeButton = document.getElementById('privacyControlClose');
                const backdrop = document.getElementById('privacyControlBackdrop');
                const form = document.getElementById('privacyControlForm');
                const saveButton = document.getElementById('privacySaveButton');
                const status = document.getElementById('privacySaveStatus');
                if (!drawer || !openButton || !form) return;

                function setOpen(open) {
                    drawer.classList.toggle('is-open', open);
                    drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
                    document.body.classList.toggle('privacy-drawer-open', open);
                    if (open) {
                        window.setTimeout(function () { closeButton && closeButton.focus(); }, 80);
                    } else {
                        openButton.focus();
                    }
                }

                openButton.addEventListener('click', function () { setOpen(true); });
                closeButton && closeButton.addEventListener('click', function () { setOpen(false); });
                backdrop && backdrop.addEventListener('click', function () { setOpen(false); });
                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && drawer.classList.contains('is-open')) setOpen(false);
                });

                form.addEventListener('submit', async function (event) {
                    event.preventDefault();
                    status.textContent = '';
                    saveButton.disabled = true;
                    saveButton.classList.add('is-saving');
                    try {
                        const response = await fetch('privacy_control.php', {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            body: new FormData(form)
                        });
                        const data = await response.json();
                        if (!response.ok || !data.success) throw new Error(data.message || 'Unable to save privacy settings.');
                        status.textContent = data.message || 'Privacy settings saved.';
                        status.className = 'privacy-save-status is-success';
                        window.setTimeout(function () { setOpen(false); }, 650);
                    } catch (error) {
                        status.textContent = error.message || 'Unable to save privacy settings.';
                        status.className = 'privacy-save-status is-error';
                    } finally {
                        saveButton.disabled = false;
                        saveButton.classList.remove('is-saving');
                    }
                });
            }());
            </script>
        <?php endif; ?>

    </main>
</div>


<script>
(function () {
    'use strict';
    const bn = {
        'Photo private':'ছবি ব্যক্তিগত', 'Profile':'প্রোফাইল', 'MATRIMONY PROFILE':'বিয়ের জন্য প্রোফাইল',
        'Download Biodata PDF':'বায়োডাটা PDF ডাউনলোড', 'Edit My Profile':'আমার প্রোফাইল সম্পাদনা', 'Privacy Control Center':'গোপনীয়তা ব্যবস্থাপনা কেন্দ্র',
        'Bookmarked':'বুকমার্ক করা হয়েছে', 'Bookmark':'বুকমার্ক', 'Respond':'জবাব দিন',
        'Personal Information':'ব্যক্তিগত তথ্য', 'Gender':'লিঙ্গ', 'Age':'বয়স',
        'NID / Birth Certificate No':'জাতীয় পরিচয়পত্র / জন্মনিবন্ধন নম্বর', 'Marital Status':'বৈবাহিক অবস্থা', 'Education':'শিক্ষা',
        'Profession':'পেশা', 'Occupation Details':'পেশার বিবরণ', 'Biodata Created By':'বায়োডাটা তৈরি করেছেন',
        'Guardian Consent for Biodata Creation':'বায়োডাটা তৈরিতে অভিভাবকের সম্মতি', 'Physical & Health Information':'শারীরিক ও স্বাস্থ্যসংক্রান্ত তথ্য', 'Height':'উচ্চতা',
        'Weight':'ওজন', 'Complexion':'গায়ের রং', 'Smoking':'ধূমপান',
        'Blood Group':'রক্তের গ্রুপ', 'Disability':'প্রতিবন্ধিতা', 'Allergies':'অ্যালার্জি',
        'Current Medications':'বর্তমান ওষুধ', 'Medical Notes':'চিকিৎসাসংক্রান্ত নোট', 'Health Conditions':'স্বাস্থ্যগত অবস্থা',
        'Religion & Practice':'ধর্ম ও ধর্মীয় অনুশীলন', 'Religion':'ধর্ম', 'Madhhab':'মাযহাব',
        'Prayer':'নামাজ', 'Mahram':'মাহরাম', 'Hijab':'হিজাব',
        'Favorite Islamic Scholars':'প্রিয় ইসলামি আলেম', 'Favorite Islamic Books':'প্রিয় ইসলামি বই', 'Religious Learning & Practice Sources':'ধর্মীয় শিক্ষা ও অনুশীলনের উৎস',
        'ah & Shrine Worship':'বিদআত ও মাজার-সংক্রান্ত দৃষ্টিভঙ্গি', 'Country':'দেশ', 'Division':'বিভাগ',
        'District':'জেলা', 'Upazila':'উপজেলা', 'Area':'এলাকা',
        'Area Type':'এলাকার ধরন', 'Post Office':'ডাকঘর', 'Postal Code':'পোস্ট কোড',
        'Family Information':'পারিবারিক তথ্য', 's Profession':'মায়ের পেশা', 'Brothers':'ভাই',
        'Sisters':'বোন', 'Paternal Uncles':'চাচা', 'Paternal Aunts':'ফুফু',
        ' Details':'ফুফুদের বিবরণ', 'Lifestyle':'জীবনযাপন', 'Halal Lifestyle':'হালাল জীবনযাপন',
        'Diet':'খাদ্যাভ্যাস', 'Personality':'ব্যক্তিত্ব', 'Travel':'ভ্রমণ',
        'Pets':'পোষা প্রাণী', 'About Me':'আমার সম্পর্কে', 'Personal Q&A':'ব্যক্তিগত প্রশ্নোত্তর',
        "What I'm Looking For":'আমি যেমন জীবনসঙ্গী চাই',
        'm Looking For':'আমি যেমন জীবনসঙ্গী চাই', 'Personality:':'ব্যক্তিত্ব:', 'Media Gallery':'মিডিয়া গ্যালারি',
        'Contact details and sensitive identity information are intentionally not displayed on public profile views.':'সবার জন্য উন্মুক্ত প্রোফাইলে যোগাযোগের তথ্য ও পরিচয়সংক্রান্ত সংবেদনশীল তথ্য দেখানো হয় না।', 'Back to Dashboard':'ড্যাশবোর্ডে ফিরে যান', 'Age not provided':'বয়স উল্লেখ করা হয়নি',
        'Residential Details':'বসবাসের বিবরণ', 'Family Type':'পরিবারের ধরন', 'Family Status':'পারিবারিক অবস্থা',
        'Father Alive?':'বাবা জীবিত কি না', 'Mother Alive?':'মা জীবিত কি না', 'Voice Introduction':'কণ্ঠ পরিচিতি',
        'Video Introduction':'ভিডিও পরিচিতি', 'Audio introduction':'অডিও পরিচিতি', 'Manage how your profile and introductions are shared.':'আপনার প্রোফাইল ও পরিচিতি কারা দেখতে বা শুনতে পারবেন, তা নির্ধারণ করুন।',
        'Interest Unavailable':'আগ্রহ জানানো যাচ্ছে না', 'Interest Sent':'আগ্রহ পাঠানো হয়েছে', 'Send Interest':'আগ্রহ জানান',
        'Privacy settings saved.':'গোপনীয়তার সেটিংস সংরক্ষণ করা হয়েছে।', 'Unable to save privacy settings.':'গোপনীয়তার সেটিংস সংরক্ষণ করা যায়নি।', 'Basic identity & background':'মৌলিক পরিচয় ও পটভূমি',
        'of profile steps completed':'টি প্রোফাইলের ধাপ সম্পন্ন হয়েছে', 'Appearance & personal details':'শারীরিক গঠন ও ব্যক্তিগত বিবরণ', 'Faith & religious lifestyle':'ধর্মবিশ্বাস ও ধর্মীয় জীবনযাপন',
        'Current and permanent address details':'বর্তমান ও স্থায়ী ঠিকানার বিবরণ', 'Family background, relatives & household':'পারিবারিক পটভূমি, আত্মীয়স্বজন ও গৃহস্থালি', 'Daily habits & personal nature':'দৈনন্দিন অভ্যাস ও ব্যক্তিগত স্বভাব',
        'A little about this person':'এই ব্যক্তির সম্পর্কে কিছু কথা', 'Partner preferences shared by the member':'সদস্যের জানানো জীবনসঙ্গী-সংক্রান্ত পছন্দ', 'Photos, voice & video introductions':'ছবি, কণ্ঠ ও ভিডিও পরিচিতি',
        'Whether your profile can appear to other members.':'অন্য সদস্যরা আপনার প্রোফাইল দেখতে পাবেন কি না।', 'Choose who can view your profile photo.':'কারা আপনার প্রোফাইলের ছবি দেখতে পারবেন তা বেছে নিন।', 'Control who can play your voice introduction.':'কারা আপনার কণ্ঠ পরিচিতি শুনতে পারবেন তা নিয়ন্ত্রণ করুন।',
        'Control who can watch your video introduction.':'কারা আপনার ভিডিও পরিচিতি দেখতে পারবেন তা নিয়ন্ত্রণ করুন।', 'Your browser does not support audio playback.':'আপনার ব্রাউজারে অডিও চালানোর সুবিধা নেই।', 'Your browser does not support video playback.':'আপনার ব্রাউজারে ভিডিও চালানোর সুবিধা নেই।',
        'No introduction has been added yet.':'এখনো কোনো পরিচিতিমূলক লেখা যোগ করা হয়নি।', 'No questions have been answered yet.':'এখনো কোনো প্রশ্নের উত্তর দেওয়া হয়নি।', 'No partner preferences have been added yet.':'এখনো জীবনসঙ্গী-সংক্রান্ত কোনো পছন্দ যোগ করা হয়নি।',
        'No media has been shared with you yet.':'এখনো আপনার সঙ্গে কোনো মিডিয়া শেয়ার করা হয়নি।', 'Privacy protected':'গোপনীয়তা সুরক্ষিত', 'Privacy settings saved successfully.':'গোপনীয়তার সেটিংস সফলভাবে সংরক্ষিত হয়েছে।',
        'Could not save privacy settings.':'গোপনীয়তার সেটিংস সংরক্ষণ করা যায়নি।', 'Privacy settings could not be saved.':'গোপনীয়তার সেটিংস সংরক্ষণ করা যায়নি।', 'Close privacy control center':'গোপনীয়তা ব্যবস্থাপনা কেন্দ্র বন্ধ করুন',
        'Account Privacy':'অ্যাকাউন্টের গোপনীয়তা', 'Profile Visibility':'প্রোফাইলের দৃশ্যমানতা', 'Profile Photo Visibility':'প্রোফাইলের ছবির দৃশ্যমানতা',
        'Voice Introduction Visibility':'কণ্ঠ পরিচিতির দৃশ্যমানতা', 'Video Introduction Visibility':'ভিডিও পরিচিতির দৃশ্যমানতা', 'Save Changes':'পরিবর্তন সংরক্ষণ করুন',
        'years':'বছর', '/ month':'/ মাস', 'Minimum Income':'সর্বনিম্ন আয়', 'Monthly Income':'মাসিক আয়',
        'Age Range':'বয়সের সীমা', 'Height Range':'উচ্চতার সীমা', 'Weight Range':'ওজনের সীমা',
        'Lifestyle Expectations':'জীবনযাপন সম্পর্কে প্রত্যাশা', 'Additional Filters':'অতিরিক্ত ফিল্টার', 'Additional Preferences':'অতিরিক্ত পছন্দ',
        'Detailed Description of Your Ideal Partner':'আপনার পছন্দের জীবনসঙ্গীর বিস্তারিত বিবরণ', 'Question-based Preferences':'প্রশ্নভিত্তিক পছন্দ', 'Preferred Gender':'প্রত্যাশিত জীবনসঙ্গী',
        'Preferred Location':'পছন্দের ঠিকানা', 'Tea / Coffee':'চা / কফি', 'Sleep Pattern':'ঘুমের ধরন',
        'Social Nature':'সামাজিক স্বভাব', 'Spending Style':'খরচের ধরন', 'Free Time':'অবসর সময়',
        'Additional Details About Parents':'বাবা-মা সম্পর্কে অতিরিক্ত তথ্য', 'Paternal Siblings Married':'চাচা-ফুফুদের বৈবাহিক অবস্থা', 'Family Purdah Environment':'পরিবারে পর্দার পরিবেশ',
        'Family Religious Lifestyle':'পরিবারের ধর্মীয় জীবনযাপন', 'Living With Family':'পরিবারের সঙ্গে বসবাস', 'Guardian Contact':'অভিভাবকের যোগাযোগের তথ্য',
        'Permanent Home / Hometown':'স্থায়ী বাড়ি / নিজ এলাকা', 'Address Details':'ঠিকানার বিবরণ', 'No health profile information provided.':'স্বাস্থ্যসংক্রান্ত কোনো তথ্য দেওয়া হয়নি।',
        'Severity:':'তীব্রতা:', 'Treatment:':'চিকিৎসা:', 'None / Not maintained':'নেই / পালন করা হয় না',
        'Available':'উপলভ্য', 'Not Available':'উপলভ্য নয়', 'Large':'বড়',
        'Service Holder':'চাকরিজীবী', 'Private Job':'বেসরকারি চাকরি', 'Government Job':'সরকারি চাকরি',
        'Reserved':'স্বল্পভাষী', 's':'স্নাতকোত্তর', 'High School':'মাধ্যমিক',
        'Higher Secondary':'উচ্চমাধ্যমিক', '5 ft':'৫ ফুট', 'Social Communication Medium':'সামাজিক যোগাযোগমাধ্যম',
        'Social communication medium':'সামাজিক যোগাযোগমাধ্যম', 'Verified':'যাচাইকৃত', 'Status':'অবস্থা',
        'Location':'ঠিকানা', 'Profile Completion':'প্রোফাইল সম্পূর্ণতা', 'profile steps completed':'টি প্রোফাইলের ধাপ সম্পন্ন হয়েছে',
        'years old':'বছর বয়স', 'Mostly practicing':'বেশিরভাগ ক্ষেত্রে পালন করেন', 'Beard':'দাড়ি',
        'Partially':'আংশিকভাবে', 's Degree':'স্নাতকোত্তর', '22 – 26 years':'২২ – ২৬ বছর',
        '5 ft 5 in':'৫ ফুট ৫ ইঞ্চি', '5 ft 8 in':'৫ ফুট ৮ ইঞ্চি', 'Dhaka':'ঢাকা',
        'Tangail':'টাঙ্গাইল', 'Moderate tea/coffee':'মাঝামাঝি', '40,000 / month':'৪০,০০০ / মাস',
        '80,000 / month':'৮০,০০০ / মাস'
    };
    // Central, audited dropdown translations shared by all profile-value cards.
    Object.assign(bn, <?= json_encode(sm_profile_value_translation_map(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);

    function getLanguage() {
        // The site's language switcher stores its choice in the sm_language cookie.
        // Read it first so a generic HTML lang attribute cannot override the user's choice.
        try {
            const cookieMatch = document.cookie.match(/(?:^|;\s*)sm_language=(bn|en)(?:;|$)/i);
            if (cookieMatch) return cookieMatch[1].toLowerCase();
        } catch (e) {}
        try {
            for (const key of ['sm_language','language','lang','site_language','preferred_language']) {
                const value = (localStorage.getItem(key) || '').toLowerCase();
                if (value === 'bn' || value === 'en' || value.startsWith('bn-') || value.startsWith('en-')) return value.startsWith('bn') ? 'bn' : 'en';
            }
        } catch (e) {}
        const bodyLang = document.body.getAttribute('data-language') || document.body.getAttribute('data-lang') || '';
        if (bodyLang.toLowerCase().startsWith('bn')) return 'bn';
        if (bodyLang.toLowerCase().startsWith('en')) return 'en';
        const rootLang = (document.documentElement.lang || '').toLowerCase();
        return rootLang.startsWith('bn') ? 'bn' : 'en';
    }
    function toEnglishDigits(value) {
        return String(value).replace(/[০-৯]/g, function (digit) { return String('০১২৩৪৫৬৭৮৯'.indexOf(digit)); });
    }
    function applyLanguage() {
        const useBn = getLanguage() === 'bn';
        const root = document.querySelector('.profile-view-page') || document.body;
        // Keep the photo-overlay verification badge in the same language as the hero badge.
        // Use dedicated spans so the status and the word "Profile" are translated independently.
        root.querySelectorAll('.profile-photo-badge').forEach(function (badge) {
            const label = badge.querySelector('.profile-photo-badge-label');
            const suffix = badge.querySelector('.profile-photo-badge-suffix');
            if (!label || !suffix) return;
            const status = (label.dataset.originalStatus || label.textContent.trim());
            label.dataset.originalStatus = status;
            const statusBn = { 'Verified': 'যাচাইকৃত', 'Pending': 'অপেক্ষমাণ', 'Rejected': 'প্রত্যাখ্যাত' };
            label.textContent = useBn ? (statusBn[status] || status) : status;
            suffix.textContent = useBn ? 'প্রোফাইল' : 'Profile';
        });
        const reverse = {};
        Object.keys(bn).forEach(function (key) { if (!(bn[key] in reverse)) reverse[bn[key]] = key; });
        const bnNormalized = {};
        const enNormalized = {};
        Object.keys(bn).forEach(function (key) { bnNormalized[key.trim().toLowerCase()] = bn[key]; });
        Object.keys(reverse).forEach(function (key) { enNormalized[key.trim().toLowerCase()] = reverse[key]; });
        function lookupLabel(map, normalizedMap, value) {
            if (Object.prototype.hasOwnProperty.call(map, value)) return map[value];
            const normalized = String(value).trim().toLowerCase();
            return Object.prototype.hasOwnProperty.call(normalizedMap, normalized) ? normalizedMap[normalized] : null;
        }
        // Translate only elements intended to contain interface labels. Never walk profile-value fields,
        // member-written answers, introductions, preference descriptions, or arbitrary <strong> data.
        const selectors = [
            'h1','h2','h3','h4','h5','h6','small','label','button','a','option',
            '.profile-view-kicker','.profile-header-summary','.profile-photo-badge','.profile-verified',
            '.profile-completion-mini-head span','.profile-completion-mini small',
            '.profile-empty-state span','.detail-card-heading','.profile-quick-copy small','.preference-summary-grid > div > span',
            '.detail-list > div > span','.health-summary-grid > div > span',
            '.health-subheading','.health-wide > span','.interest-label',
            '.preference-block-title','.profile-privacy-note > div > strong','.profile-qa-card-title',
            '.privacy-drawer-kicker','.privacy-drawer-head p','.privacy-control-field small',
            '.profile-detail-card > p','.profile-empty-state p','.profile-privacy-note p',
            '.profile-preferences-card > p','.profile-media-card > p',
            '.privacy-save-button','.profile-action.unavailable','.profile-action.state-pending',
            '.profile-action.state-received','.profile-bookmark-label','.detail-wide > strong'
        ];
        const seen = new Set();
        // Translate only whitelisted database enum/option values in structured information rows.
        // Free-text fields (bio, notes, Q&A, family descriptions), names, email and phone are excluded.
        const valueSelectors = [
            '.detail-list > div:not(.detail-wide) > strong',
            '.preference-summary-grid > div > strong',
            '.profile-interest-tags .interest-tags span',
            '.preference-tags span',
            '.health-summary-grid > div > strong',
            '.health-condition-item > span',
            '.detail-wide > strong',
            '.profile-quick-info .profile-quick-item strong',
            '.profile-privacy-note > div > strong'
        ];
        root.querySelectorAll(valueSelectors.join(',')).forEach(function (el) {
            if (el.closest('.profile-about-content, .profile-qa-item, .profile-quick-copy[data-personal], .health-wide')) return;
            const original = el.textContent.trim();
            if (!original) return;
            let translated = original;
            // Dynamic values are transformed by pattern, not by hard-coded numeric examples.
            // This covers any monthly income amount and keeps user-entered names/free text untouched.
            const incomeBn = original.match(/^([0-9০-৯][0-9০-৯,]*(?:\.[0-9০-৯]+)?)\s*\/\s*month$/i);
            const incomeEn = original.match(/^([0-9০-৯][0-9০-৯,]*(?:\.[0-9০-৯]+)?)\s*\/\s*মাস$/i);
            if (useBn && incomeBn) {
                translated = incomeBn[1].replace(/[0-9]/g, function (d) { return '০১২৩৪৫৬৭৮৯'[Number(d)]; }) + ' / মাস';
            } else if (!useBn && incomeEn) {
                translated = incomeEn[1].replace(/[০-৯]/g, function (d) { return String('০১২৩৪৫৬৭৮৯'.indexOf(d)); }) + ' / month';
            } else if (useBn) { const label = lookupLabel(bn, bnNormalized, original); if (label !== null) translated = label; }
            else { const label = lookupLabel(reverse, enNormalized, original); if (label !== null) translated = label; }
            if (translated !== original) el.textContent = translated;
            else if (useBn) {
                const age = original.match(/^(\d+) years?$/i);
                if (age) el.textContent = age[1] + ' বছর';
                const weight = original.match(/^(\d+(?:\.\d+)?) kg$/i);
                if (weight) el.textContent = weight[1] + ' কেজি';
                const height = original.match(/^(\d+) ft (\d+) in(?:ch(?:es)?)?$/i);
                if (height) el.textContent = height[1] + ' ফুট ' + height[2] + ' ইঞ্চি';
                const ageRange = original.match(/^(\d+)\s*[–-]\s*(\d+) years?$/i);
                if (ageRange) el.textContent = ageRange[1] + ' – ' + ageRange[2] + ' বছর';
                const heightRange = original.match(/^(\d+) ft (\d+) in\s*[–-]\s*(\d+) ft (\d+) in$/i);
                if (heightRange) el.textContent = heightRange[1] + ' ফুট ' + heightRange[2] + ' ইঞ্চি – ' + heightRange[3] + ' ফুট ' + heightRange[4] + ' ইঞ্চি';
            } else {
                const age = original.match(/^(\d+) বছর$/);
                if (age) el.textContent = age[1] + ' years';
                const weight = original.match(/^(\d+(?:\.\d+)?) কেজি$/);
                if (weight) el.textContent = weight[1] + ' kg';
                const height = original.match(/^(\d+) ফুট (\d+) ইঞ্চি$/);
                if (height) el.textContent = height[1] + ' ft ' + height[2] + ' in';
                const ageRange = original.match(/^(\d+)\s*[–-]\s*(\d+) বছর$/);
                if (ageRange) el.textContent = ageRange[1] + ' – ' + ageRange[2] + ' years';
                const heightRange = original.match(/^(\d+) ফুট (\d+) ইঞ্চি\s*[–-]\s*(\d+) ফুট (\d+) ইঞ্চি$/);
                if (heightRange) el.textContent = heightRange[1] + ' ft ' + heightRange[2] + ' in – ' + heightRange[3] + ' ft ' + heightRange[4] + ' in';
            }
        });
        // Location names use the database's actual Bangla labels; user-entered free text stays untouched.
        root.querySelectorAll('[data-location-en][data-location-bn]').forEach(function (el) {
            el.textContent = useBn ? el.getAttribute('data-location-bn') : el.getAttribute('data-location-en');
        });
        // Convert digits only in generated numeric UI fields. Never convert public ID, phone numbers,
        // member-written family details, Q&A answers, names, or other free-text values.
        function toBanglaDigits(value) {
            return String(value).replace(/[0-9]/g, function (digit) { return '০১২৩৪৫৬৭৮৯'[Number(digit)]; });
        }
        if (useBn) {
            root.querySelectorAll('.profile-header-summary, .profile-completion-mini-head strong, .profile-completion-mini small, .detail-list > div > span + strong, .preference-summary-grid > div > strong').forEach(function (el) {
                if (el.closest('.profile-qa-item, .health-condition-item')) return;
                const rowLabel = el.parentElement && el.parentElement.querySelector(':scope > span');
                const labelText = rowLabel ? rowLabel.textContent.trim() : '';
                const isCount = ['ভাই','বোন','চাচা','ফুফু','Brothers','Sisters','Paternal Uncles','Paternal Aunts'].includes(labelText);
                const isGeneratedNumeric = el.matches('.profile-header-summary, .profile-completion-mini-head strong, .profile-completion-mini small')
                    || isCount || ['বয়স','উচ্চতা','ওজন','Age','Height','Weight'].includes(labelText)
                    || ['বয়সের সীমা','উচ্চতার সীমা','ওজনের সীমা','Age Range','Height Range','Weight Range'].includes(labelText)
                    || ['সর্বনিম্ন আয়','Minimum Income'].includes(labelText);
                if (!isGeneratedNumeric) return;
                let value = el.textContent;
                value = value.replace(/\bkg\b/gi, 'কেজি');
                el.textContent = toBanglaDigits(value);
            });
            // Keep weight range unit localized and numeric values in Bangla digits.
            root.querySelectorAll('.preference-summary-grid > div').forEach(function (row) {
                const label = row.querySelector(':scope > span');
                const value = row.querySelector(':scope > strong');
                if (label && value && ['ওজনের সীমা','Weight Range'].includes(label.textContent.trim())) {
                    value.textContent = toBanglaDigits(value.textContent.replace(/\bkg\b/gi, 'কেজি'));
                }
            });
        }
        // The public profile ID is always Latin/English digits, independent of the selected language.
        const publicId = root.querySelector('.profile-view-id');
        if (publicId) publicId.textContent = 'SM-' + String(publicId.textContent).replace(/\D/g, '').replace(/[০-৯]/g, function (digit) { return '০১২৩৪৫৬৭৮৯'.indexOf(digit); }).padStart(6, '0');
        root.querySelectorAll(selectors.join(',')).forEach(function (el) {
            if (el.closest('.profile-qa-item, .health-condition-item, .profile-about-content')) return;
            // For compound controls/headings, translate eligible text nodes only, not nested data values.
            const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
            const nodes = [];
            while (walker.nextNode()) {
                const node = walker.currentNode;
                if (node.parentElement && node.parentElement.closest('strong, .profile-qa-number, i, svg, script, style')) continue;
                nodes.push(node);
            }
            nodes.forEach(function (node) {
                if (seen.has(node)) return;
                seen.add(node);
                const original = node.nodeValue;
                const trimmed = original.trim();
                if (!trimmed) return;
                let translated = trimmed;
                if (useBn) { const label = lookupLabel(bn, bnNormalized, trimmed); if (label !== null) translated = label; }
                else { const label = lookupLabel(reverse, enNormalized, trimmed); if (label !== null) translated = label; }
                if (translated !== trimmed) node.nodeValue = original.replace(trimmed, translated);
            });
        });
        // Translate the hero summary's fixed-format age/religion/marital-status line.
        const heroSummary = root.querySelector('.profile-header-summary');
        if (heroSummary) {
            const raw = heroSummary.textContent.trim();
            const parts = raw.split(/\s*·\s*/);
            if (useBn) {
                heroSummary.textContent = parts.map(function (part) {
                    const age = part.match(/^([0-9০-৯]+) years old$/i);
                    if (age) return toBanglaDigits(age[1]) + ' বছর বয়স';
                    return Object.prototype.hasOwnProperty.call(bn, part) ? bn[part] : part;
                }).join(' · ');
            } else {
                heroSummary.textContent = parts.map(function (part) {
                    const age = part.match(/^([0-9০-৯]+) বছর বয়স$/);
                    if (age) return toEnglishDigits(age[1]) + ' years old';
                    return Object.prototype.hasOwnProperty.call(reverse, part) ? reverse[part] : part;
                }).join(' · ');
            }
        }
        // Translate the dynamic completion count while preserving the numeric progress.
        const completionText = root.querySelector('.profile-completion-mini small');
        if (completionText) {
            const match = completionText.textContent.trim().match(/^(\d+) of (\d+) profile steps completed$/i);
            const bnMatch = completionText.textContent.trim().match(/^(\d+)\s*\/\s*(\d+)টি প্রোফাইলের ধাপ সম্পন্ন হয়েছে$/);
            if (useBn && match) completionText.textContent = match[1] + ' / ' + match[2] + 'টি প্রোফাইলের ধাপ সম্পন্ন হয়েছে';
            else if (!useBn && bnMatch) completionText.textContent = bnMatch[1] + ' of ' + bnMatch[2] + ' profile steps completed';
        }
        root.querySelectorAll('[aria-label]').forEach(function (el) {
            const val = el.getAttribute('aria-label');
            if (useBn && Object.prototype.hasOwnProperty.call(bn, val)) el.setAttribute('aria-label', bn[val]);
            else if (!useBn && Object.prototype.hasOwnProperty.call(reverse, val)) el.setAttribute('aria-label', reverse[val]);
        });
        // Privacy save feedback is inserted asynchronously by the existing request handler.
        const status = root.querySelector('#privacySaveStatus');
        if (status && status.textContent.trim()) {
            const value = status.textContent.trim();
            if (useBn && bn[value]) status.textContent = bn[value];
            else if (!useBn && reverse[value]) status.textContent = reverse[value];
        }
    }
    applyLanguage();
    document.addEventListener('languageChanged', applyLanguage);
    document.addEventListener('DOMContentLoaded', applyLanguage);
    window.addEventListener('storage', applyLanguage);
    const statusNode = document.getElementById('privacySaveStatus');
    if (statusNode && window.MutationObserver) new MutationObserver(applyLanguage).observe(statusNode, { childList: true, characterData: true, subtree: true });
}());
</script>

<?php include '../includes/footer.php'; ?>
