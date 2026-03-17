<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'models/Ticket.php';

requireLogin();

$ticketModel = new Ticket();
$filter = in_array($_GET['filter'] ?? '', ['active','expired','all']) ? ($_GET['filter'] ?? 'active') : 'active';
$warranties = $ticketModel->getWarrantyList($filter);

// Group by category_type
$grouped = [];
foreach ($warranties as $w) {
    $grouped[$w['category_type']][] = $w;
}
$typeLabels = ['hardware' => 'Phần cứng', 'software' => 'Phần mềm', 'other' => 'Khác'];
$typeColors = ['hardware' => 'primary', 'software' => 'success', 'other' => 'secondary'];
$typeIcons  = ['hardware' => 'bi-cpu', 'software' => 'bi-code-slash', 'other' => 'bi-tag'];

// Stats
$allActive  = $ticketModel->getWarrantyList('active');
$allExpired = $ticketModel->getWarrantyList('expired');
$allAll     = $ticketModel->getWarrantyList('all');

$pageTitle = 'Quản lý Bảo hành';
$pageActions = '';
include 'includes/header.php';
?>

<!-- Stats row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(79,70,229,.1);color:var(--primary)"><i class="bi bi-shield-check"></i></div>
            <div>
                <div class="stat-value"><?php echo count($allAll); ?></div>
                <div class="stat-label">Tổng có bảo hành</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.1);color:#10b981"><i class="bi bi-shield-fill-check"></i></div>
            <div>
                <div class="stat-value"><?php echo count($allActive); ?></div>
                <div class="stat-label">Còn bảo hành</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(239,68,68,.1);color:#ef4444"><i class="bi bi-shield-x"></i></div>
            <div>
                <div class="stat-value"><?php echo count($allExpired); ?></div>
                <div class="stat-label">Hết bảo hành</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.1);color:#f59e0b"><i class="bi bi-exclamation-triangle"></i></div>
            <div>
                <?php
                $expiringSoon = array_filter($allActive, function($w) {
                    return (strtotime($w['warranty_end_date']) - strtotime('today')) <= 30 * 86400;
                });
                ?>
                <div class="stat-value"><?php echo count($expiringSoon); ?></div>
                <div class="stat-label">Sắp hết (≤30 ngày)</div>
            </div>
        </div>
    </div>
</div>

<!-- Filter tabs -->
<div class="card mb-4">
    <div class="card-body py-2">
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <a href="warranty.php?filter=active"  class="btn btn-sm <?php echo $filter==='active'  ? 'btn-success' : 'btn-outline-success'; ?>">
                <i class="bi bi-shield-fill-check me-1"></i>Còn bảo hành
                <span class="badge bg-light text-dark ms-1"><?php echo count($allActive); ?></span>
            </a>
            <a href="warranty.php?filter=expired" class="btn btn-sm <?php echo $filter==='expired' ? 'btn-danger' : 'btn-outline-danger'; ?>">
                <i class="bi bi-shield-x me-1"></i>Hết bảo hành
                <span class="badge bg-light text-dark ms-1"><?php echo count($allExpired); ?></span>
            </a>
            <a href="warranty.php?filter=all"     class="btn btn-sm <?php echo $filter==='all'     ? 'btn-primary' : 'btn-outline-primary'; ?>">
                <i class="bi bi-shield me-1"></i>Tất cả
                <span class="badge bg-light text-dark ms-1"><?php echo count($allAll); ?></span>
            </a>
        </div>
    </div>
</div>

<?php if (empty($warranties)): ?>
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-shield-slash" style="font-size:48px;opacity:.3"></i>
        <p class="mt-3 mb-0">Không có dữ liệu bảo hành.</p>
    </div>
</div>
<?php else: ?>

<?php
// Show each type group
$typeOrder = ['hardware','software','other'];
foreach ($typeOrder as $type):
    if (empty($grouped[$type])) continue;
    $rows = $grouped[$type];
    $label = $typeLabels[$type];
    $color = $typeColors[$type];
    $icon  = $typeIcons[$type];
    // warranty_months from first row
    $wMonths = $rows[0]['warranty_months'];
?>
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-<?php echo $color; ?> d-flex align-items-center gap-1" style="font-size:13px;padding:6px 12px;">
                <i class="<?php echo $icon; ?>"></i> <?php echo $label; ?>
            </span>
            <span class="text-muted" style="font-size:13px;">
                Bảo hành: <strong><?php echo $wMonths > 0 ? $wMonths . ' tháng' : 'Không bảo hành'; ?></strong>
                &nbsp;·&nbsp; <?php echo count($rows); ?> ticket
            </span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:13.5px;">
            <thead>
                <tr>
                    <th style="width:50px">ID</th>
                    <th>Tiêu đề</th>
                    <th>Khách hàng</th>
                    <th>Trạng thái</th>
                    <th>Ngày hết bảo hành</th>
                    <th>Còn lại</th>
                    <th>Yêu cầu BH</th>
                    <th style="width:80px">Xem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $w):
                    $hasWarranty = !empty($w['warranty_end_date']);
                    $daysLeft = $hasWarranty ? (int)ceil((strtotime($w['warranty_end_date']) - strtotime('today')) / 86400) : null;
                    $isExpired = $hasWarranty && $daysLeft < 0;
                    $rowClass = !$hasWarranty ? '' : ($isExpired ? 'table-light' : ($daysLeft <= 7 ? 'table-warning' : ''));
                ?>
                <tr class="<?php echo $rowClass; ?>">
                    <td class="text-muted">#<?php echo $w['id']; ?></td>
                    <td>
                        <a href="tickets.php?action=view&id=<?php echo $w['id']; ?>" class="fw-semibold text-decoration-none">
                            <?php echo htmlspecialchars($w['title']); ?>
                        </a>
                    </td>
                    <td>
                        <div><?php echo htmlspecialchars($w['customer_name']); ?></div>
                        <?php if (!empty($w['customer_phone'])): ?>
                            <small class="text-muted"><?php echo htmlspecialchars($w['customer_phone']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge" style="background:<?php echo htmlspecialchars($w['status_color'] ?? '#6c757d'); ?>;color:#fff;">
                            <?php echo htmlspecialchars($w['status_name']); ?>
                        </span>
                    </td>
                    <td>
                        <?php echo $hasWarranty ? date('d/m/Y', strtotime($w['warranty_end_date'])) : '<span class="text-muted">—</span>'; ?>
                    </td>
                    <td>
                        <?php if (!$hasWarranty): ?>
                            <span class="badge bg-secondary">Chưa có</span>
                        <?php elseif ($isExpired): ?>
                            <span class="badge bg-danger">Hết hạn <?php echo abs($daysLeft); ?> ngày trước</span>
                        <?php elseif ($daysLeft <= 30): ?>
                            <span class="badge bg-warning text-dark">Còn <?php echo $daysLeft; ?> ngày</span>
                        <?php else: ?>
                            <span class="badge bg-success">Còn <?php echo $daysLeft; ?> ngày</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($w['warranty_claim_id'])): ?>
                            <a href="tickets.php?action=view&id=<?php echo (int)$w['warranty_claim_id']; ?>"
                               class="badge bg-warning text-dark text-decoration-none">
                                <i class="bi bi-ticket me-1"></i>#<?php echo (int)$w['warranty_claim_id']; ?>
                            </a>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="tickets.php?action=view&id=<?php echo $w['id']; ?>"
                           class="btn btn-sm btn-outline-primary" title="Xem chi tiết">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
