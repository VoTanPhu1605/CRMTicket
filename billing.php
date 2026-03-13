<?php
ob_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'controllers/billingController.php';

requireAnyRole(['Admin', 'Manager']);

$billingController = new BillingController();

function jsonOut2($d) { ob_end_clean(); header('Content-Type: application/json'); echo json_encode($d); exit(); }

// AJAX handlers
$action = $_GET['action'] ?? 'list';

if ($action === 'save_template' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    jsonOut2($billingController->saveTemplate($_POST, $id ?: null));
}
if ($action === 'delete_template' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    jsonOut2($billingController->deleteTemplate((int)($_POST['id'] ?? 0)));
}

// Page data
$templates   = $billingController->getAllTemplates();
$allBilling  = $billingController->getAllBilling();
$revenueStats = $billingController->getRevenueStats();
$unpaidCount  = $billingController->getUnpaidCount();

require_once 'controllers/ticketController.php';
$tc = new TicketController();
$categories = $tc->getCategories();

$pageTitle = 'Quản lý Thanh toán';
include 'includes/header.php';
?>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon stat-icon-warning"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="stat-value"><?php echo $unpaidCount; ?></div>
                <div class="stat-label">Chưa thanh toán</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon stat-icon-success"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="stat-value"><?php echo number_format($revenueStats['today'], 0, ',', '.'); ?>đ</div>
                <div class="stat-label">Doanh thu hôm nay</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info"><i class="bi bi-calendar-week"></i></div>
            <div>
                <div class="stat-value"><?php echo number_format($revenueStats['this_month'], 0, ',', '.'); ?>đ</div>
                <div class="stat-label">Doanh thu tháng</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon stat-icon-primary"><i class="bi bi-graph-up-arrow"></i></div>
            <div>
                <div class="stat-value"><?php echo number_format($revenueStats['this_year'], 0, ',', '.'); ?>đ</div>
                <div class="stat-label">Doanh thu năm</div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4" id="billingTabs">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#tabBilling">
            <i class="bi bi-credit-card me-1"></i>Danh sách thanh toán
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tabTemplates">
            <i class="bi bi-list-check me-1"></i>Quản lý dịch vụ
        </a>
    </li>
</ul>

<div class="tab-content">
    <!-- Tab 1: Billing List -->
    <div class="tab-pane fade show active" id="tabBilling">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-table me-2"></i>Tất cả giao dịch (<?php echo count($allBilling); ?>)</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($allBilling)): ?>
                    <p class="text-muted text-center py-4">Chưa có giao dịch nào.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Khách hàng</th>
                                <th>Dịch vụ</th>
                                <th>Giá</th>
                                <th>Phương thức</th>
                                <th>Trạng thái</th>
                                <th>Cập nhật</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allBilling as $b):
                                $statusColors = ['unpaid' => 'warning', 'pending' => 'info', 'paid' => 'success', 'waived' => 'secondary'];
                                $statusLabels = ['unpaid' => 'Chưa TT', 'pending' => 'Đang xử lý', 'paid' => 'Đã thanh toán', 'waived' => 'Miễn phí'];
                                $sc = $statusColors[$b['payment_status']] ?? 'secondary';
                                $sl = $statusLabels[$b['payment_status']] ?? $b['payment_status'];
                            ?>
                            <tr>
                                <td>
                                    <a href="tickets.php?action=view&id=<?php echo $b['ticket_id']; ?>" class="text-decoration-none fw-semibold">
                                        #<?php echo $b['ticket_id']; ?>
                                    </a>
                                    <div class="text-muted" style="font-size:12px;"><?php echo htmlspecialchars(mb_strimwidth($b['ticket_title'], 0, 40, '...')); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($b['customer_name'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($b['template_name'] ?? 'Tự nhập'); ?></td>
                                <td class="fw-semibold"><?php echo number_format((int)$b['price'], 0, ',', '.'); ?>đ</td>
                                <td>
                                    <?php if ($b['payment_method'] === 'momo'): ?>
                                        <span style="color:#ae2070;font-weight:600;"><i class="bi bi-qr-code me-1"></i>MoMo</span>
                                    <?php elseif ($b['payment_method'] === 'cash'): ?>
                                        <span class="text-success fw-semibold"><i class="bi bi-cash me-1"></i>Tiền mặt</span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-<?php echo $sc; ?>"><?php echo $sl; ?></span></td>
                                <td class="text-muted" style="font-size:12px;"><?php echo date('d/m/Y H:i', strtotime($b['updated_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tab 2: Service Templates -->
    <div class="tab-pane fade" id="tabTemplates">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-grid-3x2-gap me-2"></i>Mẫu dịch vụ & giá (<?php echo count($templates); ?>)</span>
                <button class="btn btn-primary btn-sm" onclick="openTemplateModal()">
                    <i class="bi bi-plus-circle me-1"></i>Thêm dịch vụ
                </button>
            </div>
            <div class="card-body p-0">
                <?php if (empty($templates)): ?>
                    <p class="text-muted text-center py-4">Chưa có mẫu dịch vụ nào. Hãy thêm mới.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Dịch vụ</th>
                                <th>Danh mục</th>
                                <th>Giá</th>
                                <th>Trạng thái</th>
                                <th>Thứ tự</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templates as $t): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($t['name']); ?></div>
                                    <?php if ($t['description']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($t['description'], 0, 60, '...')); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($t['category_name']); ?></td>
                                <td class="fw-semibold text-primary"><?php echo number_format((int)$t['price'], 0, ',', '.'); ?>đ</td>
                                <td>
                                    <?php if ($t['is_active']): ?>
                                        <span class="badge bg-success">Kích hoạt</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Tắt</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $t['sort_order']; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary me-1"
                                            onclick='openTemplateModal(<?php echo htmlspecialchars(json_encode($t), ENT_QUOTES); ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger"
                                            onclick="deleteTemplate(<?php echo $t['id']; ?>, '<?php echo htmlspecialchars(addslashes($t['name'])); ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Service Template Modal -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="templateModalTitle">Thêm dịch vụ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="templateError" class="alert alert-danger d-none"></div>
                <input type="hidden" id="tmplId">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Danh mục <span class="text-danger">*</span></label>
                    <select class="form-select" id="tmplCategory">
                        <option value="">— Chọn danh mục —</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Tên dịch vụ <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="tmplName" placeholder="VD: Cài đặt phần mềm cơ bản">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Mô tả</label>
                    <textarea class="form-control" id="tmplDesc" rows="2"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Giá (VND) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="tmplPrice" min="0" step="1000" placeholder="0">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-semibold">Thứ tự</label>
                        <input type="number" class="form-control" id="tmplOrder" min="0" value="0">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-semibold">Kích hoạt</label>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="tmplActive" checked>
                            <label class="form-check-label" for="tmplActive">Có</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="saveTemplate()">
                    <i class="bi bi-check-circle me-1"></i>Lưu
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function openTemplateModal(t) {
    document.getElementById('templateError').classList.add('d-none');
    if (t) {
        document.getElementById('templateModalTitle').textContent = 'Chỉnh sửa dịch vụ';
        document.getElementById('tmplId').value        = t.id;
        document.getElementById('tmplCategory').value  = t.category_id;
        document.getElementById('tmplName').value      = t.name;
        document.getElementById('tmplDesc').value      = t.description || '';
        document.getElementById('tmplPrice').value     = t.price;
        document.getElementById('tmplOrder').value     = t.sort_order;
        document.getElementById('tmplActive').checked  = t.is_active == 1;
    } else {
        document.getElementById('templateModalTitle').textContent = 'Thêm dịch vụ';
        document.getElementById('tmplId').value = '';
        document.getElementById('tmplCategory').value = '';
        document.getElementById('tmplName').value = '';
        document.getElementById('tmplDesc').value = '';
        document.getElementById('tmplPrice').value = '';
        document.getElementById('tmplOrder').value = '0';
        document.getElementById('tmplActive').checked = true;
    }
    new bootstrap.Modal(document.getElementById('templateModal')).show();
}

function saveTemplate() {
    const errEl = document.getElementById('templateError');
    const id    = document.getElementById('tmplId').value;
    const body  = new URLSearchParams({
        id:          id,
        category_id: document.getElementById('tmplCategory').value,
        name:        document.getElementById('tmplName').value,
        description: document.getElementById('tmplDesc').value,
        price:       document.getElementById('tmplPrice').value,
        sort_order:  document.getElementById('tmplOrder').value,
        is_active:   document.getElementById('tmplActive').checked ? '1' : '0',
    });
    fetch('billing.php?action=save_template', { method: 'POST', body })
        .then(r => r.json()).then(d => {
            if (d.success) { location.reload(); }
            else { errEl.textContent = d.message; errEl.classList.remove('d-none'); }
        });
}

function deleteTemplate(id, name) {
    if (!confirm('Xóa dịch vụ "' + name + '"?')) return;
    fetch('billing.php?action=delete_template', {
        method: 'POST',
        body: new URLSearchParams({ id })
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
        else alert(d.message);
    });
}
</script>

<?php include 'includes/footer.php'; ?>
