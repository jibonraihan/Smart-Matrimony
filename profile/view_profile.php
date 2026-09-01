<?php

$page_css = 'assets/css/profile-view.css';

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$viewer_id = (int) $_SESSION['user_id'];
$profile_user_id = (int) ($_GET['user_id'] ?? 0);

if ($profile_user_id <= 0 || $profile_user_id === $viewer_id) {
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
        up.family_type,
        up.family_status,
        up.siblings,
        up.smoking_status,
        up.prayer_status,
        up.beard_status,
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
      AND up.profile_visibility = 'Public'
    LIMIT 1
");

mysqli_stmt_bind_param($stmt, 'i', $profile_user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$profile = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$profile) {
    header('Location: ../dashboard.php?profile=not-found#partner-search');
    exit;
}

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

$can_show_photo = !empty($profile['photo']) && ($profile['photo_visibility'] ?? '') === 'Everyone';

include '../includes/header.php';
?>

<div class="profile-view-page">
    <div class="profile-view-topbar">
        <div class="profile-view-container profile-view-nav">
            <a href="<?= BASE_URL; ?>dashboard.php" class="back-dashboard">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Dashboard
            </a>
            <span>Smart Matrimony</span>
        </div>
    </div>

    <main class="profile-view-container profile-view-main">
        <section class="profile-view-hero-card">
            <div class="profile-view-photo">
                <?php if ($can_show_photo): ?>
                    <img src="<?= BASE_URL; ?>uploads/profile/<?= htmlspecialchars($profile['photo']); ?>" alt="Profile photo">
                <?php else: ?>
                    <div class="private-photo-large">
                        <i class="fa-solid fa-lock"></i>
                        <span>Photo private</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="profile-view-intro">
                <div class="profile-view-title-row">
                    <div>
                        <span class="profile-view-kicker">MEMBER PROFILE</span>
                        <h1><?= htmlspecialchars($name); ?></h1>
                        <p>
                            <?= $age !== null ? (int) $age . ' years old' : 'Age not provided'; ?>
                            <?php if (!empty($profile['religion'])): ?> · <?= htmlspecialchars($profile['religion']); ?><?php endif; ?>
                            <?php if (!empty($profile['marital_status'])): ?> · <?= htmlspecialchars($profile['marital_status']); ?><?php endif; ?>
                        </p>
                    </div>
                    <?php if (($profile['verification_status'] ?? '') === 'Verified'): ?>
                        <span class="profile-verified"><i class="fa-solid fa-circle-check"></i> Verified</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($profile['bio'])): ?>
                    <div class="profile-bio">
                        <?= nl2br(htmlspecialchars($profile['bio'])); ?>
                    </div>
                <?php endif; ?>

                <div class="profile-view-actions">
                    <button type="button" class="profile-action secondary" disabled>
                        <i class="fa-regular fa-bookmark"></i> Bookmark
                    </button>
                    <button type="button" class="profile-action primary" disabled>
                        <i class="fa-solid fa-heart"></i> Send Interest
                    </button>
                </div>
            </div>
        </section>

        <div class="profile-detail-grid">
            <section class="profile-detail-card">
                <div class="detail-card-heading"><i class="fa-solid fa-user"></i><h2>Personal Information</h2></div>
                <div class="detail-list">
                    <div><span>Gender</span><strong><?= htmlspecialchars($profile['gender'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Religion</span><strong><?= htmlspecialchars($profile['religion'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Madhhab</span><strong><?= htmlspecialchars($profile['madhhab'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Education</span><strong><?= htmlspecialchars($profile['highest_education'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Profession</span><strong><?= htmlspecialchars($profile['profession'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Occupation Details</span><strong><?= htmlspecialchars($profile['occupation_details'] ?? 'Not provided'); ?></strong></div>
                </div>
            </section>

            <section class="profile-detail-card">
                <div class="detail-card-heading"><i class="fa-solid fa-ruler-combined"></i><h2>Physical Information</h2></div>
                <div class="detail-list">
                    <div><span>Height</span><strong><?= !empty($profile['height_cm']) ? htmlspecialchars($profile['height_cm']) . ' cm' : 'Not provided'; ?></strong></div>
                    <div><span>Weight</span><strong><?= !empty($profile['weight_kg']) ? htmlspecialchars($profile['weight_kg']) . ' kg' : 'Not provided'; ?></strong></div>
                    <div><span>Complexion</span><strong><?= htmlspecialchars($profile['complexion'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Smoking</span><strong><?= htmlspecialchars($profile['smoking_status'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Prayer</span><strong><?= htmlspecialchars($profile['prayer_status'] ?? 'Not provided'); ?></strong></div>
                    <?php if (($profile['gender'] ?? '') === 'Male'): ?>
                        <div><span>Beard</span><strong><?= htmlspecialchars($profile['beard_status'] ?? 'Not provided'); ?></strong></div>
                    <?php else: ?>
                        <div><span>Hijab</span><strong><?= htmlspecialchars($profile['hijab_status'] ?? 'Not provided'); ?></strong></div>
                    <?php endif; ?>
                    <div><span>Mahram Maintenance</span><strong><?= $profile['mahram_maintained'] === null ? 'Not provided' : ((int)$profile['mahram_maintained'] === 1 ? 'Maintained' : 'Not maintained'); ?></strong></div>
                </div>
            </section>

            <section class="profile-detail-card">
                <div class="detail-card-heading"><i class="fa-solid fa-location-dot"></i><h2>Location</h2></div>
                <div class="detail-list">
                    <div><span>Country</span><strong><?= htmlspecialchars($profile['country'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Division</span><strong><?= htmlspecialchars($profile['division_name'] ?? 'Not provided'); ?></strong></div>
                    <div><span>District</span><strong><?= htmlspecialchars($profile['district_name'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Upazila</span><strong><?= htmlspecialchars($profile['upazila_name'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Area</span><strong><?= htmlspecialchars($profile['area'] ?? 'Not provided'); ?></strong></div>
                </div>
            </section>

            <section class="profile-detail-card">
                <div class="detail-card-heading"><i class="fa-solid fa-people-roof"></i><h2>Family Information</h2></div>
                <div class="detail-list">
                    <div><span>Family Type</span><strong><?= htmlspecialchars($profile['family_type'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Family Status</span><strong><?= htmlspecialchars($profile['family_status'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Father's Profession</span><strong><?= htmlspecialchars($profile['father_profession'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Mother's Profession</span><strong><?= htmlspecialchars($profile['mother_profession'] ?? 'Not provided'); ?></strong></div>
                    <div><span>Siblings</span><strong><?= $profile['siblings'] !== null ? (int)$profile['siblings'] : 'Not provided'; ?></strong></div>
                </div>
            </section>
        </div>

        <div class="profile-privacy-note">
            <i class="fa-solid fa-shield-halved"></i>
            <div>
                <strong>Privacy protected</strong>
                <p>Contact details and sensitive identity information are intentionally not displayed on public profile views.</p>
            </div>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>
