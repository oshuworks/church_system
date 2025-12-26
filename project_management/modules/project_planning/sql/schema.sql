-- Project Planning and Scheduling Module - Database Schema
-- This file contains only the tables required for Project Planning and Scheduling

-- Projects Table
CREATE TABLE IF NOT EXISTS projects (
    project_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATE,
    end_date DATE,
    budget DECIMAL(15,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tasks Table (Work Breakdown Structure)
CREATE TABLE IF NOT EXISTS tasks (
    task_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATE,
    end_date DATE,
    dependency_task_id INT DEFAULT NULL,
    progress_percent INT DEFAULT 0,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    FOREIGN KEY (dependency_task_id) REFERENCES tasks(task_id) ON DELETE SET NULL
);

-- Sample Data (Optional)
-- INSERT INTO projects (name, description, start_date, end_date, budget) VALUES
-- ('Sample Project', 'A sample project for testing', '2025-01-01', '2025-12-31', 100000.00);

-- INSERT INTO tasks (project_id, name, description, start_date, end_date, dependency_task_id, progress_percent) VALUES
-- (1, 'Task 1', 'First task', '2025-01-01', '2025-01-15', NULL, 0),
-- (1, 'Task 2', 'Second task depends on Task 1', '2025-01-16', '2025-01-30', 1, 0);

