<?php
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$user_id=(int)$_SESSION['user_id'];

/*
 * Bookmark-page removal is handled locally instead of routing through the
 * general profile action endpoint. This keeps the user on this page and
 * avoids unrelated profile-availability checks causing a dashboard redirect.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_bookmark'])) {
    $posted_csrf = (string)($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['matching_csrf'])) {
        $_SESSION['matching_csrf'] = bin2hex(random_bytes(32));
    }

    if (!hash_equals($_SESSION['matching_csrf'], $posted_csrf)) {
        header('Location: bookmarks.php?status=error');
        exit;
    }

    $target_id = (int)($_POST['bookmarked_user_id'] ?? 0);

    if ($target_id > 0) {
        $remove = mysqli_prepare(
            $conn,
            "DELETE FROM bookmarks WHERE user_id=? AND bookmarked_user_id=?"
        );

        if ($remove) {
            mysqli_stmt_bind_param($remove, 'ii', $user_id, $target_id);
            $ok = mysqli_stmt_execute($remove);
            mysqli_stmt_close($remove);

            header('Location: bookmarks.php?status=' . ($ok ? 'removed' : 'error'));
            exit;
        }
    }

    header('Location: bookmarks.php?status=error');
    exit;
}

$user_id=(int)$_SESSION['user_id']; if(empty($_SESSION['matching_csrf']))$_SESSION['matching_csrf']=bin2hex(random_bytes(32)); $csrf=$_SESSION['matching_csrf']; $page_css='assets/css/matching.css';
$rows=[]; $stmt=mysqli_prepare($conn,"SELECT b.bookmark_id, b.bookmarked_user_id, b.created_at, up.first_name, up.last_name, up.photo, up.photo_visibility, up.verification_status, up.profession, up.religion, up.date_of_birth FROM bookmarks b JOIN user_profiles up ON up.user_id=b.bookmarked_user_id JOIN users u ON u.user_id=up.user_id WHERE b.user_id=? AND u.account_status='Active' AND up.profile_visibility='Public' ORDER BY b.created_at DESC"); mysqli_stmt_bind_param($stmt,'i',$user_id); mysqli_stmt_execute($stmt);$res=mysqli_stmt_get_result($stmt);while($r=mysqli_fetch_assoc($res))$rows[]=$r;mysqli_stmt_close($stmt);
// Translate known profile values for Bengali UI only; keep stored values and logic unchanged.
function bookmark_display_value($value, $translations) {
    $value = (string)($value ?? '');
    return sm_text($value, $translations[$value] ?? $value);
}
$bookmark_religion_bn = [
    'Islam' => 'ইসলাম', 'Muslim' => 'মুসলিম', 'Hinduism' => 'হিন্দুধর্ম',
    'Hindu' => 'হিন্দু', 'Christianity' => 'খ্রিষ্টধর্ম', 'Christian' => 'খ্রিষ্টান',
    'Buddhism' => 'বৌদ্ধধর্ম', 'Buddhist' => 'বৌদ্ধ', 'Other' => 'অন্যান্য'
];
$bookmark_profession_bn = [
    'Student' => 'শিক্ষার্থী', 'Self' => 'নিজে', 'Teacher' => 'শিক্ষক',
    'Doctor' => 'চিকিৎসক', 'Engineer' => 'প্রকৌশলী', 'Business' => 'ব্যবসা',
    'Housewife' => 'গৃহিণী', 'Government Job' => 'সরকারি চাকরি',
    'Private Job' => 'বেসরকারি চাকরি', 'Unemployed' => 'বেকার',
    'Freelancer' => 'ফ্রিল্যান্সার', 'Other' => 'অন্যান্য'
];
include '../includes/header.php';include '../includes/navbar.php';
?><main class="matching-page matching-standalone-header"><div class="matching-wrap">
<a class="back-link" href="../dashboard.php"><i class="fa-solid fa-arrow-left"></i> <?= sm_text('Dashboard', 'ড্যাশবোর্ড') ?></a>
<section class="matching-hero bookmark-dashboard-hero">
  <div class="matching-hero-copy">
    <span class="section-kicker"><?= sm_text('YOUR SAVED PROFILES', 'আপনার সংরক্ষিত প্রোফাইল') ?></span>
    <h1><?= sm_text('Bookmarks', 'সংরক্ষিত প্রোফাইল') ?></h1>
    <p><?= sm_text('Keep the profiles that caught your attention in one private shortlist, ready to revisit anytime.', 'আপনার পছন্দের প্রোফাইলগুলো এখানে ব্যক্তিগতভাবে সংরক্ষণ করুন, যাতে যেকোনো সময় আবার দেখতে পারেন।') ?></p>
    <div class="matching-hero-badges">
      <span><i class="fa-regular fa-bookmark"></i> <?= sm_text('Saved for later', 'পরে দেখার জন্য সংরক্ষিত') ?></span>
      <span><i class="fa-solid fa-lock"></i> <?= sm_text('Private to you', 'শুধু আপনার জন্য ব্যক্তিগত') ?></span>
    </div>
  </div>
  <div class="matching-hero-visual bookmark-hero-visual" aria-hidden="true">
    <div class="hero-orbit hero-orbit-one"></div>
    <div class="hero-orbit hero-orbit-two"></div>
    <div class="hero-icon-circle"><i class="fa-solid fa-bookmark"></i></div>
    <span class="hero-floating-card hero-floating-top"><i class="fa-solid fa-heart"></i> <?= sm_text('Worth revisiting', 'আবার দেখে নেওয়ার মতো') ?></span>
    <span class="hero-floating-card hero-floating-bottom"><i class="fa-solid fa-shield-heart"></i> <?= sm_text('Your shortlist', 'আপনার পছন্দের তালিকা') ?></span>
  </div>
</section><?php if(($_GET['status'] ?? '') === 'removed'): ?><div class="matching-notice matching-notice-success"><?= sm_text('Bookmark removed successfully.', 'প্রোফাইলটি সংরক্ষিত তালিকা থেকে সরানো হয়েছে।') ?></div><?php elseif(($_GET['status'] ?? '') === 'error'): ?><div class="matching-notice matching-notice-error"><?= sm_text('Unable to remove the bookmark right now.', 'এই মুহূর্তে প্রোফাইলটি সরানো যাচ্ছে না।') ?></div><?php endif; ?><?php if(!$rows): ?><div class="matching-empty"><i class="fa-regular fa-bookmark"></i><h3><?= sm_text('No bookmarks yet', 'এখনো কোনো প্রোফাইল সংরক্ষণ করা হয়নি') ?></h3><p><?= sm_text('Use the bookmark button on a profile to save it here.', 'এখানে প্রোফাইল সংরক্ষণ করতে সেই প্রোফাইলের সংরক্ষণ বাটনে চাপ দিন।') ?></p><a href="../dashboard.php#partner-search" class="primary-btn"><?= sm_text('Find Profiles', 'প্রোফাইল খুঁজুন') ?></a></div><?php else: ?><div class="bookmark-grid"><?php foreach($rows as $r): $photo=$r['photo']??'';$can=$photo!==''&&$r['photo_visibility']==='Everyone'; ?><article class="bookmark-card"><div class="bookmark-photo"><?php if($can): ?><img src="<?= BASE_URL ?>uploads/profile/<?= htmlspecialchars($photo) ?>" alt="<?= sm_text('Profile photo', 'প্রোফাইলের ছবি') ?>"><?php else:?><i class="fa-solid fa-user"></i><?php endif;?></div><div class="bookmark-body"><h3><?= htmlspecialchars(trim(($r['first_name']??'').' '.($r['last_name']??''))) ?></h3><span class="bookmark-profile-id"><i class="fa-solid fa-id-card"></i> SM-<?= str_pad((string)((int)$r['bookmarked_user_id']), 6, '0', STR_PAD_LEFT) ?></span><p><?= htmlspecialchars(bookmark_display_value($r['religion'] ?? '', $bookmark_religion_bn)) ?><?= !empty($r['profession']) ? ' · ' . htmlspecialchars(bookmark_display_value($r['profession'], $bookmark_profession_bn)) : '' ?></p><div class="bookmark-actions"><a class="secondary-btn" href="../profile/view_profile.php?user_id=<?= (int)$r['bookmarked_user_id'] ?>"><?= sm_text('View Profile', 'প্রোফাইল দেখুন') ?></a><form method="post" action="bookmarks.php"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="bookmarked_user_id" value="<?= (int)$r['bookmarked_user_id'] ?>"><button type="submit" name="remove_bookmark" value="1" class="danger-btn"><?= sm_text('Remove', 'সরিয়ে দিন') ?></button></form></div></div></article><?php endforeach;?></div><?php endif;?></div></main><?php include '../includes/footer.php'; ?>
