<?php
require_once __DIR__ . '/../config/database.php';

class Ticket {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function getPdo() {
        return $this->pdo;
    }

    public function create($data) {
        $stmt = $this->pdo->prepare("INSERT INTO tickets (title, description, customer_name, customer_email, customer_phone, category_id, priority_id, status_id, created_by, due_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $data['title'],
            $data['description'] ?? null,
            $data['customer_name'],
            $data['customer_email'],
            $data['customer_phone'] ?? null,
            $data['category_id'],
            $data['priority_id'],
            $data['status_id'],
            $data['created_by'],
            $data['due_date'] ?? null,
        ]);
    }

    public function findById($id) {
        $stmt = $this->pdo->prepare("
            SELECT t.*, c.name as category_name, p.name as priority_name, p.color as priority_color,
                   s.name as status_name, s.color as status_color,
                   u_assigned.fullname as assigned_name, u_created.fullname as created_name
            FROM tickets t
            JOIN categories c ON t.category_id = c.id
            JOIN priorities p ON t.priority_id = p.id
            JOIN statuses s ON t.status_id = s.id
            LEFT JOIN users u_assigned ON t.assigned_to = u_assigned.id
            JOIN users u_created ON t.created_by = u_created.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getAll($filters = []) {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "s.name = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $where[] = "p.name = ?";
            $params[] = $filters['priority'];
        }
        if (!empty($filters['category'])) {
            $where[] = "c.name = ?";
            $params[] = $filters['category'];
        }
        if (!empty($filters['search'])) {
            $where[] = "(t.title LIKE ? OR t.customer_name LIKE ? OR t.customer_email LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$search, $search, $search]);
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->pdo->prepare("
            SELECT t.*, c.name as category_name, p.name as priority_name, p.color as priority_color,
                   s.name as status_name, s.color as status_color,
                   u_assigned.fullname as assigned_name
            FROM tickets t
            JOIN categories c ON t.category_id = c.id
            JOIN priorities p ON t.priority_id = p.id
            JOIN statuses s ON t.status_id = s.id
            LEFT JOIN users u_assigned ON t.assigned_to = u_assigned.id
            $whereClause
            ORDER BY t.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getRecent($limit = 5) {
        $limit = (int)$limit;
        $stmt = $this->pdo->prepare("
            SELECT t.*, c.name as category_name, p.name as priority_name, p.color as priority_color,
                   s.name as status_name, s.color as status_color,
                   u_assigned.fullname as assigned_name
            FROM tickets t
            JOIN categories c ON t.category_id = c.id
            JOIN priorities p ON t.priority_id = p.id
            JOIN statuses s ON t.status_id = s.id
            LEFT JOIN users u_assigned ON t.assigned_to = u_assigned.id
            ORDER BY t.created_at DESC
            LIMIT $limit
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function update($id, $data) {
        $fields = [];
        $values = [];

        if (isset($data['title'])) {
            $fields[] = "title = ?";
            $values[] = $data['title'];
        }
        if (isset($data['description'])) {
            $fields[] = "description = ?";
            $values[] = $data['description'];
        }
        if (isset($data['customer_name'])) {
            $fields[] = "customer_name = ?";
            $values[] = $data['customer_name'];
        }
        if (isset($data['customer_email'])) {
            $fields[] = "customer_email = ?";
            $values[] = $data['customer_email'];
        }
        if (isset($data['customer_phone'])) {
            $fields[] = "customer_phone = ?";
            $values[] = $data['customer_phone'];
        }
        if (isset($data['category_id'])) {
            $fields[] = "category_id = ?";
            $values[] = $data['category_id'];
        }
        if (isset($data['priority_id'])) {
            $fields[] = "priority_id = ?";
            $values[] = $data['priority_id'];
        }
        if (isset($data['status_id'])) {
            $fields[] = "status_id = ?";
            $values[] = $data['status_id'];
        }
        if (isset($data['assigned_to'])) {
            $fields[] = "assigned_to = ?";
            $values[] = $data['assigned_to'];
        }

        $values[] = $id;
        $stmt = $this->pdo->prepare("UPDATE tickets SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($values);
    }

    public function updateStatus($id, $newStatusId) {
        // Allowed transitions: 1(Mở)→2(Đang xử lý)→3(Đã đóng), no backwards
        $allowed = [1 => [2], 2 => [3], 3 => []];
        $stmt = $this->pdo->prepare("SELECT status_id FROM tickets WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return false;
        $current = (int)$row['status_id'];
        if (!in_array((int)$newStatusId, $allowed[$current] ?? [])) return false;
        $stmt = $this->pdo->prepare("UPDATE tickets SET status_id = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$newStatusId, $id]);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM tickets WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getStats() {
        $stmt = $this->pdo->query("
            SELECT
                COUNT(*) as total_tickets,
                SUM(CASE WHEN s.name = 'Đang chờ' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN s.name = 'Đang xử lý' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN s.name = 'Đã đóng' THEN 1 ELSE 0 END) as closed
            FROM tickets t
            JOIN statuses s ON t.status_id = s.id
        ");
        return $stmt->fetch();
    }

    public function getCategories() {
        $stmt = $this->pdo->query("SELECT * FROM categories");
        return $stmt->fetchAll();
    }

    public function getCategoryWarrantyMonths($categoryId) {
        $stmt = $this->pdo->prepare("SELECT warranty_months FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        $row = $stmt->fetch();
        return $row ? (int)$row['warranty_months'] : 3;
    }

    public function getWarrantyList($filter = 'active') {
        // Auto-fix: set warranty_end_date for old closed tickets that don't have it yet
        $this->pdo->exec("
            UPDATE tickets t
            JOIN categories c ON c.id = t.category_id
            JOIN statuses s ON s.id = t.status_id
            SET t.warranty_end_date = DATE_ADD(t.updated_at, INTERVAL GREATEST(COALESCE(c.warranty_months,0),12) MONTH)
            WHERE s.id = 3 AND t.warranty_end_date IS NULL
        ");

        if ($filter === 'active') {
            $where = "s.id = 3 AND t.warranty_end_date >= CURDATE()";
        } elseif ($filter === 'expired') {
            $where = "s.id = 3 AND t.warranty_end_date < CURDATE()";
        } else {
            $where = "s.id = 3";
        }
        $stmt = $this->pdo->query("
            SELECT t.id, t.title, t.customer_name, t.customer_email, t.customer_phone,
                   t.warranty_end_date, t.warranty_claim_id, t.created_at,
                   c.name AS category_name, c.category_type, c.warranty_months,
                   s.name AS status_name, s.color AS status_color,
                   u.fullname AS assigned_name
            FROM tickets t
            JOIN categories c ON t.category_id = c.id
            JOIN statuses s ON t.status_id = s.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE {$where}
            ORDER BY t.warranty_end_date ASC
        ");
        return $stmt->fetchAll();
    }

    public function getPriorities() {
        $stmt = $this->pdo->query("SELECT * FROM priorities");
        return $stmt->fetchAll();
    }

    public function getStatuses() {
        $stmt = $this->pdo->query("SELECT * FROM statuses");
        return $stmt->fetchAll();
    }

    public function assignToAgent($ticketId, $agentId) {
        $stmt = $this->pdo->prepare("UPDATE tickets SET assigned_to = ? WHERE id = ?");
        return $stmt->execute([$agentId, $ticketId]);
    }

    public function getChartData() {
        $byStatus = $this->pdo->query("
            SELECT s.name AS label, COUNT(t.id) AS count
            FROM statuses s
            LEFT JOIN tickets t ON t.status_id = s.id
            GROUP BY s.id, s.name
            ORDER BY count DESC
        ")->fetchAll();

        $byCategory = $this->pdo->query("
            SELECT c.name AS label, COUNT(t.id) AS count
            FROM categories c
            LEFT JOIN tickets t ON t.category_id = c.id
            GROUP BY c.id, c.name
            ORDER BY count DESC
        ")->fetchAll();

        $byPriority = $this->pdo->query("
            SELECT p.name AS label, COUNT(t.id) AS count
            FROM priorities p
            LEFT JOIN tickets t ON t.priority_id = p.id
            GROUP BY p.id, p.name
            ORDER BY count DESC
        ")->fetchAll();

        return [
            'by_status'   => $byStatus,
            'by_category' => $byCategory,
            'by_priority' => $byPriority,
        ];
    }
}
?>