<?php
// Utility functions for the CRM system

// Sanitize input data
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Format date for display
function formatDate($date, $format = 'd/m/Y H:i') {
    if (empty($date) || $date === '0000-00-00 00:00:00') {
        return '';
    }
    return date($format, strtotime($date));
}

// Format date for input fields
function formatDateForInput($date) {
    if (empty($date) || $date === '0000-00-00') {
        return '';
    }
    return date('Y-m-d', strtotime($date));
}

// Generate random password

// Validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validate phone number (Vietnamese format)
function isValidPhone($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    return preg_match('/^(0[3|5|7|8|9])+([0-9]{8})$/', $phone);
}

// Get priority color class
function getPriorityColorClass($priorityName) {
    $colors = [
        'Thấp' => 'success',
        'Trung bình' => 'warning',
        'Cao' => 'danger',
        'Khẩn cấp' => 'dark'
    ];
    return $colors[$priorityName] ?? 'secondary';
}

// Get status color class
function getStatusColorClass($statusName) {
    $colors = [
        'Mở'        => 'secondary',
        'Đang chờ'  => 'secondary',
        'Đang xử lý'=> 'primary',
        'Đã đóng'   => 'success',
        'Đã hủy'    => 'danger'
    ];
    return $colors[$statusName] ?? 'secondary';
}

// Truncate text
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length - strlen($suffix)) . $suffix;
}

// Generate pagination links
function generatePagination($currentPage, $totalPages, $baseUrl = '', $params = []) {
    if ($totalPages <= 1) {
        return '';
    }

    $pagination = '<nav><ul class="pagination justify-content-center">';

    // Previous button
    if ($currentPage > 1) {
        $prevUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $currentPage - 1]));
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $prevUrl . '">Trước</a></li>';
    } else {
        $pagination .= '<li class="page-item disabled"><span class="page-link">Trước</span></li>';
    }

    // Page numbers
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);

    if ($startPage > 1) {
        $firstUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => 1]));
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $firstUrl . '">1</a></li>';
        if ($startPage > 2) {
            $pagination .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $currentPage) {
            $pagination .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $pageUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $i]));
            $pagination .= '<li class="page-item"><a class="page-link" href="' . $pageUrl . '">' . $i . '</a></li>';
        }
    }

    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $pagination .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $lastUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $totalPages]));
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $lastUrl . '">' . $totalPages . '</a></li>';
    }

    // Next button
    if ($currentPage < $totalPages) {
        $nextUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $currentPage + 1]));
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $nextUrl . '">Sau</a></li>';
    } else {
        $pagination .= '<li class="page-item disabled"><span class="page-link">Sau</span></li>';
    }

    $pagination .= '</ul></nav>';

    return $pagination;
}

// Check if a ticket's due_date has passed
function isOverdue($dueDate, $statusName = '') {
    if (empty($dueDate) || $dueDate === '0000-00-00') return false;
    if ($statusName === 'Đã đóng') return false;
    return strtotime($dueDate) < strtotime(date('Y-m-d'));
}

// Role hierarchy - lower number = higher authority
function getRoleLevel($roleName) {
    $levels = [
        'Admin'       => 1,
        'Manager'     => 2,
        'IT Helpdesk' => 3,
        'IT Support'  => 4,
        'IT Intern'   => 5,
    ];
    return $levels[$roleName] ?? 99;
}

// Check if current user can assign to a target role
function canAssignToRole($currentRoleName, $targetRoleName) {
    return getRoleLevel($currentRoleName) < getRoleLevel($targetRoleName);
}

// Check if current user role can transfer to target role
function canTransferToRole($currentRoleName, $targetRoleName) {
    $target  = getRoleLevel($targetRoleName);
    // Admin can transfer to anyone (Manager=2 or lower authority)
    // Manager can only transfer to IT staff (level 3+)
    if ($currentRoleName === 'Admin')   return $target >= 2;
    if ($currentRoleName === 'Manager') return $target >= 3;
    return false;
}

// Flash messages
function setFlashMessage($type, $message) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
}

function getFlashMessages() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

function displayFlashMessages() {
    $messages = getFlashMessages();
    $allowed = ['success', 'error', 'warning', 'info'];
    foreach ($messages as $message) {
        $type = in_array($message['type'], $allowed) ? $message['type'] : 'info';
        $alertClass = $type === 'error' ? 'danger' : $type;
        $icon = ['success' => 'check-circle-fill', 'error' => 'exclamation-circle-fill',
                 'warning' => 'exclamation-triangle-fill', 'info' => 'info-circle-fill'][$type];
        echo '<div class="alert alert-' . $alertClass . ' alert-dismissible fade show" role="alert">';
        echo '<i class="bi bi-' . $icon . '"></i> ';
        echo htmlspecialchars($message['message'], ENT_QUOTES, 'UTF-8');
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}

// CSRF protection
function generateCSRFToken() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Redirect with message
function redirectWithMessage($url, $type, $message) {
    setFlashMessage($type, $message);
    header('Location: ' . $url);
    exit();
}

// Get base URL
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    $path = str_replace(basename($script), '', $script);

    return $protocol . '://' . $host . $path;
}
?>