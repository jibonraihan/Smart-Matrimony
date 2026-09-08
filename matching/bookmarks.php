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
include '../includes/header.php';include '../includes/navbar.php';
?><main class="matching-page"><div class="matching-wrap"><a class="back-link" href="../dashboard.php"><i class="fa-solid fa-arrow-left"></i> Dashboard</a><div class="matching-hero"><div><span class="section-kicker">SAVED PROFILES</span><h1>Bookmarks</h1><p>Your private shortlist of profiles you may want to revisit.</p></div></div><?php if(($_GET['status'] ?? '') === 'removed'): ?><div class="matching-notice matching-notice-success">Bookmark removed successfully.</div><?php elseif(($_GET['status'] ?? '') === 'error'): ?><div class="matching-notice matching-notice-error">Unable to remove the bookmark right now.</div><?php endif; ?><?php if(!$rows): ?><div class="matching-empty"><i class="fa-regular fa-bookmark"></i><h3>No bookmarks yet</h3><p>Use the bookmark button on a profile to save it here.</p><a href="../dashboard.php#partner-search" class="primary-btn">Find Profiles</a></div><?php else: ?><div class="bookmark-grid"><?php foreach($rows as $r): $photo=$r['photo']??'';$can=$photo!==''&&$r['photo_visibility']==='Everyone'; ?><article class="bookmark-card"><div class="bookmark-photo"><?php if($can): ?><img src="<?= BASE_URL ?>uploads/profile/<?= htmlspecialchars($photo) ?>" alt="Profile photo"><?php else:?><i class="fa-solid fa-user"></i><?php endif;?></div><div class="bookmark-body"><h3><?= htmlspecialchars(trim(($r['first_name']??'').' '.($r['last_name']??''))) ?></h3><span class="bookmark-profile-id"><i class="fa-solid fa-id-card"></i> SM-<?= str_pad((string)((int)$r['bookmarked_user_id']), 6, '0', STR_PAD_LEFT) ?></span><p><?= htmlspecialchars($r['religion']??'') ?><?= !empty($r['profession'])?' · '.htmlspecialchars($r['profession']):'' ?></p><div class="bookmark-actions"><a class="secondary-btn" href="../profile/view_profile.php?user_id=<?= (int)$r['bookmarked_user_id'] ?>">View Profile</a><form method="post" action="bookmarks.php"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="bookmarked_user_id" value="<?= (int)$r['bookmarked_user_id'] ?>"><button type="submit" name="remove_bookmark" value="1" class="danger-btn">Remove</button></form></div></div></article><?php endforeach;?></div><?php endif;?></div></main><?php include '../includes/footer.php'; ?>
