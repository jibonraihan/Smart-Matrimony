<?php
require_once '../config/db.php';
require_once '../includes/language.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$user_id=(int)$_SESSION['user_id'];
if(empty($_SESSION['chat_csrf'])) $_SESSION['chat_csrf']=bin2hex(random_bytes(32)); $csrf=$_SESSION['chat_csrf'];
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function pid($id){return 'SM-'.str_pad((string)(int)$id,6,'0','0');}
function chat_number($value): string {
    $value = (string)$value;
    if (($GLOBALS['current_language'] ?? 'bn') === 'en') return $value;
    return strtr($value, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']);
}
function chat_date($value): string {
    $formatted = date('d M Y', strtotime((string)$value));
    if (($GLOBALS['current_language'] ?? 'bn') !== 'bn') return $formatted;
    $formatted = strtr($formatted, ['Jan'=>'জানু','Feb'=>'ফেব্রু','Mar'=>'মার্চ','Apr'=>'এপ্রি','May'=>'মে','Jun'=>'জুন','Jul'=>'জুলা','Aug'=>'আগ','Sep'=>'সেপ্টে','Oct'=>'অক্টো','Nov'=>'নভে','Dec'=>'ডিসে']);
    return chat_number($formatted);
}
function chat_ui_text(string $text): string {
    static $translations = [
        'Invalid or expired request. Please refresh and try again.' => 'অনুরোধটি অবৈধ বা মেয়াদোত্তীর্ণ। অনুগ্রহ করে পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।',
        'Your account is not available.' => 'আপনার অ্যাকাউন্টটি বর্তমানে ব্যবহারযোগ্য নয়।',
        'Invalid chat recipient.' => 'চ্যাটের প্রাপক সঠিক নয়।',
        'Chat is available only for an active accepted match.' => 'সক্রিয়ভাবে গ্রহণ করা ম্যাচের ক্ষেত্রেই শুধু চ্যাট করা যাবে।',
        'A chat request already exists.' => 'এই চ্যাটের অনুরোধটি আগে থেকেই রয়েছে।',
        'Chat request sent.' => 'চ্যাটের অনুরোধ পাঠানো হয়েছে।',
        'Unable to send chat request.' => 'চ্যাটের অনুরোধ পাঠানো যায়নি।',
        'Invalid chat action.' => 'চ্যাটের কার্যক্রমটি সঠিক নয়।',
        'Chat request not found.' => 'চ্যাটের অনুরোধটি পাওয়া যায়নি।',
        'This request cannot be accepted.' => 'এই অনুরোধটি গ্রহণ করা যাচ্ছে না।',
        'Chat request accepted. You can now start a conversation.' => 'চ্যাটের অনুরোধ গ্রহণ করা হয়েছে। এখন কথোপকথন শুরু করতে পারবেন।',
        'Unable to accept the chat request right now.' => 'এই মুহূর্তে চ্যাটের অনুরোধ গ্রহণ করা যাচ্ছে না।',
        'This request cannot be rejected.' => 'এই অনুরোধটি প্রত্যাখ্যান করা যাচ্ছে না।',
        'Chat request rejected.' => 'চ্যাটের অনুরোধ প্রত্যাখ্যান করা হয়েছে।',
        'Unable to reject the request.' => 'অনুরোধটি প্রত্যাখ্যান করা যায়নি।',
        'This request cannot be cancelled.' => 'এই অনুরোধটি বাতিল করা যাচ্ছে না।',
        'Chat request cancelled.' => 'চ্যাটের অনুরোধ বাতিল করা হয়েছে।',
        'Unable to cancel the request.' => 'অনুরোধটি বাতিল করা যায়নি।',
        'This conversation is not active.' => 'এই কথোপকথনটি সক্রিয় নয়।',
        'Conversation closed.' => 'কথোপকথন বন্ধ করা হয়েছে.',
        'Religion not informed' => 'ধর্ম উল্লেখ করা হয়নি',
        'Pending' => 'অপেক্ষমাণ', 'Accepted' => 'গৃহীত', 'Rejected' => 'প্রত্যাখ্যাত', 'Cancelled' => 'বাতিল',
        'Active Chat' => 'সক্রিয় চ্যাট', 'Received' => 'প্রাপ্ত', 'Sent' => 'প্রেরিত',
        'Islam' => 'ইসলাম', 'Hinduism' => 'হিন্দুধর্ম', 'Christianity' => 'খ্রিষ্টধর্ম', 'Buddhism' => 'বৌদ্ধধর্ম', 'Other' => 'অন্যান্য'
    ];
    return (($GLOBALS['current_language'] ?? 'bn') === 'en') ? $text : ($translations[$text] ?? $text);
}
$tab=$_GET['tab']??'all'; if(!in_array($tab,['all','received','sent','active','history'],true))$tab='all';
$flash=isset($_GET['message'])?['text'=>$_GET['message'],'type'=>($_GET['type']??'error')]:null;
$where='(cr.sender_user_id=? OR cr.receiver_user_id=?)'; $params=[$user_id,$user_id]; $types='ii';
if($tab==='received'){ $where.=" AND cr.receiver_user_id=? AND cr.status='Pending'";$params[]=$user_id;$types.='i'; }
elseif($tab==='sent'){ $where.=" AND cr.sender_user_id=? AND cr.status='Pending'";$params[]=$user_id;$types.='i'; }
elseif($tab==='active'){ $where.=" AND cr.status='Accepted' AND cr.chat_active=1"; }
elseif($tab==='history'){ $where.=" AND cr.status IN ('Rejected','Cancelled') OR (cr.status='Accepted' AND cr.chat_active=0)"; }
$sql="SELECT cr.*,m.status AS match_status, u.user_id AS other_id, CONCAT(u.first_name,' ',u.last_name) AS other_name, u.gender, up.photo, up.photo_visibility, up.religion, up.date_of_birth, c.conversation_id,c.status AS conversation_status,c.user1_id,c.user2_id,c.user1_message_count,c.user2_message_count,c.message_limit
FROM chat_requests cr JOIN users u ON u.user_id=CASE WHEN cr.sender_user_id=? THEN cr.receiver_user_id ELSE cr.sender_user_id END LEFT JOIN user_profiles up ON up.user_id=u.user_id LEFT JOIN matches m ON m.match_id=cr.match_id LEFT JOIN conversations c ON c.chat_request_id=cr.chat_request_id WHERE $where ORDER BY cr.created_at DESC";
$rows=[];$st=mysqli_prepare($conn,$sql);$bind=array_merge([$user_id],$params);$bt='i'.$types;mysqli_stmt_bind_param($st,$bt,...$bind);mysqli_stmt_execute($st);$res=mysqli_stmt_get_result($st);while($r=mysqli_fetch_assoc($res))$rows[]=$r;mysqli_stmt_close($st);
$counts=['all'=>0,'received'=>0,'sent'=>0,'active'=>0,'history'=>0];
$st=mysqli_prepare($conn,"SELECT
    COUNT(*) AS all_count,
    COALESCE(SUM(CASE WHEN status='Pending' AND receiver_user_id=? THEN 1 ELSE 0 END),0) AS received_count,
    COALESCE(SUM(CASE WHEN status='Pending' AND sender_user_id=? THEN 1 ELSE 0 END),0) AS sent_count,
    COALESCE(SUM(CASE WHEN status='Accepted' AND chat_active=1 THEN 1 ELSE 0 END),0) AS active_count,
    COALESCE(SUM(CASE WHEN status IN ('Rejected','Cancelled') OR (status='Accepted' AND chat_active=0) THEN 1 ELSE 0 END),0) AS history_count
FROM chat_requests
WHERE sender_user_id=? OR receiver_user_id=?");
if($st){
    mysqli_stmt_bind_param($st,'iiii',$user_id,$user_id,$user_id,$user_id);
    mysqli_stmt_execute($st);
    $c=mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?: [];
    mysqli_stmt_close($st);
    $counts['all']=(int)($c['all_count']??0);
    $counts['received']=(int)($c['received_count']??0);
    $counts['sent']=(int)($c['sent_count']??0);
    $counts['active']=(int)($c['active_count']??0);
    $counts['history']=(int)($c['history_count']??0);
}

// The All tab also includes accepted matches that are ready for the first chat request.
// They are not chat_requests rows yet, so add them to the All counter without
// double-counting matches that already have a Pending/Accepted chat request.
$ready_match_count=0;
$ready_count_sql="SELECT COUNT(*) AS ready_count
    FROM matches m
    WHERE m.status='Accepted' AND m.relationship_active=1
      AND (m.sender_user_id=? OR m.receiver_user_id=?)
      AND NOT EXISTS (
          SELECT 1 FROM chat_requests cr2
          WHERE cr2.match_id=m.match_id AND cr2.status IN ('Pending','Accepted')
      )";
$rcst=mysqli_prepare($conn,$ready_count_sql);
if($rcst){
    mysqli_stmt_bind_param($rcst,'ii',$user_id,$user_id);
    mysqli_stmt_execute($rcst);
    $rcrow=mysqli_fetch_assoc(mysqli_stmt_get_result($rcst)) ?: [];
    $ready_match_count=(int)($rcrow['ready_count']??0);
    mysqli_stmt_close($rcst);
}
$counts['all'] += $ready_match_count;

function age($dob){if(!$dob)return null;try{return (new DateTime($dob))->diff(new DateTime('today'))->y;}catch(Throwable $e){return null;}}

// Accepted matches that do not yet have a pending/accepted chat request.
$ready_matches=[];
if($tab==='all'){
    $ready_sql="SELECT m.match_id,
        CASE WHEN m.sender_user_id=? THEN m.receiver_user_id ELSE m.sender_user_id END AS other_id,
        CONCAT(u.first_name,' ',u.last_name) AS other_name, up.photo, up.photo_visibility
        FROM matches m
        JOIN users u ON u.user_id=CASE WHEN m.sender_user_id=? THEN m.receiver_user_id ELSE m.sender_user_id END
        LEFT JOIN user_profiles up ON up.user_id=u.user_id
        WHERE m.status='Accepted' AND m.relationship_active=1
          AND (m.sender_user_id=? OR m.receiver_user_id=?)
          AND NOT EXISTS (
              SELECT 1 FROM chat_requests cr2
              WHERE cr2.match_id=m.match_id AND cr2.status IN ('Pending','Accepted')
          )
        ORDER BY m.created_at DESC, m.match_id DESC";
    $rst=mysqli_prepare($conn,$ready_sql);
    if($rst){
        mysqli_stmt_bind_param($rst,'iiii',$user_id,$user_id,$user_id,$user_id);
        mysqli_stmt_execute($rst);$rr=mysqli_stmt_get_result($rst);
        while($rm=mysqli_fetch_assoc($rr))$ready_matches[]=$rm;
        mysqli_stmt_close($rst);
    }
}
$isPartial = isset($_GET['partial']) && $_GET['partial'] === '1';
if (!$isPartial) {
    include '../includes/header.php';
    include '../includes/navbar.php';
}
?>
<main class="chat-page"><div class="chat-wrap"><div id="chatRequestsContent"><a class="chat-back" href="../dashboard.php"><i class="fa-solid fa-arrow-left"></i> <?=sm_text('Dashboard','ড্যাশবোর্ড')?></a>
<section class="chat-hero"><div><span><?=sm_text('RESPECTFUL COMMUNICATION','সম্মানজনক যোগাযোগ')?></span><h1><?=sm_text('Messages','বার্তা')?></h1><p><?=sm_text('Chat is available only after both members have an accepted match and the receiver approves the chat request.','দুজন সদস্যের ম্যাচ গৃহীত হওয়ার পর এবং প্রাপক চ্যাটের অনুরোধ অনুমোদন করলেই চ্যাট করা যাবে।')?></p></div><i class="fa-solid fa-comments"></i></section>
<?php if($flash):?><div class="chat-alert <?=h($flash['type'])?>"><?=h(chat_ui_text($flash['text']))?></div><?php endif;?>
<nav class="chat-tabs" aria-label="<?=sm_text('Chat request sections','চ্যাট অনুরোধের বিভাগ')?>"><?php foreach(['all'=>sm_text('All','সব'),'received'=>sm_text('Received','প্রাপ্ত'),'sent'=>sm_text('Sent','প্রেরিত'),'active'=>sm_text('Active Chats','সক্রিয় চ্যাট'),'history'=>sm_text('History','ইতিহাস')] as $k=>$label):?><a class="<?=$tab===$k?'active':''?>" href="?tab=<?=$k?>"><?=$label?> <b><?=chat_number($counts[$k])?></b></a><?php endforeach;?></nav>
<?php if($tab==='all' && $ready_matches): ?>
<section class="chat-ready">
  <div class="chat-ready-head"><div><span><?=sm_text('READY TO CONNECT','যোগাযোগের জন্য প্রস্তুত')?></span><h2><?=sm_text('Your accepted matches','আপনার গৃহীত ম্যাচসমূহ')?></h2><p><?=sm_text('Choose someone below to send the first chat request.','প্রথম চ্যাটের অনুরোধ পাঠাতে নিচ থেকে কাউকে বেছে নিন।')?></p></div><i class="fa-solid fa-comments"></i></div>
  <div class="chat-list">
  <?php foreach($ready_matches as $rm): ?>
    <article class="chat-card chat-ready-card">
      <div class="chat-avatar"><?php if(!empty($rm['photo']) && in_array($rm['photo_visibility']??'', ['Everyone','Verified Users'], true)): ?><img src="<?=BASE_URL?>uploads/profile/<?=h($rm['photo'])?>" alt="<?=sm_text('Profile photo','প্রোফাইলের ছবি')?>"><?php else: ?><i class="fa-solid fa-user"></i><?php endif; ?></div>
      <div class="chat-main"><div class="chat-top"><div><h2><?=h($rm['other_name'])?></h2><span class="chat-id"><?=pid($rm['other_id'])?></span><p><?=sm_text('You are matched. Send a chat request to start the conversation.','আপনাদের ম্যাচ হয়েছে। কথোপকথন শুরু করতে চ্যাটের অনুরোধ পাঠান।')?></p></div><span class="chat-status accepted"><?=sm_text('Matched','ম্যাচ হয়েছে')?></span></div>
      <div class="chat-actions"><a href="../profile/view_profile.php?user_id=<?=h($rm['other_id'])?>" class="chat-secondary"><?=sm_text('View Profile','প্রোফাইল দেখুন')?></a><form method="post" action="chat_action.php"><input type="hidden" name="csrf_token" value="<?=h($csrf)?>"><input type="hidden" name="match_id" value="<?=h($rm['match_id'])?>"><input type="hidden" name="target_user_id" value="<?=h($rm['other_id'])?>"><button type="submit" name="action" value="send_request" class="chat-primary"><i class="fa-solid fa-paper-plane"></i> <?=sm_text('Request Chat','চ্যাটের অনুরোধ পাঠান')?></button></form></div></div>
    </article>
  <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php if($rows): ?><div class="chat-list"><?php foreach($rows as $r):$other=(int)$r['other_id'];$a=age($r['date_of_birth']);$is_received=(int)$r['receiver_user_id']===$user_id;$active=$r['status']==='Accepted'&&(int)$r['chat_active']===1;?><article class="chat-card"><div class="chat-avatar"><?php if(!empty($r['photo']) && (($r['photo_visibility']==='Everyone') || ($r['photo_visibility']==='Verified Users'))):?><img src="<?=BASE_URL?>uploads/profile/<?=h($r['photo'])?>" alt="<?=sm_text('Profile photo','প্রোফাইলের ছবি')?>"><?php else:?><i class="fa-solid fa-user"></i><?php endif;?></div><div class="chat-main"><div class="chat-top"><div><h2><?=h($r['other_name'])?></h2><span class="chat-id"><?=pid($other)?></span><p><?= $a!==null?($current_language==='bn'?strtr((string)$a,['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']):$a).' '.sm_text('years','বছর').' · ':'' ?><?=h(chat_ui_text($r['religion']??'Religion not informed'))?></p></div><span class="chat-status <?=$active?'accepted':strtolower($r['status'])?>"><?=h(chat_ui_text($active?'Active Chat':$r['status']))?></span></div><div class="chat-meta"><span><i class="fa-regular fa-clock"></i> <?=h(chat_date($r['created_at']))?></span><span><i class="fa-solid fa-arrow-right-arrow-left"></i> <?=$is_received?sm_text('Received','প্রাপ্ত'):sm_text('Sent','প্রেরিত')?></span><?php if($active):?><span><i class="fa-solid fa-message"></i> <?=chat_number(((int)$r['user1_id']===$user_id?(int)$r['user1_message_count']:(int)$r['user2_message_count']))?> / <?=chat_number($r['message_limit'])?> <?=sm_text('messages used','টি বার্তা ব্যবহৃত')?></span><?php endif;?></div><div class="chat-actions"><a href="../profile/view_profile.php?user_id=<?=$other?>" class="chat-secondary"><?=sm_text('View Profile','প্রোফাইল দেখুন')?></a><?php if($active && !empty($r['conversation_id'])):?><a href="message_chat.php?conversation_id=<?=h($r['conversation_id'])?>" class="chat-primary"><?=sm_text('Open Chat','চ্যাট খুলুন')?> <i class="fa-solid fa-arrow-right"></i></a><?php elseif($is_received && $r['status']==='Pending'):?><form method="post" action="chat_action.php"><input type="hidden" name="csrf_token" value="<?=h($csrf)?>"><input type="hidden" name="chat_request_id" value="<?=h($r['chat_request_id'])?>"><button name="action" value="accept_request" class="chat-primary"><?=sm_text('Accept','গ্রহণ করুন')?></button><button name="action" value="reject_request" class="chat-danger"><?=sm_text('Reject','প্রত্যাখ্যান করুন')?></button></form><?php elseif(!$is_received && $r['status']==='Pending'):?><form method="post" action="chat_action.php"><input type="hidden" name="csrf_token" value="<?=h($csrf)?>"><input type="hidden" name="chat_request_id" value="<?=h($r['chat_request_id'])?>"><button name="action" value="cancel_request" class="chat-danger"><?=sm_text('Cancel Request','অনুরোধ বাতিল করুন')?></button></form><?php endif;?></div></div></article><?php endforeach;?></div><?php elseif(!($tab==='all' && $ready_matches)): ?><section class="chat-empty"><i class="fa-regular fa-comments"></i><h3><?= $tab==='all'?sm_text('No conversations here','এখানে কোনো কথোপকথন নেই'):sm_text('Nothing in this section','এই বিভাগে কিছু নেই') ?></h3><p><?= $tab==='all'?sm_text('When an accepted match starts a chat request, it will appear here.','গৃহীত ম্যাচের কেউ চ্যাটের অনুরোধ পাঠালে তা এখানে দেখা যাবে।'):sm_text('There are no chat requests in this section right now.','এই মুহূর্তে এই বিভাগে কোনো চ্যাটের অনুরোধ নেই।') ?></p><a href="my_matches.php?tab=matches" class="chat-primary"><?=sm_text('View Accepted Matches','গৃহীত ম্যাচ দেখুন')?></a></section><?php endif;?></div></div></main>
<?php if (!$isPartial): ?>
<link rel="stylesheet" href="../assets/css/chat.css">
<script src="../assets/js/chat-menu.js"></script>
<script src="../assets/js/chat-requests.js"></script>
<?php endif; ?>
