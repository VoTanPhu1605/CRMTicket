<?php
require_once __DIR__ . '/../models/Ticket.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/authController.php';

class ReportController {
    private $ticketModel;
    private $userModel;
    private $authController;

    public function __construct() {
        $this->ticketModel = new Ticket();
        $this->userModel = new User();
        $this->authController = new AuthController();
    }

    public function getDashboardStats() {
        $this->authController->requireLogin();

        $stats = $this->ticketModel->getStats();
        $recentTickets = $this->ticketModel->getRecent(5);

        return [
            'ticket_stats' => $stats,
            'recent_tickets' => $recentTickets
        ];
    }

    public function getTicketReports($filters = []) {
        $this->authController->requireLogin();

        $startDate = $filters['start_date'] ?? date('Y-m-01'); // First day of current month
        $endDate = $filters['end_date'] ?? date('Y-m-t'); // Last day of current month

        // Tickets by status
        $pdo = $this->ticketModel->getPdo();
        $stmt = $pdo->prepare("
            SELECT s.name as status, COUNT(*) as count
            FROM tickets t
            JOIN statuses s ON t.status_id = s.id
            WHERE DATE(t.created_at) BETWEEN ? AND ?
            GROUP BY s.id, s.name
            ORDER BY s.name
        ");
        $stmt->execute([$startDate, $endDate]);
        $ticketsByStatus = $stmt->fetchAll();

        // Tickets by priority
        $stmt = $pdo->prepare("
            SELECT p.name as priority, COUNT(*) as count
            FROM tickets t
            JOIN priorities p ON t.priority_id = p.id
            WHERE DATE(t.created_at) BETWEEN ? AND ?
            GROUP BY p.id, p.name
            ORDER BY p.name
        ");
        $stmt->execute([$startDate, $endDate]);
        $ticketsByPriority = $stmt->fetchAll();

        // Tickets by category
        $stmt = $pdo->prepare("
            SELECT c.name as category, COUNT(*) as count
            FROM tickets t
            JOIN categories c ON t.category_id = c.id
            WHERE DATE(t.created_at) BETWEEN ? AND ?
            GROUP BY c.id, c.name
            ORDER BY c.name
        ");
        $stmt->execute([$startDate, $endDate]);
        $ticketsByCategory = $stmt->fetchAll();

        // Agent performance (only for Admin and Manager)
        $agentPerformance = [];
        if ($this->authController->hasAnyRole(['Admin', 'Manager'])) {
            $pdo = $this->ticketModel->getPdo();
            $stmt = $pdo->prepare("
                SELECT u.fullname as agent_name,
                       COUNT(t.id) as total_tickets,
                       SUM(CASE WHEN s.name = 'Đã đóng' THEN 1 ELSE 0 END) as closed_tickets
                FROM users u
                LEFT JOIN tickets t ON u.id = t.assigned_to
                LEFT JOIN statuses s ON t.status_id = s.id
                WHERE u.role_id IN (SELECT id FROM roles WHERE name IN ('IT Support', 'IT Helpdesk', 'IT Intern', 'Manager'))
                GROUP BY u.id, u.fullname
                ORDER BY total_tickets DESC
            ");
            $stmt->execute();
            $agentPerformance = $stmt->fetchAll();
        }

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'tickets_by_status' => $ticketsByStatus,
            'tickets_by_priority' => $ticketsByPriority,
            'tickets_by_category' => $ticketsByCategory,
            'agent_performance' => $agentPerformance
        ];
    }

    public function getUserActivityReport($userId = null, $filters = []) {
        $this->authController->requireAnyRole(['Admin', 'Manager']);

        $startDate = $filters['start_date'] ?? date('Y-m-01');
        $endDate = $filters['end_date'] ?? date('Y-m-t');

        $params = [$startDate, $endDate];
        $userCondition = '';

        if ($userId) {
            $userCondition = ' AND t.created_by = ?';
            $params[] = $userId;
        }

        // Tickets created by user
        $stmt = $this->ticketModel->pdo->prepare("
            SELECT COUNT(*) as tickets_created
            FROM tickets t
            WHERE DATE(t.created_at) BETWEEN ? AND ? $userCondition
        ");
        $stmt->execute($params);
        $ticketsCreated = $stmt->fetch()['tickets_created'];

        // Notes added by user
        $stmt = $this->ticketModel->pdo->prepare("
            SELECT COUNT(*) as notes_added
            FROM ticket_notes n
            WHERE DATE(n.created_at) BETWEEN ? AND ? $userCondition
        ");
        $stmt->execute([$startDate, $endDate, $userId ?? null]);
        $notesAdded = $stmt->fetch()['notes_added'];

        // Tickets assigned to user (if agent)
        $stmt = $this->ticketModel->pdo->prepare("
            SELECT COUNT(*) as tickets_assigned,
                   SUM(CASE WHEN s.name = 'Đã đóng' THEN 1 ELSE 0 END) as tickets_closed
            FROM tickets t
            JOIN statuses s ON t.status_id = s.id
            WHERE t.assigned_to = ? AND DATE(t.created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$userId, $startDate, $endDate]);
        $assignmentStats = $stmt->fetch();

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'tickets_created' => $ticketsCreated,
            'notes_added' => $notesAdded,
            'tickets_assigned' => $assignmentStats['tickets_assigned'] ?? 0,
            'tickets_closed' => $assignmentStats['tickets_closed'] ?? 0
        ];
    }

    public function exportTickets($filters = []) {
        $this->authController->requireAnyRole(['Admin', 'Manager']);

        $tickets = $this->ticketModel->getAll($filters);

        // Convert to CSV format
        $csv = "\xEF\xBB\xBF" . "ID,Tiêu đề,Khách hàng,Email,Điện thoại,Danh mục,Ưu tiên,Trạng thái,Phân công,Ngày tạo\n";

        foreach ($tickets as $ticket) {
            $csv .= sprintf(
                "%s,\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $ticket['id'],
                str_replace('"', '""', $ticket['title']),
                str_replace('"', '""', $ticket['customer_name']),
                $ticket['customer_email'],
                $ticket['customer_phone'] ?? '',
                $ticket['category_name'],
                $ticket['priority_name'],
                $ticket['status_name'],
                $ticket['assigned_name'] ?? '',
                $ticket['created_at']
            );
        }

        return $csv;
    }
}
?>