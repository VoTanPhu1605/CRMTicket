-- Migrations for existing database (run these if you already have the crmhelpdesk DB)
-- Generated: 2026-03-12

USE crmhelpdesk;

-- Add due_date column to tickets table (if not exists)
ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS due_date DATE DEFAULT NULL AFTER assigned_to;

-- Create ticket_activity_log table (if not exists)
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
