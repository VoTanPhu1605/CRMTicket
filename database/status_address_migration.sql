-- Migration: Add "Đang chờ" status + customer_address field

-- 1. Add "Đang chờ" status (id will be auto-assigned, likely 4)
INSERT IGNORE INTO statuses (name, color) VALUES ('Đang chờ', '#fde68a');

-- 2. Add customer_address column to tickets
ALTER TABLE tickets ADD COLUMN customer_address VARCHAR(255) NULL AFTER customer_phone;
