<?php
require_once '../config/db.php';
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
    redirect_chat('chat_requests.php', 'Invalid or expired request. Please refresh and try again.');
}
$action = (string)($_POST['action'] ?? '');
$chat_request_id = (int)($_POST['chat_request_id'] ?? 0);
$match_id = (int)($_POST['match_id'] ?? 0);
$target_id = (int)($_POST['target_user_id'] ?? 0);

// Only active normal users may use the communication module.
$st = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_id=? AND role='User' AND account_status='Active' LIMIT 1");
mysqli_stmt_bind_param($st,'i',$user_id); mysqli_stmt_execute($st);
$viewer = mysqli_fetch_assoc(mysqli_stmt_get_result($st)); mysqli_stmt_close($st);
if (!$viewer) redirect_chat('chat_requests.php','Your account is not available.');

if ($action === 'send_request') {
    if ($target_id <= 0 || $target_id === $user_id) redirect_chat('chat_requests.php','Invalid chat recipient.');
    // A chat request can only originate from an accepted, active match.
    $st = mysqli_prepare($conn, "SELECT match_id, sender_user_id, receiver_user_id FROM matches WHERE match_id=? AND status='Accepted' AND relationship_active=1 AND ((sender_user_id=? AND receiver_user_id=?) OR (sender_user_id=? AND receiver_user_id=?)) LIMIT 1");
    mysqli_stmt_bind_param($st,'iiiii',$match_id,$user_id,$target_id,$target_id,$user_id); mysqli_stmt_execute($st);
    $match = mysqli_fetch_assoc(mysqli_stmt_get_result($st)); mysqli_stmt_close($st);
    if (!$match) redirect_chat('chat_requests.php','Chat is available only for an active accepted match.');
    $st = mysqli_prepare($conn, "SELECT chat_request_id,status FROM chat_requests WHERE match_id=? AND ((sender_user_id=? AND receiver_user_id=?) OR (sender_user_id=? AND receiver_user_id=?)) ORDER BY chat_request_id DESC LIMIT 1");
    mysqli_stmt_bind_param($st,'iiiii',$match_id,$user_id,$target_id,$target_id,$user_id); mysqli_stmt_execute($st);
    $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($st)); mysqli_stmt_close($st);
    if ($existing && in_array($existing['status'],['Pending','Accepted'],true)) redirect_chat('chat_requests.php','A chat request already exists.','info');
    $st = mysqli_prepare($conn, "INSERT INTO chat_requests (match_id,sender_user_id,receiver_user_id,status,chat_active) VALUES (?,?,?,?,0)");
    $status='Pending'; mysqli_stmt_bind_param($st,'iiis',$match_id,$user_id,$target_id,$status);
    $ok=mysqli_stmt_execute($st); mysqli_stmt_close($st);
    redirect_chat('chat_requests.php',$ok?'Chat request sent.':'Unable to send chat request.', $ok?'success':'error');
}

if ($chat_request_id <= 0 || !in_array($action,['accept_request','reject_request','cancel_request','close_chat'],true)) redirect_chat('chat_requests.php','Invalid chat action.');
$st = mysqli_prepare($conn, "SELECT * FROM chat_requests WHERE chat_request_id=? AND (sender_user_id=? OR receiver_user_id=?) LIMIT 1");
mysqli_stmt_bind_param($st,'iii',$chat_request_id,$user_id,$user_id); mysqli_stmt_execute($st);
$req=mysqli_fetch_assoc(mysqli_stmt_get_result($st)); mysqli_stmt_close($st);
if (!$req) redirect_chat('chat_requests.php','Chat request not found.');

if ($action === 'accept_request') {
    if ((int)$req['receiver_user_id'] !== $user_id || $req['status'] !== 'Pending') redirect_chat('chat_requests.php','This request cannot be accepted.');
    mysqli_begin_transaction($conn);
    try {
        $st=mysqli_prepare($conn,"UPDATE chat_requests SET status='Accepted',chat_active=1,responded_at=NOW(),closed_at=NULL WHERE chat_request_id=? AND receiver_user_id=? AND status='Pending'");
        mysqli_stmt_bind_param($st,'ii',$chat_request_id,$user_id); mysqli_stmt_execute($st); if(mysqli_stmt_affected_rows($st)!==1) throw new Exception(); mysqli_stmt_close($st);
        $u1=(int)$req['sender_user_id']; $u2=(int)$req['receiver_user_id'];
        $st=mysqli_prepare($conn,"INSERT INTO conversations (chat_request_id,user1_id,user2_id,status,message_limit,user1_message_count,user2_message_count) VALUES (?,?,?,'Active',10,0,0)");
        mysqli_stmt_bind_param($st,'iii',$chat_request_id,$u1,$u2); if(!mysqli_stmt_execute($st)) throw new Exception(); mysqli_stmt_close($st);
        mysqli_commit($conn); redirect_chat('chat_requests.php','Chat request accepted. You can now start a conversation.','success');
    } catch(Throwable $e) { mysqli_rollback($conn); redirect_chat('chat_requests.php','Unable to accept the chat request right now.'); }
}
if ($action === 'reject_request') {
    if ((int)$req['receiver_user_id'] !== $user_id || $req['status'] !== 'Pending') redirect_chat('chat_requests.php','This request cannot be rejected.');
    $st=mysqli_prepare($conn,"UPDATE chat_requests SET status='Rejected',chat_active=0,responded_at=NOW(),closed_at=NOW() WHERE chat_request_id=? AND receiver_user_id=? AND status='Pending'");
    mysqli_stmt_bind_param($st,'ii',$chat_request_id,$user_id); $ok=mysqli_stmt_execute($st)&&mysqli_stmt_affected_rows($st)===1; mysqli_stmt_close($st);
    redirect_chat('chat_requests.php',$ok?'Chat request rejected.':'Unable to reject the request.', $ok?'success':'error');
}
if ($action === 'cancel_request') {
    if ((int)$req['sender_user_id'] !== $user_id || $req['status'] !== 'Pending') redirect_chat('chat_requests.php','This request cannot be cancelled.');
    $st=mysqli_prepare($conn,"UPDATE chat_requests SET status='Cancelled',chat_active=0,closed_at=NOW() WHERE chat_request_id=? AND sender_user_id=? AND status='Pending'");
    mysqli_stmt_bind_param($st,'ii',$chat_request_id,$user_id); $ok=mysqli_stmt_execute($st)&&mysqli_stmt_affected_rows($st)===1; mysqli_stmt_close($st);
    redirect_chat('chat_requests.php',$ok?'Chat request cancelled.':'Unable to cancel the request.', $ok?'success':'error');
}
if ($action === 'close_chat') {
    if ($req['status'] !== 'Accepted') redirect_chat('chat_requests.php','This conversation is not active.');
    $st=mysqli_prepare($conn,"UPDATE chat_requests SET chat_active=0,closed_at=NOW() WHERE chat_request_id=? AND (sender_user_id=? OR receiver_user_id=?)");
    mysqli_stmt_bind_param($st,'iii',$chat_request_id,$user_id,$user_id); mysqli_stmt_execute($st); mysqli_stmt_close($st);
    $st=mysqli_prepare($conn,"UPDATE conversations SET status='Closed' WHERE chat_request_id=? AND (user1_id=? OR user2_id=?)");
    mysqli_stmt_bind_param($st,'iii',$chat_request_id,$user_id,$user_id); mysqli_stmt_execute($st); mysqli_stmt_close($st);
    redirect_chat('chat_requests.php','Conversation closed.','success');
}
