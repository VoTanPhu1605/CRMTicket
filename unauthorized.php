<?php
require_once 'includes/auth.php';
requireLogin();

$pageTitle = 'Không có quyền truy cập';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - HelpDesk CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            text-align: center;
        }
        .error-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 3rem 2rem;
        }
        .error-body {
            padding: 2rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="error-card">
                    <div class="error-header">
                        <i class="bi bi-shield-x" style="font-size: 4rem;"></i>
                        <h2 class="mt-3">Truy cập bị từ chối</h2>
                    </div>
                    <div class="error-body">
                        <h5>Bạn không có quyền truy cập trang này</h5>
                        <p class="text-muted">Vui lòng liên hệ quản trị viên nếu bạn nghĩ đây là lỗi.</p>

                        <div class="d-grid gap-2 mt-4">
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="bi bi-house me-1"></i>Về trang chủ
                            </a>
                            <a href="logout.php" class="btn btn-outline-secondary">
                                <i class="bi bi-box-arrow-right me-1"></i>Đăng xuất
                            </a>
                        </div>

                        <hr class="my-4">

                        <div class="small text-muted">
                            <strong>Thông tin tài khoản:</strong><br>
                            Tên: <?php echo getUserDisplayName(); ?><br>
                            Vai trò: <?php echo getUserRoleDisplayName(); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>