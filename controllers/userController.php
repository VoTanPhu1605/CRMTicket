<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/authController.php';

class UserController {
    private $userModel;
    private $authController;

    public function __construct() {
        $this->userModel = new User();
        $this->authController = new AuthController();
    }

    public function createUser($data) {
        $this->authController->requireRole('Admin');

        $userData = [
            'username' => trim($data['username']),
            'password' => $data['password'],
            'fullname' => trim($data['fullname']),
            'email' => trim($data['email']),
            'phone' => trim($data['phone'] ?? ''),
            'role_id' => (int)$data['role_id'],
            'status'  => 'active',
        ];

        // Validate required fields
        if (empty($userData['username']) || empty($userData['password']) || empty($userData['fullname']) || empty($userData['email'])) {
            return ['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin bắt buộc.'];
        }

        // Validate password length
        if (strlen($userData['password']) < 6) {
            return ['success' => false, 'message' => 'Mật khẩu phải có ít nhất 6 ký tự.'];
        }

        // Validate username format
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $userData['username'])) {
            return ['success' => false, 'message' => 'Tên đăng nhập chỉ gồm chữ, số, dấu gạch dưới (3–50 ký tự).'];
        }

        // Validate username uniqueness
        if ($this->userModel->findByUsername($userData['username'])) {
            return ['success' => false, 'message' => 'Tên đăng nhập đã tồn tại.'];
        }

        // Validate email
        if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Email không hợp lệ.'];
        }

        // Check email uniqueness
        if ($this->userModel->findByEmail($userData['email'])) {
            return ['success' => false, 'message' => 'Email đã được sử dụng.'];
        }

        // Hash password
        $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);

        if ($this->userModel->create($userData)) {
            return ['success' => true, 'message' => 'Người dùng đã được tạo thành công.'];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra khi tạo người dùng.'];
    }

    public function getUsers() {
        $this->authController->requireRole('Admin');

        return $this->userModel->getAll();
    }

    public function getUser($id) {
        $this->authController->requireRole('Admin');

        return $this->userModel->findById($id);
    }

    public function updateUser($id, $data) {
        $this->authController->requireRole('Admin');

        $user = $this->userModel->findById($id);
        if (!$user) {
            return ['success' => false, 'message' => 'Người dùng không tồn tại.'];
        }

        $updateData = [];

        if (isset($data['fullname']) && !empty(trim($data['fullname']))) {
            $updateData['fullname'] = trim($data['fullname']);
        }
        if (isset($data['email'])) {
            $email = trim($data['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Email không hợp lệ.'];
            }
            $existing = $this->userModel->findByEmail($email);
            if ($existing && $existing['id'] != $id) {
                return ['success' => false, 'message' => 'Email đã được sử dụng.'];
            }
            $updateData['email'] = $email;
        }
        if (isset($data['phone'])) $updateData['phone'] = trim($data['phone']);
        if (isset($data['role_id'])) $updateData['role_id'] = (int)$data['role_id'];
        if (isset($data['status']) && in_array($data['status'], ['active', 'inactive'])) {
            $updateData['status'] = $data['status'];
        }

        // Only super admins can change passwords
        if ($this->authController->hasRole('Admin') && !empty($data['password'])) {
            if (strlen($data['password']) < 6) {
                return ['success' => false, 'message' => 'Mật khẩu phải có ít nhất 6 ký tự.'];
            }
            $updateData['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if ($this->userModel->update($id, $updateData)) {
            return ['success' => true, 'message' => 'Người dùng đã được cập nhật thành công.'];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra khi cập nhật người dùng.'];
    }

    public function deleteUser($id) {
        $this->authController->requireRole('Admin');

        $user = $this->userModel->findById($id);
        if (!$user) {
            return ['success' => false, 'message' => 'Người dùng không tồn tại.'];
        }

        // Prevent deleting self
        $currentUser = $this->authController->getCurrentUser();
        if ($currentUser['id'] == $id) {
            return ['success' => false, 'message' => 'Không thể xóa tài khoản của chính mình.'];
        }

        if ($this->userModel->delete($id)) {
            return ['success' => true, 'message' => 'Người dùng đã được xóa thành công.'];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra khi xóa người dùng.'];
    }

    public function resetPassword($id, $newPassword) {
        $this->authController->requireRole('Admin');

        $user = $this->userModel->findById($id);
        if (!$user) {
            return ['success' => false, 'message' => 'Người dùng không tồn tại.'];
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        if ($this->userModel->update($id, ['password' => $hashedPassword])) {
            return ['success' => true, 'message' => 'Mật khẩu đã được đặt lại thành công.'];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra khi đặt lại mật khẩu.'];
    }

    public function changePassword($currentPassword, $newPassword) {
        $this->authController->requireLogin();

        $currentUser = $this->authController->getCurrentUser();
        $user = $this->userModel->findById($currentUser['id']);

        if (!$user || !password_verify($currentPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Mật khẩu hiện tại không đúng.'];
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        if ($this->userModel->update($currentUser['id'], ['password' => $hashedPassword])) {
            return ['success' => true, 'message' => 'Mật khẩu đã được thay đổi thành công.'];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra khi thay đổi mật khẩu.'];
    }

    public function getAssignableUsers() {
        // Admin and Manager can get users list for ticket assignment
        $this->authController->requireAnyRole(['Admin', 'Manager']);
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT u.*, r.name AS role_name
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE r.name IN ('IT Helpdesk', 'IT Support', 'IT Intern', 'Manager')
              AND u.status = 'active'
            ORDER BY r.hierarchy_level ASC, u.fullname ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getOwnProfile() {
        $this->authController->requireLogin();
        $currentUser = $this->authController->getCurrentUser();
        return $this->userModel->findById($currentUser['id']);
    }

    public function updateAvatar($file) {
        $this->authController->requireLogin();

        $currentUser = $this->authController->getCurrentUser();

        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Lỗi khi upload file.'];
        }
        if (!in_array($file['type'], $allowedTypes)) {
            return ['success' => false, 'message' => 'Chỉ chấp nhận file ảnh JPG, PNG, GIF.'];
        }
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'File ảnh không được vượt quá 2MB.'];
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $currentUser['id'] . '_' . time() . '.' . $ext;
        $uploadPath = __DIR__ . '/../assets/avatars/' . $filename;

        // Delete old avatar if exists
        $user = $this->userModel->findById($currentUser['id']);
        if ($user['avatar']) {
            $oldPath = __DIR__ . '/../assets/avatars/' . $user['avatar'];
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return ['success' => false, 'message' => 'Không thể lưu file ảnh.'];
        }

        if ($this->userModel->update($currentUser['id'], ['avatar' => $filename])) {
            return ['success' => true, 'message' => 'Avatar đã được cập nhật thành công.', 'avatar' => $filename];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra khi cập nhật avatar.'];
    }

    public function getRoles() {
        return $this->userModel->getRoles();
    }
}
?>