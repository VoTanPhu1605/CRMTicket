<?php
require_once __DIR__ . '/../config/database.php';

class ActivityLog {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function log($ticketId, $userId, $actionType, $description, $oldValue = null, $newValue = null) {
        $ticketId = (int)$ticketId;
        if ($ticketId <= 0) return false;
        $stmt = $this->pdo->prepare("
            INSERT INTO ticket_activity_log (ticket_id, user_id, action_type, description, old_value, new_value)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$ticketId, $userId, $actionType, $description, $oldValue, $newValue]);
    }

    public function getByTicketId($ticketId) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, u.fullname as user_name
            FROM ticket_activity_log a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.ticket_id = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }

    public function tableExists() {
        try {
            $this->pdo->query("SELECT 1 FROM ticket_activity_log LIMIT 1");
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }
}
?>
