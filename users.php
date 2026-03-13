<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'controllers/userController.php';

requireRole('Admin');

$userController = new UserController();
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Handle different actions
switch ($action) {
    case 'create':
        $pageTitle = 'Tạo User mới';
        $pageActions = '<a href="users.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i>Quay lại
        </a>';

        $roles = $userController->getRoles();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $userController->createUser($_POST);
            if ($result['success']) {
                redirectWithMessage('users.php', 'success', $result['message']);
            } else {
                $error = $result['message'];
            }
        }
        break;

    case 'edit':
        if (!$id) {
            header('Location: users.php');
            exit();
        }

        $user = $userController->getUser($id);
        if (!$user) {
            redirectWithMessage('users.php', 'error', 'User không tồn tại.');
        }

        $pageTitle = 'Chỉnh sửa User: ' . $user['fullname'];
        $pageActions = '<a href="users.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i>Quay lại
        </a>';

        $roles = $userController->getRoles();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $userController->updateUser($id, $_POST);
            if ($result['success']) {
                redirectWithMessage('users.php', 'success', $result['message']);
            } else {
                $error = $result['message'];
            }
        }
        break;

    case 'delete':
        if (!$id) {
            header('Location: users.php');
            exit();
        }

        $result = $userController->deleteUser($id);
        redirectWithMessage('users.php', $result['success'] ? 'success' : 'error', $result['message']);
        break;

    case 'reset_password':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && isset($_POST['new_password'])) {
            $result = $userController->resetPassword($_POST['user_id'], $_POST['new_password']);
            echo json_encode($result);
            exit();
        }
        break;

    default: // list
        $pageTitle = 'Quản lý Users';
        $pageActions = '<a href="users.php?action=create" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Tạo User mới
        </a>';

        $users = $userController->getUsers();
        break;
}

include 'includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <!-- Users Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($users)): ?>
                <p class="text-muted text-center py-4">Chưa có user nào.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên đăng nhập</th>
                                <th>Họ tên</th>
                                <th>Email</th>
                                <th>Vai trò</th>
                                <th>Trạng thái</th>
                                <th>Ngày tạo</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $user['role_name']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo ($user['status'] === 'active') ? 'success' : 'danger'; ?>">
                                            <?php echo ($user['status'] === 'active') ? 'Hoạt động' : 'Khóa'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($user['created_at']); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="users.php?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary" title="Sửa">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if (hasRole('Admin')): ?>
                                                <button type="button" class="btn btn-sm btn-outline-warning" title="Đặt lại mật khẩu"
                                                        data-bs-toggle="modal" data-bs-target="#resetPasswordModal"
                                                        data-user-id="<?php echo (int)$user['id']; ?>"
                                                        data-user-name="<?php echo htmlspecialchars($user['fullname'], ENT_QUOTES); ?>">
                                                    <i class="bi bi-key"></i>
                                                </button>
                                                <?php if ($user['id'] != getCurrentUser()['id']): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Xóa"
                                                            onclick="deleteUser(<?php echo (int)$user['id']; ?>, <?php echo htmlspecialchars(json_encode($user['fullname']), ENT_QUOTES); ?>)">
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

                    <form method="POST" action="" id="userForm" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Tên đăng nhập <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="username" name="username"
                                       value="<?php echo $action === 'edit' ? htmlspecialchars($user['username']) : (isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''); ?>"
                                       pattern="[a-zA-Z0-9_]{3,50}"
                                       <?php echo $action === 'edit' ? 'readonly' : 'required'; ?>>
                                <?php if ($action === 'edit'): ?>
                                    <small class="text-muted">Không thể thay đổi tên đăng nhập</small>
                                <?php else: ?>
                                    <div class="invalid-feedback">Chỉ gồm chữ, số, dấu gạch dưới (3–50 ký tự).</div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="fullname" class="form-label">Họ tên <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="fullname" name="fullname"
                                       value="<?php echo $action === 'edit' ? htmlspecialchars($user['fullname']) : (isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''); ?>"
                                       minlength="2" required>
                                <div class="invalid-feedback">Họ tên phải có ít nhất 2 ký tự.</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo $action === 'edit' ? htmlspecialchars($user['email']) : (isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''); ?>" required>
                                <div class="invalid-feedback">Email không hợp lệ.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Số điện thoại</label>
                                <input type="tel" class="form-control" id="phone" name="phone"
                                       value="<?php echo $action === 'edit' ? htmlspecialchars($user['phone'] ?? '') : (isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''); ?>"
                                       pattern="^(0[3-9][0-9]{8}|[+]?[0-9\s\-]{7,15})$">
                                <div class="invalid-feedback">Số điện thoại không hợp lệ.</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="role_id" class="form-label">Vai trò <span class="text-danger">*</span></label>
                                <select class="form-select" id="role_id" name="role_id" required>
                                    <option value="">Chọn vai trò</option>
                                    <?php foreach ($roles as $role): ?>
                                        <?php if ($role['name'] !== 'Admin' || hasRole('Admin')): // Only Admin can assign Admin role ?>
                                            <option value="<?php echo $role['id']; ?>"
                                                    <?php echo ($action === 'edit' && $user['role_id'] == $role['id']) || (isset($_POST['role_id']) && $_POST['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                                <?php echo $role['name']; ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="is_active" class="form-label">Trạng thái</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?php echo ($action === 'edit' && $user['status'] === 'active') || (!isset($_POST['status']) || $_POST['status'] == 'active') ? 'selected' : ''; ?>>Hoạt động</option>
                                    <option value="inactive" <?php echo ($action === 'edit' && $user['status'] === 'inactive') || (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'selected' : ''; ?>>Khóa</option>
                                </select>
                            </div>
                        </div>

                        <?php if ($action === 'create'): ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password"
                                               minlength="6" required>
                                        <button type="button" class="btn btn-outline-secondary" id="togglePwdUser" tabindex="-1">
                                            <i class="bi bi-eye" id="togglePwdUserIcon"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">Mật khẩu phải có ít nhất 6 ký tự.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="password_confirm" class="form-label">Xác nhận mật khẩu <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                                    <div class="invalid-feedback">Mật khẩu xác nhận không khớp.</div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i><?php echo $action === 'create' ? 'Tạo User' : 'Cập nhật'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Đặt lại mật khẩu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="resetPasswordForm">
                    <input type="hidden" id="resetUserId" name="user_id">
                    <div class="mb-3">
                        <label for="newPassword" class="form-label">Mật khẩu mới</label>
                        <input type="password" class="form-control" id="newPassword" name="new_password" required>
                        <small class="text-muted">Mật khẩu phải có ít nhất 6 ký tự</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeResetModal()" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="submitResetPassword()">Đặt lại</button>
            </div>
        </div>
    </div>
</div>

<script>
// User form validation
(function() {
    const form = document.getElementById('userForm');
    if (!form) return;

    const isCreate = <?php echo json_encode($action === 'create'); ?>;

    function setInvalid(el, msg) {
        el.classList.remove('is-valid');
        el.classList.add('is-invalid');
        // find the .invalid-feedback sibling (may be inside input-group's parent)
        const fb = el.closest('.mb-3, .col-md-6')?.querySelector('.invalid-feedback');
        if (fb && msg) fb.textContent = msg;
    }
    function setValid(el) {
        el.classList.remove('is-invalid');
        el.classList.add('is-valid');
    }

    form.addEventListener('submit', function(e) {
        let valid = true;
        form.querySelectorAll('.form-control, .form-select').forEach(el => el.classList.remove('is-invalid','is-valid'));

        const fullname = form.querySelector('#fullname');
        if (!fullname.value.trim() || fullname.value.trim().length < 2) { setInvalid(fullname, 'Họ tên phải có ít nhất 2 ký tự.'); valid = false; } else setValid(fullname);

        const email = form.querySelector('#email');
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) { setInvalid(email, 'Email không hợp lệ.'); valid = false; } else setValid(email);

        const phone = form.querySelector('#phone');
        if (phone && phone.value.trim() && !/^(0[3-9][0-9]{8}|[+]?[0-9\s\-]{7,15})$/.test(phone.value.trim())) {
            setInvalid(phone, 'Số điện thoại không hợp lệ.'); valid = false;
        } else if (phone) setValid(phone);

        const role = form.querySelector('#role_id');
        if (role && !role.value) { setInvalid(role, 'Vui lòng chọn vai trò.'); valid = false; } else if (role) setValid(role);

        if (isCreate) {
            const username = form.querySelector('#username');
            if (username && !/^[a-zA-Z0-9_]{3,50}$/.test(username.value.trim())) {
                setInvalid(username, 'Chỉ gồm chữ, số, dấu gạch dưới (3–50 ký tự).'); valid = false;
            } else if (username) setValid(username);

            const pwd = form.querySelector('#password');
            const pwdC = form.querySelector('#password_confirm');
            if (!pwd.value || pwd.value.length < 6) { setInvalid(pwd, 'Mật khẩu phải có ít nhất 6 ký tự.'); valid = false; } else setValid(pwd);
            if (pwdC) {
                if (pwdC.value !== pwd.value) { setInvalid(pwdC, 'Mật khẩu xác nhận không khớp.'); valid = false; } else setValid(pwdC);
            }
        }

        if (!valid) e.preventDefault();
    });

    // Password confirm live check
    const pwdC = form.querySelector('#password_confirm');
    const pwd  = form.querySelector('#password');
    if (pwdC && pwd) {
        pwdC.addEventListener('input', function() {
            if (this.value && this.value !== pwd.value) setInvalid(this, 'Mật khẩu xác nhận không khớp.');
            else if (this.value) setValid(this);
        });
    }

    // Toggle password visibility
    document.getElementById('togglePwdUser')?.addEventListener('click', function() {
        const p = document.getElementById('password');
        const icon = document.getElementById('togglePwdUserIcon');
        p.type = p.type === 'password' ? 'text' : 'password';
        icon.className = p.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    });

    // Reset password modal validation
    const resetForm = document.getElementById('resetPasswordForm');
    if (resetForm) {
        resetForm.querySelector('#newPassword')?.addEventListener('input', function() {
            if (this.value.length > 0 && this.value.length < 6) {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            } else if (this.value.length >= 6) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
    }
})();

function deleteUser(id, name) {
    if (confirm('Bạn có chắc chắn muốn xóa user "' + name + '"?')) {
        window.location.href = 'users.php?action=delete&id=' + id;
    }
}

// Populate modal when it opens
document.getElementById('resetPasswordModal').addEventListener('show.bs.modal', function(event) {
    const btn = event.relatedTarget;
    document.getElementById('resetUserId').value = btn.getAttribute('data-user-id');
    document.getElementById('newPassword').value = '';
    document.getElementById('newPassword').classList.remove('is-valid','is-invalid');
    this.querySelector('.modal-title').textContent = 'Đặt lại mật khẩu cho ' + btn.getAttribute('data-user-name');
});

function closeResetModal() {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('resetPasswordModal')).hide();
}

function submitResetPassword() {
    const newPwd = document.getElementById('newPassword');
    if (!newPwd.value || newPwd.value.length < 6) {
        newPwd.classList.add('is-invalid');
        return;
    }
    const form = document.getElementById('resetPasswordForm');
    const formData = new FormData(form);

    fetch('users.php?action=reset_password', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeResetModal();
            // Show success message inline
            const alert = document.createElement('div');
            alert.className = 'alert alert-success mt-3';
            alert.textContent = 'Mật khẩu đã được đặt lại thành công!';
            document.querySelector('.card-body') && document.querySelector('.card-body').prepend(alert);
            setTimeout(() => alert.remove(), 3000);
        } else {
            alert('Có lỗi xảy ra: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Có lỗi xảy ra khi đặt lại mật khẩu.');
    });
}
</script>

<?php include 'includes/footer.php'; ?>