-- Role restructuring migration
-- Run this in phpMyAdmin or MySQL command line

USE crmhelpdesk;

-- Step 1: Add hierarchy_level column to roles table
ALTER TABLE roles ADD COLUMN IF NOT EXISTS hierarchy_level TINYINT UNSIGNED NOT NULL DEFAULT 99;

-- Step 2: Rename roles based on ACTUAL current DB state
-- Current:  id=5 Super Admin (admin user), id=2 Manager, id=1 Admin (unused), id=3 Agent, id=4 Viewer
UPDATE roles SET name='Admin',       hierarchy_level=1 WHERE id=5; -- Super Admin → Admin (top role)
UPDATE roles SET name='Manager',     hierarchy_level=2 WHERE id=2; -- Manager stays
UPDATE roles SET name='IT Helpdesk', hierarchy_level=3 WHERE id=1; -- old Admin (IT staff) → IT Helpdesk
UPDATE roles SET name='IT Support',  hierarchy_level=4 WHERE id=3; -- Agent → IT Support
UPDATE roles SET name='IT Intern',   hierarchy_level=5 WHERE id=4; -- Viewer → IT Intern

-- Verify the changes
SELECT id, name, hierarchy_level FROM roles ORDER BY hierarchy_level;
