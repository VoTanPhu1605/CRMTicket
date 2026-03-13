<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' · ' : ''; ?>HelpDesk CRM</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/icons/bootstrap-icons.css" rel="stylesheet">
    <style>
/* ── CSS Variables ── */
:root{--primary:#1a6bb5;--primary-dark:#155a9a;--primary-light:#dbeafe;--sidebar-bg:#1c2b3a;--sidebar-w:255px;--bg:#f4f5f7;--card-bg:#fff;--border:#dde1e7;--border-light:#edf0f4;--text:#1e293b;--text-muted:#64748b;--text-light:#94a3b8;--success:#16a34a;--warning:#d97706;--danger:#dc2626;--info:#2563eb;--radius:8px;--radius-lg:12px;--shadow-sm:0 1px 3px rgba(0,0,0,.08),0 1px 2px rgba(0,0,0,.05);--shadow:0 4px 12px rgba(0,0,0,.10);--shadow-lg:0 8px 24px rgba(0,0,0,.12);--transition:all .18s ease}
*,*::before,*::after{box-sizing:border-box}
body{font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:14px;line-height:1.6;color:var(--text);background:var(--bg);-webkit-font-smoothing:antialiased}
h1,h2,h3,h4,h5,h6{font-weight:600;letter-spacing:-0.02em}
a{color:var(--primary);text-decoration:none}
a:hover{color:var(--primary-dark)}

/* ── Sidebar ── */
.sidebar{width:var(--sidebar-w);min-height:100vh;background:var(--sidebar-bg);display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:1000;transition:var(--transition);overflow-y:auto}
.sidebar-brand{display:flex;align-items:center;gap:10px;padding:20px 16px 18px;border-bottom:1px solid rgba(255,255,255,.08)}
.sidebar-brand-icon{width:34px;height:34px;background:var(--primary);border-radius:6px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:15px;flex-shrink:0}
.sidebar-brand-text{font-size:14px;font-weight:600;color:#fff;letter-spacing:0;line-height:1.2}
.sidebar-brand-sub{font-size:10px;color:rgba(255,255,255,.4);font-weight:400;letter-spacing:.02em}
.sidebar-nav{flex:1;padding:12px 10px}
.sidebar-label{font-size:11px;font-weight:500;letter-spacing:.03em;text-transform:uppercase;color:rgba(255,255,255,.3);padding:14px 8px 5px}
.sidebar-link{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:5px;color:rgba(255,255,255,.65);font-size:13.5px;font-weight:400;transition:var(--transition);margin-bottom:1px}
.sidebar-link i{font-size:15px;width:18px;text-align:center;flex-shrink:0}
.sidebar-link:hover{background:rgba(255,255,255,.08);color:rgba(255,255,255,.92)}
.sidebar-link.active{background:rgba(26,107,181,.55);color:#fff;border-left:3px solid #5ba3e0;padding-left:7px}
.sidebar-footer{padding:14px 10px;border-top:1px solid rgba(255,255,255,.07)}
.sidebar-user{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;transition:var(--transition);margin-bottom:10px;text-decoration:none}
.sidebar-user:hover{background:rgba(255,255,255,.07)}
.sidebar-user-avatar{width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.15);flex-shrink:0}
.sidebar-user-placeholder{width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.5);font-size:15px;flex-shrink:0;border:2px solid rgba(255,255,255,.1)}
.sidebar-user-info{flex:1;min-width:0}
.sidebar-user-name{font-size:13px;font-weight:600;color:rgba(255,255,255,.9);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sidebar-user-role{font-size:11px;color:rgba(255,255,255,.35)}
.sidebar-logout{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:9px;border-radius:8px;border:1px solid rgba(255,255,255,.1);background:transparent;color:rgba(255,255,255,.5);font-size:13px;font-weight:500;transition:var(--transition);cursor:pointer;text-decoration:none}
.sidebar-logout:hover{background:rgba(239,68,68,.15);border-color:rgba(239,68,68,.3);color:#fca5a5}

/* ── Main Content ── */
.main-content{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:#fff;border-bottom:1px solid var(--border);padding:0 28px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.topbar-title{font-size:16px;font-weight:700;color:var(--text);letter-spacing:-0.02em}
.topbar-actions{display:flex;align-items:center;gap:10px}
.page-content{padding:24px 28px;flex:1}

/* ── Cards ── */
.card{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);transition:var(--transition)}
.card:hover{box-shadow:var(--shadow)}
.card-header{background:transparent;border-bottom:1px solid var(--border-light);padding:16px 20px;font-weight:600;font-size:14px;color:var(--text)}
.card-body{padding:20px}

/* ── Stat Cards ── */
.stat-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;display:flex;align-items:center;gap:16px;transition:var(--transition);box-shadow:var(--shadow-sm)}
.stat-card:hover{transform:translateY(-2px);box-shadow:var(--shadow)}
.stat-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.stat-icon-primary{background:#ede9fe;color:var(--primary)}
.stat-icon-success{background:#d1fae5;color:var(--success)}
.stat-icon-warning{background:#fef3c7;color:var(--warning)}
.stat-icon-danger{background:#fee2e2;color:var(--danger)}
.stat-icon-info{background:#dbeafe;color:var(--info)}
.stat-value{font-size:26px;font-weight:700;letter-spacing:-0.04em;color:var(--text);line-height:1}
.stat-label{font-size:12px;font-weight:500;color:var(--text-muted);margin-top:3px}

/* ── Buttons ── */
.btn{font-family:'Inter',sans-serif;font-weight:500;font-size:13.5px;border-radius:8px;padding:7px 16px;transition:var(--transition);display:inline-flex;align-items:center;gap:6px;letter-spacing:-0.01em}
.btn-primary{background:var(--primary);border-color:var(--primary);color:#fff}
.btn-primary:hover{background:var(--primary-dark);border-color:var(--primary-dark);color:#fff}
.btn-secondary{background:#fff;border-color:var(--border);color:var(--text)}
.btn-secondary:hover{background:var(--bg);border-color:#cbd5e1;color:var(--text)}
.btn-outline-primary{color:var(--primary);border-color:var(--primary)}
.btn-outline-primary:hover{background:var(--primary);border-color:var(--primary);color:#fff}
.btn-outline-secondary{color:var(--text-muted);border-color:var(--border)}
.btn-outline-secondary:hover{background:var(--bg);border-color:#cbd5e1;color:var(--text)}
.btn-outline-danger{color:var(--danger);border-color:#fecaca}
.btn-outline-danger:hover{background:var(--danger);border-color:var(--danger);color:#fff}
.btn-outline-warning{color:#d97706;border-color:#fde68a}
.btn-outline-warning:hover{background:var(--warning);border-color:var(--warning);color:#fff}
.btn-outline-info{color:var(--info);border-color:#bfdbfe}
.btn-outline-info:hover{background:var(--info);border-color:var(--info);color:#fff}
.btn-outline-success{color:var(--success);border-color:#a7f3d0}
.btn-outline-success:hover{background:var(--success);border-color:var(--success);color:#fff}
.btn-danger{background:var(--danger);border-color:var(--danger);color:#fff}
.btn-danger:hover{background:#dc2626;border-color:#dc2626;transform:translateY(-1px)}
.btn-sm{font-size:12px;padding:5px 12px;border-radius:6px}
.btn-lg{font-size:15px;padding:11px 24px;border-radius:10px}

/* ── Forms ── */
.form-label{font-size:13px;font-weight:500;color:var(--text);margin-bottom:6px}
.form-control,.form-select{font-family:'Inter',sans-serif;font-size:14px;border:1px solid var(--border);border-radius:8px;padding:9px 13px;color:var(--text);background:#fff;transition:var(--transition);box-shadow:0 1px 2px rgba(0,0,0,.03)}
.form-control:focus,.form-select:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(79,70,229,.12);outline:none}
.form-control::placeholder{color:var(--text-light)}
.form-control[readonly]{background:var(--border-light);color:var(--text-muted)}
.input-group .form-control{border-radius:8px 0 0 8px}
.input-group .btn{border-radius:0 8px 8px 0}

/* ── Tables ── */
.table{font-size:13.5px;color:var(--text);margin-bottom:0}
.table thead th{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);border-bottom:1px solid var(--border);padding:12px 16px;background:#fafafa;white-space:nowrap}
.table tbody td{padding:13px 16px;border-bottom:1px solid var(--border-light);vertical-align:middle}
.table tbody tr:last-child td{border-bottom:none}
.table-hover tbody tr:hover{background:#fafbff}

/* ── Badges ── */
.badge{font-family:'Inter',sans-serif;font-size:11px;font-weight:600;padding:3.5px 9px;border-radius:100px;letter-spacing:.01em}
.bg-success{background:#d1fae5!important;color:#065f46!important}
.bg-danger{background:#fee2e2!important;color:#991b1b!important}
.bg-warning{background:#fef3c7!important;color:#92400e!important}
.bg-primary{background:#dbeafe!important;color:#1e40af!important}
.bg-secondary{background:#f1f5f9!important;color:#475569!important}
.bg-info{background:#dbeafe!important;color:#1e40af!important}

/* ── Alerts ── */
.alert{border:none;border-radius:var(--radius);font-size:13.5px;padding:12px 16px;display:flex;align-items:center;gap:10px;animation:slideDown .25s ease}
.alert-success{background:#d1fae5;color:#065f46}
.alert-danger{background:#fee2e2;color:#991b1b}
.alert-warning{background:#fef3c7;color:#92400e}
.alert-info{background:#dbeafe;color:#1e40af}
.alert .btn-close{margin-left:auto}
@keyframes slideDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}

/* ── Nav Tabs ── */
.nav-tabs{border-bottom:1px solid var(--border);gap:2px}
.nav-tabs .nav-link{font-size:13.5px;font-weight:500;color:var(--text-muted);border:none;border-bottom:2px solid transparent;padding:10px 16px;border-radius:0;transition:var(--transition)}
.nav-tabs .nav-link:hover{color:var(--primary);border-bottom-color:rgba(79,70,229,.3)}
.nav-tabs .nav-link.active{color:var(--primary);border-bottom-color:var(--primary);background:transparent;font-weight:600}

/* ── Modal ── */
.modal-content{border:none;border-radius:var(--radius-lg);box-shadow:var(--shadow-lg)}
.modal-header{border-bottom:1px solid var(--border-light);padding:20px 24px 16px}
.modal-title{font-size:15px;font-weight:700}
.modal-body{padding:20px 24px}
.modal-footer{border-top:1px solid var(--border-light);padding:16px 24px 20px}

/* ── Priority / Status Badges ── */
.badge-priority-high{background:#fee2e2;color:#991b1b}
.badge-priority-medium{background:#fef3c7;color:#92400e}
.badge-priority-low{background:#d1fae5;color:#065f46}
.badge-status-open{background:#dbeafe;color:#1e40af}
.badge-status-inprogress{background:#fef3c7;color:#92400e}
.badge-status-closed{background:#d1fae5;color:#065f46}

/* ── Scrollbar ── */
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:10px}
::-webkit-scrollbar-thumb:hover{background:#94a3b8}

/* ── Login Page ── */
.login-page{min-height:100vh;background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 50%,#0f172a 100%);display:flex;align-items:center;justify-content:center;padding:20px}
.login-card{background:#fff;border-radius:20px;box-shadow:0 25px 60px rgba(0,0,0,.25);width:100%;max-width:400px;padding:40px}
.login-logo{width:52px;height:52px;background:var(--primary);border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:22px;color:#fff;box-shadow:0 8px 20px rgba(79,70,229,.35)}
.login-title{font-size:22px;font-weight:700;text-align:center;color:var(--text);letter-spacing:-0.04em;margin-bottom:4px}
.login-subtitle{font-size:13px;text-align:center;color:var(--text-muted);margin-bottom:28px}

/* ── Mobile / Responsive ── */
.mobile-nav-toggle{display:none;position:fixed;top:14px;left:14px;z-index:1100;width:38px;height:38px;border-radius:9px;background:var(--sidebar-bg);color:#fff;border:none;font-size:16px;cursor:pointer;align-items:center;justify-content:center}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;backdrop-filter:blur(2px)}

/* Tablet + Mobile (≤991px) */
@media(max-width:991px){
  .mobile-nav-toggle{display:flex}
  .sidebar{transform:translateX(-100%);box-shadow:none}
  .sidebar.open{transform:translateX(0);box-shadow:var(--shadow-lg)}
  .sidebar-overlay.open{display:block}
  .main-content{margin-left:0}
  .page-content{padding:16px}
  .topbar{padding:0 16px 0 60px}
  /* Nav tabs scrollable */
  .nav-tabs{flex-wrap:nowrap;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none}
  .nav-tabs::-webkit-scrollbar{display:none}
  .nav-tabs .nav-link{white-space:nowrap}
}

/* Small tablets (≤768px) */
@media(max-width:768px){
  .topbar-title{font-size:14px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  /* Hide button text labels on topbar, keep icons */
  .topbar-actions .btn-label{display:none}
  .topbar-actions .btn{padding:6px 10px}
  /* Stat cards: 2 per row */
  .stat-card{padding:14px;gap:12px}
  .stat-icon{width:40px;height:40px;font-size:16px;border-radius:10px}
  .stat-value{font-size:20px}
  .stat-label{font-size:11px}
  /* Row with g-3 gap reduce on mobile */
  .row.g-3{--bs-gutter-x:.75rem;--bs-gutter-y:.75rem}
  /* Modal: wider on tablet */
  .modal-dialog{max-width:calc(100% - 32px);margin:16px auto}
  .modal-body{padding:16px 20px}
  .modal-header,.modal-footer{padding:14px 20px}
  /* Card header */
  .card-header{padding:12px 16px;font-size:13px}
}

/* Mobile phones (≤576px) */
@media(max-width:576px){
  .page-content{padding:10px}
  .card-body{padding:14px}
  .card-header{padding:11px 14px;font-size:12.5px}
  /* Tables */
  .table thead th{font-size:10.5px;padding:9px 10px;letter-spacing:.03em}
  .table tbody td{padding:9px 10px;font-size:13px}
  /* Stat cards: compact */
  .stat-card{padding:12px 14px;gap:10px;border-radius:10px}
  .stat-icon{width:38px;height:38px;font-size:15px;border-radius:8px}
  .stat-value{font-size:18px}
  .stat-label{font-size:10.5px}
  /* Topbar */
  .topbar{height:52px;padding:0 12px 0 56px}
  .topbar-title{font-size:13.5px}
  .topbar-actions .btn{padding:5px 8px;font-size:12px}
  .topbar-actions .btn i{font-size:13px}
  /* Modals full-width */
  .modal-dialog{margin:8px;max-width:calc(100% - 16px)}
  .modal-body{padding:14px 16px}
  .modal-header,.modal-footer{padding:12px 16px}
  .modal-title{font-size:14px}
  /* Buttons in card header: icon only on small */
  .card-header .btn-label{display:none}
  .card-header .btn{padding:5px 10px}
  /* Nav tabs */
  .nav-tabs .nav-link{padding:8px 12px;font-size:12.5px}
  /* Forms */
  .form-control,.form-select{font-size:14px;padding:8px 12px}
  .form-label{font-size:12.5px}
  /* Badges */
  .badge{font-size:10.5px;padding:3px 7px}
  /* Row gutters tight */
  .row.g-3,.row.g-4{--bs-gutter-x:.5rem;--bs-gutter-y:.5rem}
  .row.mb-4{margin-bottom:1rem!important}
  /* h5 section titles */
  h5.fw-semibold{font-size:14px}
}

/* Extra small (≤400px) */
@media(max-width:400px){
  .stat-value{font-size:16px}
  .stat-icon{width:34px;height:34px;font-size:14px}
  .stat-card{padding:10px 12px;gap:8px}
  .page-content{padding:8px}
}

/* ── Responsive helpers ── */
/* Hide columns on mobile with d-none d-md-table-cell on td/th */
/* Chart containers adapt height */
@media(max-width:768px){
  canvas{max-height:260px!important}
}
@media(max-width:576px){
  canvas{max-height:200px!important}
  /* Login card */
  .login-card{padding:28px 20px}
  /* Table: allow horizontal scroll, no column hiding needed */
  .table-responsive{-webkit-overflow-scrolling:touch}
  /* Buttons: icon-only mode when .btn-icon-only is added */
  .btn-icon-only .btn-label{display:none}
  /* Stack col-md-* to full on tiny screens */
  .topbar-actions{gap:6px}
  /* Fix overflow on ticket detail page */
  .col-lg-8,.col-lg-4{width:100%}
}

/* ── Form Validation ── */
.invalid-feedback{font-size:12px;color:var(--danger);margin-top:4px;display:none}
.valid-feedback{font-size:12px;color:var(--success);margin-top:4px;display:none}
.form-control.is-invalid,.form-select.is-invalid{border-color:var(--danger)!important}
.form-control.is-invalid:focus,.form-select.is-invalid:focus{box-shadow:0 0 0 3px rgba(239,68,68,.15)!important}
.form-control.is-valid,.form-select.is-valid{border-color:var(--success)!important}
.form-control.is-invalid~.invalid-feedback,.form-select.is-invalid~.invalid-feedback{display:block}
.input-group .form-control.is-invalid~.invalid-feedback{display:block}

/* ── Utilities ── */
.text-muted{color:var(--text-muted)!important}
.border-bottom{border-bottom:1px solid var(--border)!important}
.fw-semibold{font-weight:600}
.fs-xs{font-size:11px}
.rounded-xl{border-radius:var(--radius-lg)!important}
.shadow-none{box-shadow:none!important}
.bg-light{background:var(--border-light)!important}
hr{border-color:var(--border-light);opacity:1}
.dropdown-menu{border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);font-size:13.5px;padding:6px}
.dropdown-item{border-radius:6px;padding:8px 12px;color:var(--text)}
.dropdown-item:hover{background:var(--bg);color:var(--text)}
.table-responsive{border-radius:var(--radius)}
.btn-group .btn{border-radius:6px!important;margin-right:3px}
.btn-group .btn:last-child{margin-right:0}
    </style>
<script>window.APP_BASE='<?php echo rtrim(str_replace(basename($_SERVER["SCRIPT_NAME"]),"",$_SERVER["SCRIPT_NAME"]),"/")?>/'; </script>
</head>
<body>

<?php
// Load sidebar user avatar
$sidebarUser = null;
if (isset($userFull)) {
    $sidebarUser = $userFull;
} else {
    global $pdo;
    $cu = getCurrentUser();
    if ($cu && $pdo) {
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->execute([$cu['id']]);
        $sidebarUser = $stmt->fetch();
    }
}
$currentPage = basename($_SERVER['PHP_SELF']);

// Notification badge: count unassigned open tickets (for Admin, Manager, IT Helpdesk)
$_sidebarBadge = 0;
if (hasAnyRole(['Admin', 'Manager', 'IT Helpdesk']) && isset($pdo)) {
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM tickets t
            JOIN statuses s ON t.status_id = s.id
            WHERE t.assigned_to IS NULL AND s.name != 'Đã đóng'
        ");
        $_sidebarBadge = (int)$stmt->fetchColumn();
    } catch (\PDOException $e) {
        $_sidebarBadge = 0;
    }
}
?>

<!-- Mobile toggle -->
<button class="mobile-nav-toggle" id="sidebarToggle">
    <i class="bi bi-list"></i>
</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <i class="bi bi-headset"></i>
        </div>
        <div>
            <div class="sidebar-brand-text">HelpDesk</div>
            <div class="sidebar-brand-sub">CRM System</div>
        </div>
    </div>

    <div class="sidebar-nav">
        <div class="sidebar-label">Menu chính</div>

        <a href="dashboard.php" class="sidebar-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i>
            Dashboard
        </a>

        <?php
        $_chatUnread = 0;
        try {
            require_once __DIR__ . '/../models/Chat.php';
            $_chatModel = new Chat();
            $_chatUnread = $_chatModel->totalUnread(getCurrentUser()['id']);
        } catch (\Exception $e) {}
        ?>
        <a href="chat.php" class="sidebar-link <?php echo $currentPage === 'chat.php' ? 'active' : ''; ?>" style="justify-content:space-between;">
            <span style="display:flex;align-items:center;gap:10px;">
                <i class="bi bi-chat-dots-fill"></i>
                Chat nội bộ
            </span>
            <?php if ($_chatUnread > 0): ?>
                <span style="background:#ef4444;color:#fff;font-size:10px;font-weight:700;padding:2px 6px;border-radius:100px;line-height:1.4;flex-shrink:0;"><?php echo $_chatUnread; ?></span>
            <?php endif; ?>
        </a>

        <a href="tickets.php" class="sidebar-link <?php echo $currentPage === 'tickets.php' ? 'active' : ''; ?>" style="justify-content:space-between;">
            <span style="display:flex;align-items:center;gap:10px;">
                <i class="bi bi-ticket-detailed"></i>
                Tickets
            </span>
            <?php if ($_sidebarBadge > 0): ?>
                <span style="background:#ef4444;color:#fff;font-size:10px;font-weight:700;padding:2px 6px;border-radius:100px;line-height:1.4;flex-shrink:0;"><?php echo $_sidebarBadge; ?></span>
            <?php endif; ?>
        </a>

        <?php if (hasRole('Admin')): ?>
        <a href="users.php" class="sidebar-link <?php echo $currentPage === 'users.php' ? 'active' : ''; ?>">
            <i class="bi bi-people"></i>
            Người dùng
        </a>
        <?php endif; ?>

        <div class="sidebar-label" style="margin-top:8px;">Phân tích</div>

        <a href="warranty.php" class="sidebar-link <?php echo $currentPage === 'warranty.php' ? 'active' : ''; ?>" style="justify-content:space-between;">
            <span style="display:flex;align-items:center;gap:10px;">
                <i class="bi bi-shield-check"></i>
                Bảo hành
            </span>
            <?php
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM tickets WHERE warranty_end_date IS NOT NULL AND warranty_end_date >= CURDATE() AND warranty_end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
                $_warrantyBadge = (int)$stmt->fetchColumn();
                if ($_warrantyBadge > 0) echo '<span style="background:#f59e0b;color:#fff;font-size:10px;font-weight:700;padding:2px 6px;border-radius:100px;line-height:1.4;flex-shrink:0;">' . $_warrantyBadge . '</span>';
            } catch (\PDOException $e) {}
            ?>
        </a>

        <a href="reports.php" class="sidebar-link <?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>">
            <i class="bi bi-bar-chart-line"></i>
            Báo cáo
        </a>

        <?php if (hasAnyRole(['Admin', 'Manager'])): ?>
        <?php
        // Billing unpaid badge
        try {
            require_once __DIR__ . '/../controllers/billingController.php';
            $_billingUnpaid = (new BillingController())->getUnpaidCount();
        } catch (\Exception $e) { $_billingUnpaid = 0; }
        ?>
        <a href="billing.php" class="sidebar-link <?php echo $currentPage === 'billing.php' ? 'active' : ''; ?>" style="justify-content:space-between;">
            <span style="display:flex;align-items:center;gap:9px;">
                <i class="bi bi-credit-card-2-front"></i>
                Thanh toán
            </span>
            <?php if ($_billingUnpaid > 0): ?>
                <span style="background:#d97706;color:#fff;font-size:10px;font-weight:700;padding:2px 6px;border-radius:100px;line-height:1.4;flex-shrink:0;"><?php echo $_billingUnpaid; ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <div class="sidebar-label" style="margin-top:8px;">Tài khoản</div>

        <a href="profile.php" class="sidebar-link <?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>">
            <i class="bi bi-person-circle"></i>
            Hồ sơ cá nhân
        </a>
    </div>

    <div class="sidebar-footer">
        <a href="profile.php" class="sidebar-user">
            <?php if (!empty($sidebarUser['avatar'])): ?>
                <img src="assets/avatars/<?php echo htmlspecialchars($sidebarUser['avatar']); ?>"
                     class="sidebar-user-avatar" alt="Avatar">
            <?php else: ?>
                <div class="sidebar-user-placeholder">
                    <i class="bi bi-person-fill"></i>
                </div>
            <?php endif; ?>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?php echo htmlspecialchars(getUserDisplayName()); ?></div>
                <div class="sidebar-user-role"><?php echo htmlspecialchars(getUserRoleDisplayName()); ?></div>
            </div>
        </a>
        <a href="logout.php" class="sidebar-logout">
            <i class="bi bi-box-arrow-right"></i>
            Đăng xuất
        </a>
    </div>
</nav>

<!-- Main Content -->
<div class="main-content">
    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-title"><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard'; ?></div>
        <div class="topbar-actions">
            <?php if (isset($pageActions)) echo $pageActions; ?>
        </div>
    </div>

    <!-- Page Content -->
    <div class="page-content">
        <?php displayFlashMessages(); ?>
