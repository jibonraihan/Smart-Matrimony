<?php

$page_css = 'assets/css/profile-view.css';

require_once '../config/db.php';
require_once '../includes/profile_completion.php';

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
        up.first_name,
        up.last_name,
        up.gender,
        up.date_of_birth,
        up.nid_number,
        up.religion,
        up.madhhab,
        up.marital_status,
        up.bio,
        up.photo,
        up.height_cm,
        up.weight_kg,
        up.complexion,
        up.country,
        up.area,
        up.highest_education,
        up.profession,
        up.occupation_details,
        up.monthly_income,
        up.father_name,
        up.father_profession,
        up.mother_name,
        up.mother_profession,
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
        d.name_bn AS district_name,
        dv.name_bn AS division_name,
        uz.name_bn AS upazila_name
    FROM users u
    INNER JOIN user_profiles up ON up.user_id = u.user_id
    LEFT JOIN divisions dv ON dv.id = up.division_id
    LEFT JOIN districts d ON d.id = up.district_id
    LEFT JOIN upazilas uz ON uz.id = up.upazila_id
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
        dv.name_bn AS preferred_division_name,
        d.name_bn AS preferred_district_name,
        uz.name_bn AS preferred_upazila_name
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
    <div class="profile-view-topbar">
        <div class="profile-view-container profile-view-nav">
            <a href="<?= htmlspecialchars($return_url); ?>" class="back-dashboard">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Dashboard
            </a>
            <span>Smart Matrimony</span>
        </div>
    </div>

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
                    <?= htmlspecialchars($verification_badge['label']); ?> Profile
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
                                    <strong><?= htmlspecialchars($item['value']); ?></strong>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="profile-view-actions">
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
                </div>
            </section>

            <section class="profile-detail-card profile-part4-card">
                <div class="detail-card-heading"><i class="fa-solid fa-location-dot"></i><div><h2>Location</h2><small>Current residence &amp; hometown details</small></div></div>
                <div class="detail-list">
                    <div><span>Country</span><strong><?= htmlspecialchars($profile['country'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Division</span><strong><?= htmlspecialchars($profile['division_name'] ?? 'Not provided'); ?></strong></div>
                    <div><span>District</span><strong><?= htmlspecialchars($profile['district_name'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Upazila</span><strong><?= htmlspecialchars($profile['upazila_name'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Area</span><strong><?= htmlspecialchars($profile['area'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Area Type</span><strong><?= htmlspecialchars($profile['area_type'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Residence Type</span><strong><?= htmlspecialchars($profile['residence_type'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Post Office</span><strong><?= htmlspecialchars($profile['post_office'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Postal Code</span><strong><?= htmlspecialchars($profile['postal_code'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Permanent Hometown</span><strong><?= htmlspecialchars($profile['permanent_hometown'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Address Details</span><strong><?= htmlspecialchars($profile['address_details'] ?? 'Not provided'); ?></strong></div>
                </div>
            </section>

            <section class="profile-detail-card profile-part4-card">
                <div class="detail-card-heading"><i class="fa-solid fa-people-roof"></i><div><h2>Family Information</h2><small>Family background, relatives &amp; household</small></div></div>
                <div class="detail-list">
                    <div><span>Family Type</span><strong><?= htmlspecialchars($profile['family_type'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Family Status</span><strong><?= htmlspecialchars($profile['family_status'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Father</span><strong><?= htmlspecialchars($profile['father_name'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Father's Profession</span><strong><?= htmlspecialchars($profile['father_profession'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Mother</span><strong><?= htmlspecialchars($profile['mother_name'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Mother's Profession</span><strong><?= htmlspecialchars($profile['mother_profession'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Brothers</span><strong><?= $profile['brothers_count'] !== null ? (int)$profile['brothers_count'] : 'Not provided'; ?></strong></div>
                    <div><span>Sisters</span><strong><?= $profile['sisters_count'] !== null ? (int)$profile['sisters_count'] : 'Not provided'; ?></strong></div>
                    <div><span>Paternal Uncles</span><strong><?= $profile['paternal_uncles_count'] !== null ? (int)$profile['paternal_uncles_count'] : 'Not provided'; ?></strong></div>
                    <div><span>Paternal Aunts</span><strong><?= $profile['paternal_aunts_count'] !== null ? (int)$profile['paternal_aunts_count'] : 'Not provided'; ?></strong></div>
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
                                <?php if (!empty($partner_preferences['preferred_division_name'])): ?><span><?= htmlspecialchars($partner_preferences['preferred_division_name']); ?></span><?php endif; ?>
                                <?php if (!empty($partner_preferences['preferred_district_name'])): ?><span><?= htmlspecialchars($partner_preferences['preferred_district_name']); ?></span><?php endif; ?>
                                <?php if (!empty($partner_preferences['preferred_upazila_name'])): ?><span><?= htmlspecialchars($partner_preferences['preferred_upazila_name']); ?></span><?php endif; ?>
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

<?php include '../includes/footer.php'; ?>
