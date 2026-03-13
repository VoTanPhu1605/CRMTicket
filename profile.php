<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'controllers/userController.php';

requireLogin();

$userController = new UserController();
$currentUser = getCurrentUser();

$pageTitle = 'Thông tin cá nhân';
$pageActions = '';

$activeTab = $_GET['tab'] ?? 'profile';

// Handle avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $result = $userController->updateAvatar($_FILES['avatar']);
    if ($result['success']) {
        redirectWithMessage('profile.php?tab=profile', 'success', $result['message']);
    } else {
        $avatarError = $result['message'];
    }
}

// Reload user data to get latest avatar
$userFull = $userController->getOwnProfile();

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeTab === 'profile' ? 'active' : ''; ?>" href="profile.php?tab=profile">
                            <i class="bi bi-person me-1"></i>Thông tin cá nhân
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeTab === 'password' ? 'active' : ''; ?>" href="profile.php?tab=password">
                            <i class="bi bi-key me-1"></i>Đổi mật khẩu
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <?php if ($activeTab === 'profile'): ?>
                    <!-- Profile Information -->
                    <div class="row">
                        <div class="col-md-8">
                            <h5>Thông tin tài khoản</h5>
                            <div class="table-responsive"><table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold" style="width: 150px;">Tên đăng nhập:</td>
                                    <td><?php echo htmlspecialchars($currentUser['username']); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Họ tên:</td>
                                    <td><?php echo htmlspecialchars($currentUser['fullname']); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Email:</td>
                                    <td><?php echo htmlspecialchars($currentUser['email'] ?? 'Chưa cập nhật'); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Số điện thoại:</td>
                                    <td><?php echo htmlspecialchars($currentUser['phone'] ?? 'Chưa cập nhật'); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Vai trò:</td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $currentUser['role_name']; ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Trạng thái:</td>
                                    <td>
                                        <span class="badge bg-success">Hoạt động</span>
                                    </td>
                                </tr>
                            </table></div>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="mb-3">
                                <?php if (!empty($userFull['avatar'])): ?>
                                    <img src="assets/avatars/<?php echo htmlspecialchars($userFull['avatar']); ?>"
                                         class="rounded-circle" style="width: 100px; height: 100px; object-fit: cover;"
                                         alt="Avatar" id="avatarPreview">
                                <?php else: ?>
                                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;" id="avatarPlaceholder">
                                        <i class="bi bi-person-fill text-secondary" style="font-size: 2.5rem;"></i>
                                    </div>
                                    <img src="" class="rounded-circle d-none" style="width: 100px; height: 100px; object-fit: cover;" alt="Avatar" id="avatarPreview">
                                <?php endif; ?>
                            </div>

                            <?php if (isset($avatarError)): ?>
                                <div class="alert alert-danger py-1 small"><?php echo $avatarError; ?></div>
                            <?php endif; ?>

                            <form method="POST" enctype="multipart/form-data">
                                <label for="avatarInput" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-camera me-1"></i>Chọn ảnh
                                </label>
                                <input type="file" id="avatarInput" name="avatar" accept="image/*" class="d-none">
                                <button type="submit" class="btn btn-primary btn-sm d-none" id="uploadBtn">
                                    <i class="bi bi-upload me-1"></i>Upload
                                </button>
                            </form>
                            <p class="text-muted small mt-2">JPG, PNG, GIF. Tối đa 2MB</p>
                        </div>
                    </div>

                <?php elseif ($activeTab === 'password'): ?>
                    <!-- Change Password -->
                    <?php
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $currentPassword = $_POST['current_password'] ?? '';
                        $newPassword = $_POST['new_password'] ?? '';
                        $confirmPassword = $_POST['confirm_password'] ?? '';

                        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                            $error = 'Vui lòng điền đầy đủ thông tin.';
                        } elseif ($newPassword !== $confirmPassword) {
                            $error = 'Mật khẩu mới và xác nhận mật khẩu không khớp.';
                        } elseif (strlen($newPassword) < 6) {
                            $error = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
                        } else {
                            $result = $userController->changePassword($currentPassword, $newPassword);
                            if ($result['success']) {
                                setFlashMessage('success', $result['message']);
                                header('Location: profile.php?tab=password');
                                exit();
                            } else {
                                $error = $result['message'];
                            }
                        }
                    }
                    ?>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="" id="pwdForm" novalidate>
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Mật khẩu hiện tại <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePwdField('current_password','icon0')" tabindex="-1">
                                    <i class="bi bi-eye" id="icon0"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Vui lòng nhập mật khẩu hiện tại.</div>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">Mật khẩu mới <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePwdField('new_password','icon1')" tabindex="-1">
                                    <i class="bi bi-eye" id="icon1"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Mật khẩu phải có ít nhất 6 ký tự.</div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePwdField('confirm_password','icon2')" tabindex="-1">
                                    <i class="bi bi-eye" id="icon2"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Mật khẩu xác nhận không khớp.</div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i>Đổi mật khẩu
                            </button>
                        </div>
                    </form>

                    <script>
                    function togglePwdField(id, iconId) {
                        const el = document.getElementById(id);
                        const ic = document.getElementById(iconId);
                        el.type = el.type === 'password' ? 'text' : 'password';
                        ic.className = el.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
                    }
                    (function() {
                        const form = document.getElementById('pwdForm');
                        if (!form) return;
                        function setInvalid(el, msg) {
                            el.classList.add('is-invalid'); el.classList.remove('is-valid');
                            const fb = el.closest('.mb-3')?.querySelector('.invalid-feedback');
                            if (fb && msg) fb.textContent = msg;
                        }
                        function setValid(el) { el.classList.remove('is-invalid'); el.classList.add('is-valid'); }

                        form.addEventListener('submit', function(e) {
                            let ok = true;
                            form.querySelectorAll('.form-control').forEach(el => el.classList.remove('is-invalid','is-valid'));
                            const cur = document.getElementById('current_password');
                            if (!cur.value) { setInvalid(cur, 'Vui lòng nhập mật khẩu hiện tại.'); ok = false; } else setValid(cur);
                            const np = document.getElementById('new_password');
                            if (!np.value || np.value.length < 6) { setInvalid(np, 'Mật khẩu phải có ít nhất 6 ký tự.'); ok = false; } else setValid(np);
                            const cp = document.getElementById('confirm_password');
                            if (cp.value !== np.value) { setInvalid(cp, 'Mật khẩu xác nhận không khớp.'); ok = false; } else setValid(cp);
                            if (!ok) e.preventDefault();
                        });
                        document.getElementById('new_password').addEventListener('input', function() {
                            if (this.value.length > 0 && this.value.length < 6) setInvalid(this, 'Mật khẩu phải có ít nhất 6 ký tự.');
                            else if (this.value.length >= 6) setValid(this);
                        });
                        document.getElementById('confirm_password').addEventListener('input', function() {
                            const np = document.getElementById('new_password').value;
                            if (this.value && this.value !== np) setInvalid(this, 'Mật khẩu xác nhận không khớp.');
                            else if (this.value) setValid(this);
                        });
                    })();
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('avatarInput')?.addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        const preview = document.getElementById('avatarPreview');
        const placeholder = document.getElementById('avatarPlaceholder');
        preview.src = e.target.result;
        preview.classList.remove('d-none');
        if (placeholder) placeholder.classList.add('d-none');
        document.getElementById('uploadBtn').classList.remove('d-none');
    };
    reader.readAsDataURL(file);
});
</script>

<?php include 'includes/footer.php'; ?>