<?php
ob_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'models/Chat.php';
require_once 'models/User.php';

requireLogin();
$me   = getCurrentUser();
$chat = new Chat();

function chatJson($data) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

$ajaxAction = $_GET['ajax'] ?? '';

if ($ajaxAction === 'send') {
    $roomId  = (int)($_POST['room_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    $type    = in_array($_POST['type'] ?? 'text', ['text','sticker']) ? ($_POST['type'] ?? 'text') : 'text';
    if (!$roomId || $message === '') chatJson(['ok' => false]);
    $chat->sendMessage($roomId, $me['id'], $message, $type);
    $chat->markRead($roomId, $me['id']);
    chatJson(['ok' => true]);
}

if ($ajaxAction === 'poll') {
    $roomId = (int)($_GET['room_id'] ?? 0);
    $after  = (int)($_GET['after'] ?? 0);
    if (!$roomId) chatJson([]);
    $chat->markRead($roomId, $me['id']);
    chatJson($chat->getMessages($roomId, $after));
}

if ($ajaxAction === 'open_direct') {
    $otherId = (int)($_POST['user_id'] ?? 0);
    if (!$otherId || $otherId === (int)$me['id']) chatJson(['ok' => false, 'message' => 'Invalid user']);
    $roomId = $chat->getOrCreateDirectRoom((int)$me['id'], $otherId);
    chatJson(['ok' => true, 'room_id' => $roomId]);
}

if ($ajaxAction === 'create_group') {
    $name    = trim($_POST['name'] ?? '');
    $members = $_POST['members'] ?? [];
    if (empty($name)) chatJson(['ok' => false, 'message' => 'Vui lòng đặt tên nhóm.']);
    if (count($members) < 1) chatJson(['ok' => false, 'message' => 'Chọn ít nhất 1 thành viên.']);
    $memberIds = array_map('intval', (array)$members);
    $memberIds[] = (int)$me['id'];
    $roomId = $chat->createGroupRoom($name, $memberIds);
    chatJson(['ok' => true, 'room_id' => $roomId, 'name' => $name, 'member_count' => count($memberIds)]);
}

if ($ajaxAction === 'unread_counts') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT room_id FROM chat_members WHERE user_id = ?");
    $stmt->execute([$me['id']]);
    $rooms  = $stmt->fetchAll(\PDO::FETCH_COLUMN);
    $counts = [];
    foreach ($rooms as $r) $counts[$r] = $chat->countUnread($r, $me['id']);
    $counts[1] = $chat->countUnread(1, $me['id']);
    chatJson($counts);
}

chatJson(['ok' => false, 'message' => 'Unknown action']);
