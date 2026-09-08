<?php
// Use the dedicated Authenticator session established by login.php.
if (session_status() === PHP_SESSION_NONE) {
    session_name('SMART_AUTH_SESSION');
    session_start();
}

require_once '../config/db.php';

if (empty($_SESSION['authenticator_user_id'])) {
    header('Location: login.php');
    exit;
}
$authenticator_id = (int)$_SESSION['authenticator_user_id'];

$auth_stmt = mysqli_prepare($conn, "SELECT user_id, first_name, last_name, role, account_status FROM users WHERE user_id=? LIMIT 1");
mysqli_stmt_bind_param($auth_stmt, 'i', $authenticator_id);
mysqli_stmt_execute($auth_stmt);
$auth = mysqli_fetch_assoc(mysqli_stmt_get_result($auth_stmt));
mysqli_stmt_close($auth_stmt);
if (!$auth || $auth['role'] !== 'Authenticator' || $auth['account_status'] !== 'Active') { header('Location: ../login.php'); exit; }

if (empty($_SESSION['auth_csrf'])) $_SESSION['auth_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['auth_csrf'];
$profile_id = (int)($_GET['id'] ?? $_POST['profile_id'] ?? 0);
if ($profile_id < 1) { header('Location: dashboard.php'); exit; }

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verification_action'])) {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        $error = 'Security check failed. Please try again.';
    } else {
        $action = $_POST['verification_action'];
        $remarks = trim($_POST['remarks'] ?? '');
        if (!in_array($action, ['Verified','Rejected'], true)) {
            $error = 'Invalid verification action.';
        } elseif ($action === 'Rejected' && $remarks === '') {
            $error = 'Please provide a reason when rejecting a profile.';
        } else {
            mysqli_begin_transaction($conn);
            try {
                $check = mysqli_prepare($conn, "SELECT up.profile_id, up.user_id, up.verification_status FROM user_profiles up INNER JOIN users u ON u.user_id=up.user_id WHERE up.profile_id=? AND u.role='User' LIMIT 1");
                mysqli_stmt_bind_param($check, 'i', $profile_id);
                mysqli_stmt_execute($check);
                $target = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
                mysqli_stmt_close($check);
                if (!$target) throw new Exception('Profile not found or not eligible for verification.');

                $update = mysqli_prepare($conn, "UPDATE user_profiles SET verification_status=? WHERE profile_id=?");
                mysqli_stmt_bind_param($update, 'si', $action, $profile_id);
                if (!mysqli_stmt_execute($update)) throw new Exception('Could not update verification status.');
                mysqli_stmt_close($update);

                $log = mysqli_prepare($conn, "INSERT INTO verification_logs (authenticator_id, profile_id, action, remarks) VALUES (?,?,?,?)");
                mysqli_stmt_bind_param($log, 'iiss', $authenticator_id, $profile_id, $action, $remarks);
                if (!mysqli_stmt_execute($log)) throw new Exception('Could not save verification history.');
                mysqli_stmt_close($log);

                mysqli_commit($conn);
                $notice = "Profile {$action}. Verification history has been recorded.";
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                $error = $e->getMessage();
            }
        }
    }
}

$stmt = mysqli_prepare($conn, "SELECT up.*, u.email, u.mobile, u.account_status, u.created_at AS account_created_at, d.name_bn AS location_division_name, dist.name_bn AS location_district_name, uz.name_bn AS location_upazila_name FROM user_profiles up INNER JOIN users u ON u.user_id=up.user_id LEFT JOIN divisions d ON d.id=up.division_id LEFT JOIN districts dist ON dist.id=up.district_id LEFT JOIN upazilas uz ON uz.id=up.upazila_id WHERE up.profile_id=? AND u.role='User' LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $profile_id);
mysqli_stmt_execute($stmt);
$profile = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$profile) { header('Location: dashboard.php'); exit; }

$voice_visibility = 'Not provided';
$video_visibility = 'Not provided';
$media_stmt = mysqli_prepare($conn, "SELECT media_type, visibility FROM profile_media WHERE user_id=? AND status='Active' AND media_type IN ('Voice Introduction','Video Introduction') ORDER BY media_id DESC");
if ($media_stmt) {
    mysqli_stmt_bind_param($media_stmt, 'i', $profile['user_id']);
    mysqli_stmt_execute($media_stmt);
    $media_result = mysqli_stmt_get_result($media_stmt);
    while ($media_result && ($media_row = mysqli_fetch_assoc($media_result))) {
        if ($media_row['media_type'] === 'Voice Introduction' && $voice_visibility === 'Not provided') $voice_visibility = (string)$media_row['visibility'];
        if ($media_row['media_type'] === 'Video Introduction' && $video_visibility === 'Not provided') $video_visibility = (string)$media_row['visibility'];
    }
    mysqli_stmt_close($media_stmt);
}

$health = null;
$health_stmt = mysqli_prepare($conn, "SELECT * FROM health_profiles WHERE user_id=? LIMIT 1");
mysqli_stmt_bind_param($health_stmt, 'i', $profile['user_id']);
mysqli_stmt_execute($health_stmt);
$health = mysqli_fetch_assoc(mysqli_stmt_get_result($health_stmt));
mysqli_stmt_close($health_stmt);

$conditions = [];
$condition_stmt = mysqli_prepare($conn, "SELECT hc.condition_name, uhc.status, uhc.severity, uhc.diagnosed_date, uhc.treatment_status, uhc.disclosure_required, uhc.remarks FROM user_health_conditions uhc INNER JOIN health_conditions hc ON hc.condition_id=uhc.condition_id WHERE uhc.user_id=? ORDER BY hc.condition_name");
mysqli_stmt_bind_param($condition_stmt, 'i', $profile['user_id']);
mysqli_stmt_execute($condition_stmt);
$condition_result = mysqli_stmt_get_result($condition_stmt);
while ($condition_result && ($r = mysqli_fetch_assoc($condition_result))) $conditions[] = $r;
mysqli_stmt_close($condition_stmt);

$traits = [];
$trait_stmt = mysqli_prepare($conn, "SELECT tq.question_text, uta.answer, uta.answered_at FROM user_trait_answers uta INNER JOIN trait_questions tq ON tq.question_id=uta.question_id WHERE uta.user_id=? ORDER BY tq.display_order");
mysqli_stmt_bind_param($trait_stmt, 'i', $profile['user_id']);
mysqli_stmt_execute($trait_stmt);
$trait_result = mysqli_stmt_get_result($trait_stmt);
while ($trait_result && ($r = mysqli_fetch_assoc($trait_result))) $traits[] = $r;
mysqli_stmt_close($trait_stmt);

$logs = [];
$log_stmt = mysqli_prepare($conn, "SELECT vl.action, vl.remarks, vl.action_at, u.first_name, u.last_name FROM verification_logs vl INNER JOIN users u ON u.user_id=vl.authenticator_id WHERE vl.profile_id=? ORDER BY vl.action_at DESC LIMIT 10");
mysqli_stmt_bind_param($log_stmt, 'i', $profile_id);
mysqli_stmt_execute($log_stmt);
$log_result = mysqli_stmt_get_result($log_stmt);
while ($log_result && ($r = mysqli_fetch_assoc($log_result))) $logs[] = $r;
mysqli_stmt_close($log_stmt);

function esc($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'); }
function val($value): string { $v = trim((string)($value ?? '')); return $v === '' ? 'Not provided' : esc($v); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Review Profile | Smart Matrimony</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/authenticator-verification.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="auth-page">
<header class="auth-header compact-header">
  <div><span class="eyebrow">PROFILE REVIEW</span><h1>Verification Review</h1><p>Authenticator: <?= esc($auth['first_name'].' '.$auth['last_name']) ?></p></div>
  <div class="auth-header-actions"><a href="dashboard.php" class="header-btn"><i class="fa-solid fa-arrow-left"></i> Verification Center</a><a href="logout.php" class="header-btn danger">Logout</a></div>
</header>
<main class="auth-container review-container">
<?php if ($notice): ?><div class="flash success"><i class="fa-solid fa-circle-check"></i><?= esc($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash error"><i class="fa-solid fa-triangle-exclamation"></i><?= esc($error) ?></div><?php endif; ?>

<section class="review-hero">
  <div class="review-photo">
    <?php if (!empty($profile['photo'])): ?><img src="../uploads/profile/<?= esc($profile['photo']) ?>" alt="Profile photo"><?php else: ?><div class="photo-placeholder"><i class="fa-solid fa-user"></i></div><?php endif; ?>
  </div>
  <div class="review-main">
    <span class="id-badge">SM-<?= str_pad((string)$profile['user_id'],6,'0',STR_PAD_LEFT) ?></span>
    <h2><?= esc(trim($profile['first_name'].' '.$profile['last_name'])) ?></h2>
    <p><?= esc($profile['gender']) ?> · <?= val($profile['religion']) ?> · <?= esc($profile['email']) ?> · <?= esc($profile['mobile']) ?></p>
    <div class="review-badges"><span class="status-pill <?= strtolower($profile['verification_status']) ?>"><?= esc($profile['verification_status']) ?></span><span class="neutral-pill">Account: <?= esc($profile['account_status']) ?></span><span class="neutral-pill">Visibility: <?= esc($profile['profile_visibility']) ?></span></div>
  </div>
</section>

<section class="info-grid">
  <div class="info-card"><h3><i class="fa-solid fa-user"></i> Personal Information</h3><div class="data-grid">
    <div><span>Gender</span><strong><?= val($profile['gender']) ?></strong></div><div><span>Date of birth</span><strong><?= val($profile['date_of_birth']) ?></strong></div><div><span>Age</span><strong><?= !empty($profile['date_of_birth']) ? (new DateTime($profile['date_of_birth']))->diff(new DateTime('today'))->y.' years' : 'Not provided' ?></strong></div><div><span>Marital status</span><strong><?= val($profile['marital_status']) ?></strong></div><div><span>Religion</span><strong><?= val($profile['religion']) ?></strong></div><div><span>Madhhab</span><strong><?= val($profile['madhhab']) ?></strong></div><div><span>Education</span><strong><?= val($profile['highest_education']) ?></strong></div><div><span>Profession</span><strong><?= val($profile['profession']) ?></strong></div><div><span>Occupation details</span><strong><?= val($profile['occupation_details']) ?></strong></div><div><span>Monthly income</span><strong><?= $profile['monthly_income'] !== null && $profile['monthly_income'] !== '' ? '৳ '.number_format((float)$profile['monthly_income'],2) : 'Not provided' ?></strong></div>
  </div></div>
  <div class="info-card"><h3><i class="fa-solid fa-ruler-combined"></i> Physical & Lifestyle</h3><div class="data-grid">
    <div><span>Height</span><strong><?= $profile['height_cm'] ? esc($profile['height_cm']).' cm' : 'Not provided' ?></strong></div><div><span>Weight</span><strong><?= $profile['weight_kg'] ? esc($profile['weight_kg']).' kg' : 'Not provided' ?></strong></div><div><span>Complexion</span><strong><?= val($profile['complexion']) ?></strong></div><div><span>Smoking</span><strong><?= val($profile['smoking_status']) ?></strong></div><div><span>Prayer</span><strong><?= val($profile['prayer_status']) ?></strong></div><div><span>Quran reading</span><strong><?= val($profile['quran_reading']) ?></strong></div><div><span>Fasting</span><strong><?= val($profile['fasting_status']) ?></strong></div><div><span>Religious practice</span><strong><?= val($profile['religious_practice']) ?></strong></div><div><span>Islamic knowledge</span><strong><?= val($profile['islamic_knowledge']) ?></strong></div><div><span>Halal lifestyle</span><strong><?= val($profile['halal_lifestyle']) ?></strong></div><div><span>Islamic activities</span><strong><?= val($profile['islamic_activities']) ?></strong></div><div><span>Mahram</span><strong><?= $profile['mahram_maintained'] === null ? 'Not provided' : ($profile['mahram_maintained'] ? 'Maintained' : 'Not maintained') ?></strong></div><div><span>Beard</span><strong><?= val($profile['beard_status']) ?></strong></div><div><span>Hijab</span><strong><?= val($profile['hijab_status']) ?></strong></div><div><span>Hijab details</span><strong><?= val($profile['hijab_details']) ?></strong></div>
  </div></div>
  <div class="info-card full"><h3><i class="fa-solid fa-location-dot"></i> Family & Location</h3><div class="data-grid">
    <div><span>Father</span><strong><?= val($profile['father_name']) ?> · <?= val($profile['father_profession']) ?></strong></div><div><span>Mother</span><strong><?= val($profile['mother_name']) ?> · <?= val($profile['mother_profession']) ?></strong></div><div><span>Guardian</span><strong><?= val($profile['guardian_name']) ?> · <?= val($profile['guardian_relation']) ?></strong></div><div><span>Guardian mobile</span><strong><?= val($profile['guardian_contact']) ?></strong></div><div><span>Family type</span><strong><?= val($profile['family_type']) ?></strong></div><div><span>Family status</span><strong><?= val($profile['family_status']) ?></strong></div><div><span>Living with family</span><strong><?= val($profile['living_with_family']) ?></strong></div><div><span>Family religious lifestyle</span><strong><?= val($profile['family_religious_lifestyle']) ?></strong></div><div><span>Family pordah environment</span><strong><?= val($profile['family_purdah_environment']) ?></strong></div><div><span>Brothers</span><strong><?= $profile['brothers_count'] === null ? 'Not provided' : esc($profile['brothers_count']) ?></strong></div><div><span>Sisters</span><strong><?= $profile['sisters_count'] === null ? 'Not provided' : esc($profile['sisters_count']) ?></strong></div><div><span>Paternal uncles</span><strong><?= $profile['paternal_uncles_count'] === null ? 'Not provided' : esc($profile['paternal_uncles_count']) ?></strong></div><div><span>Paternal aunts</span><strong><?= $profile['paternal_aunts_count'] === null ? 'Not provided' : esc($profile['paternal_aunts_count']) ?></strong></div><div><span>Siblings</span><strong><?= $profile['siblings'] === null ? 'Not provided' : esc($profile['siblings']) ?></strong></div><div><span>Location</span><strong><?= esc(implode(', ', array_filter([$profile['area'], $profile['location_upazila_name'], $profile['location_district_name'], $profile['location_division_name']]))) ?: 'Not provided' ?></strong></div><div><span>Area type</span><strong><?= val($profile['area_type']) ?></strong></div><div><span>Residence type</span><strong><?= val($profile['residence_type']) ?></strong></div><div><span>Post office</span><strong><?= val($profile['post_office']) ?></strong></div><div><span>Postal code</span><strong><?= val($profile['postal_code']) ?></strong></div><div><span>Permanent hometown</span><strong><?= val($profile['permanent_hometown']) ?></strong></div><div><span>Address</span><strong><?= val($profile['address_details']) ?></strong></div>
  </div></div>
  <div class="info-card full"><h3><i class="fa-solid fa-notes-medical"></i> Health Information</h3>
    <?php if ($health): ?><div class="data-grid"><div><span>Blood group</span><strong><?= val($health['blood_group']) ?></strong></div><div><span>Disability</span><strong><?= val($health['disability_status']) ?></strong></div><div><span>Allergies</span><strong><?= val($health['allergies']) ?></strong></div><div><span>Current medications</span><strong><?= val($health['current_medications']) ?></strong></div><div class="wide"><span>Medical notes</span><strong><?= val($health['medical_notes']) ?></strong></div></div><?php else: ?><p class="muted">No separate health profile record was submitted.</p><?php endif; ?>
    <?php if ($conditions): ?><div class="condition-list"><h4>Declared conditions</h4><?php foreach($conditions as $c): ?><div class="condition-row"><strong><?= esc($c['condition_name']) ?></strong><span><?= esc($c['status']) ?><?= $c['severity'] ? ' · '.esc($c['severity']) : '' ?></span><small><?= $c['remarks'] ? esc($c['remarks']) : 'No remarks' ?><?= $c['diagnosed_date'] ? ' · Diagnosed: '.esc($c['diagnosed_date']) : '' ?><?= $c['treatment_status'] ? ' · Treatment: '.esc($c['treatment_status']) : '' ?><?= isset($c['disclosure_required']) ? ' · Disclosure: '.($c['disclosure_required'] ? 'Required' : 'Not required') : '' ?></small></div><?php endforeach; ?></div><?php endif; ?>
  </div>
  <div class="info-card full"><h3><i class="fa-solid fa-comment-dots"></i> Bio & Trait Answers</h3><div class="bio-box"><?= $profile['bio'] ? nl2br(esc($profile['bio'])) : 'No bio provided.' ?></div>
    <?php if ($traits): ?><div class="trait-list"><?php foreach($traits as $t): ?><div><span><?= esc($t['question_text']) ?></span><strong><?= esc($t['answer']) ?></strong></div><?php endforeach; ?></div><?php else: ?><p class="muted">No trait answers submitted.</p><?php endif; ?>
  </div>
  <div class="info-card full"><h3><i class="fa-solid fa-shield-halved"></i> Privacy & Verification Data</h3><div class="data-grid"><div><span>NID / Birth Certificate No</span><strong><?= val($profile['nid_number']) ?></strong></div><div><span>Photo visibility</span><strong><?= val($profile['photo_visibility']) ?></strong></div><div><span>Voice visibility</span><strong><?= val($voice_visibility) ?></strong></div><div><span>Video visibility</span><strong><?= val($video_visibility) ?></strong></div><div><span>Profile visibility</span><strong><?= val($profile['profile_visibility']) ?></strong></div><div><span>Profile created</span><strong><?= esc(date('d M Y, h:i A', strtotime($profile['created_at']))) ?></strong></div><div><span>Last updated</span><strong><?= esc(date('d M Y, h:i A', strtotime($profile['updated_at']))) ?></strong></div></div></div>
</section>

<section class="decision-card">
  <div class="section-heading"><div><span class="section-label">FINAL DECISION</span><h2>Verification Action</h2></div><span class="result-count">Current: <?= esc($profile['verification_status']) ?></span></div>
  <form method="POST" class="decision-form">
    <input type="hidden" name="csrf" value="<?= esc($csrf) ?>"><input type="hidden" name="profile_id" value="<?= $profile_id ?>">
    <textarea name="remarks" maxlength="1000" placeholder="Verification remarks<?= $profile['verification_status']==='Rejected' ? ' (required for rejection)' : ' (optional)' ?>"></textarea>
    <div class="decision-actions"><button class="approve" name="verification_action" value="Verified"><i class="fa-solid fa-circle-check"></i> Verify Profile</button><button class="reject" name="verification_action" value="Rejected"><i class="fa-solid fa-circle-xmark"></i> Reject Profile</button></div>
  </form>
</section>

<section class="history-card"><div class="section-heading"><div><span class="section-label">AUDIT TRAIL</span><h2>Verification History</h2></div></div>
<?php if (!$logs): ?><p class="muted">No previous verification action has been recorded.</p><?php else: ?><div class="history-list"><?php foreach($logs as $log): ?><div class="history-row"><span class="status-pill <?= strtolower($log['action']) ?>"><?= esc($log['action']) ?></span><div><strong><?= esc($log['first_name'].' '.$log['last_name']) ?></strong><p><?= $log['remarks'] ? esc($log['remarks']) : 'No remarks' ?></p></div><time><?= esc(date('d M Y, h:i A', strtotime($log['action_at']))) ?></time></div><?php endforeach; ?></div><?php endif; ?>
</section>
</main>
</body></html>
