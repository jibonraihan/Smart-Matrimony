<?php
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$user_id=(int)$_SESSION['user_id'];
if(empty($_SESSION['chat_csrf'])) $_SESSION['chat_csrf']=bin2hex(random_bytes(32)); $csrf=$_SESSION['chat_csrf'];
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function pid($id){return 'SM-'.str_pad((string)(int)$id,6,'0','0');}
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
$st=mysqli_prepare($conn,"SELECT COUNT(*) all_count,SUM(status='Pending' AND receiver_user_id=?) received_count,SUM(status='Pending' AND sender_user_id=?) sent_count,SUM(status='Accepted' AND chat_active=1) active_count,SUM(status IN ('Rejected','Cancelled') OR (status='Accepted' AND chat_active=0)) history_count FROM chat_requests WHERE sender_user_id=? OR receiver_user_id=?");mysqli_stmt_bind_param($st,'iiii',$user_id,$user_id,$user_id,$user_id);mysqli_stmt_execute($st);$c=mysqli_fetch_assoc(mysqli_stmt_get_result($st));mysqli_stmt_close($st);foreach($counts as $k=>$v)$counts[$k]=(int)($c[$k==='all'?'all_count':$k.'_count']??0);
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
include '../includes/header.php'; include '../includes/navbar.php';
?>
<main class="chat-page"><div class="chat-wrap"><a class="chat-back" href="../dashboard.php"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
<section class="chat-hero"><div><span>RESPECTFUL COMMUNICATION</span><h1>Messages</h1><p>Chat is available only after both members have an accepted match and the receiver approves the chat request.</p></div><i class="fa-solid fa-comments"></i></section>
<?php if($flash):?><div class="chat-alert <?=h($flash['type'])?>"><?=h($flash['text'])?></div><?php endif;?>
<nav class="chat-tabs"><?php foreach(['all'=>'All','received'=>'Received','sent'=>'Sent','active'=>'Active Chats','history'=>'History'] as $k=>$label):?><a class="<?=$tab===$k?'active':''?>" href="?tab=<?=$k?>"><?=$label?> <b><?=$counts[$k]?></b></a><?php endforeach;?></nav>
<?php if($tab==='all' && $ready_matches): ?>
<section class="chat-ready">
  <div class="chat-ready-head"><div><span>READY TO CONNECT</span><h2>Your accepted matches</h2><p>Choose someone below to send the first chat request.</p></div><i class="fa-solid fa-comments"></i></div>
  <div class="chat-list">
  <?php foreach($ready_matches as $rm): ?>
    <article class="chat-card chat-ready-card">
      <div class="chat-avatar"><?php if(!empty($rm['photo']) && in_array($rm['photo_visibility']??'', ['Everyone','Verified Users'], true)): ?><img src="<?=BASE_URL?>uploads/profile/<?=h($rm['photo'])?>" alt="Profile photo"><?php else: ?><i class="fa-solid fa-user"></i><?php endif; ?></div>
      <div class="chat-main"><div class="chat-top"><div><h2><?=h($rm['other_name'])?></h2><span class="chat-id"><?=pid($rm['other_id'])?></span><p>You are matched. Send a chat request to start the conversation.</p></div><span class="chat-status accepted">Matched</span></div>
      <div class="chat-actions"><a href="../profile/view_profile.php?user_id=<?=h($rm['other_id'])?>" class="chat-secondary">View Profile</a><form method="post" action="chat_action.php"><input type="hidden" name="csrf_token" value="<?=h($csrf)?>"><input type="hidden" name="match_id" value="<?=h($rm['match_id'])?>"><input type="hidden" name="target_user_id" value="<?=h($rm['other_id'])?>"><button type="submit" name="action" value="send_request" class="chat-primary"><i class="fa-solid fa-paper-plane"></i> Request Chat</button></form></div></div>
    </article>
  <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php if($rows): ?><div class="chat-list"><?php foreach($rows as $r):$other=(int)$r['other_id'];$a=age($r['date_of_birth']);$is_received=(int)$r['receiver_user_id']===$user_id;$active=$r['status']==='Accepted'&&(int)$r['chat_active']===1;?><article class="chat-card"><div class="chat-avatar"><?php if(!empty($r['photo']) && (($r['photo_visibility']==='Everyone') || ($r['photo_visibility']==='Verified Users'))):?><img src="<?=BASE_URL?>uploads/profile/<?=h($r['photo'])?>" alt="Profile photo"><?php else:?><i class="fa-solid fa-user"></i><?php endif;?></div><div class="chat-main"><div class="chat-top"><div><h2><?=h($r['other_name'])?></h2><span class="chat-id"><?=pid($other)?></span><p><?= $a!==null?$a.' yrs · ':'' ?><?=h($r['religion']??'Religion not informed')?></p></div><span class="chat-status <?=$active?'accepted':strtolower($r['status'])?>"><?=h($active?'Active Chat':$r['status'])?></span></div><div class="chat-meta"><span><i class="fa-regular fa-clock"></i> <?=h(date('d M Y',strtotime($r['created_at'])))?></span><span><i class="fa-solid fa-arrow-right-arrow-left"></i> <?=$is_received?'Received':'Sent'?></span><?php if($active):?><span><i class="fa-solid fa-message"></i> <?=((int)$r['user1_id']===$user_id?(int)$r['user1_message_count']:(int)$r['user2_message_count'])?> / <?=h($r['message_limit'])?> messages used</span><?php endif;?></div><div class="chat-actions"><a href="../profile/view_profile.php?user_id=<?=$other?>" class="chat-secondary">View Profile</a><?php if($active && !empty($r['conversation_id'])):?><a href="chat.php?conversation_id=<?=h($r['conversation_id'])?>" class="chat-primary">Open Chat <i class="fa-solid fa-arrow-right"></i></a><?php elseif($is_received && $r['status']==='Pending'):?><form method="post" action="chat_action.php"><input type="hidden" name="csrf_token" value="<?=h($csrf)?>"><input type="hidden" name="chat_request_id" value="<?=h($r['chat_request_id'])?>"><button name="action" value="accept_request" class="chat-primary">Accept</button><button name="action" value="reject_request" class="chat-danger">Reject</button></form><?php elseif(!$is_received && $r['status']==='Pending'):?><form method="post" action="chat_action.php"><input type="hidden" name="csrf_token" value="<?=h($csrf)?>"><input type="hidden" name="chat_request_id" value="<?=h($r['chat_request_id'])?>"><button name="action" value="cancel_request" class="chat-danger">Cancel Request</button></form><?php endif;?></div></div></article><?php endforeach;?></div><?php elseif(!($tab==='all' && $ready_matches)): ?><section class="chat-empty"><i class="fa-regular fa-comments"></i><h3><?= $tab==='all'?'No conversations here':'Nothing in this section' ?></h3><p><?= $tab==='all'?'When an accepted match starts a chat request, it will appear here.':'There are no chat requests in this section right now.' ?></p><a href="my_matches.php?tab=matches" class="chat-primary">View Accepted Matches</a></section><?php endif;?></div></main>
<link rel="stylesheet" href="../assets/css/chat.css">
<script src="../assets/js/chat-menu.js"></script>
