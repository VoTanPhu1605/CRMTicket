-- Billing & Payment Migration
-- Run in phpMyAdmin: USE crmhelpdesk; then execute this file

USE crmhelpdesk;

-- Table 1: Service price templates (linked to categories)
CREATE TABLE IF NOT EXISTS service_templates (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    category_id  INT NOT NULL,
    name         VARCHAR(150) NOT NULL,
    description  TEXT DEFAULT NULL,
    price        DECIMAL(12,0) NOT NULL DEFAULT 0,
    is_active    TINYINT(1) NOT NULL DEFAULT 1,
    sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 2: Billing record per ticket (what is owed)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 3: Payment event log (actual payments)
CREATE TABLE IF NOT EXISTS ticket_payments (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id      INT NOT NULL,
    billing_id     INT NOT NULL,
    method         ENUM('cash','momo') NOT NULL,
    amount         DECIMAL(12,0) NOT NULL,
    status         ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
    momo_ref       VARCHAR(100) DEFAULT NULL,
    confirmed_by   INT DEFAULT NULL,
    confirmed_at   TIMESTAMP NULL DEFAULT NULL,
    created_by     INT NOT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id)    REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (billing_id)   REFERENCES ticket_billing(id) ON DELETE CASCADE,
    FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)   REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed sample service templates
-- Adjust category_id values to match your actual categories table
INSERT IGNORE INTO service_templates (category_id, name, price, sort_order)
SELECT id, 'Kiểm tra & vệ sinh máy tính', 150000, 1 FROM categories WHERE id=1 LIMIT 1;
INSERT IGNORE INTO service_templates (category_id, name, price, sort_order)
SELECT id, 'Cài đặt & cấu hình phần mềm', 200000, 2 FROM categories WHERE id=1 LIMIT 1;
INSERT IGNORE INTO service_templates (category_id, name, price, sort_order)
SELECT id, 'Sửa lỗi hệ điều hành Windows', 350000, 3 FROM categories WHERE id=1 LIMIT 1;
INSERT IGNORE INTO service_templates (category_id, name, price, sort_order)
SELECT id, 'Khôi phục tài khoản / đặt lại mật khẩu', 100000, 1 FROM categories WHERE id=2 LIMIT 1;
INSERT IGNORE INTO service_templates (category_id, name, price, sort_order)
SELECT id, 'Thiết lập bảo mật 2 lớp', 150000, 2 FROM categories WHERE id=2 LIMIT 1;
INSERT IGNORE INTO service_templates (category_id, name, price, sort_order)
SELECT id, 'Khắc phục lỗi kết nối mạng', 200000, 1 FROM categories WHERE id=3 LIMIT 1;
INSERT IGNORE INTO service_templates (category_id, name, price, sort_order)
SELECT id, 'Tư vấn & cải tiến quy trình IT', 500000, 1 FROM categories WHERE id=4 LIMIT 1;

-- Verify
SELECT 'service_templates' AS tbl, COUNT(*) AS rows FROM service_templates
UNION ALL
SELECT 'ticket_billing', COUNT(*) FROM ticket_billing
UNION ALL
SELECT 'ticket_payments', COUNT(*) FROM ticket_payments;
