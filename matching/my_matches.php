<?php
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

$user_id = (int) $_SESSION['user_id'];
if (empty($_SESSION['matching_csrf'])) $_SESSION['matching_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['matching_csrf'];
$page_css = 'assets/css/matching.css';

$tab = $_GET['tab'] ?? 'all';
$allowed_tabs = ['all','received','sent','matches','history'];
if (!in_array($tab, $allowed_tabs, true)) $tab = 'all';

$counts = ['all'=>0,'received'=>0,'sent'=>0,'matches'=>0,'history'=>0];
$count_stmt = mysqli_prepare($conn, "SELECT
    COUNT(*) AS all_count,
    SUM(CASE WHEN receiver_user_id=? AND status='Pending' AND relationship_active=1 THEN 1 ELSE 0 END) AS received_count,
    SUM(CASE WHEN sender_user_id=? AND status='Pending' AND relationship_active=1 THEN 1 ELSE 0 END) AS sent_count,
    SUM(CASE WHEN status='Accepted' AND relationship_active=1 THEN 1 ELSE 0 END) AS match_count,
    SUM(CASE WHEN relationship_active=0 THEN 1 ELSE 0 END) AS history_count
    FROM matches WHERE sender_user_id=? OR receiver_user_id=?");
if ($count_stmt) {
    mysqli_stmt_bind_param($count_stmt, 'iiii', $user_id, $user_id, $user_id, $user_id);
    mysqli_stmt_execute($count_stmt);
    $c = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt));
    $counts = [
        'all'=>(int)($c['all_count']??0), 'received'=>(int)($c['received_count']??0),
        'sent'=>(int)($c['sent_count']??0), 'matches'=>(int)($c['match_count']??0),
        'history'=>(int)($c['history_count']??0)
    ];
    mysqli_stmt_close($count_stmt);
}

$where = '(m.sender_user_id=? OR m.receiver_user_id=?)';
$params = [$user_id, $user_id]; $types = 'ii';
if ($tab === 'received') { $where .= " AND m.receiver_user_id=? AND m.status='Pending' AND m.relationship_active=1"; $params[]=$user_id; $types.='i'; }
elseif ($tab === 'sent') { $where .= " AND m.sender_user_id=? AND m.status='Pending' AND m.relationship_active=1"; $params[]=$user_id; $types.='i'; }
elseif ($tab === 'matches') { $where .= " AND m.status='Accepted' AND m.relationship_active=1"; }
elseif ($tab === 'history') { $where .= " AND m.relationship_active=0"; }

$sql = "SELECT m.*, u.gender AS other_gender,
    CONCAT(up.first_name,' ',COALESCE(up.last_name,'')) AS other_name,
    up.photo, up.photo_visibility, up.profession, up.religion, up.date_of_birth,
    up.madhhab, up.height_cm, up.area, up.post_office, up.postal_code,
    up.country, up.division_id, up.district_id, up.upazila_id,
    up.profile_visibility,
    dv.name_en AS division_name, dt.name_en AS district_name, uz.name_en AS upazila_name
    FROM matches m
    JOIN users u ON u.user_id = CASE WHEN m.sender_user_id=? THEN m.receiver_user_id ELSE m.sender_user_id END
    JOIN user_profiles up ON up.user_id=u.user_id
    LEFT JOIN divisions dv ON dv.id=up.division_id
    LEFT JOIN districts dt ON dt.id=up.district_id
    LEFT JOIN upazilas uz ON uz.id=up.upazila_id
    WHERE $where
    ORDER BY CASE WHEN m.status='Pending' AND m.receiver_user_id=? THEN 0 WHEN m.status='Accepted' THEN 1 ELSE 2 END, m.created_at DESC";

// The CASE join and priority both need the viewer id before the WHERE parameters.
$bind_values = array_merge([$user_id], $params, [$user_id]);
$bind_types = 'i' . $types . 'i';
$stmt = mysqli_prepare($conn, $sql);
$rows=[];
if ($stmt) {
    mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_values);
    mysqli_stmt_execute($stmt);
    $res=mysqli_stmt_get_result($stmt);
    while($r=mysqli_fetch_assoc($res)) $rows[]=$r;
    mysqli_stmt_close($stmt);
}

function match_age($dob) { if (!$dob) return ''; try { return (new DateTime($dob))->diff(new DateTime('today'))->y; } catch (Exception $e) { return ''; } }
function public_profile_id($id) { return 'SM-' . str_pad((string)((int)$id), 6, '0', STR_PAD_LEFT); }
function match_height($cm) {
    if ($cm === null || $cm === '' || !is_numeric($cm) || (float)$cm <= 0) return null;
    $total_inches = (float)$cm / 2.54;
    $feet = (int) floor($total_inches / 12);
    $inches = (int) round($total_inches - ($feet * 12));
    if ($inches === 12) { $feet++; $inches = 0; }
    return $feet . ' ft ' . $inches . ' in';
}
function match_location($r) {
    $parts = [];
    foreach (['area','upazila_name','district_name','division_name'] as $key) {
        $value = trim((string)($r[$key] ?? ''));
        if ($value !== '' && !in_array($value, $parts, true)) $parts[] = $value;
    }
    return $parts ? implode(', ', $parts) : null;
}

include '../includes/header.php'; include '../includes/navbar.php';
?>
<main class="matching-page"><div class="matching-wrap">
<a class="back-link" href="../dashboard.php"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
<section class="matching-hero match-dashboard-hero">
  <div><span class="section-kicker">PROFILE INTERACTION</span><h1>My Matches & Interests</h1><p>Keep track of requests, accepted matches, and past interactions in one place.</p></div>
  <div class="match-hero-icon"><i class="fa-solid fa-heart-circle-check"></i></div>
</section>

<?php if (isset($_GET['message'])): ?><div class="matching-alert <?= ($_GET['type']??'')==='success'?'success':(($_GET['type']??'')==='info'?'info':'error'); ?>"><?= htmlspecialchars($_GET['message']); ?></div><?php endif; ?>

<nav class="match-tabs" aria-label="Match categories">
  <a class="<?= $tab==='all'?'active':'' ?>" href="?tab=all">All <b><?= $counts['all'] ?></b></a>
  <a class="<?= $tab==='received'?'active':'' ?>" href="?tab=received">Received <b><?= $counts['received'] ?></b></a>
  <a class="<?= $tab==='sent'?'active':'' ?>" href="?tab=sent">Sent <b><?= $counts['sent'] ?></b></a>
  <a class="<?= $tab==='matches'?'active':'' ?>" href="?tab=matches">Accepted <b><?= $counts['matches'] ?></b></a>
  <a class="<?= $tab==='history'?'active':'' ?>" href="?tab=history">History <b><?= $counts['history'] ?></b></a>
</nav>

<?php if (!$rows): ?>
<div class="matching-empty"><i class="fa-regular fa-heart"></i><h3><?= $tab==='all'?'No interactions yet':'Nothing in this section' ?></h3><p><?= $tab==='all'?'Search for a compatible profile and send an interest to start.':'There are no interactions matching this category right now.' ?></p><a href="../dashboard.php#partner-search" class="primary-btn">Find a Partner</a></div>
<?php else: ?>
<div class="match-list">
<?php foreach($rows as $r):
  $other_id = (int)($r['sender_user_id']==$user_id ? $r['receiver_user_id'] : $r['sender_user_id']);
  $age=match_age($r['date_of_birth']); $photo=$r['photo']??'';
  $is_verified = (($r['verification_status']??'') === 'Verified');
  $can_photo = $photo!=='' && in_array($r['photo_visibility']??'', ['Everyone','Verified Users'], true) && (($r['photo_visibility']==='Everyone') || $is_verified);
  $is_received = ((int)$r['receiver_user_id'] === $user_id);
  $is_pending = $r['status']==='Pending' && (int)$r['relationship_active']===1;
  $is_accepted = $r['status']==='Accepted' && (int)$r['relationship_active']===1;
?>
<article id="match-card-<?= (int)$r['match_id'] ?>" class="match-card <?= $is_accepted?'is-accepted':'' ?>">
  <div class="match-avatar"><?php if($can_photo): ?><img src="<?= BASE_URL ?>uploads/profile/<?= htmlspecialchars($photo) ?>" alt="Profile photo"><?php else: ?><i class="fa-solid fa-user"></i><?php endif; ?></div>
  <div class="match-main">
    <div class="match-top"><div><h3><?= htmlspecialchars(trim($r['other_name'])) ?></h3><span class="match-profile-id"><i class="fa-solid fa-id-card"></i> <?= public_profile_id($other_id) ?></span><div class="match-profile-details">
      <span><i class="fa-solid fa-cake-candles"></i> <?= $age!=='' ? (int)$age.' yrs' : 'Age not informed' ?></span>
      <span><i class="fa-solid fa-ruler-vertical"></i> <?= ($h=match_height($r['height_cm'] ?? null)) !== null ? htmlspecialchars($h) : 'Height not informed' ?></span>
      <span><i class="fa-solid fa-mosque"></i> <?= !empty($r['madhhab']) ? htmlspecialchars($r['madhhab']) : 'Madhhab not informed' ?></span>
      <span><i class="fa-solid fa-briefcase"></i> <?= !empty($r['profession']) ? htmlspecialchars($r['profession']) : 'Profession not informed' ?></span>
      <span><i class="fa-solid fa-location-dot"></i> <?= ($loc=match_location($r)) !== null ? htmlspecialchars($loc) : 'Location not informed' ?></span>
    </div></div><span class="status-pill status-<?= strtolower($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></div>
    <?php if (!empty($r['interest_message'])): ?><p class="match-message"><i class="fa-regular fa-message"></i> <?= htmlspecialchars($r['interest_message']) ?></p><?php endif; ?>
    <div class="match-meta"><span><i class="fa-regular fa-clock"></i> <?= htmlspecialchars(date('d M Y',strtotime($r['created_at']))) ?></span><span><i class="fa-solid fa-arrow-right-arrow-left"></i> <?= $is_received?'Received':'Sent' ?></span><?php if($is_accepted): ?><span class="match-connected"><i class="fa-solid fa-link"></i> Connected</span><?php endif; ?></div>
    <div class="match-actions">
      <a href="../profile/view_profile.php?user_id=<?= $other_id ?>" class="secondary-btn"><i class="fa-regular fa-user"></i> View Profile</a>
      <?php if($is_received && $is_pending): ?>
      <form method="post" action="action.php" class="inline-action-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="match_id" value="<?= (int)$r['match_id'] ?>"><input type="hidden" name="match_tab" value="<?= htmlspecialchars($tab) ?>"><button type="submit" name="action" value="accept" class="primary-btn"><i class="fa-solid fa-check"></i> Accept</button><button type="submit" name="action" value="reject" class="danger-btn"><i class="fa-solid fa-xmark"></i> Reject</button></form>
      <?php elseif(!$is_received && $is_pending): ?>
      <form method="post" action="action.php" class="inline-action-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="match_id" value="<?= (int)$r['match_id'] ?>"><input type="hidden" name="match_tab" value="<?= htmlspecialchars($tab) ?>"><button name="action" value="cancel" class="danger-btn"><i class="fa-solid fa-ban"></i> Cancel Interest</button></form>
      <?php elseif($is_accepted): ?>
      <?php
      $chat_state=null;
      $chat_conv=null;
      $chat_check=mysqli_prepare($conn,"SELECT chat_request_id,status,chat_active FROM chat_requests WHERE match_id=? AND ((sender_user_id=? AND receiver_user_id=?) OR (sender_user_id=? AND receiver_user_id=?)) ORDER BY chat_request_id DESC LIMIT 1");
      if($chat_check){mysqli_stmt_bind_param($chat_check,'iiiii',$r['match_id'],$user_id,$other_id,$other_id,$user_id);mysqli_stmt_execute($chat_check);$chat_state=mysqli_fetch_assoc(mysqli_stmt_get_result($chat_check));mysqli_stmt_close($chat_check);}
      if($chat_state && $chat_state['status']==='Accepted' && (int)$chat_state['chat_active']===1){
          $chat_link_stmt=mysqli_prepare($conn,"SELECT conversation_id FROM conversations WHERE chat_request_id=? LIMIT 1");
          if($chat_link_stmt){mysqli_stmt_bind_param($chat_link_stmt,'i',$chat_state['chat_request_id']);mysqli_stmt_execute($chat_link_stmt);$chat_conv=mysqli_fetch_assoc(mysqli_stmt_get_result($chat_link_stmt));mysqli_stmt_close($chat_link_stmt);}
      }
      ?>
      <?php if($chat_conv): ?>
        <a href="chat.php?conversation_id=<?= (int)$chat_conv['conversation_id'] ?>" class="primary-btn"><i class="fa-solid fa-comments"></i> Open Chat</a>
      <?php elseif($chat_state && $chat_state['status']==='Pending'): ?>
        <a href="chat_requests.php?tab=<?= $is_received?'received':'sent' ?>" class="secondary-btn"><i class="fa-regular fa-clock"></i> Chat Request Pending</a>
      <?php else: ?>
        <form method="post" action="chat_action.php" class="inline-action-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="match_id" value="<?= (int)$r['match_id'] ?>"><input type="hidden" name="target_user_id" value="<?= $other_id ?>"><button type="submit" name="action" value="send_request" class="primary-btn"><i class="fa-solid fa-paper-plane"></i> Request Chat</button></form>
      <?php endif; ?>
      <form method="post" action="action.php" class="inline-action-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="match_id" value="<?= (int)$r['match_id'] ?>"><input type="hidden" name="match_tab" value="<?= htmlspecialchars($tab) ?>"><button name="action" value="unmatch" class="danger-btn"><i class="fa-solid fa-link-slash"></i> Unmatch</button></form>
      <?php endif; ?>
    </div>
  </div>
</article>
<?php endforeach; ?></div><?php endif; ?>
</div></main>

<!-- Lightweight SweetAlert-style confirmation/toast; no external library required. -->
<div class="match-swal" id="matchSwal" aria-hidden="true">
  <div class="match-swal-backdrop"></div>
  <div class="match-swal-box" role="dialog" aria-modal="true" aria-labelledby="matchSwalTitle">
    <div class="match-swal-icon" id="matchSwalIcon"><i class="fa-solid fa-circle-question"></i></div>
    <h3 id="matchSwalTitle">Are you sure?</h3>
    <p id="matchSwalText"></p>
    <div class="match-swal-actions">
      <button type="button" class="swal-cancel" id="matchSwalCancel">Cancel</button>
      <button type="button" class="swal-confirm" id="matchSwalConfirm">Confirm</button>
    </div>
  </div>
</div>

<script>
(function(){
  const modal = document.getElementById('matchSwal');
  const title = document.getElementById('matchSwalTitle');
  const text = document.getElementById('matchSwalText');
  const confirmBtn = document.getElementById('matchSwalConfirm');
  const cancelBtn = document.getElementById('matchSwalCancel');
  let pendingForm = null;
  let pendingAction = '';

  function openConfirm(form, action){
    pendingForm = form;
    pendingAction = action;
    const messages = {
      reject: ['Reject this interest?', 'The request will be moved to your interaction history.'],
      cancel: ['Cancel this interest?', 'The pending request will be cancelled.'],
      unmatch: ['Unmatch this connection?', 'This connection will be closed and moved to history.']
    };
    const m = messages[action] || ['Are you sure?', 'This action cannot be undone.'];
    title.textContent = m[0];
    text.textContent = m[1];
    confirmBtn.textContent = action === 'reject' ? 'Yes, Reject' : (action === 'cancel' ? 'Yes, Cancel' : 'Yes, Unmatch');
    confirmBtn.className = 'swal-confirm';
    modal.classList.add('show');
    modal.setAttribute('aria-hidden','false');
    document.body.classList.add('swal-open');
    setTimeout(()=>confirmBtn.focus(),30);
  }

  function closeConfirm(){
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden','true');
    document.body.classList.remove('swal-open');
    pendingForm=null;
    pendingAction='';
  }

  cancelBtn.addEventListener('click', closeConfirm);
  modal.querySelector('.match-swal-backdrop').addEventListener('click', closeConfirm);
  document.addEventListener('keydown', e=>{
    if(e.key==='Escape' && modal.classList.contains('show')) closeConfirm();
  });

  confirmBtn.addEventListener('click', function(){
    if(!pendingForm) return;
    const form = pendingForm;
    const action = pendingAction;
    sessionStorage.setItem('matching_restore_scroll', String(window.scrollY));
    sessionStorage.setItem('matching_restore_action', action);

    // HTMLFormElement.submit() does not include the clicked submit button's
    // name/value. Add the confirmed action explicitly before submitting so
    // Reject/Cancel/Unmatch reach action.php with the correct command.
    let actionInput = form.querySelector('input[data-confirmed-action]');
    if (!actionInput) {
      actionInput = document.createElement('input');
      actionInput.type = 'hidden';
      actionInput.name = 'action';
      actionInput.setAttribute('data-confirmed-action', '1');
      form.appendChild(actionInput);
    }
    actionInput.value = action;

    closeConfirm();
    form.submit();
  });

  document.querySelectorAll('.inline-action-form').forEach(function(form){
    form.addEventListener('submit', function(e){
      const action = this.querySelector('button[name="action"]')?.value || '';
      if(action === 'reject' || action === 'cancel' || action === 'unmatch'){
        e.preventDefault();
        openConfirm(this, action);
        return;
      }
      // Accept uses the normal POST/redirect flow as well.
      sessionStorage.setItem('matching_restore_scroll', String(window.scrollY));
      sessionStorage.setItem('matching_restore_action', 'accept');
    });
  });

  // Restore the previous reading position after the normal POST redirect.
  const restore = sessionStorage.getItem('matching_restore_scroll');
  if(restore !== null){
    sessionStorage.removeItem('matching_restore_scroll');
    sessionStorage.removeItem('matching_restore_action');
    requestAnimationFrame(()=>{
      requestAnimationFrame(()=>window.scrollTo({top: Number(restore) || 0, behavior:'auto'}));
    });
  }
})();
</script>
<?php include '../includes/footer.php'; ?>
