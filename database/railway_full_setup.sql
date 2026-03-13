-- ============================================================
-- FULL SETUP FOR RAILWAY (database: railway)
-- Paste this entire script into Railway MySQL Query tab
-- ============================================================

-- CORE TABLES --

SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    hierarchy_level TINYINT UNSIGNED NOT NULL DEFAULT 99
);

INSERT IGNORE INTO roles (id, name, hierarchy_level) VALUES
(1, 'IT Helpdesk', 3),
(2, 'Manager',     2),
(3, 'IT Support',  4),
(4, 'IT Intern',   5),
(5, 'Admin',       1);

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    avatar VARCHAR(255) DEFAULT NULL,
    role_id INT NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

-- Default users (password: password for all)
DELETE FROM users WHERE username IN ('admin','manager','agent','viewer');
INSERT INTO users (username, password, fullname, email, phone, role_id, status) VALUES
('admin',   '$2y$10$62mge3TdsGN/DxDe0oAYB.VquDHY9e0jLO6BrTI6tZjLHLMBeJzum', 'Administrator', 'admin@crmhelpdesk.com',   '0123456789', 5, 'active'),
('manager', '$2y$10$62mge3TdsGN/DxDe0oAYB.VquDHY9e0jLO6BrTI6tZjLHLMBeJzum', 'Manager',       'manager@crmhelpdesk.com', '0123456788', 2, 'active'),
('agent',   '$2y$10$62mge3TdsGN/DxDe0oAYB.VquDHY9e0jLO6BrTI6tZjLHLMBeJzum', 'Agent',         'agent@crmhelpdesk.com',   '0123456787', 3, 'active'),
('viewer',  '$2y$10$62mge3TdsGN/DxDe0oAYB.VquDHY9e0jLO6BrTI6tZjLHLMBeJzum', 'Viewer',        'viewer@crmhelpdesk.com',  '0123456786', 4, 'active');

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    category_type ENUM('service','warranty','other') NOT NULL DEFAULT 'service',
    warranty_months TINYINT UNSIGNED NOT NULL DEFAULT 0
);
INSERT IGNORE INTO categories (id, name, category_type, warranty_months) VALUES
(1,'Kỹ thuật','service',0),(2,'Tài khoản','service',0),(3,'Thanh toán','service',0),(4,'Cải tiến','other',0);

CREATE TABLE IF NOT EXISTS priorities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(20) NOT NULL
);
INSERT IGNORE INTO priorities (name, color) VALUES ('Cao','#dc2626'),('Trung bình','#ea580c'),('Thấp','#6b7280');

CREATE TABLE IF NOT EXISTS statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(20) NOT NULL
);
INSERT IGNORE INTO statuses (name, color) VALUES ('Mở','#fef3c7'),('Đang xử lý','#dbeafe'),('Đã đóng','#d1fae5');

CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20),
    category_id INT NOT NULL,
    priority_id INT NOT NULL,
    status_id INT NOT NULL,
    assigned_to INT,
    created_by INT NOT NULL,
    due_date DATE DEFAULT NULL,
    warranty_end_date DATE DEFAULT NULL,
    warranty_claim_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (priority_id) REFERENCES priorities(id) ON DELETE CASCADE,
    FOREIGN KEY (status_id)   REFERENCES statuses(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ticket_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ticket_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT,
    action_type ENUM('create','update','note','status_change','assign') NOT NULL,
    description VARCHAR(255) NOT NULL,
    old_value TEXT DEFAULT NULL,
    new_value TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id) ON DELETE SET NULL
);

-- BILLING --

CREATE TABLE IF NOT EXISTS service_templates (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name        VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    price       DECIMAL(12,0) NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO service_templates (category_id, name, price, sort_order) VALUES
(1,'Kiểm tra & vệ sinh máy tính',150000,1),
(1,'Cài đặt & cấu hình phần mềm',200000,2),
(1,'Sửa lỗi hệ điều hành Windows',350000,3),
(2,'Khôi phục tài khoản / đặt lại mật khẩu',100000,1),
(2,'Thiết lập bảo mật 2 lớp',150000,2),
(3,'Khắc phục lỗi kết nối mạng',200000,1),
(4,'Tư vấn & cải tiến quy trình IT',500000,1);

CREATE TABLE IF NOT EXISTS ticket_billing (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id           INT NOT NULL UNIQUE,
    service_template_id INT DEFAULT NULL,
    price               DECIMAL(12,0) NOT NULL DEFAULT 0,
    payment_status      ENUM('unpaid','pending','paid','waived') NOT NULL DEFAULT 'unpaid',
    payment_method      ENUM('cash','momo') DEFAULT NULL,
    note                VARCHAR(255) DEFAULT NULL,
    created_by          INT NOT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id)           REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (service_template_id) REFERENCES service_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)          REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ticket_payments (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id    INT NOT NULL,
    billing_id   INT NOT NULL,
    method       ENUM('cash','momo') NOT NULL,
    amount       DECIMAL(12,0) NOT NULL,
    status       ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
    momo_ref     VARCHAR(100) DEFAULT NULL,
    confirmed_by INT DEFAULT NULL,
    confirmed_at TIMESTAMP NULL DEFAULT NULL,
    created_by   INT NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id)    REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (billing_id)   REFERENCES ticket_billing(id) ON DELETE CASCADE,
    FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)   REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CHAT --

CREATE TABLE IF NOT EXISTS chat_rooms (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('general','direct','group') NOT NULL DEFAULT 'general',
    name VARCHAR(100) NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
INSERT IGNORE INTO chat_rooms (id, type, name) VALUES (1, 'general', 'General');

CREATE TABLE IF NOT EXISTS chat_members (
    room_id      INT NOT NULL,
    user_id      INT NOT NULL,
    last_read_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (room_id, user_id),
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS chat_messages (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    room_id    INT NOT NULL,
    sender_id  INT NOT NULL,
    message    TEXT NOT NULL,
    msg_type   ENUM('text','sticker') NOT NULL DEFAULT 'text',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id)   REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

-- VERIFY --
SELECT table_name, table_rows FROM information_schema.tables
WHERE table_schema = DATABASE()
ORDER BY table_name;
