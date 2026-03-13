<?php
require_once __DIR__ . '/../models/User.php';

class AuthController {
    private $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    public function login($username, $password) {
        $user = $this->userModel->findByUsername($username);

        $passwordOk = false;
        if ($user) {
            if (password_verify($password, $user['password'])) {
                $passwordOk = true;
            } elseif ($password === $user['password']) {
                // Plaintext fallback — auto-upgrade stored password to bcrypt
                $passwordOk = true;
                $this->userModel->update($user['id'], [
                    'password' => password_hash($password, PASSWORD_DEFAULT)
                ]);
            }
        }

        if ($passwordOk) {
            if ($user['status'] === 'inactive') {
                return 'locked';
            }

            // Start session if not already started
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }

            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];

            return true;
        }

        return false;
    }

    public function logout() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Clear all session variables
        $_SESSION = array();

        // Destroy the session
        session_destroy();

        return true;
    }

    public function isLoggedIn() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        return isset($_SESSION['user_id']);
    }

    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'fullname' => $_SESSION['fullname'],
            'role_id' => $_SESSION['role_id'],
            'role_name' => $_SESSION['role_name']
        ];
    }

    public function hasRole($roleName) {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }

        return $user['role_name'] === $roleName;
    }

    public function hasAnyRole($roles) {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }

        return in_array($user['role_name'], $roles);
    }

    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit();
        }

        // Check if user is still active
        $user = $this->userModel->findById($_SESSION['user_id']);
        if (!$user || $user['status'] === 'inactive') {
            $this->logout();
            header('Location: login.php?error=locked');
            exit();
        }
    }

    public function requireRole($roleName) {
        $this->requireLogin();

        if (!$this->hasRole($roleName)) {
            header('Location: unauthorized.php');
            exit();
        }
    }

    public function requireAnyRole($roles) {
        $this->requireLogin();

        if (!$this->hasAnyRole($roles)) {
            header('Location: unauthorized.php');
            exit();
        }
    }
}
?>