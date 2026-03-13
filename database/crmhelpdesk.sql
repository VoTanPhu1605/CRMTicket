-- Database: crmhelpdesk
-- Generated on: 2026-03-10

CREATE DATABASE IF NOT EXISTS crmhelpdesk;
USE crmhelpdesk;

-- Table: roles
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

-- Insert default roles
-- Super Admin: full access including user management
-- Admin: IT staff, ticket management only
-- Manager: ticket assignment + reports
-- Agent: handle assigned tickets
-- Viewer: read only
INSERT INTO roles (name) VALUES ('Super Admin'), ('Admin'), ('Manager'), ('Agent'), ('Viewer');

-- Table: users
CREATE TABLE users (
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

-- Insert default super admin user (password: admin123)
-- role_id 1 = Super Admin
INSERT INTO users (username, password, fullname, email, phone, role_id, status) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@crmhelpdesk.com', '0123456789', 1, 'active');

-- Table: categories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

-- Insert default categories
INSERT INTO categories (name) VALUES ('Kỹ thuật'), ('Tài khoản'), ('Thanh toán'), ('Cải tiến');

-- Table: priorities
CREATE TABLE priorities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(20) NOT NULL
);

-- Insert default priorities
INSERT INTO priorities (name, color) VALUES
('Cao', '#dc2626'),
('Trung bình', '#ea580c'),
('Thấp', '#6b7280');

-- Table: statuses
CREATE TABLE statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(20) NOT NULL
);

-- Insert default statuses
INSERT INTO statuses (name, color) VALUES
('Mở', '#fef3c7'),
('Đang xử lý', '#dbeafe'),
('Đã đóng', '#d1fae5');

-- Table: tickets
CREATE TABLE tickets (
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (priority_id) REFERENCES priorities(id) ON DELETE CASCADE,
    FOREIGN KEY (status_id) REFERENCES statuses(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert sample tickets
INSERT INTO tickets (title, description, customer_name, customer_email, customer_phone, category_id, priority_id, status_id, assigned_to, created_by) VALUES
('Vấn đề đăng nhập', 'Người dùng không thể đăng nhập vào hệ thống. Thông báo lỗi xuất hiện khi nhập thông tin đăng nhập.', 'John Smith', 'john@example.com', '+1234567890', 1, 1, 1, NULL, 1),
('Đặt lại mật khẩu', 'Khách hàng yêu cầu đặt lại mật khẩu tài khoản.', 'Jane Doe', 'jane@example.com', '+1234567891', 2, 2, 2, 1, 1),
('Yêu cầu tính năng', 'Khách hàng đề xuất thêm tính năng mới vào hệ thống.', 'Bob Johnson', 'bob@example.com', '+1234567892', 4, 3, 3, NULL, 1),
('Vấn đề thanh toán', 'Khách hàng gặp vấn đề khi thanh toán hóa đơn.', 'Alice Brown', 'alice@example.com', '+1234567893', 3, 1, 1, NULL, 1),
('Lỗi hệ thống', 'Hệ thống báo lỗi khi thực hiện thao tác.', 'Charlie Wilson', 'charlie@example.com', '+1234567894', 1, 2, 2, 1, 1);

-- Table: ticket_notes
CREATE TABLE ticket_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert sample notes
INSERT INTO ticket_notes (ticket_id, user_id, note) VALUES
(1, 1, 'Đã nhận được yêu cầu. Đang điều tra vấn đề.'),
(2, 1, 'Đã gửi email hướng dẫn đặt lại mật khẩu.'),
(5, 1, 'Đang kiểm tra logs hệ thống.');

-- Table: ticket_activity_log
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
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);