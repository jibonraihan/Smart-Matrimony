<?php
require_once '../config/db.php';
require_once '../includes/language.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$user_id = (int)$_SESSION['user_id'];
if (empty($_SESSION['chat_csrf'])) $_SESSION['chat_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['chat_csrf'];
function redirect_chat(string $url, string $message, string $type='error'): void {
    $sep = strpos($url, '?') === false ? '?' : '&';
    header('Location: '.$url.$sep.'message='.urlencode($message).'&type='.urlencode($type)); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
    redirect_chat('messages.php', sm_text('Invalid or expired request. Please refresh and try again.','অনুরোধটি অবৈধ বা মেয়াদোত্তীর্ণ। অনুগ্রহ করে পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।'));
}
$action = (string)($_POST['action'] ?? '');
$chat_request_id = (int)($_POST['chat_request_id'] ?? 0);
$match_id = (int)($_POST['match_id'] ?? 0);
$target_id = (int)($_POST['target_user_id'] ?? 0);

// Only active normal users may use the communication module.
$st = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_id=? AND role='User' AND account_status='Active' LIMIT 1");
mysqli_stmt_bind_param($st,'i',$user_id); mysqli_stmt_execute($st);
$viewer = mysqli_fetch_assoc(mysqli_stmt_get_result($st)); mysqli_stmt_close($st);
if (!$viewer) redirect_chat('messages.php',sm_text('Your account is not available.','আপনার অ্যাকাউন্টটি বর্তমানে ব্যবহারযোগ্য নয়।'));

if ($action === 'send_request') {
    if ($target_id <= 0 || $target_id === $user_id) redirect_chat('messages.php',sm_text('Invalid chat recipient.','অবৈধ চ্যাট প্রাপক।'));
    // A chat request can only originate from an accepted, active match.
    $st = mysqli_prepare($conn, "SELECT match_id, sender_user_id, receiver_user_id FROM matches WHERE match_id=? AND status='Accepted' AND relationship_active=1 AND ((sender_user_id=? AND receiver_user_id=?) OR (sender_user_id=? AND receiver_user_id=?)) LIMIT 1");
    mysqli_stmt_bind_param($st,'iiiii',$match_id,$user_id,$target_id,$target_id,$user_id); mysqli_stmt_execute($st);
    $match = mysqli_fetch_assoc(mysqli_stmt_get_result($st)); mysqli_stmt_close($st);
    if (!$match) redirect_chat('messages.php',sm_text('Chat is available only for an active accepted match.','শুধু সক্রিয়ভাবে গৃহীত ম্যাচের ক্ষেত্রেই চ্যাট করা যাবে।'));
    $st = mysqli_prepare($conn, "SELECT chat_request_id,status FROM chat_requests WHERE match_id=? AND ((sender_user_id=? AND receiver_user_id=?) OR (sender_user_id=? AND receiver_user_id=?)) ORDER BY chat_request_id DESC LIMIT 1");
    mysqli_stmt_bind_param($st,'iiiii',$match_id,$user_id,$target_id,$target_id,$user_id); mysqli_stmt_execute($st);
    $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($st)); mysqli_stmt_close($st);
    if ($existing && in_array($existing['status'],['Pending','Accepted'],true)) redirect_chat('messages.php',sm_text('A chat request already exists.','এই চ্যাটের অনুরোধ আগে থেকেই রয়েছে.'),'info');
    $st = mysqli_prepare($conn, "INSERT INTO chat_requests (match_id,sender_user_id,receiver_user_id,status,chat_active) VALUES (?,?,?,?,0)");
    $status='Pending'; mysqli_stmt_bind_param($st,'iiis',$match_id,$user_id,$target_id,$status);
    $ok=mysqli_stmt_execute($st); mysqli_stmt_close($st);
    redirect_chat('messages.php',$ok?sm_text('Chat request sent.','চ্যাটের অনুরোধ পাঠানো হয়েছে।'):sm_text('Unable to send chat request.','চ্যাটের অনুরোধ পাঠানো যায়নি।'), $ok?'success':'error');
}

if ($chat_request_id <= 0 || !in_array($action,['accept_request','reject_request','cancel_request','close_chat'],true)) redirect_chat('messages.php',sm_text('Invalid chat action.','চ্যাটের কার্যক্রমটি অবৈধ।'));
$st = mysqli_prepare($conn, "SELECT * FROM chat_requests WHERE chat_request_id=? AND (sender_user_id=? OR receiver_user_id=?) LIMIT 1");
mysqli_stmt_bind_param($st,'iii',$chat_request_id,$user_id,$user_id); mysqli_stmt_execute($st);
$req=mysqli_fetch_assoc(mysqli_stmt_get_result($st)); mysqli_stmt_close($st);
if (!$req) redirect_chat('messages.php',sm_text('Chat request not found.','চ্যাটের অনুরোধ পাওয়া যায়নি।'));

if ($action === 'accept_request') {
    if ((int)$req['receiver_user_id'] !== $user_id || $req['status'] !== 'Pending') redirect_chat('messages.php',sm_text('This request cannot be accepted.','এই অনুরোধটি গ্রহণ করা যাবে না।'));
    mysqli_begin_transaction($conn);
    try {
        $st=mysqli_prepare($conn,"UPDATE chat_requests SET status='Accepted',chat_active=1,responded_at=NOW(),closed_at=NULL WHERE chat_request_id=? AND receiver_user_id=? AND status='Pending'");
        mysqli_stmt_bind_param($st,'ii',$chat_request_id,$user_id); mysqli_stmt_execute($st); if(mysqli_stmt_affected_rows($st)!==1) throw new Exception(); mysqli_stmt_close($st);
        $u1=(int)$req['sender_user_id']; $u2=(int)$req['receiver_user_id'];
        $st=mysqli_prepare($conn,"INSERT INTO conversations (chat_request_id,user1_id,user2_id,status,message_limit,user1_message_count,user2_message_count) VALUES (?,?,?,'Active',10,0,0)");
        mysqli_stmt_bind_param($st,'iii',$chat_request_id,$u1,$u2); if(!mysqli_stmt_execute($st)) throw new Exception(); mysqli_stmt_close($st);
        mysqli_commit($conn); redirect_chat('messages.php',sm_text('Chat request accepted. You can now start a conversation.','চ্যাটের অনুরোধ গ্রহণ করা হয়েছে। এখন কথোপকথন শুরু করতে পারবেন।'),'success');
    } catch(Throwable $e) { mysqli_rollback($conn); redirect_chat('messages.php',sm_text('Unable to accept the chat request right now.','এই মুহূর্তে চ্যাটের অনুরোধ গ্রহণ করা যাচ্ছে না।')); }
}
if ($action === 'reject_request') {
    if ((int)$req['receiver_user_id'] !== $user_id || $req['status'] !== 'Pending') redirect_chat('messages.php',sm_text('This request cannot be rejected.','এই অনুরোধটি প্রত্যাখ্যান করা যাবে না।'));
    $st=mysqli_prepare($conn,"UPDATE chat_requests SET status='Rejected',chat_active=0,responded_at=NOW(),closed_at=NOW() WHERE chat_request_id=? AND receiver_user_id=? AND status='Pending'");
    mysqli_stmt_bind_param($st,'ii',$chat_request_id,$user_id); $ok=mysqli_stmt_execute($st)&&mysqli_stmt_affected_rows($st)===1; mysqli_stmt_close($st);
    redirect_chat('messages.php',$ok?sm_text('Chat request rejected.','চ্যাটের অনুরোধ প্রত্যাখ্যান করা হয়েছে।'):sm_text('Unable to reject the request.','অনুরোধটি প্রত্যাখ্যান করা যায়নি।'), $ok?'success':'error');
}
if ($action === 'cancel_request') {
    if ((int)$req['sender_user_id'] !== $user_id || $req['status'] !== 'Pending') redirect_chat('messages.php',sm_text('This request cannot be cancelled.','এই অনুরোধটি বাতিল করা যাবে না।'));
    $st=mysqli_prepare($conn,"UPDATE chat_requests SET status='Cancelled',chat_active=0,closed_at=NOW() WHERE chat_request_id=? AND sender_user_id=? AND status='Pending'");
    mysqli_stmt_bind_param($st,'ii',$chat_request_id,$user_id); $ok=mysqli_stmt_execute($st)&&mysqli_stmt_affected_rows($st)===1; mysqli_stmt_close($st);
    redirect_chat('messages.php',$ok?sm_text('Chat request cancelled.','চ্যাটের অনুরোধ বাতিল করা হয়েছে।'):sm_text('Unable to cancel the request.','অনুরোধটি বাতিল করা যায়নি।'), $ok?'success':'error');
}
if ($action === 'close_chat') {
    if ($req['status'] !== 'Accepted') redirect_chat('messages.php',sm_text('This conversation is not active.','এই কথোপকথনটি সক্রিয় নয়।'));
    $st=mysqli_prepare($conn,"UPDATE chat_requests SET chat_active=0,closed_at=NOW() WHERE chat_request_id=? AND (sender_user_id=? OR receiver_user_id=?)");
    mysqli_stmt_bind_param($st,'iii',$chat_request_id,$user_id,$user_id); mysqli_stmt_execute($st); mysqli_stmt_close($st);
    $st=mysqli_prepare($conn,"UPDATE conversations SET status='Closed' WHERE chat_request_id=? AND (user1_id=? OR user2_id=?)");
    mysqli_stmt_bind_param($st,'iii',$chat_request_id,$user_id,$user_id); mysqli_stmt_execute($st); mysqli_stmt_close($st);
    redirect_chat('messages.php',sm_text('Conversation closed.','কথোপকথন বন্ধ করা হয়েছে।'),'success');
}
