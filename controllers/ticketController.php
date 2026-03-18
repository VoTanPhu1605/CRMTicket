<?php
require_once __DIR__ . '/../models/Ticket.php';
require_once __DIR__ . '/../models/Note.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/ActivityLog.php';
require_once __DIR__ . '/../models/Billing.php';
require_once __DIR__ . '/authController.php';
require_once __DIR__ . '/../includes/functions.php';

class TicketController {
    private $ticketModel;
    private $noteModel;
    private $authController;
    private $userModel;
    private $activityLog;
    private $billingModel;

    public function __construct() {
        $this->ticketModel = new Ticket();
        $this->noteModel = new Note();
        $this->authController = new AuthController();
        $this->userModel = new User();
        $this->activityLog = new ActivityLog();
        $this->billingModel = new Billing();
    }

    /** Returns warranty end date string for a ticket based on its category (defaults to 12 months). */
    private function calcWarrantyEnd($ticket) {
        $months = $this->ticketModel->getCategoryWarrantyMonths((int)($ticket['category_id'] ?? 0));
        if ($months <= 0) $months = 12;
        return date('Y-m-d', strtotime("+{$months} months"));
    }

    public function createTicket($data) {
        $this->authController->requireLogin();

        $currentUser = $this->authController->getCurrentUser();

        $ticketData = [
            'title' => trim($data['title']),
            'description' => trim($data['description'] ?? ''),
            'customer_name' => trim($data['customer_name']),
            'customer_email' => trim($data['customer_email']),
            'customer_phone' => trim($data['customer_phone'] ?? ''),
            'customer_address' => trim($data['customer_address'] ?? ''),
            'category_id' => $data['category_id'],
            'priority_id' => $data['priority_id'],
            'status_id' => 1, // Default to "Mở"
            'created_by' => $currentUser['id'],
            'due_date' => !empty($data['due_date']) ? $data['due_date'] : null,
        ];

        // Validate required fields
        if (empty($ticketData['title']) || empty($ticketData['customer_name']) || empty($ticketData['customer_email']) || empty($ticketData['customer_address'])) {
            return ['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin bắt buộc (bao gồm địa chỉ khách hàng).'];
        }

        // Validate due_date is not in the past
        if (!empty($ticketData['due_date']) && $ticketData['due_date'] < date('Y-m-d')) {
            return ['success' => false, 'message' => 'Hạn xử lý không thể là ngày trong quá khứ.'];
        }

        // Validate email
        if (!filter_var($ticketData['customer_email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Email không hợp lệ.'];
        }

        if ($this->ticketModel->create($ticketData)) {
            // Get lastInsertId BEFORE any other query (tableExists runs a SELECT)
            $lastId = (int)$this->ticketModel->getPdo()->lastInsertId();
            if ($lastId > 0 && $this->activityLog->tableExists()) {
                $this->activityLog->log($lastId, $currentUser['id'], 'create', 'Ticket được tạo');
            }
            return ['success' => true, 'message' => 'Ticket đã được tạo thành công.', 'new_id' => $lastId];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra khi tạo ticket.'];
    }

    public function getTickets($filters = []) {
        $this->authController->requireLogin();

        return $this->ticketModel->getAll($filters);
    }

    public function getTicket($id) {
        $this->authController->requireLogin();

        $ticket = $this->ticketModel->findById($id);
        if (!$ticket) return null;

        $currentUser = $this->authController->getCurrentUser();

        // Admin & Manager: full access to all tickets
        if ($this->authController->hasAnyRole(['Admin', 'Manager'])) {
            return $ticket;
        }

        // IT Helpdesk: only view tickets assigned to them
        if ($this->authController->hasRole('IT Helpdesk')) {
            if ($ticket['assigned_to'] == $currentUser['id']) return $ticket;
            return null;
        }

        return null;
    }

    public function updateTicket($id, $data) {
        $this->authController->requireLogin();

        $ticket = $this->ticketModel->findById($id);
        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket không tồn tại.'];
        }

        $currentUser = $this->authController->getCurrentUser();
        $isAdminOrManager = $this->authController->hasAnyRole(['Admin', 'Manager']);
        $isITHelpdesk = $this->authController->hasRole('IT Helpdesk');

        // IT Helpdesk: only update tickets assigned to them, only status_id field
        if ($isITHelpdesk) {
            if ($ticket['assigned_to'] != $currentUser['id']) {
                return ['success' => false, 'message' => 'Bạn chỉ có thể cập nhật ticket được phân công cho bạn.'];
            }
        } elseif (!$isAdminOrManager) {
            return ['success' => false, 'message' => 'Bạn không có quyền cập nhật ticket này.'];
        }

        $updateData = [];

        // Only Admin & Manager can edit ticket details
        if ($isAdminOrManager) {
            if (isset($data['title'])) $updateData['title'] = trim($data['title']);
            if (isset($data['description'])) $updateData['description'] = trim($data['description']);
            if (isset($data['customer_name'])) $updateData['customer_name'] = trim($data['customer_name']);
            if (isset($data['customer_email'])) $updateData['customer_email'] = trim($data['customer_email']);
            if (isset($data['customer_phone'])) $updateData['customer_phone'] = trim($data['customer_phone']);
            if (isset($data['customer_address'])) $updateData['customer_address'] = trim($data['customer_address']);
            if (isset($data['category_id'])) $updateData['category_id'] = $data['category_id'];
            if (isset($data['priority_id'])) $updateData['priority_id'] = $data['priority_id'];
            if (array_key_exists('due_date', $data)) $updateData['due_date'] = !empty($data['due_date']) ? $data['due_date'] : null;
        }

        // Block editing closed tickets
        if ((int)$ticket['status_id'] === 3) {
            return ['success' => false, 'message' => 'Ticket đã đóng, không thể chỉnh sửa.'];
        }

        // Admin & Manager: can change assignment
        if ($isAdminOrManager) {
            if (isset($data['assigned_to']) && !empty($data['assigned_to'])) {
                $targetUser = $this->userModel->findById($data['assigned_to']);
                if ($targetUser && canAssignToRole($currentUser['role_name'], $targetUser['role_name'])) {
                    $updateData['assigned_to'] = $data['assigned_to'];
                }
            } elseif (isset($data['assigned_to']) && $data['assigned_to'] === '') {
                $updateData['assigned_to'] = null;
            }
        }

        // Status update: Admin, Manager AND IT Helpdesk (assigned only) can update status
        if (isset($data['status_id'])) {
            $newStatusId    = (int)$data['status_id'];
            $currentStatusId = (int)$ticket['status_id'];

            // Require assigned_to before moving out of Mở (Admin/Manager only, since IT Helpdesk is already assigned)
            if ($isAdminOrManager && $newStatusId !== $currentStatusId && empty($updateData['assigned_to']) && empty($ticket['assigned_to'])) {
                return ['success' => false, 'message' => 'Vui lòng phân công nhân viên xử lý trước khi chuyển trạng thái.'];
            }

            // Get closed status ID dynamically
            $closedStatusId = $this->ticketModel->getStatusIdByName('Đã đóng') ?? 3;

            // Payment gate: must pay before closing
            if ($newStatusId === $closedStatusId) {
                $billing = $this->billingModel->getBillingByTicket((int)$id);
                if ($billing && (int)$billing['price'] > 0 && !in_array($billing['payment_status'], ['paid', 'waived'])) {
                    return ['success' => false, 'message' => 'Vui lòng hoàn thành thanh toán trước khi đóng ticket.'];
                }
            }
            $updateData['status_id'] = $newStatusId;
            if ($newStatusId === $closedStatusId && empty($ticket['warranty_end_date'])) {
                $updateData['warranty_end_date'] = $this->calcWarrantyEnd($ticket);
            }
        }

        if ($this->ticketModel->update($id, $updateData)) {
            if ($this->activityLog->tableExists()) {
                $this->activityLog->log($id, $currentUser['id'], 'update', 'Ticket được cập nhật');
            }
            return ['success' => true, 'message' => 'Ticket đã được cập nhật thành công.'];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra khi cập nhật ticket.'];
    }

    public function deleteTicket($id) {
        $this->authController->requireAnyRole(['Admin']);

        $ticket = $this->ticketModel->findById($id);
        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket không tồn tại.'];
        }

        if ($this->ticketModel->delete($id)) {
            return ['success' => true, 'message' => 'Ticket đã được xóa thành công.'];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra khi xóa ticket.'];
    }

    public function assignTicket($ticketId, $agentId) {
        $this->authController->requireAnyRole(['Admin', 'Manager']);

        $currentUserRole = $this->authController->getCurrentUser()['role_name'];
        $targetUser = $this->userModel->findById($agentId);

        if (!$targetUser) {
            return ['success' => false, 'message' => 'Người dùng không tồn tại.'];
        }

        if (!canAssignToRole($currentUserRole, $targetUser['role_name'])) {
            return ['success' => false, 'message' => 'Không thể gán ticket cho người có vai trò ngang hoặc cao hơn bạn.'];
        }

        if ($this->ticketModel->assignToAgent($ticketId, $agentId)) {
            return ['success' => true, 'message' => 'Ticket đã được phân công thành công.'];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra khi phân công ticket.'];
    }

    public function addNote($ticketId, $note) {
        $this->authController->requireLogin();

        $ticket = $this->ticketModel->findById($ticketId);
        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket không tồn tại.'];
        }

        // Check permissions
        $currentUser = $this->authController->getCurrentUser();
        if (!$this->authController->hasAnyRole(['Admin', 'Manager', 'IT Helpdesk', 'IT Support', 'IT Intern']) &&
            $ticket['created_by'] != $currentUser['id'] &&
            $ticket['assigned_to'] != $currentUser['id']) {
            return ['success' => false, 'message' => 'Bạn không có quyền thêm ghi chú cho ticket này.'];
        }

        $noteData = [
            'ticket_id' => $ticketId,
            'note' => trim($note),
            'created_by' => $currentUser['id']
        ];

        if (empty($noteData['note'])) {
            return ['success' => false, 'message' => 'Nội dung ghi chú không được để trống.'];
        }

        if ($this->noteModel->create($noteData)) {
            if ($this->activityLog->tableExists()) {
                $this->activityLog->log($ticketId, $currentUser['id'], 'note', 'Thêm ghi chú mới');
            }
            return ['success' => true, 'message' => 'Ghi chú đã được thêm thành công.'];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra khi thêm ghi chú.'];
    }

    public function getNotes($ticketId) {
        $this->authController->requireLogin();

        $ticket = $this->ticketModel->findById($ticketId);
        if (!$ticket) {
            return [];
        }

        // Check permissions
        $currentUser = $this->authController->getCurrentUser();
        if (!$this->authController->hasAnyRole(['Admin', 'Manager', 'IT Helpdesk', 'IT Support', 'IT Intern']) &&
            $ticket['created_by'] != $currentUser['id'] &&
            $ticket['assigned_to'] != $currentUser['id']) {
            return [];
        }

        return $this->noteModel->getByTicketId($ticketId);
    }

    public function quickUpdateStatus($ticketId, $statusId) {
        $this->authController->requireAnyRole(['Admin', 'Manager', 'IT Helpdesk', 'IT Support', 'IT Intern']);

        $ticket = $this->ticketModel->findById($ticketId);
        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket không tồn tại.'];
        }
        $currentUser = $this->authController->getCurrentUser();
        // IT Helpdesk, IT Support, and IT Intern can only update tickets assigned to them
        if ($this->authController->hasAnyRole(['IT Helpdesk', 'IT Support', 'IT Intern']) && $ticket['assigned_to'] != $currentUser['id']) {
            return ['success' => false, 'message' => 'Không có quyền.'];
        }

        $transitions = [1 => 'Đang xử lý', 2 => 'Đã đóng'];
        $currentStatusId = (int)$ticket['status_id'];
        if ($currentStatusId === 3) {
            return ['success' => false, 'message' => 'Ticket đã đóng, không thể thay đổi trạng thái.'];
        }
        if (!isset($transitions[$currentStatusId]) || (int)$statusId !== ($currentStatusId + 1)) {
            $nextName = $transitions[$currentStatusId] ?? '';
            return ['success' => false, 'message' => "Chỉ có thể chuyển sang: $nextName."];
        }
        // Payment gate: must pay before closing
        if ((int)$statusId === 3) {
            $billing = $this->billingModel->getBillingByTicket((int)$ticketId);
            if ($billing && (int)$billing['price'] > 0 && !in_array($billing['payment_status'], ['paid', 'waived'])) {
                return ['success' => false, 'message' => 'Vui lòng hoàn thành thanh toán trước khi đóng ticket.'];
            }
        }
        // Auto-set warranty when closing ticket
        if ((int)$statusId === 3 && empty($ticket['warranty_end_date'])) {
            $this->ticketModel->update((int)$ticketId, ['warranty_end_date' => $this->calcWarrantyEnd($ticket)]);
        }
        if ($this->ticketModel->updateStatus((int)$ticketId, (int)$statusId)) {
            if ($this->activityLog->tableExists()) {
                $this->activityLog->log($ticketId, $currentUser['id'], 'status_change', 'Thay đổi trạng thái', null, (string)$statusId);
            }
            return ['success' => true, 'message' => 'Đã cập nhật trạng thái.'];
        }
        return ['success' => false, 'message' => 'Lỗi cập nhật trạng thái.'];
    }

    public function createWarrantyClaim($originalId, $customerName, $customerEmail) {
        $original = $this->ticketModel->findById($originalId);
        if (!$original) {
            return ['success' => false, 'message' => 'Ticket gốc không tồn tại.'];
        }
        if (empty($original['warranty_end_date']) || strtotime($original['warranty_end_date']) < strtotime('today')) {
            return ['success' => false, 'message' => 'Ticket này không còn trong thời hạn bảo hành.'];
        }
        if (!empty($original['warranty_claim_id'])) {
            return ['success' => false, 'message' => 'Ticket bảo hành đã được tạo trước đó.'];
        }

        $currentUser = $this->authController->getCurrentUser();
        $claimData = [
            'title'          => '[BH] ' . $original['title'],
            'description'    => 'Yêu cầu bảo hành từ ticket #' . $originalId . '. Bảo hành hết hạn: ' . date('d/m/Y', strtotime($original['warranty_end_date'])),
            'customer_name'  => $customerName ?: $original['customer_name'],
            'customer_email' => $customerEmail ?: $original['customer_email'],
            'customer_phone' => $original['customer_phone'] ?? '',
            'category_id'    => $original['category_id'],
            'priority_id'    => $original['priority_id'],
            'status_id'      => 1, // Mở
            'created_by'     => $currentUser['id'],
            'due_date'       => null,
        ];

        if ($this->ticketModel->create($claimData)) {
            $newId = (int)$this->ticketModel->getPdo()->lastInsertId();
            // Link original ticket → claim
            $this->ticketModel->update($originalId, ['warranty_claim_id' => $newId]);
            if ($newId > 0 && $this->activityLog->tableExists()) {
                $this->activityLog->log($newId, $currentUser['id'], 'create', 'Ticket bảo hành từ #' . $originalId);
                $this->activityLog->log($originalId, $currentUser['id'], 'update', 'Tạo yêu cầu bảo hành #' . $newId);
            }
            return ['success' => true, 'new_id' => $newId, 'message' => 'Đã tạo ticket bảo hành.'];
        }
        return ['success' => false, 'message' => 'Không thể tạo ticket bảo hành.'];
    }

    public function getActivityLog($ticketId) {
        $this->authController->requireLogin();
        if (!$this->activityLog->tableExists()) return [];
        return $this->activityLog->getByTicketId($ticketId);
    }

    public function getStats() {
        $this->authController->requireLogin();

        return $this->ticketModel->getStats();
    }

    public function getCategories() {
        return $this->ticketModel->getCategories();
    }

    public function getPriorities() {
        return $this->ticketModel->getPriorities();
    }

    public function getStatuses() {
        return $this->ticketModel->getStatuses();
    }

    public function transferTicket($ticketId, $toUserId, $reason = '') {
        $currentUser = $this->authController->getCurrentUser();
        $ticket = $this->ticketModel->findById($ticketId);
        if (!$ticket) return ['success' => false, 'message' => 'Ticket không tồn tại.'];

        $toUser = $this->userModel->findById($toUserId);
        if (!$toUser) return ['success' => false, 'message' => 'Nhân viên không tồn tại.'];

        // Check transfer permission by hierarchy
        if (!canTransferToRole($currentUser['role_name'], $toUser['role_name'])) {
            return ['success' => false, 'message' => 'Bạn không có quyền chuyển giao cho người này.'];
        }

        if ((int)$toUserId === (int)($ticket['assigned_to'] ?? 0)) {
            return ['success' => false, 'message' => 'Ticket đã được phân công cho nhân viên này.'];
        }

        $fromName = $ticket['assigned_name'] ?? 'Chưa phân công';
        $desc = "Chuyển giao từ [{$fromName}] → [{$toUser['fullname']}]" . ($reason ? ": {$reason}" : '');

        if ($this->ticketModel->update($ticketId, ['assigned_to' => $toUserId])) {
            if ($this->activityLog->tableExists()) {
                $this->activityLog->log($ticketId, $currentUser['id'], 'assign', $desc,
                    $ticket['assigned_name'], $toUser['fullname']);
            }
            return ['success' => true, 'message' => 'Đã chuyển giao ticket cho ' . $toUser['fullname'] . '.'];
        }
        return ['success' => false, 'message' => 'Lỗi khi chuyển giao ticket.'];
    }

    public function getAgentsForTransfer($excludeUserId = 0) {
        global $pdo;
        $currentUser = getCurrentUser();
        $currentRole = $currentUser['role_name'] ?? '';

        // Determine which hierarchy levels can be targets
        // Admin (level 1) → can transfer to level 2+ (Manager, IT Helpdesk, IT Support, IT Intern)
        // Manager (level 2) → can transfer to level 3+ (IT Helpdesk, IT Support, IT Intern)
        if ($currentRole === 'Admin') $minLevel = 2;
        elseif ($currentRole === 'Manager') $minLevel = 3;
        else return [];

        $stmt = $pdo->prepare("
            SELECT u.id, u.fullname, r.name AS role_name, r.hierarchy_level
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE r.hierarchy_level >= ?
              AND u.status = 'active'
              AND u.id != ?
            ORDER BY r.hierarchy_level ASC, u.fullname ASC
        ");
        $stmt->execute([$minLevel, $excludeUserId]);
        return $stmt->fetchAll();
    }
}
?>