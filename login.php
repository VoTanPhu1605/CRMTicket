<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'controllers/authController.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$authController = new AuthController();
$error = '';

if (isset($_GET['error']) && $_GET['error'] === 'locked') {
    $error = 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập tên đăng nhập và mật khẩu.';
    } else {
        $loginResult = $authController->login($username, $password);
        if ($loginResult === true) {
            header('Location: dashboard.php');
            exit();
        } elseif ($loginResult === 'locked') {
            $error = 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.';
        } else {
            $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
        }
    }
}

$pageTitle = 'Đăng nhập';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập · HelpDesk CRM</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/icons/bootstrap-icons.css" rel="stylesheet">
    <style>
:root{--primary:#4f46e5;--primary-dark:#4338ca;--border:#e2e8f0;--text:#1e293b;--text-muted:#64748b;--text-light:#94a3b8;--radius:10px;--transition:all .2s ease}
*,*::before,*::after{box-sizing:border-box}
body{font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:14px;-webkit-font-smoothing:antialiased}
.login-page{min-height:100vh;background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 50%,#0f172a 100%);display:flex;align-items:center;justify-content:center;padding:20px}
.login-card{background:#fff;border-radius:20px;box-shadow:0 25px 60px rgba(0,0,0,.25);width:100%;max-width:400px;padding:40px}
.login-logo{width:52px;height:52px;background:var(--primary);border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:22px;color:#fff;box-shadow:0 8px 20px rgba(79,70,229,.35)}
.login-title{font-size:22px;font-weight:700;text-align:center;color:var(--text);letter-spacing:-0.04em;margin-bottom:4px}
.login-subtitle{font-size:13px;text-align:center;color:var(--text-muted);margin-bottom:28px}
.form-label{font-size:13px;font-weight:500;color:var(--text);margin-bottom:6px}
.form-control{font-family:'Inter',sans-serif;font-size:14px;border:1px solid var(--border);border-radius:8px;padding:9px 13px;color:var(--text);background:#fff;transition:var(--transition)}
.form-control:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(79,70,229,.12);outline:none}
.form-control::placeholder{color:var(--text-light)}
.form-control.is-invalid{border-color:#ef4444}
.form-control.is-invalid:focus{box-shadow:0 0 0 3px rgba(239,68,68,.15)}
.invalid-feedback{font-size:12px;color:#ef4444;margin-top:4px;display:none}
.was-validated .form-control:invalid~.invalid-feedback,.form-control.is-invalid~.invalid-feedback{display:block}
.input-group .form-control{border-radius:8px 0 0 8px}
.input-group .btn{border-radius:0 8px 8px 0}
.btn{font-family:'Inter',sans-serif;font-weight:500;font-size:13.5px;border-radius:8px;padding:7px 16px;transition:var(--transition);display:inline-flex;align-items:center;justify-content:center;gap:6px}
.btn-primary{background:var(--primary);border-color:var(--primary);color:#fff}
.btn-primary:hover{background:var(--primary-dark);border-color:var(--primary-dark);color:#fff}
.btn-secondary{background:#fff;border-color:var(--border);color:var(--text)}
.btn-secondary:hover{background:#f1f5f9;color:var(--text)}
.btn-lg{font-size:15px;padding:11px 24px;border-radius:10px}
.w-100{width:100%}
.alert{border:none;border-radius:var(--radius);font-size:13.5px;padding:12px 16px;display:flex;align-items:center;gap:8px;margin-bottom:20px}
.alert-danger{background:#fee2e2;color:#991b1b}
.text-muted{color:var(--text-muted)!important}
.text-center{text-align:center}
.mb-0{margin-bottom:0}
.mb-3{margin-bottom:1rem}
.mb-4{margin-bottom:1.5rem}
.mt-4{margin-top:1.5rem}
.ms-1{margin-left:.25rem}
    </style>
</head>
<body class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <i class="bi bi-headset"></i>
        </div>
        <div class="login-title">HelpDesk CRM</div>
        <div class="login-subtitle">Đăng nhập để tiếp tục</div>

        <?php if ($error): ?>
            <div class="alert alert-danger mb-4">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label for="username" class="form-label">Tên đăng nhập</label>
                <input type="text" class="form-control" id="username" name="username"
                       placeholder="Nhập tên đăng nhập"
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                       autocomplete="username" required autofocus>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Mật khẩu</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="Nhập mật khẩu" autocomplete="current-password" required>
                    <button type="button" class="btn btn-secondary" id="togglePwd" tabindex="-1">
                        <i class="bi bi-eye" id="togglePwdIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg">
                Đăng nhập
                <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </form>

        <p class="text-center text-muted mt-4 mb-0" style="font-size:12px;">
            &copy; <?php echo date('Y'); ?> HelpDesk CRM. All rights reserved.
        </p>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>if (typeof bootstrap === 'undefined') { document.write('<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"><\/script>'); }</script>
    <script>
    document.getElementById('togglePwd')?.addEventListener('click', function() {
        const pwd = document.getElementById('password');
        const icon = document.getElementById('togglePwdIcon');
        if (pwd.type === 'password') {
            pwd.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            pwd.type = 'password';
            icon.className = 'bi bi-eye';
        }
    });
    </script>
</body>
</html>