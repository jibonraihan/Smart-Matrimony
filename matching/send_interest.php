<?php
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$target = (int) ($_GET['user_id'] ?? 0);

if ($target <= 0 || $target === $user_id) {
    header('Location: ../dashboard.php');
    exit;
}

$return_url = trim($_GET['return_url'] ?? '');
$return_path = parse_url($return_url, PHP_URL_PATH);
if ($return_path !== 'dashboard.php') {
    $return_url = '../dashboard.php#partner-search';
}

if (empty($_SESSION['matching_csrf'])) {
    $_SESSION['matching_csrf'] = bin2hex(random_bytes(32));
}

$stmt = mysqli_prepare($conn, "SELECT u.gender, up.user_id, up.first_name, up.last_name, up.photo, up.photo_visibility, up.religion, up.profession, up.verification_status FROM user_profiles up JOIN users u ON u.user_id=up.user_id WHERE up.user_id=? AND u.role='User' AND u.account_status='Active' AND up.profile_visibility='Public' LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $target);
mysqli_stmt_execute($stmt);
$profile = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$profile) {
    header('Location: ' . $return_url);
    exit;
}

$viewer_stmt = mysqli_prepare($conn, "SELECT gender FROM users WHERE user_id=? LIMIT 1");
mysqli_stmt_bind_param($viewer_stmt, 'i', $user_id);
mysqli_stmt_execute($viewer_stmt);
$viewer_row = mysqli_fetch_assoc(mysqli_stmt_get_result($viewer_stmt));
mysqli_stmt_close($viewer_stmt);

$can_send_interest = in_array($viewer_row['gender'] ?? '', ['Male', 'Female'], true)
    && in_array($profile['gender'] ?? '', ['Male', 'Female'], true)
    && ($viewer_row['gender'] ?? '') !== ($profile['gender'] ?? '');

$page_css = 'assets/css/matching.css';
include '../includes/header.php';
include '../includes/navbar.php';
?>
<main class="matching-page">
    <div class="matching-wrap narrow">
        <a class="back-link" href="<?= htmlspecialchars($return_url); ?>"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <section class="interest-form-card">
            <span class="section-kicker">SEND INTEREST</span>
            <h1>Express your interest</h1>
            <p>Send a respectful message to <strong><?= htmlspecialchars(trim($profile['first_name'].' '.$profile['last_name'])) ?></strong>. Keep it sincere and appropriate.</p>
            <?php if ($can_send_interest): ?>
                <form method="post" action="send_interest_action.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['matching_csrf']) ?>">
                    <input type="hidden" name="user_id" value="<?= $target ?>">
                    <input type="hidden" name="action" value="send_interest">
                    <input type="hidden" name="return_to" value="profile">
                    <textarea id="interest_message" name="interest_message" maxlength="300" placeholder="I would like to express my interest in your profile..."></textarea>
                    <div class="form-note">Maximum 300 characters.</div>
                    <button class="primary-btn" type="submit"><i class="fa-solid fa-heart"></i> Send Interest</button>
                </form>
            <?php else: ?>
                <div class="interest-unavailable"><i class="fa-solid fa-ban"></i><strong>Interest cannot be sent</strong><p>You can view and bookmark this profile, but interest requests can only be sent to the opposite gender.</p></div>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php include '../includes/footer.php'; ?>
