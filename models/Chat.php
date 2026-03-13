<?php
require_once __DIR__ . '/../config/database.php';

class Chat {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    /** Get or create a direct-message room between two users. */
    public function getOrCreateDirectRoom($userA, $userB) {
        // Look for existing direct room with exactly these two members
        $stmt = $this->pdo->prepare("
            SELECT r.id FROM chat_rooms r
            JOIN chat_members ma ON ma.room_id = r.id AND ma.user_id = ?
            JOIN chat_members mb ON mb.room_id = r.id AND mb.user_id = ?
            WHERE r.type = 'direct'
            LIMIT 1
        ");
        $stmt->execute([$userA, $userB]);
        $row = $stmt->fetch();
        if ($row) return $row['id'];

        // Create new direct room
        $this->pdo->prepare("INSERT INTO chat_rooms (type) VALUES ('direct')")->execute();
        $roomId = (int)$this->pdo->lastInsertId();
        $ins = $this->pdo->prepare("INSERT INTO chat_members (room_id, user_id) VALUES (?,?)");
        $ins->execute([$roomId, $userA]);
        $ins->execute([$roomId, $userB]);
        return $roomId;
    }

    /** Get messages for a room, newest-last, limited. */
    public function getMessages($roomId, $after = 0, $limit = 60) {
        $stmt = $this->pdo->prepare("
            SELECT m.id, m.message, m.msg_type, m.created_at,
                   u.id AS sender_id, u.fullname AS sender_name, u.username,
                   r.name AS role_name
            FROM chat_messages m
            JOIN users u ON m.sender_id = u.id
            JOIN roles r ON u.role_id = r.id
            WHERE m.room_id = ? AND m.id > ?
            ORDER BY m.created_at ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, (int)$roomId, \PDO::PARAM_INT);
        $stmt->bindValue(2, (int)$after,  \PDO::PARAM_INT);
        $stmt->bindValue(3, (int)$limit,  \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Get last message preview for a room. */
    public function getLastMessage($roomId) {
        $stmt = $this->pdo->prepare("
            SELECT m.message, m.created_at, u.fullname AS sender_name
            FROM chat_messages m JOIN users u ON m.sender_id = u.id
            WHERE m.room_id = ?
            ORDER BY m.created_at DESC LIMIT 1
        ");
        $stmt->execute([$roomId]);
        return $stmt->fetch();
    }

    /** Count unread messages in a room for a user. */
    public function countUnread($roomId, $userId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM chat_messages m
            LEFT JOIN chat_members mb ON mb.room_id = m.room_id AND mb.user_id = ?
            WHERE m.room_id = ?
              AND m.sender_id != ?
              AND (mb.last_read_at IS NULL OR m.created_at > mb.last_read_at)
        ");
        $stmt->execute([$userId, $roomId, $userId]);
        return (int)$stmt->fetchColumn();
    }

    /** Mark room as read for user. */
    public function markRead($roomId, $userId) {
        $this->pdo->prepare("
            INSERT INTO chat_members (room_id, user_id, last_read_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_read_at = NOW()
        ")->execute([$roomId, $userId]);
    }

    /** Post a message. */
    public function sendMessage($roomId, $senderId, $message, $type = 'text') {
        $message = trim($message);
        if ($message === '') return false;
        $type = in_array($type, ['text', 'sticker']) ? $type : 'text';
        $stmt = $this->pdo->prepare("INSERT INTO chat_messages (room_id, sender_id, message, msg_type) VALUES (?,?,?,?)");
        return $stmt->execute([$roomId, $senderId, $message, $type]);
    }

    /** Create a named group room. */
    public function createGroupRoom(string $name, array $memberIds): int {
        $this->pdo->prepare("INSERT INTO chat_rooms (type, name) VALUES ('group', ?)")->execute([trim($name)]);
        $roomId = (int)$this->pdo->lastInsertId();
        $ins = $this->pdo->prepare("INSERT IGNORE INTO chat_members (room_id, user_id) VALUES (?,?)");
        foreach (array_unique($memberIds) as $uid) {
            $ins->execute([$roomId, (int)$uid]);
        }
        return $roomId;
    }

    /** Get all group rooms (type='group') a user belongs to. */
    public function getGroupRooms(int $userId): array {
        $stmt = $this->pdo->prepare("
            SELECT cr.id, cr.name,
                   COUNT(cm2.user_id) AS member_count
            FROM chat_rooms cr
            JOIN chat_members cm  ON cm.room_id  = cr.id AND cm.user_id = ?
            JOIN chat_members cm2 ON cm2.room_id = cr.id
            WHERE cr.type = 'group'
            GROUP BY cr.id, cr.name
            ORDER BY cr.id DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** Get basic room info. */
    public function getRoomInfo(int $roomId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM chat_rooms WHERE id = ?");
        $stmt->execute([$roomId]);
        return $stmt->fetch() ?: null;
    }

    /** Get member count of a room. */
    public function getMemberCount(int $roomId): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM chat_members WHERE room_id = ?");
        $stmt->execute([$roomId]);
        return (int)$stmt->fetchColumn();
    }

    /** Get the other user in a direct room. */
    public function getDirectPartner($roomId, $myId) {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.fullname, u.username, r.name AS role_name
            FROM chat_members cm
            JOIN users u ON u.id = cm.user_id
            JOIN roles r ON r.id = u.role_id
            WHERE cm.room_id = ? AND cm.user_id != ?
            LIMIT 1
        ");
        $stmt->execute([$roomId, $myId]);
        return $stmt->fetch();
    }

    /** Get all users except self for the contact list. */
    public function getContacts($myId) {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.fullname, u.username, u.status, r.name AS role_name
            FROM users u JOIN roles r ON r.id = u.role_id
            WHERE u.id != ? AND u.status = 'active'
            ORDER BY r.id DESC, u.fullname ASC
        ");
        $stmt->execute([$myId]);
        return $stmt->fetchAll();
    }

    /** Get total unread count across all rooms for a user. */
    public function totalUnread($userId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM chat_messages m
            JOIN chat_members cm ON cm.room_id = m.room_id AND cm.user_id = ?
            WHERE m.sender_id != ?
              AND (cm.last_read_at IS NULL OR m.created_at > cm.last_read_at)
        ");
        $stmt->execute([$userId, $userId]);
        return (int)$stmt->fetchColumn();
    }
}
?>
