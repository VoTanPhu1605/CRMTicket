<?php
ob_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'controllers/ticketController.php';
require_once 'controllers/billingController.php';
require_once __DIR__ . '/config/ai_config.php';

requireLogin();

$ticketController  = new TicketController();
$billingController = new BillingController();
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Helper: send clean JSON (discards any PHP warning output)
function jsonOut($data) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// AJAX: get service templates by category
if ($action === 'get_templates' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $catId = (int)($_GET['category_id'] ?? 0);
    jsonOut(['templates' => $billingController->getTemplatesByCategory($catId)]);
}

// AJAX: set billing for a ticket
if ($action === 'set_billing' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hasAnyRole(['Admin', 'Manager'])) jsonOut(['success' => false, 'message' => 'Không có quyền.']);
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    if (!$ticketId) jsonOut(['success' => false, 'message' => 'Thiếu ticket_id.']);
    jsonOut($billingController->setBilling($ticketId, $_POST));
}

// AJAX: initiate payment
if ($action === 'initiate_payment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticketId      = (int)($_POST['ticket_id'] ?? 0);
    $allowedMethods = ['cash', 'momo', 'bank_transfer', 'visa', 'zalopay', 'vnpay'];
    $method        = in_array($_POST['method'] ?? '', $allowedMethods) ? $_POST['method'] : 'cash';
    jsonOut($billingController->initiatePayment($ticketId, $method));
}

// AJAX: confirm payment
if ($action === 'confirm_payment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentId = (int)($_POST['payment_id'] ?? 0);
    jsonOut($billingController->confirmPayment($paymentId));
}

// AJAX: cancel payment
if ($action === 'cancel_payment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentId = (int)($_POST['payment_id'] ?? 0);
    jsonOut($billingController->cancelPayment($paymentId));
}

// AJAX: waive billing
if ($action === 'waive_billing' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hasAnyRole(['Admin', 'Manager'])) jsonOut(['success' => false, 'message' => 'Không có quyền.']);
    $billingId = (int)($_POST['billing_id'] ?? 0);
    jsonOut($billingController->waiveBilling($billingId));
}

// AJAX: quick status update
if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $statusId = (int)($_POST['status_id'] ?? 0);
    if (!$ticketId || !$statusId) jsonOut(['success' => false, 'message' => 'Thiếu tham số.']);
    try {
        jsonOut($ticketController->quickUpdateStatus($ticketId, $statusId));
    } catch (Exception $e) {
        jsonOut(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
}

// AJAX: create warranty claim ticket
if ($action === 'create_warranty' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hasAnyRole(['Admin', 'Manager'])) jsonOut(['success' => false, 'message' => 'Không có quyền.']);
    $originalId = (int)($_POST['warranty_original_id'] ?? 0);
    if (!$originalId) jsonOut(['success' => false, 'message' => 'Thiếu tham số.']);
    jsonOut($ticketController->createWarrantyClaim($originalId, $_POST['customer_name'] ?? '', $_POST['customer_email'] ?? ''));
}

// AJAX: transfer ticket to another agent
if ($action === 'transfer_ticket' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hasAnyRole(['Admin', 'Manager'])) jsonOut(['success' => false, 'message' => 'Không có quyền.']);
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $toUserId = (int)($_POST['to_user_id'] ?? 0);
    $reason   = trim($_POST['reason'] ?? '');
    if (!$ticketId || !$toUserId) jsonOut(['success' => false, 'message' => 'Thiếu tham số.']);
    jsonOut($ticketController->transferTicket($ticketId, $toUserId, $reason));
}

// Handle different actions
switch ($action) {
    case 'create':
        $pageTitle = 'Tạo Ticket mới';
        $pageActions = '<a href="tickets.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i>Quay lại
        </a>';

        $categories = $ticketController->getCategories();
        $priorities = $ticketController->getPriorities();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $ticketController->createTicket($_POST);
            if ($result['success']) {
                // Create billing record if price was set
                $newId = $result['new_id'] ?? 0;
                $price = (int)($_POST['ticket_price'] ?? 0);
                if ($newId > 0 && $price > 0) {
                    $billingController->setBilling($newId, [
                        'service_template_id' => $_POST['service_template_id'] ?? null,
                        'price'               => $price,
                        'note'                => '',
                    ]);
                }
                redirectWithMessage('tickets.php', 'success', $result['message']);
            } else {
                $error = $result['message'];
            }
        }
        break;

    case 'view':
        if (!$id) {
            header('Location: tickets.php');
            exit();
        }

        $ticket = $ticketController->getTicket($id);
        if (!$ticket) {
            redirectWithMessage('tickets.php', 'error', 'Ticket không tồn tại hoặc bạn không có quyền xem.');
        }

        $pageTitle = 'Chi tiết Ticket #' . $ticket['id'];
        $canTransfer = hasAnyRole(['Admin', 'Manager']);
        $isClosed = (int)$ticket['status_id'] === 3;
        $pageActions = '<div class="btn-group">
            <a href="tickets.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
            ' . ($canTransfer && !$isClosed ? '<button type="button" class="btn btn-warning" onclick="openTransferModal()">
                <i class="bi bi-arrow-left-right me-1"></i>Chuyển giao
            </button>' : '') . '
            ' . (!$isClosed && hasAnyRole(['Admin', 'Manager', 'IT Helpdesk']) ? '<a href="tickets.php?action=edit&id=' . $ticket['id'] . '" class="btn btn-primary">
                <i class="bi bi-pencil me-1"></i>Chỉnh sửa
            </a>' : '') . '
            ' . ($isClosed ? '<a href="warranty.php" class="btn btn-success">
                <i class="bi bi-shield-check me-1"></i>Xem Bảo hành
            </a>' : '') . '
        </div>';

        $notes = $ticketController->getNotes($id);
        $activityLogs = $ticketController->getActivityLog($id);
        $agentsForTransfer = $canTransfer ? $ticketController->getAgentsForTransfer((int)($ticket['assigned_to'] ?? 0)) : [];
        $billing  = $billingController->getBillingByTicket((int)$id);
        $payments = $billingController->getPaymentsByTicket((int)$id);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note'])) {
            $result = $ticketController->addNote($id, $_POST['note']);
            if ($result['success']) {
                redirectWithMessage('tickets.php?action=view&id=' . $id, 'success', $result['message']);
            } else {
                $error = $result['message'];
            }
        }
        break;

    case 'edit':
        requireAnyRole(['Admin', 'Manager', 'IT Helpdesk']);

        if (!$id) {
            header('Location: tickets.php');
            exit();
        }

        $ticket = $ticketController->getTicket($id);
        if (!$ticket) {
            redirectWithMessage('tickets.php', 'error', 'Ticket không tồn tại.');
        }

        // Ticket đã đóng → không cho chỉnh sửa
        if ((int)$ticket['status_id'] === 3) {
            redirectWithMessage('tickets.php?action=view&id=' . $id, 'error', 'Ticket đã đóng, không thể chỉnh sửa.');
        }

        $pageTitle = 'Chỉnh sửa Ticket #' . $ticket['id'];
        $pageActions = '<a href="tickets.php?action=view&id=' . $ticket['id'] . '" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i>Quay lại
        </a>';

        $categories = $ticketController->getCategories();
        $priorities = $ticketController->getPriorities();
        $statuses = $ticketController->getStatuses();
        $users = [];

        if (hasAnyRole(['Admin', 'Manager'])) {
            require_once 'controllers/userController.php';
            $userController = new UserController();
            $users = $userController->getAssignableUsers();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $ticketController->updateTicket($id, $_POST);
            if ($result['success']) {
                redirectWithMessage('tickets.php?action=view&id=' . $id, 'success', $result['message']);
            } else {
                $error = $result['message'];
            }
        }
        break;

    case 'delete':
        requireAnyRole(['Admin']);

        if (!$id) {
            header('Location: tickets.php');
            exit();
        }

        $result = $ticketController->deleteTicket($id);
        redirectWithMessage('tickets.php', $result['success'] ? 'success' : 'error', $result['message']);
        break;

    case 'assign':
        requireAnyRole(['Admin', 'Manager']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticket_id']) && isset($_POST['agent_id'])) {
            $result = $ticketController->assignTicket($_POST['ticket_id'], $_POST['agent_id']);
            echo json_encode($result);
            exit();
        }
        break;

    default: // list
        $pageTitle = 'Danh sách Tickets';
        $pageActions = '<a href="tickets.php?action=create" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Tạo Ticket mới
        </a>';

        // Build filters
        $filters = [];
        if (isset($_GET['status']) && !empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        if (isset($_GET['priority']) && !empty($_GET['priority'])) {
            $filters['priority'] = $_GET['priority'];
        }
        if (isset($_GET['category']) && !empty($_GET['category'])) {
            $filters['category'] = $_GET['category'];
        }
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $filters['search'] = $_GET['search'];
        }

        $tickets = $ticketController->getTickets($filters);
        $categories = $ticketController->getCategories();
        $priorities = $ticketController->getPriorities();
        $statuses = $ticketController->getStatuses();
        break;
}

include 'includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="tickets.php" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Trạng thái</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Tất cả</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo $status['name']; ?>" <?php echo (isset($_GET['status']) && $_GET['status'] === $status['name']) ? 'selected' : ''; ?>>
                                <?php echo $status['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="priority" class="form-label">Ưu tiên</label>
                    <select class="form-select" id="priority" name="priority">
                        <option value="">Tất cả</option>
                        <?php foreach ($priorities as $priority): ?>
                            <option value="<?php echo $priority['name']; ?>" <?php echo (isset($_GET['priority']) && $_GET['priority'] === $priority['name']) ? 'selected' : ''; ?>>
                                <?php echo $priority['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="category" class="form-label">Danh mục</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">Tất cả</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['name']; ?>" <?php echo (isset($_GET['category']) && $_GET['category'] === $category['name']) ? 'selected' : ''; ?>>
                                <?php echo $category['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="search" class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Tiêu đề, tên khách hàng..."
                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-search me-1"></i>Lọc
                    </button>
                    <a href="tickets.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i>Xóa bộ lọc
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tickets Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($tickets)): ?>
                <p class="text-muted text-center py-4">Không có ticket nào phù hợp với bộ lọc.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tiêu đề</th>
                                <th>Khách hàng</th>
                                <th>Danh mục</th>
                                <th>Ưu tiên</th>
                                <th>Trạng thái</th>
                                <th>Phân công</th>
                                <th>Hạn xử lý</th>
                                <th>Ngày tạo</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                                <tr>
                                    <td><?php echo $ticket['id']; ?></td>
                                    <td>
                                        <a href="tickets.php?action=view&id=<?php echo $ticket['id']; ?>" class="text-decoration-none">
                                            <?php echo truncateText($ticket['title'], 50); ?>
                                        </a>
                                        <?php if (!empty($ticket['warranty_end_date']) && strtotime($ticket['warranty_end_date']) >= strtotime('today')): ?>
                                            <span class="badge bg-success ms-1" title="Còn bảo hành đến <?php echo date('d/m/Y', strtotime($ticket['warranty_end_date'])); ?>">
                                                <i class="bi bi-shield-check"></i>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $ticket['customer_name']; ?></td>
                                    <td><?php echo $ticket['category_name']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo getPriorityColorClass($ticket['priority_name']); ?>">
                                            <?php echo $ticket['priority_name']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo getStatusColorClass($ticket['status_name']); ?>">
                                            <?php echo htmlspecialchars($ticket['status_name']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $ticket['assigned_name'] ? htmlspecialchars($ticket['assigned_name']) : '<span class="text-muted">Chưa phân công</span>'; ?></td>
                                    <td>
                                        <?php if (!empty($ticket['due_date'])): ?>
                                            <?php $overdue = isOverdue($ticket['due_date'], $ticket['status_name']); ?>
                                            <span class="<?php echo $overdue ? 'text-danger fw-semibold' : ''; ?>">
                                                <?php echo date('d/m/Y', strtotime($ticket['due_date'])); ?>
                                                <?php if ($overdue): ?><i class="bi bi-exclamation-circle-fill ms-1" title="Quá hạn"></i><?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatDate($ticket['created_at']); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="tickets.php?action=view&id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-primary" title="Xem">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if (hasAnyRole(['Admin', 'Manager', 'IT Helpdesk'])): ?>
                                                <a href="tickets.php?action=edit&id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Sửa">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if (hasRole('Admin')): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" title="Xóa"
                                                        onclick="deleteTicket(<?php echo $ticket['id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($action === 'create' || $action === 'edit'): ?>
    <!-- Create/Edit Form -->
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="" id="ticketForm" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="title" class="form-label">Tiêu đề <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title"
                                       value="<?php echo $action === 'edit' ? htmlspecialchars($ticket['title']) : (isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''); ?>"
                                       minlength="3" maxlength="255" required>
                                <div class="invalid-feedback">Tiêu đề phải có ít nhất 3 ký tự.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">Danh mục <span class="text-danger">*</span></label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Chọn danh mục</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"
                                                <?php echo ($action === 'edit' && $ticket['category_id'] == $category['id']) || (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo $category['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Vui lòng chọn danh mục.</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="customer_name" class="form-label">Tên khách hàng <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="customer_name" name="customer_name"
                                       value="<?php echo $action === 'edit' ? htmlspecialchars($ticket['customer_name']) : (isset($_POST['customer_name']) ? htmlspecialchars($_POST['customer_name']) : ''); ?>"
                                       minlength="2" required>
                                <div class="invalid-feedback">Vui lòng nhập tên khách hàng (ít nhất 2 ký tự).</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="customer_email" class="form-label">Email khách hàng <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="customer_email" name="customer_email"
                                       value="<?php echo $action === 'edit' ? htmlspecialchars($ticket['customer_email']) : (isset($_POST['customer_email']) ? htmlspecialchars($_POST['customer_email']) : ''); ?>"
                                       required>
                                <div class="invalid-feedback">Vui lòng nhập email hợp lệ.</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="customer_phone" class="form-label">Số điện thoại</label>
                                <input type="tel" class="form-control" id="customer_phone" name="customer_phone"
                                       value="<?php echo $action === 'edit' ? htmlspecialchars($ticket['customer_phone'] ?? '') : (isset($_POST['customer_phone']) ? htmlspecialchars($_POST['customer_phone']) : ''); ?>"
                                       maxlength="10" pattern="^0[3-9][0-9]{8}$">
                                <div class="invalid-feedback">Số điện thoại không hợp lệ.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="priority_id" class="form-label">Ưu tiên <span class="text-danger">*</span></label>
                                <select class="form-select" id="priority_id" name="priority_id" required>
                                    <option value="">Chọn ưu tiên</option>
                                    <?php foreach ($priorities as $priority): ?>
                                        <option value="<?php echo $priority['id']; ?>"
                                                <?php echo ($action === 'edit' && $ticket['priority_id'] == $priority['id']) || (isset($_POST['priority_id']) && $_POST['priority_id'] == $priority['id']) ? 'selected' : ''; ?>>
                                            <?php echo $priority['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Vui lòng chọn mức ưu tiên.</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="due_date" class="form-label">Hạn xử lý</label>
                                <input type="date" class="form-control" id="due_date" name="due_date"
                                       min="<?php echo date('Y-m-d'); ?>"
                                       value="<?php echo $action === 'edit' ? htmlspecialchars($ticket['due_date'] ?? '') : (isset($_POST['due_date']) ? htmlspecialchars($_POST['due_date']) : ''); ?>">
                            </div>
                        </div>

                        <?php if ($action === 'edit' && hasAnyRole(['Admin', 'Manager', 'IT Helpdesk'])): ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="status_id" class="form-label">Trạng thái</label>
                                    <?php
                                    $currentStatusId = (int)$ticket['status_id'];
                                    $allowedNext = [1 => 2, 2 => 3]; // Mở→Đang xử lý→Đã đóng
                                    $isClosed = $currentStatusId === 3;
                                    ?>
                                    <?php if ($isClosed): ?>
                                        <div class="form-control bg-light text-muted">
                                            <i class="bi bi-lock me-1"></i>Đã đóng — không thể thay đổi
                                        </div>
                                        <input type="hidden" name="status_id" value="<?php echo $currentStatusId; ?>">
                                    <?php else: ?>
                                        <select class="form-select" id="status_id" name="status_id">
                                            <?php foreach ($statuses as $status):
                                                $sid = (int)$status['id'];
                                                // Show current and allowed next only
                                                if ($sid !== $currentStatusId && $sid !== ($allowedNext[$currentStatusId] ?? -1)) continue;
                                            ?>
                                                <option value="<?php echo $sid; ?>"
                                                        <?php echo $sid === $currentStatusId ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($status['name']); ?>
                                                    <?php echo $sid === $currentStatusId ? '(hiện tại)' : '→ chuyển sang'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text text-muted">
                                            <?php if ($currentStatusId === 1): ?>
                                                <i class="bi bi-arrow-right"></i> Tiếp theo: Đang xử lý
                                            <?php elseif ($currentStatusId === 2): ?>
                                                <i class="bi bi-arrow-right"></i> Tiếp theo: Đã đóng (sau khi đóng sẽ không thể thay đổi)
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php
                                $currentUserRole = getCurrentUser()['role_name'];
                                $assignableUsers = array_filter($users, function($u) use ($currentUserRole) {
                                    return canAssignToRole($currentUserRole, $u['role_name']);
                                });
                                if (hasAnyRole(['Admin', 'Manager']) && !empty($assignableUsers)): ?>
                                    <div class="col-md-6 mb-3">
                                        <label for="assigned_to" class="form-label">Phân công cho</label>
                                        <select class="form-select" id="assigned_to" name="assigned_to">
                                            <option value="">Chưa phân công</option>
                                            <?php foreach ($assignableUsers as $user): ?>
                                                <option value="<?php echo $user['id']; ?>"
                                                        <?php echo ($ticket['assigned_to'] == $user['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($user['fullname']); ?> (<?php echo $user['role_name']; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="description" class="form-label">Mô tả</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?php echo $action === 'edit' ? htmlspecialchars($ticket['description'] ?? '') : (isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''); ?></textarea>
                        </div>

                        <?php if ($action === 'create'): ?>
                        <div class="border rounded p-3 mb-3 bg-light">
                            <h6 class="mb-3"><i class="bi bi-receipt me-2"></i>Dịch vụ & Báo giá <span class="text-muted fw-normal" style="font-size:12px;">(tùy chọn, có thể thiết lập sau)</span></h6>
                            <div class="row">
                                <div class="col-md-7 mb-3">
                                    <label class="form-label">Dịch vụ từ mẫu</label>
                                    <select class="form-select" id="service_template_id" name="service_template_id">
                                        <option value="">— Chọn dịch vụ hoặc nhập giá tự do —</option>
                                    </select>
                                    <small class="text-muted">Chọn danh mục trước để hiển thị dịch vụ</small>
                                </div>
                                <div class="col-md-5 mb-3">
                                    <label class="form-label">Giá dịch vụ (VND)</label>
                                    <input type="number" class="form-control" id="ticket_price" name="ticket_price"
                                           min="0" step="1000" placeholder="0"
                                           value="<?php echo isset($_POST['ticket_price']) ? (int)$_POST['ticket_price'] : ''; ?>">
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i><?php echo $action === 'create' ? 'Tạo Ticket' : 'Cập nhật'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($action === 'view'): ?>
    <!-- View Ticket Details -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Ticket #<?php echo $ticket['id']; ?> - <?php echo htmlspecialchars($ticket['title']); ?></h5>
                        <span class="badge bg-<?php echo getStatusColorClass($ticket['status_name']); ?> fs-6">
                            <?php echo $ticket['status_name']; ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Thông tin khách hàng</h6>
                            <p><strong>Tên:</strong> <?php echo htmlspecialchars($ticket['customer_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($ticket['customer_email']); ?></p>
                            <?php if ($ticket['customer_phone']): ?>
                                <p><strong>Điện thoại:</strong> <?php echo htmlspecialchars($ticket['customer_phone']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h6>Thông tin ticket</h6>
                            <p><strong>Danh mục:</strong> <?php echo htmlspecialchars($ticket['category_name']); ?></p>
                            <p><strong>Ưu tiên:</strong>
                                <span class="badge bg-<?php echo getPriorityColorClass($ticket['priority_name']); ?>">
                                    <?php echo $ticket['priority_name']; ?>
                                </span>
                            </p>
                            <p><strong>Người tạo:</strong> <?php echo htmlspecialchars($ticket['created_name']); ?></p>
                            <p><strong>Ngày tạo:</strong> <?php echo formatDate($ticket['created_at']); ?></p>
                            <?php if (!empty($ticket['due_date'])): ?>
                                <?php $overdue = isOverdue($ticket['due_date'], $ticket['status_name']); ?>
                                <p><strong>Hạn xử lý:</strong>
                                    <span class="<?php echo $overdue ? 'text-danger fw-semibold' : ''; ?>">
                                        <?php echo date('d/m/Y', strtotime($ticket['due_date'])); ?>
                                        <?php if ($overdue): ?><span class="badge bg-danger ms-1">Quá hạn</span><?php endif; ?>
                                    </span>
                                </p>
                            <?php endif; ?>
                            <?php if ($ticket['assigned_name']): ?>
                                <p><strong>Phân công:</strong> <?php echo htmlspecialchars($ticket['assigned_name']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($ticket['description']): ?>
                        <div class="mt-3">
                            <h6>Mô tả</h6>
                            <div class="border p-3 bg-light">
                                <?php echo nl2br(htmlspecialchars($ticket['description'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notes Section -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">Ghi chú</h6>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <?php if (empty($notes)): ?>
                        <p class="text-muted">Chưa có ghi chú nào.</p>
                    <?php else: ?>
                        <?php foreach ($notes as $note): ?>
                            <div class="border-bottom pb-3 mb-3">
                                <div class="d-flex justify-content-between">
                                    <strong><?php echo htmlspecialchars($note['created_by_name']); ?></strong>
                                    <small class="text-muted"><?php echo formatDate($note['created_at']); ?></small>
                                </div>
                                <div class="mt-2">
                                    <?php echo nl2br(htmlspecialchars($note['note'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Add Note Form -->
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="note" class="form-label">Thêm ghi chú mới</label>
                            <textarea class="form-control" id="note" name="note" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i>Thêm ghi chú
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Activity Log -->
            <?php if (!empty($activityLogs)): ?>
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-clock-history me-2"></i>Lịch sử hoạt động</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush" style="font-size:12.5px;">
                        <?php
                        $actionIcons = [
                            'create'        => 'bi-plus-circle-fill text-primary',
                            'update'        => 'bi-pencil-fill text-warning',
                            'note'          => 'bi-chat-left-text-fill text-info',
                            'status_change' => 'bi-arrow-left-right text-success',
                        ];
                        foreach (array_slice($activityLogs, 0, 10) as $log):
                            $icon = $actionIcons[$log['action_type']] ?? 'bi-circle-fill text-muted';
                        ?>
                            <li class="list-group-item px-3 py-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi <?php echo $icon; ?>" style="font-size:13px;flex-shrink:0;"></i>
                                    <div>
                                        <div><?php echo htmlspecialchars($log['description']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($log['user_name'] ?? 'Hệ thống'); ?> · <?php echo formatDate($log['created_at']); ?></small>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <!-- Warranty Card -->
            <?php
            $warrantyActive  = !empty($ticket['warranty_end_date']) && strtotime($ticket['warranty_end_date']) >= strtotime('today');
            $warrantyExpired = !empty($ticket['warranty_end_date']) && strtotime($ticket['warranty_end_date']) < strtotime('today');
            $daysLeft = !empty($ticket['warranty_end_date']) ? (int)ceil((strtotime($ticket['warranty_end_date']) - strtotime('today')) / 86400) : 0;
            ?>
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="bi bi-shield-check text-success"></i>
                    <h6 class="mb-0">Bảo hành</h6>
                </div>
                <div class="card-body">
                    <?php if ($warrantyActive): ?>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-success fs-6"><i class="bi bi-shield-check me-1"></i>Còn bảo hành</span>
                        </div>
                        <p class="mb-1"><strong>Hết hạn:</strong> <?php echo date('d/m/Y', strtotime($ticket['warranty_end_date'])); ?></p>
                        <p class="mb-2 text-success"><small><i class="bi bi-clock me-1"></i>Còn <strong><?php echo $daysLeft; ?> ngày</strong></small></p>
                        <p class="text-muted small mb-2">Nếu lỗi do bên chúng tôi gây ra trong thời gian bảo hành, chúng tôi sẽ hỗ trợ miễn phí.</p>
                        <?php if (!empty($ticket['warranty_claim_id'])): ?>
                            <a href="tickets.php?action=view&id=<?php echo (int)$ticket['warranty_claim_id']; ?>" class="btn btn-sm btn-outline-warning w-100">
                                <i class="bi bi-exclamation-triangle me-1"></i>Xem yêu cầu bảo hành
                            </a>
                        <?php elseif (hasAnyRole(['Admin', 'Manager'])): ?>
                            <button type="button" class="btn btn-sm btn-outline-warning w-100" onclick="createWarrantyClaim(<?php echo (int)$ticket['id']; ?>, <?php echo htmlspecialchars(json_encode($ticket['customer_name']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($ticket['customer_email']), ENT_QUOTES); ?>)">
                                <i class="bi bi-plus-circle me-1"></i>Tạo yêu cầu bảo hành
                            </button>
                        <?php endif; ?>
                    <?php elseif ($warrantyExpired): ?>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-secondary fs-6"><i class="bi bi-shield-x me-1"></i>Hết bảo hành</span>
                        </div>
                        <p class="mb-0 text-muted"><small>Bảo hành đã hết ngày <?php echo date('d/m/Y', strtotime($ticket['warranty_end_date'])); ?></small></p>
                    <?php else: ?>
                        <p class="text-muted mb-0 small">Bảo hành sẽ được kích hoạt tự động (3 tháng) khi ticket được đóng.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Billing & Payment Card -->
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-credit-card-2-front me-2"></i>Thanh toán</span>
                    <?php
                    $bsColor = ['unpaid'=>'warning','pending'=>'info','paid'=>'success','waived'=>'secondary'];
                    $bsLabel = ['unpaid'=>'Chưa TT','pending'=>'Chờ xác nhận','paid'=>'Đã thanh toán','waived'=>'Miễn phí'];
                    $bs = $billing['payment_status'] ?? null;
                    if ($bs): ?>
                        <span class="badge bg-<?php echo $bsColor[$bs] ?? 'secondary'; ?>"><?php echo $bsLabel[$bs] ?? $bs; ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!$billing): ?>
                        <p class="text-muted small mb-2">Chưa thiết lập giá dịch vụ.</p>
                        <?php if (hasAnyRole(['Admin', 'Manager'])): ?>
                        <button class="btn btn-outline-primary btn-sm w-100" onclick="openBillingModal(<?php echo $ticket['id']; ?>, <?php echo $ticket['category_id'] ?? 0; ?>)">
                            <i class="bi bi-plus-circle me-1"></i>Thiết lập giá
                        </button>
                        <?php endif; ?>

                    <?php elseif ($billing['payment_status'] === 'paid'): ?>
                        <div class="text-center py-2">
                            <i class="bi bi-check-circle-fill text-success" style="font-size:28px;"></i>
                            <div class="fw-semibold mt-1"><?php echo number_format((int)$billing['price'], 0, ',', '.'); ?>đ</div>
                            <div class="text-muted small">
                                <?php
                                $methodIcons = [
                                    'cash' => '<i class="bi bi-cash"></i> Tiền mặt',
                                    'momo' => '<i class="bi bi-qr-code"></i> MoMo',
                                    'bank_transfer' => '<i class="bi bi-bank"></i> Chuyển khoản',
                                    'visa' => '<i class="bi bi-credit-card"></i> Visa/Mastercard',
                                    'zalopay' => '<i class="bi bi-wallet2"></i> ZaloPay',
                                    'vnpay' => '<i class="bi bi-phone"></i> VNPay',
                                ];
                                echo $methodIcons[$billing['payment_method']] ?? '<i class="bi bi-dash"></i> —';
                                ?>
                            </div>
                        </div>

                    <?php elseif ($billing['payment_status'] === 'waived'): ?>
                        <div class="text-center text-muted py-2">
                            <i class="bi bi-gift-fill" style="font-size:24px;"></i>
                            <div class="small mt-1">Miễn phí dịch vụ</div>
                        </div>

                    <?php else: ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="fw-semibold fs-5"><?php echo number_format((int)$billing['price'], 0, ',', '.'); ?>đ</div>
                                <div class="text-muted small"><?php echo htmlspecialchars($billing['template_name'] ?? 'Tự nhập'); ?></div>
                            </div>
                            <?php if (hasAnyRole(['Admin', 'Manager'])): ?>
                            <button class="btn btn-outline-secondary btn-sm" onclick="openBillingModal(<?php echo $ticket['id']; ?>, <?php echo $ticket['category_id'] ?? 0; ?>, <?php echo $billing['id']; ?>, <?php echo (int)$billing['price']; ?>, <?php echo (int)($billing['service_template_id'] ?? 0); ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php if (hasAnyRole(['Admin', 'Manager', 'IT Helpdesk'])): ?>
                        <p class="text-muted small mb-2">Chọn phương thức thanh toán:</p>
                        <div class="d-grid gap-2">
                            <button class="btn btn-success btn-sm" onclick="payByCash(<?php echo $ticket['id']; ?>)">
                                <i class="bi bi-cash me-1"></i>Tiền mặt
                            </button>
                            <button class="btn btn-sm" style="background:#ae2070;color:#fff;border:none;" onclick="payByMomo(<?php echo $ticket['id']; ?>, <?php echo (int)$billing['price']; ?>)">
                                <i class="bi bi-qr-code me-1"></i>MoMo
                            </button>
                            <button class="btn btn-sm" style="background:#005bab;color:#fff;border:none;" onclick="payByBank(<?php echo $ticket['id']; ?>, <?php echo (int)$billing['price']; ?>)">
                                <i class="bi bi-bank me-1"></i>Chuyển khoản ngân hàng
                            </button>
                            <button class="btn btn-sm" style="background:#1a1f71;color:#fff;border:none;" onclick="payByVisa(<?php echo $ticket['id']; ?>, <?php echo (int)$billing['price']; ?>)">
                                <i class="bi bi-credit-card me-1"></i>Visa / Mastercard
                            </button>
                            <button class="btn btn-sm" style="background:#006daf;color:#fff;border:none;" onclick="payByZaloPay(<?php echo $ticket['id']; ?>, <?php echo (int)$billing['price']; ?>)">
                                <i class="bi bi-wallet2 me-1"></i>ZaloPay
                            </button>
                            <button class="btn btn-sm" style="background:#e41c23;color:#fff;border:none;" onclick="payByVNPay(<?php echo $ticket['id']; ?>, <?php echo (int)$billing['price']; ?>)">
                                <i class="bi bi-phone me-1"></i>VNPay
                            </button>
                            <?php if (hasAnyRole(['Admin', 'Manager'])): ?>
                            <button class="btn btn-outline-secondary btn-sm" onclick="waiveBilling(<?php echo $billing['id']; ?>)">
                                <i class="bi bi-gift me-1"></i>Miễn phí
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!empty($payments)): ?>
                    <div class="mt-3 border-top pt-2">
                        <small class="text-muted fw-semibold">Lịch sử giao dịch</small>
                        <?php foreach ($payments as $p):
                            $pColors = ['pending'=>'warning','confirmed'=>'success','cancelled'=>'secondary'];
                            $pLabels = ['pending'=>'Chờ xác nhận','confirmed'=>'Đã xác nhận','cancelled'=>'Đã hủy'];
                        ?>
                        <div class="d-flex justify-content-between align-items-center py-1" style="font-size:12px;">
                            <span>
                                <span class="badge bg-<?php echo $pColors[$p['status']] ?? 'secondary'; ?>"><?php echo $pLabels[$p['status']] ?? $p['status']; ?></span>
                                <?php
                                $pMethodLabels = ['cash'=>'Tiền mặt','momo'=>'MoMo','bank_transfer'=>'Chuyển khoản','visa'=>'Visa/MC','zalopay'=>'ZaloPay','vnpay'=>'VNPay'];
                                echo $pMethodLabels[$p['method']] ?? htmlspecialchars($p['method']);
                                ?>
                            </span>
                            <span class="fw-semibold"><?php echo number_format((int)$p['amount'], 0, ',', '.'); ?>đ</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Thao tác nhanh</h6>
                </div>
                <div class="card-body">
                    <?php if (hasAnyRole(['Admin', 'Manager', 'IT Helpdesk'])): ?>
                        <div class="d-grid gap-2">
                            <a href="tickets.php?action=edit&id=<?php echo $ticket['id']; ?>" class="btn btn-primary">
                                <i class="bi bi-pencil me-1"></i>Chỉnh sửa Ticket
                            </a>
                            <?php if (hasRole('Admin')): ?>
                            <button type="button" class="btn btn-outline-danger" onclick="deleteTicket(<?php echo $ticket['id']; ?>)">
                                <i class="bi bi-trash me-1"></i>Xóa Ticket
                            </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

<?php if (AI_ENABLED ?? false): ?>
<!-- AI Analysis Card -->
<div class="card mt-3">
    <div class="card-header d-flex align-items-center gap-2">
        <span style="font-size:16px;">🤖</span>
        <h6 class="mb-0">AI Assistant</h6>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-2">Phân tích ticket và gợi ý giải pháp bằng AI</p>
        <button type="button" class="btn btn-sm w-100"
                style="background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border:none;"
                onclick="analyzeTicketWithAI({
                    title: <?php echo htmlspecialchars(json_encode($ticket['title']), ENT_QUOTES); ?>,
                    description: <?php echo htmlspecialchars(json_encode($ticket['description'] ?? ''), ENT_QUOTES); ?>,
                    category: <?php echo htmlspecialchars(json_encode($ticket['category_name'] ?? ''), ENT_QUOTES); ?>,
                    priority: <?php echo htmlspecialchars(json_encode($ticket['priority_name'] ?? ''), ENT_QUOTES); ?>
                })">
            <i class="bi bi-stars me-1"></i>Phân tích với AI
        </button>
    </div>
</div>
<?php endif; ?>

        </div>
    </div>

<?php if (isset($agentsForTransfer)): ?>
<!-- Transfer Ticket Modal -->
<div class="modal fade" id="transferModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-left-right me-2"></i>Chuyển giao Ticket #<?php echo $ticket['id']; ?></h5>
                <button type="button" class="btn-close" onclick="closeTransferModal()"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3" style="font-size:13.5px">
                    Ticket đang được phân công cho:
                    <strong><?php echo htmlspecialchars($ticket['assigned_name'] ?? 'Chưa phân công'); ?></strong>
                </p>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Chuyển cho nhân viên <span class="text-danger">*</span></label>
                    <select class="form-select" id="transferTo">
                        <option value="">— Chọn nhân viên —</option>
                        <?php foreach ($agentsForTransfer as $ag): ?>
                        <option value="<?php echo $ag['id']; ?>">
                            <?php echo htmlspecialchars($ag['fullname']); ?> — <?php echo htmlspecialchars($ag['role_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Lý do chuyển giao</label>
                    <textarea class="form-control" id="transferReason" rows="3"
                              placeholder="Ví dụ: Không đủ kỹ năng xử lý vấn đề phần cứng..."></textarea>
                </div>
                <div id="transferError" class="alert alert-danger d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeTransferModal()">Hủy</button>
                <button type="button" class="btn btn-warning" onclick="submitTransfer(<?php echo $ticket['id']; ?>)">
                    <i class="bi bi-arrow-left-right me-1"></i>Xác nhận chuyển giao
                </button>
            </div>
        </div>
    </div>
</div>
<script>
function closeTransferModal() {
    var el = document.getElementById('transferModal');
    if (!el) return;
    if (typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(el).hide();
    } else {
        el.style.display = 'none';
        el.classList.remove('show');
        document.body.classList.remove('modal-open');
        var bd = document.getElementById('transferBackdrop');
        if (bd) bd.remove();
    }
}
function openTransferModal() {
    var el = document.getElementById('transferModal');
    if (!el) { alert('Không tìm thấy modal chuyển giao.'); return; }
    document.getElementById('transferTo').value = '';
    document.getElementById('transferReason').value = '';
    document.getElementById('transferError').classList.add('d-none');
    if (typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(el).show();
    } else {
        el.style.display = 'block';
        el.classList.add('show');
        document.body.classList.add('modal-open');
        var bd = document.createElement('div');
        bd.className = 'modal-backdrop fade show';
        bd.id = 'transferBackdrop';
        document.body.appendChild(bd);
    }
}
function submitTransfer(ticketId) {
    const toUser = document.getElementById('transferTo').value;
    const reason = document.getElementById('transferReason').value.trim();
    const errEl  = document.getElementById('transferError');
    if (!toUser) { errEl.textContent = 'Vui lòng chọn nhân viên.'; errEl.classList.remove('d-none'); return; }
    errEl.classList.add('d-none');
    const btn = document.querySelector('#transferModal .btn-warning');
    btn.disabled = true;
    btn.textContent = 'Đang xử lý...';
    fetch('tickets.php?action=transfer_ticket', {
        method: 'POST',
        body: new URLSearchParams({ticket_id: ticketId, to_user_id: toUser, reason: reason})
    })
    .then(r => r.text())
    .then(text => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-left-right me-1"></i>Xác nhận chuyển giao';
        try {
            const d = JSON.parse(text);
            if (d.success) {
                closeTransferModal();
                location.reload();
            } else {
                errEl.textContent = d.message || 'Lỗi không xác định.';
                errEl.classList.remove('d-none');
            }
        } catch(e) {
            errEl.textContent = 'Lỗi server: ' + text.substring(0, 200);
            errEl.classList.remove('d-none');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-left-right me-1"></i>Xác nhận chuyển giao';
        errEl.textContent = 'Lỗi kết nối: ' + err.message;
        errEl.classList.remove('d-none');
    });
}
</script>
<?php endif; ?>

<?php if ($action === 'view'): ?>
<!-- Set Billing Modal -->
<div class="modal fade" id="billingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Thiết lập giá dịch vụ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="billingError" class="alert alert-danger d-none"></div>
                <input type="hidden" id="billingTicketId">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Dịch vụ từ mẫu</label>
                    <select class="form-select" id="billingTemplateId">
                        <option value="">— Chọn dịch vụ hoặc nhập giá tự do —</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Giá (VND) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="billingPrice" min="0" step="1000" placeholder="0">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Ghi chú</label>
                    <input type="text" class="form-control" id="billingNote" placeholder="Ghi chú thêm...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="saveBilling()">
                    <i class="bi bi-check-circle me-1"></i>Lưu
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MoMo Payment Modal -->
<div class="modal fade" id="momoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:#ae2070;color:#fff;">
                <h5 class="modal-title"><i class="bi bi-qr-code me-2"></i>Thanh toán MoMo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p class="text-muted mb-3 small">Quét mã QR bằng ứng dụng MoMo để thanh toán</p>
                <img id="momoQRImg" src="" alt="MoMo QR"
                     style="width:220px;height:220px;border:3px solid #ae2070;border-radius:12px;">
                <div class="mt-3">
                    <div class="fw-bold" style="font-size:22px;color:#ae2070;" id="momoAmount"></div>
                    <div class="text-muted small mt-1">Mã giao dịch: <code id="momoRef"></code></div>
                </div>
                <div class="alert alert-warning mt-3 mb-0 text-start small">
                    <i class="bi bi-info-circle me-1"></i>Sau khi khách quét và thanh toán xong, nhấn "Xác nhận đã nhận tiền".
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="cancelMomoPayment()">Hủy giao dịch</button>
                <button type="button" class="btn btn-success" onclick="confirmMomoPayment()">
                    <i class="bi bi-check-circle me-1"></i>Xác nhận đã nhận tiền
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bank Transfer Payment Modal -->
<div class="modal fade" id="bankModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:#005bab;color:#fff;">
                <h5 class="modal-title"><i class="bi bi-bank me-2"></i>Chuyển khoản ngân hàng</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <img id="bankQRImg" src="" alt="Bank QR"
                         style="width:200px;height:200px;border:3px solid #005bab;border-radius:12px;">
                </div>
                <div class="card bg-light mb-0">
                    <div class="card-body py-2">
                        <div class="row g-1" style="font-size:14px;">
                            <div class="col-5 text-muted">Ngân hàng:</div><div class="col-7 fw-semibold">VietcomBank (VCB)</div>
                            <div class="col-5 text-muted">Số tài khoản:</div><div class="col-7 fw-semibold"><code>1234567890</code></div>
                            <div class="col-5 text-muted">Chủ tài khoản:</div><div class="col-7 fw-semibold">CONG TY HELPDESK</div>
                            <div class="col-5 text-muted">Số tiền:</div><div class="col-7 fw-bold text-primary fs-5" id="bankAmount"></div>
                            <div class="col-5 text-muted">Nội dung CK:</div><div class="col-7"><code id="bankRef"></code></div>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info mt-3 mb-0 small">
                    <i class="bi bi-info-circle me-1"></i>Nhập đúng nội dung chuyển khoản để tự động đối chiếu. Sau khi nhận tiền, nhấn xác nhận.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="cancelQRPayment('bankModal')">Hủy giao dịch</button>
                <button type="button" class="btn btn-success" onclick="confirmQRPayment('bankModal')">
                    <i class="bi bi-check-circle me-1"></i>Xác nhận đã nhận tiền
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Visa/Mastercard Payment Modal -->
<div class="modal fade" id="visaModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:#1a1f71;color:#fff;">
                <h5 class="modal-title"><i class="bi bi-credit-card me-2"></i>Thanh toán Visa / Mastercard</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-secondary small mb-3">
                    <i class="bi bi-shield-lock me-1"></i>Kết nối bảo mật SSL — Demo thanh toán thẻ quốc tế
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Số thẻ</label>
                    <input type="text" class="form-control" id="visaCardNumber" placeholder="1234 5678 9012 3456" maxlength="19"
                           oninput="this.value=this.value.replace(/[^0-9]/g,'').replace(/(.{4})/g,'$1 ').trim()">
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label fw-semibold">Ngày hết hạn</label>
                        <input type="text" class="form-control" id="visaExpiry" placeholder="MM/YY" maxlength="5"
                               oninput="this.value=this.value.replace(/[^0-9/]/g,'')">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label fw-semibold">CVV</label>
                        <input type="password" class="form-control" id="visaCVV" placeholder="•••" maxlength="4">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Tên chủ thẻ</label>
                    <input type="text" class="form-control" id="visaName" placeholder="NGUYEN VAN A">
                </div>
                <div class="d-flex justify-content-between align-items-center bg-light rounded p-2">
                    <span class="text-muted small">Số tiền thanh toán:</span>
                    <span class="fw-bold fs-5 text-primary" id="visaAmount"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="cancelQRPayment('visaModal')">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="confirmVisaPayment()">
                    <i class="bi bi-lock me-1"></i>Xác nhận thanh toán
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ZaloPay Modal -->
<div class="modal fade" id="zalopayModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:#006daf;color:#fff;">
                <h5 class="modal-title"><i class="bi bi-wallet2 me-2"></i>Thanh toán ZaloPay</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p class="text-muted mb-3 small">Quét mã QR bằng ứng dụng ZaloPay</p>
                <img id="zalopayQRImg" src="" alt="ZaloPay QR"
                     style="width:220px;height:220px;border:3px solid #006daf;border-radius:12px;">
                <div class="mt-3">
                    <div class="fw-bold" style="font-size:22px;color:#006daf;" id="zalopayAmount"></div>
                    <div class="text-muted small mt-1">Mã GD: <code id="zalopayRef"></code></div>
                </div>
                <div class="alert alert-info mt-3 mb-0 text-start small">
                    <i class="bi bi-info-circle me-1"></i>Sau khi khách quét và thanh toán xong, nhấn xác nhận.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="cancelQRPayment('zalopayModal')">Hủy giao dịch</button>
                <button type="button" class="btn btn-success" onclick="confirmQRPayment('zalopayModal')">
                    <i class="bi bi-check-circle me-1"></i>Xác nhận đã nhận tiền
                </button>
            </div>
        </div>
    </div>
</div>

<!-- VNPay Modal -->
<div class="modal fade" id="vnpayModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:#e41c23;color:#fff;">
                <h5 class="modal-title"><i class="bi bi-phone me-2"></i>Thanh toán VNPay</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p class="text-muted mb-3 small">Quét mã QR bằng ứng dụng VNPay hoặc ứng dụng ngân hàng hỗ trợ VNPay QR</p>
                <img id="vnpayQRImg" src="" alt="VNPay QR"
                     style="width:220px;height:220px;border:3px solid #e41c23;border-radius:12px;">
                <div class="mt-3">
                    <div class="fw-bold" style="font-size:22px;color:#e41c23;" id="vnpayAmount"></div>
                    <div class="text-muted small mt-1">Mã GD: <code id="vnpayRef"></code></div>
                </div>
                <div class="alert alert-warning mt-3 mb-0 text-start small">
                    <i class="bi bi-info-circle me-1"></i>Sau khi khách quét và thanh toán xong, nhấn xác nhận.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="cancelQRPayment('vnpayModal')">Hủy giao dịch</button>
                <button type="button" class="btn btn-success" onclick="confirmQRPayment('vnpayModal')">
                    <i class="bi bi-check-circle me-1"></i>Xác nhận đã nhận tiền
                </button>
            </div>
        </div>
    </div>
</div>

<script>
var _currentPaymentId = null;

function openBillingModal(ticketId, categoryId, billingId, price, templateId) {
    document.getElementById('billingError').classList.add('d-none');
    document.getElementById('billingTicketId').value = ticketId;
    document.getElementById('billingPrice').value    = price || '';
    document.getElementById('billingNote').value     = '';

    // Load templates for category
    fetch('tickets.php?action=get_templates&category_id=' + categoryId)
        .then(r => r.json()).then(data => {
            var sel = document.getElementById('billingTemplateId');
            sel.innerHTML = '<option value="">— Chọn dịch vụ hoặc nhập giá tự do —</option>';
            (data.templates || []).forEach(function(t) {
                var opt = new Option(t.name + ' — ' + Number(t.price).toLocaleString('vi-VN') + 'đ', t.id);
                opt.dataset.price = t.price;
                if (templateId && t.id == templateId) opt.selected = true;
                sel.appendChild(opt);
            });
        });

    new bootstrap.Modal(document.getElementById('billingModal')).show();
}

document.getElementById('billingTemplateId')?.addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    if (opt.dataset.price) {
        document.getElementById('billingPrice').value = opt.dataset.price;
    }
});

function saveBilling() {
    var errEl    = document.getElementById('billingError');
    var ticketId = document.getElementById('billingTicketId').value;
    var body = new URLSearchParams({
        ticket_id:           ticketId,
        service_template_id: document.getElementById('billingTemplateId').value,
        price:               document.getElementById('billingPrice').value,
        note:                document.getElementById('billingNote').value,
    });
    fetch('tickets.php?action=set_billing', { method: 'POST', body })
        .then(r => r.json()).then(d => {
            if (d.success) { bootstrap.Modal.getInstance(document.getElementById('billingModal'))?.hide(); location.reload(); }
            else { errEl.textContent = d.message; errEl.classList.remove('d-none'); }
        });
}

function payByCash(ticketId) {
    if (!confirm('Xác nhận khách hàng thanh toán tiền mặt?')) return;
    fetch('tickets.php?action=initiate_payment', {
        method: 'POST',
        body: new URLSearchParams({ ticket_id: ticketId, method: 'cash' })
    }).then(r => r.json()).then(function(d) {
        if (!d.success) { alert(d.message); return; }
        return fetch('tickets.php?action=confirm_payment', {
            method: 'POST',
            body: new URLSearchParams({ payment_id: d.payment_id })
        });
    }).then(function(r) { return r ? r.json() : null; })
    .then(function(d) {
        if (d && d.success) location.reload();
        else if (d) alert(d.message);
    }).catch(function(e) { alert('Lỗi: ' + e.message); });
}

function payByMomo(ticketId, amount) {
    fetch('tickets.php?action=initiate_payment', {
        method: 'POST',
        body: new URLSearchParams({ ticket_id: ticketId, method: 'momo' })
    }).then(r => r.json()).then(function(d) {
        if (!d.success) { alert(d.message); return; }
        _currentPaymentId = d.payment_id;
        var ref = d.momo_ref;
        var qrData = encodeURIComponent('Thanh toan HelpDesk - Ma: ' + ref + ' - So tien: ' + amount + 'VND');
        document.getElementById('momoQRImg').src = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + qrData;
        document.getElementById('momoRef').textContent = ref;
        document.getElementById('momoAmount').textContent = Number(amount).toLocaleString('vi-VN') + 'đ';
        new bootstrap.Modal(document.getElementById('momoModal')).show();
    });
}

function confirmMomoPayment() {
    if (!_currentPaymentId) return;
    fetch('tickets.php?action=confirm_payment', {
        method: 'POST',
        body: new URLSearchParams({ payment_id: _currentPaymentId })
    }).then(r => r.json()).then(function(d) {
        bootstrap.Modal.getInstance(document.getElementById('momoModal'))?.hide();
        if (d.success) location.reload();
        else alert(d.message);
    });
}

function cancelMomoPayment() {
    if (!_currentPaymentId) return;
    fetch('tickets.php?action=cancel_payment', {
        method: 'POST',
        body: new URLSearchParams({ payment_id: _currentPaymentId })
    }).then(r => r.json()).then(function() {
        _currentPaymentId = null;
        bootstrap.Modal.getInstance(document.getElementById('momoModal'))?.hide();
    });
}

function _makeQRUrl(text) {
    return 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(text);
}

function _showQRModal(modalId, imgId, amountId, refId, method, ticketId, amount) {
    fetch('tickets.php?action=initiate_payment', {
        method: 'POST',
        body: new URLSearchParams({ ticket_id: ticketId, method: method })
    }).then(r => r.json()).then(function(d) {
        if (!d.success) { alert(d.message); return; }
        _currentPaymentId = d.payment_id;
        var ref = method.toUpperCase() + '-' + ticketId + '-' + Date.now().toString().slice(-6);
        document.getElementById(imgId).src = _makeQRUrl('HelpDesk-' + method + '-' + ticketId + '-' + amount + 'VND-' + ref);
        document.getElementById(amountId).textContent = Number(amount).toLocaleString('vi-VN') + 'đ';
        if (document.getElementById(refId)) document.getElementById(refId).textContent = ref;
        new bootstrap.Modal(document.getElementById(modalId)).show();
    }).catch(function(e) { alert('Lỗi: ' + e.message); });
}

function payByBank(ticketId, amount) {
    fetch('tickets.php?action=initiate_payment', {
        method: 'POST',
        body: new URLSearchParams({ ticket_id: ticketId, method: 'bank_transfer' })
    }).then(r => r.json()).then(function(d) {
        if (!d.success) { alert(d.message); return; }
        _currentPaymentId = d.payment_id;
        var ref = 'CK' + ticketId + Date.now().toString().slice(-6);
        var qrText = 'VietcomBank|1234567890|CONG TY HELPDESK|' + amount + '|' + ref;
        document.getElementById('bankQRImg').src = _makeQRUrl(qrText);
        document.getElementById('bankAmount').textContent = Number(amount).toLocaleString('vi-VN') + 'đ';
        document.getElementById('bankRef').textContent = ref;
        new bootstrap.Modal(document.getElementById('bankModal')).show();
    }).catch(function(e) { alert('Lỗi: ' + e.message); });
}

function payByVisa(ticketId, amount) {
    fetch('tickets.php?action=initiate_payment', {
        method: 'POST',
        body: new URLSearchParams({ ticket_id: ticketId, method: 'visa' })
    }).then(r => r.json()).then(function(d) {
        if (!d.success) { alert(d.message); return; }
        _currentPaymentId = d.payment_id;
        document.getElementById('visaAmount').textContent = Number(amount).toLocaleString('vi-VN') + 'đ';
        document.getElementById('visaCardNumber').value = '';
        document.getElementById('visaExpiry').value = '';
        document.getElementById('visaCVV').value = '';
        document.getElementById('visaName').value = '';
        new bootstrap.Modal(document.getElementById('visaModal')).show();
    }).catch(function(e) { alert('Lỗi: ' + e.message); });
}

function confirmVisaPayment() {
    if (!document.getElementById('visaCardNumber').value.replace(/\s/g,'').match(/^\d{16}$/)) {
        alert('Số thẻ không hợp lệ (cần 16 chữ số).'); return;
    }
    if (!document.getElementById('visaExpiry').value.match(/^\d{2}\/\d{2}$/)) {
        alert('Ngày hết hạn không hợp lệ (MM/YY).'); return;
    }
    if (!document.getElementById('visaCVV').value.match(/^\d{3,4}$/)) {
        alert('CVV không hợp lệ.'); return;
    }
    confirmQRPayment('visaModal');
}

function payByZaloPay(ticketId, amount) {
    _showQRModal('zalopayModal', 'zalopayQRImg', 'zalopayAmount', 'zalopayRef', 'zalopay', ticketId, amount);
}

function payByVNPay(ticketId, amount) {
    _showQRModal('vnpayModal', 'vnpayQRImg', 'vnpayAmount', 'vnpayRef', 'vnpay', ticketId, amount);
}

function confirmQRPayment(modalId) {
    if (!_currentPaymentId) return;
    fetch('tickets.php?action=confirm_payment', {
        method: 'POST',
        body: new URLSearchParams({ payment_id: _currentPaymentId })
    }).then(r => r.json()).then(function(d) {
        bootstrap.Modal.getInstance(document.getElementById(modalId))?.hide();
        if (d.success) location.reload();
        else alert(d.message);
    });
}

function cancelQRPayment(modalId) {
    if (_currentPaymentId) {
        fetch('tickets.php?action=cancel_payment', {
            method: 'POST',
            body: new URLSearchParams({ payment_id: _currentPaymentId })
        }).then(r => r.json()).then(function() {
            _currentPaymentId = null;
            bootstrap.Modal.getInstance(document.getElementById(modalId))?.hide();
        });
    } else {
        bootstrap.Modal.getInstance(document.getElementById(modalId))?.hide();
    }
}

function waiveBilling(billingId) {
    if (!confirm('Xác nhận miễn phí dịch vụ này?')) return;
    fetch('tickets.php?action=waive_billing', {
        method: 'POST',
        body: new URLSearchParams({ billing_id: billingId })
    }).then(r => r.json()).then(function(d) {
        if (d.success) location.reload();
        else alert(d.message);
    });
}
</script>
<?php endif; ?>

<?php endif; ?>

<script>
// Ticket form validation
(function() {
    const form = document.getElementById('ticketForm');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        let valid = true;

        // Reset
        form.querySelectorAll('.form-control, .form-select').forEach(el => {
            el.classList.remove('is-invalid', 'is-valid');
        });

        function setInvalid(el, msg) {
            el.classList.add('is-invalid');
            const fb = el.nextElementSibling;
            if (fb && fb.classList.contains('invalid-feedback') && msg) fb.textContent = msg;
            valid = false;
        }
        function setValid(el) { el.classList.add('is-valid'); }

        const title = form.querySelector('#title');
        if (!title.value.trim() || title.value.trim().length < 3) setInvalid(title, 'Tiêu đề phải có ít nhất 3 ký tự.');
        else setValid(title);

        const custName = form.querySelector('#customer_name');
        if (!custName.value.trim() || custName.value.trim().length < 2) setInvalid(custName, 'Tên khách hàng không hợp lệ.');
        else setValid(custName);

        const custEmail = form.querySelector('#customer_email');
        const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRe.test(custEmail.value.trim())) setInvalid(custEmail, 'Email không hợp lệ.');
        else setValid(custEmail);

        const custPhone = form.querySelector('#customer_phone');
        if (custPhone && custPhone.value.trim()) {
            const phoneRe = /^(0[3-9][0-9]{8}|[+]?[0-9\s\-]{7,15})$/;
            if (!phoneRe.test(custPhone.value.trim())) setInvalid(custPhone, 'Số điện thoại không hợp lệ.');
            else setValid(custPhone);
        }

        const cat = form.querySelector('#category_id');
        if (cat && !cat.value) setInvalid(cat, 'Vui lòng chọn danh mục.');
        else if (cat) setValid(cat);

        const pri = form.querySelector('#priority_id');
        if (pri && !pri.value) setInvalid(pri, 'Vui lòng chọn mức ưu tiên.');
        else if (pri) setValid(pri);

        if (!valid) e.preventDefault();
    });
})();

function deleteTicket(id) {
    if (confirm('Bạn có chắc chắn muốn xóa ticket này?')) {
        window.location.href = 'tickets.php?action=delete&id=' + id;
    }
}

// Create form: load service templates when category changes
(function() {
    var catSel = document.getElementById('category_id');
    var tmplSel = document.getElementById('service_template_id');
    var priceIn = document.getElementById('ticket_price');
    if (!catSel || !tmplSel) return;

    catSel.addEventListener('change', function() {
        var catId = this.value;
        if (!catId) { tmplSel.innerHTML = '<option value="">— Chọn dịch vụ hoặc nhập giá tự do —</option>'; return; }
        fetch('tickets.php?action=get_templates&category_id=' + catId)
            .then(r => r.json()).then(function(data) {
                tmplSel.innerHTML = '<option value="">— Chọn dịch vụ hoặc nhập giá tự do —</option>';
                (data.templates || []).forEach(function(t) {
                    var opt = new Option(t.name + ' — ' + Number(t.price).toLocaleString('vi-VN') + 'đ', t.id);
                    opt.dataset.price = t.price;
                    tmplSel.appendChild(opt);
                });
            });
    });

    tmplSel.addEventListener('change', function() {
        var opt = this.options[this.selectedIndex];
        if (opt.dataset.price !== undefined && opt.value !== '') {
            priceIn.value = opt.dataset.price;
            priceIn.readOnly = true;
        } else {
            priceIn.value = '';
            priceIn.readOnly = false;
        }
    });
})();

function createWarrantyClaim(originalId, customerName, customerEmail) {
    if (!confirm('Tạo ticket bảo hành mới cho khách hàng "' + customerName + '"?')) return;

    const fd = new FormData();
    fd.append('warranty_original_id', originalId);
    fd.append('customer_name', customerName);
    fd.append('customer_email', customerEmail);

    fetch('tickets.php?action=create_warranty', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'tickets.php?action=view&id=' + data.new_id;
            } else {
                alert(data.message || 'Không thể tạo yêu cầu bảo hành.');
            }
        })
        .catch(() => alert('Lỗi kết nối.'));
}

// Quick status change via AJAX
document.querySelectorAll('.status-quick-select').forEach(function(sel) {
    sel.addEventListener('change', function() {
        const ticketId = this.dataset.ticketId;
        const statusId = this.value;
        const originalValue = this.dataset.original || this.value;
        this.dataset.original = this.options[this.selectedIndex].value;

        const fd = new FormData();
        fd.append('ticket_id', ticketId);
        fd.append('status_id', statusId);

        fetch('tickets.php?action=update_status', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Flash the row green briefly
                    const row = sel.closest('tr');
                    if (row) {
                        row.style.transition = 'background .3s';
                        row.style.background = '#d1fae5';
                        setTimeout(() => row.style.background = '', 1200);
                    }
                } else {
                    alert(data.message || 'Không thể cập nhật trạng thái.');
                    // revert
                    for (let i = 0; i < sel.options.length; i++) {
                        if (sel.options[i].value === originalValue) { sel.selectedIndex = i; break; }
                    }
                }
            })
            .catch(() => alert('Lỗi kết nối.'));
    });
    // store initial
    sel.dataset.original = sel.value;
});
</script>

<?php include 'includes/footer.php'; ?>