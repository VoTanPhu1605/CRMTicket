<?php
ob_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'controllers/reportController.php';
require_once 'controllers/billingController.php';

requireLogin();

$reportController  = new ReportController();
$billingController = new BillingController();

// Revenue AJAX
if (($_GET['action'] ?? '') === 'revenue_monthly' && hasAnyRole(['Admin', 'Manager'])) {
    $year = (int)($_GET['year'] ?? date('Y'));
    $rows = $billingController->getRevenueByMonth((string)$year);
    $map  = [];
    foreach ($rows as $r) $map[$r['month']] = (int)$r['revenue'];
    $out = [];
    for ($m = 1; $m <= 12; $m++) {
        $key  = $year . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
        $out[] = $map[$key] ?? 0;
    }
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['revenue' => $out]);
    exit();
}

// Get date filters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Revenue data (Admin/Manager)
$revenueStats      = hasAnyRole(['Admin', 'Manager']) ? $billingController->getRevenueStats()      : null;
$revenueByMonth    = hasAnyRole(['Admin', 'Manager']) ? $billingController->getRevenueByMonth(date('Y')) : [];
$revenueComparison = hasAnyRole(['Admin', 'Manager']) ? $billingController->getRevenueComparison((string)(date('Y')-1), date('Y')) : [];
$selectedYear      = (int)($_GET['rev_year'] ?? date('Y'));

$pageTitle = 'Báo cáo và Thống kê';
$pageActions = '<button type="button" class="btn btn-outline-primary" onclick="exportTickets()">
    <i class="bi bi-download me-1"></i>Xuất Excel
</button>';

include 'includes/header.php';
?>

<!-- Date Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="reports.php" class="row g-3">
            <div class="col-md-4">
                <label for="start_date" class="form-label">Từ ngày</label>
                <input type="date" class="form-control" id="start_date" name="start_date"
                       value="<?php echo formatDateForInput($startDate); ?>">
            </div>
            <div class="col-md-4">
                <label for="end_date" class="form-label">Đến ngày</label>
                <input type="date" class="form-control" id="end_date" name="end_date"
                       value="<?php echo formatDateForInput($endDate); ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search me-1"></i>Lọc
                </button>
                <a href="reports.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle me-1"></i>Đặt lại
                </a>
            </div>
        </form>
    </div>
</div>

<?php
$reports = $reportController->getTicketReports(['start_date' => $startDate, 'end_date' => $endDate]);
?>

<div class="row">
    <!-- Ticket Status Distribution -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-pie-chart me-2"></i>Phân bố theo Trạng thái
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($reports['tickets_by_status'])): ?>
                    <p class="text-muted text-center">Không có dữ liệu</p>
                <?php else: ?>
                    <canvas id="statusChart" width="400" height="300"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Ticket Priority Distribution -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-bar-chart me-2"></i>Phân bố theo Ưu tiên
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($reports['tickets_by_priority'])): ?>
                    <p class="text-muted text-center">Không có dữ liệu</p>
                <?php else: ?>
                    <canvas id="priorityChart" width="400" height="300"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Category Distribution -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-tag me-2"></i>Phân bố theo Danh mục
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($reports['tickets_by_category'])): ?>
                    <p class="text-muted text-center">Không có dữ liệu</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Danh mục</th>
                                    <th class="text-end">Số lượng</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports['tickets_by_category'] as $category): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category['category']); ?></td>
                                        <td class="text-end">
                                            <span class="badge bg-primary"><?php echo $category['count']; ?></span>
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

    <!-- Agent Performance -->
    <?php if (hasAnyRole(['Admin', 'Manager']) && !empty($reports['agent_performance'])): ?>
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person-check me-2"></i>Hiệu suất Agent
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Agent</th>
                                    <th class="text-end">Tổng</th>
                                    <th class="text-end">Hoàn thành</th>
                                    <th class="text-end">Tỷ lệ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports['agent_performance'] as $agent): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($agent['agent_name']); ?></td>
                                        <td class="text-end"><?php echo $agent['total_tickets']; ?></td>
                                        <td class="text-end"><?php echo $agent['closed_tickets']; ?></td>
                                        <td class="text-end">
                                            <?php
                                            $rate = $agent['total_tickets'] > 0 ? round(($agent['closed_tickets'] / $agent['total_tickets']) * 100, 1) : 0;
                                            $badgeClass = $rate >= 80 ? 'success' : ($rate >= 60 ? 'warning' : 'danger');
                                            ?>
                                            <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo $rate; ?>%</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Summary Statistics -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-graph-up me-2"></i>Tóm tắt thống kê
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="border-end">
                            <h3 class="text-primary mb-1">
                                <?php echo array_sum(array_column($reports['tickets_by_status'], 'count')); ?>
                            </h3>
                            <p class="text-muted mb-0">Tổng số Tickets</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border-end">
                            <h3 class="text-warning mb-1">
                                <?php echo $reports['tickets_by_status'][0]['count'] ?? 0; // Pending ?>
                            </h3>
                            <p class="text-muted mb-0">Đang chờ xử lý</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border-end">
                            <h3 class="text-info mb-1">
                                <?php echo $reports['tickets_by_status'][1]['count'] ?? 0; // In Progress ?>
                            </h3>
                            <p class="text-muted mb-0">Đang xử lý</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <h3 class="text-success mb-1">
                            <?php echo $reports['tickets_by_status'][2]['count'] ?? 0; // Closed ?>
                        </h3>
                        <p class="text-muted mb-0">Đã hoàn thành</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($revenueStats): ?>
<!-- Revenue Stats -->
<hr class="my-4">
<h5 class="mb-3 fw-semibold"><i class="bi bi-cash-coin me-2 text-success"></i>Doanh thu</h5>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon stat-icon-success"><i class="bi bi-calendar-day"></i></div>
            <div>
                <div class="stat-value"><?php echo number_format($revenueStats['today'], 0, ',', '.'); ?>đ</div>
                <div class="stat-label">Hôm nay</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info"><i class="bi bi-calendar-week"></i></div>
            <div>
                <div class="stat-value"><?php echo number_format($revenueStats['this_month'], 0, ',', '.'); ?>đ</div>
                <div class="stat-label">Tháng này</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon stat-icon-primary"><i class="bi bi-graph-up-arrow"></i></div>
            <div>
                <div class="stat-value"><?php echo number_format($revenueStats['this_year'], 0, ',', '.'); ?>đ</div>
                <div class="stat-label">Năm nay</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon stat-icon-warning"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="stat-value"><?php echo $billingController->getUnpaidCount(); ?></div>
                <div class="stat-label">Chưa thanh toán</div>
            </div>
        </div>
    </div>
</div>

<!-- Revenue Charts Row -->
<div class="row g-3 mb-4">
    <!-- Monthly Revenue -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bar-chart-line me-2"></i>Doanh thu theo tháng</span>
                <div class="d-flex align-items-center gap-2">
                    <select class="form-select form-select-sm" id="revYearSelect" style="width:100px;">
                        <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 4; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y === $selectedYear ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <canvas id="revenueMonthChart" height="100"></canvas>
            </div>
        </div>
    </div>
    <!-- Year Comparison -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <span><i class="bi bi-arrow-left-right me-2"></i>So sánh <?php echo date('Y')-1; ?> vs <?php echo date('Y'); ?></span>
            </div>
            <div class="card-body">
                <canvas id="revenueCompareChart" height="240"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Top Services by Revenue -->
<?php
$topServices = [];
try {
    $stmt = $billingController->getTopServiceRevenue(8);
    $topServices = $stmt;
} catch (\Throwable $e) {}
if (!empty($topServices)): ?>
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="bi bi-trophy me-2"></i>Dịch vụ doanh thu cao nhất</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Dịch vụ</th><th class="text-end">Số lần</th><th class="text-end">Doanh thu</th></tr></thead>
                        <tbody>
                        <?php foreach ($topServices as $svc): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($svc['name'] ?? 'Tự nhập'); ?></td>
                                <td class="text-end"><?php echo $svc['cnt']; ?></td>
                                <td class="text-end fw-semibold text-success"><?php echo number_format((int)$svc['revenue'], 0, ',', '.'); ?>đ</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Chart.js for visualizations -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($reports['tickets_by_status'])): ?>
// Status Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode(array_column($reports['tickets_by_status'], 'status')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($reports['tickets_by_status'], 'count')); ?>,
            backgroundColor: [
                '#6c757d', // Pending
                '#0d6efd', // In Progress
                '#198754', // Closed
                '#dc3545'  // Cancelled
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
<?php endif; ?>

<?php if (!empty($reports['tickets_by_priority'])): ?>
// Priority Chart
const priorityCtx = document.getElementById('priorityChart').getContext('2d');
new Chart(priorityCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($reports['tickets_by_priority'], 'priority')); ?>,
        datasets: [{
            label: 'Số lượng',
            data: <?php echo json_encode(array_column($reports['tickets_by_priority'], 'count')); ?>,
            backgroundColor: [
                '#198754', // Low
                '#ffc107', // Medium
                '#fd7e14', // High
                '#dc3545'  // Urgent
            ]
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
<?php endif; ?>

<?php if ($revenueStats): ?>
// ── Revenue Monthly Chart ────────────────────────────────────────
const monthLabels = ['T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12'];
<?php
$mapM = [];
foreach ($revenueByMonth as $r) $mapM[$r['month']] = (int)$r['revenue'];
$monthData = [];
for ($m = 1; $m <= 12; $m++) {
    $key = date('Y') . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
    $monthData[] = $mapM[$key] ?? 0;
}
?>
let revenueMonthData = <?php echo json_encode($monthData); ?>;
let revenueMonthYear = <?php echo date('Y'); ?>;

const rmc = document.getElementById('revenueMonthChart').getContext('2d');
let revenueMonthChart = new Chart(rmc, {
    type: 'bar',
    data: {
        labels: monthLabels,
        datasets: [{
            label: 'Doanh thu (đ)',
            data: revenueMonthData,
            backgroundColor: 'rgba(27,107,181,0.75)',
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => (v/1000000).toFixed(1) + 'M' } }
        }
    }
});

document.getElementById('revYearSelect').addEventListener('change', function() {
    const yr = this.value;
    fetch('reports.php?action=revenue_monthly&year=' + yr)
        .then(r => r.json()).then(d => {
            revenueMonthChart.data.datasets[0].data = d.revenue;
            revenueMonthChart.update();
        });
});

// ── Revenue Comparison Chart ─────────────────────────────────────
<?php
$cmpLabels = ['T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12'];
$cmpRev1 = array_column($revenueComparison, 'rev1');
$cmpRev2 = array_column($revenueComparison, 'rev2');
?>
const cmpCtx = document.getElementById('revenueCompareChart').getContext('2d');
new Chart(cmpCtx, {
    type: 'bar',
    data: {
        labels: monthLabels,
        datasets: [
            { label: '<?php echo date("Y")-1; ?>', data: <?php echo json_encode(array_map('intval',$cmpRev1)); ?>, backgroundColor: 'rgba(200,200,200,0.7)', borderRadius: 3 },
            { label: '<?php echo date("Y"); ?>',   data: <?php echo json_encode(array_map('intval',$cmpRev2)); ?>, backgroundColor: 'rgba(27,107,181,0.75)', borderRadius: 3 }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => (v/1000000).toFixed(1)+'M' } }
        }
    }
});
<?php endif; ?>

function exportTickets() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;

    let url = 'export.php?type=tickets';
    if (startDate) url += '&start_date=' + startDate;
    if (endDate) url += '&end_date=' + endDate;

    window.open(url, '_blank');
}
</script>

<?php include 'includes/footer.php'; ?>