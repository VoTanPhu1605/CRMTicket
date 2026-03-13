<?php
require_once 'controllers/authController.php';

$authController = new AuthController();

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    global $authController;
    return $authController->isLoggedIn();
}

// Get current user info
function getCurrentUser() {
    global $authController;
    return $authController->getCurrentUser();
}

// Check if user has specific role
function hasRole($roleName) {
    global $authController;
    return $authController->hasRole($roleName);
}

// Check if user has any of the specified roles
function hasAnyRole($roles) {
    global $authController;
    return $authController->hasAnyRole($roles);
}

// Require login - redirect to login if not logged in
function requireLogin() {
    global $authController;
    $authController->requireLogin();
}

// Require specific role - redirect if not authorized
function requireRole($roleName) {
    global $authController;
    $authController->requireRole($roleName);
}

// Require any of the specified roles
function requireAnyRole($roles) {
    global $authController;
    $authController->requireAnyRole($roles);
}

// Logout function
function logout() {
    global $authController;
    return $authController->logout();
}

// Get user display name
function getUserDisplayName() {
    $user = getCurrentUser();
    return $user ? $user['fullname'] : 'Guest';
}

// Get user role display name
function getUserRoleDisplayName() {
    $user = getCurrentUser();
    return $user ? $user['role_name'] : '';
}
?>