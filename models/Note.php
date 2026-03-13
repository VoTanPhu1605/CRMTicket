<?php
require_once __DIR__ . '/../config/database.php';

class Note {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function create($data) {
        $stmt = $this->pdo->prepare("INSERT INTO ticket_notes (ticket_id, note, user_id) VALUES (?, ?, ?)");
        return $stmt->execute([
            $data['ticket_id'],
            $data['note'],
            $data['created_by']
        ]);
    }

    public function getByTicketId($ticketId) {
        $stmt = $this->pdo->prepare("
            SELECT n.*, u.fullname as created_by_name
            FROM ticket_notes n
            JOIN users u ON n.user_id = u.id
            WHERE n.ticket_id = ?
            ORDER BY n.created_at DESC
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM ticket_notes WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
?>