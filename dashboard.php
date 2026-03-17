<?php
// AJAX: revenue by month for year selector
if (isset($_GET['action']) && $_GET['action'] === 'revenue_monthly') {
    require_once 'config/database.php';
    require_once 'includes/functions.php';
    require_once 'controllers/billingController.php';
    $year = (int)($_GET['year'] ?? date('Y'));
    $bc = new BillingController();
    $rows = $bc->getRevenueByMonth((string)$year);
    $slots = array_fill(1, 12, 0);
    foreach ($rows as $r) $slots[(int)substr($r['month'], 5)] = (int)$r['revenue'];
    header('Content-Type: application/json');
    echo json_encode(['monthly' => array_values($slots)]);
    exit;
}

require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'controllers/reportController.php';
require_once 'controllers/billingController.php';

requireLogin();

require_once 'models/Ticket.php';

$reportController  = new ReportController();
$stats = $reportController->getDashboardStats();

$ticketModel = new Ticket();
$chartData = $ticketModel->getChartData();

$billingController = new BillingController();
$revenueStats = $billingController->getRevenueStats();

$pageTitle = 'Dashboard';
$pageActions = '<div class="btn-group">
    <a href="tickets.php?action=create" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Tạo Ticket
    </a>
</div>';

include 'includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon stat-icon-primary"><i class="bi bi-ticket-detailed-fill"></i></div>
            <div>
                <div class="stat-value"><?php echo $stats['ticket_stats']['total_tickets']; ?></div>
                <div class="stat-label">Tổng số Tickets</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon stat-icon-warning"><i class="bi bi-clock-fill"></i></div>
            <div>
                <div class="stat-value"><?php echo $stats['ticket_stats']['pending']; ?></div>
                <div class="stat-label">Đang chờ</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info"><i class="bi bi-gear-fill"></i></div>
            <div>
                <div class="stat-value"><?php echo $stats['ticket_stats']['in_progress']; ?></div>
                <div class="stat-label">Đang xử lý</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon stat-icon-success"><i class="bi bi-check-circle-fill"></i></div>
            <div>
                <div class="stat-value"><?php echo $stats['ticket_stats']['closed']; ?></div>
                <div class="stat-label">Đã đóng</div>
            </div>
        </div>
    </div>
</div>

<!-- Revenue Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-icon stat-icon-success"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="stat-value" style="font-size:20px;"><?php echo number_format($revenueStats['today'], 0, ',', '.'); ?>đ</div>
                <div class="stat-label">Doanh thu hôm nay</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-icon stat-icon-info"><i class="bi bi-calendar-week"></i></div>
            <div>
                <div class="stat-value" style="font-size:20px;"><?php echo number_format($revenueStats['this_month'], 0, ',', '.'); ?>đ</div>
                <div class="stat-label">Doanh thu tháng này</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-icon stat-icon-warning"><i class="bi bi-graph-up-arrow"></i></div>
            <div>
                <div class="stat-value" style="font-size:20px;"><?php echo number_format($revenueStats['this_year'], 0, ',', '.'); ?>đ</div>
                <div class="stat-label">Doanh thu năm <?php echo date('Y'); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Tickets -->
    <div class="col-md-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clock-history me-2"></i>Tickets gần đây
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($stats['recent_tickets'])): ?>
                    <p class="text-muted">Chưa có ticket nào.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tiêu đề</th>
                                    <th>Khách hàng</th>
                                    <th>Ưu tiên</th>
                                    <th>Trạng thái</th>
                                    <th>Ngày tạo</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['recent_tickets'] as $ticket): ?>
                                    <tr>
                                        <td><?php echo $ticket['id']; ?></td>
                                        <td>
                                            <a href="tickets.php?action=view&id=<?php echo $ticket['id']; ?>" class="text-decoration-none">
                                                <?php echo truncateText($ticket['title'], 50); ?>
                                            </a>
                                        </td>
                                        <td><?php echo $ticket['customer_name']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo getPriorityColorClass($ticket['priority_name']); ?>">
                                                <?php echo $ticket['priority_name']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusColorClass($ticket['status_name']); ?>">
                                                <?php echo $ticket['status_name']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($ticket['created_at']); ?></td>
                                        <td>
                                            <a href="tickets.php?action=view&id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end">
                        <a href="tickets.php" class="btn btn-outline-primary">Xem tất cả</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-lightning me-2"></i>Thao tác nhanh
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="tickets.php?action=create" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Tạo Ticket mới
                    </a>
                    <a href="tickets.php?status=Đang+chờ" class="btn btn-outline-warning">
                        <i class="bi bi-clock me-2"></i>Xem Tickets đang chờ
                    </a>
                    <a href="tickets.php?status=Đang+xử+lý" class="btn btn-outline-info">
                        <i class="bi bi-gear me-2"></i>Xem Tickets đang xử lý
                    </a>
                    <?php if (hasAnyRole(['Admin', 'Manager', 'IT Helpdesk'])): ?>
                        <a href="users.php" class="btn btn-outline-secondary">
                            <i class="bi bi-people me-2"></i>Quản lý Users
                        </a>
                        <a href="reports.php" class="btn btn-outline-success">
                            <i class="bi bi-graph-up me-2"></i>Xem Reports
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- System Info -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-info-circle me-2"></i>Thông tin hệ thống
                </h6>
            </div>
            <div class="card-body small">
                <p class="mb-1"><strong>Phiên bản:</strong> 1.0.0</p>
                <p class="mb-1"><strong>PHP:</strong> <?php echo PHP_VERSION; ?></p>
                <p class="mb-1"><strong>Thời gian:</strong> <?php echo date('H:i:s d/m/Y'); ?></p>
                <p class="mb-0"><strong>User:</strong> <?php echo getUserDisplayName(); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Ticket Charts Row -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pie-chart-fill me-2"></i>Tickets theo trạng thái</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="chartStatus" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bar-chart-fill me-2"></i>Tickets theo danh mục</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="chartCategory" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-flag-fill me-2"></i>Tickets theo ưu tiên</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="chartPriority" height="220"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Revenue Charts Row -->
<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bar-chart-line-fill me-2"></i>Doanh thu theo tháng</span>
                <select id="revenueYearSelect" class="form-select form-select-sm" style="width:auto;">
                    <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == date('Y') ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="card-body">
                <canvas id="chartRevenueMonth" height="110"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bar-chart-steps me-2"></i>So sánh doanh thu theo năm</div>
            <div class="card-body">
                <canvas id="chartRevenueYear" height="220"></canvas>
            </div>
        </div>
    </div>
</div>

<?php
$statusLabels   = json_encode(array_column($chartData['by_status'],   'label'));
$statusCounts   = json_encode(array_column($chartData['by_status'],   'count'));
$categoryLabels = json_encode(array_column($chartData['by_category'], 'label'));
$categoryCounts = json_encode(array_column($chartData['by_category'], 'count'));
$priorityLabels = json_encode(array_column($chartData['by_priority'], 'label'));
$priorityCounts = json_encode(array_column($chartData['by_priority'], 'count'));

// Revenue chart data
$monthlyRows = $billingController->getRevenueByMonth(date('Y'));
$monthSlots  = array_fill(1, 12, 0);
foreach ($monthlyRows as $r) $monthSlots[(int)substr($r['month'], 5)] = (int)$r['revenue'];
$monthData = json_encode(array_values($monthSlots));

$yearRows   = $billingController->getRevenueByYear(3);
// Ensure all years in range appear (fill missing with 0)
$yearMap = [];
for ($y = date('Y') - 2; $y <= date('Y'); $y++) $yearMap[$y] = 0;
foreach ($yearRows as $r) $yearMap[(int)$r['year']] = (int)$r['revenue'];
$yearLabels = json_encode(array_keys($yearMap));
$yearData   = json_encode(array_values($yearMap));

$extraScripts = <<<HTML
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.font.size   = 12;

const PALETTE = ['#1a6bb5','#10b981','#f59e0b','#ef4444','#3b82f6','#8b5cf6','#ec4899','#14b8a6'];

(function(){
    // Ticket charts
    const statusCtx = document.getElementById('chartStatus')?.getContext('2d');
    if (statusCtx) new Chart(statusCtx, {
        type: 'doughnut',
        data: { labels: $statusLabels, datasets: [{ data: $statusCounts, backgroundColor: PALETTE, borderWidth: 2, borderColor: '#fff' }] },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14 } } }, cutout: '65%' }
    });

    const catCtx = document.getElementById('chartCategory')?.getContext('2d');
    if (catCtx) new Chart(catCtx, {
        type: 'bar',
        data: { labels: $categoryLabels, datasets: [{ label: 'Tickets', data: $categoryCounts, backgroundColor: PALETTE, borderRadius: 6, borderSkipped: false }] },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } } }
    });

    const priCtx = document.getElementById('chartPriority')?.getContext('2d');
    if (priCtx) new Chart(priCtx, {
        type: 'doughnut',
        data: { labels: $priorityLabels, datasets: [{ data: $priorityCounts, backgroundColor: ['#ef4444','#f59e0b','#6b7280','#1a6bb5'], borderWidth: 2, borderColor: '#fff' }] },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14 } } }, cutout: '65%' }
    });

    // Revenue by month (bar)
    const mCtx = document.getElementById('chartRevenueMonth')?.getContext('2d');
    if (mCtx) {
        window._revenueMonthChart = new Chart(mCtx, {
            type: 'bar',
            data: {
                labels: ['T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12'],
                datasets: [{
                    label: 'Doanh thu (đ)',
                    data: $monthData,
                    backgroundColor: '#1a6bb5',
                    borderRadius: 5,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { callback: function(v) { return v >= 1000000 ? (v/1000000).toFixed(1)+'M' : v.toLocaleString('vi-VN'); } }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // Revenue by year (horizontal bar)
    const yCtx = document.getElementById('chartRevenueYear')?.getContext('2d');
    if (yCtx) new Chart(yCtx, {
        type: 'bar',
        data: {
            labels: $yearLabels,
            datasets: [{
                label: 'Doanh thu',
                data: $yearData,
                backgroundColor: ['#10b981','#1a6bb5','#f59e0b'],
                borderRadius: 5
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { callback: function(v) { return v >= 1000000 ? (v/1000000).toFixed(0)+'M' : v; } }
                }
            }
        }
    });
})();

// Year selector: reload monthly revenue via AJAX
document.getElementById('revenueYearSelect')?.addEventListener('change', function() {
    fetch('dashboard.php?action=revenue_monthly&year=' + this.value)
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (window._revenueMonthChart) {
                window._revenueMonthChart.data.datasets[0].data = data.monthly;
                window._revenueMonthChart.update();
            }
        });
});
</script>
HTML;
?>

<?php include 'includes/footer.php'; ?>