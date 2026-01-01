-- Truncate all tables in the ERP Project Management database
-- This will remove all existing data while preserving the table structure

USE erp_project_management;

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Truncate tables in correct order (child tables first, then parent tables)
TRUNCATE TABLE budget_expenses;
TRUNCATE TABLE task_assignments;
TRUNCATE TABLE resource_alerts;
TRUNCATE TABLE resource_utilization;
TRUNCATE TABLE project_budgets;
TRUNCATE TABLE tasks;
TRUNCATE TABLE projects;
TRUNCATE TABLE employees;
TRUNCATE TABLE equipment;
TRUNCATE TABLE budget_categories;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Reset auto-increment counters
ALTER TABLE budget_categories AUTO_INCREMENT = 1;
ALTER TABLE projects AUTO_INCREMENT = 1;
ALTER TABLE tasks AUTO_INCREMENT = 1;
ALTER TABLE employees AUTO_INCREMENT = 1;
ALTER TABLE equipment AUTO_INCREMENT = 1;
ALTER TABLE task_assignments AUTO_INCREMENT = 1;
ALTER TABLE resource_utilization AUTO_INCREMENT = 1;
ALTER TABLE resource_alerts AUTO_INCREMENT = 1;
ALTER TABLE project_budgets AUTO_INCREMENT = 1;
ALTER TABLE budget_expenses AUTO_INCREMENT = 1;

-- Output confirmation
SELECT 'All tables have been truncated successfully!' AS status;
