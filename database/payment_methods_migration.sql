-- Migration: Add new payment methods (bank_transfer, visa, zalopay, vnpay)
-- Run in phpMyAdmin: USE crmhelpdesk; then execute this file

USE crmhelpdesk;

ALTER TABLE ticket_billing
    MODIFY COLUMN payment_method
        ENUM('cash','momo','bank_transfer','visa','zalopay','vnpay')
        DEFAULT NULL;

ALTER TABLE ticket_payments
    MODIFY COLUMN method
        ENUM('cash','momo','bank_transfer','visa','zalopay','vnpay')
        NOT NULL;

-- Verify
SELECT 'ticket_billing columns' AS info;
DESCRIBE ticket_billing;
SELECT 'ticket_payments columns' AS info;
DESCRIBE ticket_payments;
