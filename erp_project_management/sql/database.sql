CREATE DATABASE erp_project_management;
USE erp_project_management;

CREATE TABLE projects (
    project_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATE,
    end_date DATE,
    budget DECIMAL(15,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE tasks (
    task_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATE,
    end_date DATE,
    dependency_task_id INT DEFAULT NULL,
    progress_percent INT DEFAULT 0,
    FOREIGN KEY (project_id) REFERENCES projects(project_id),
    FOREIGN KEY (dependency_task_id) REFERENCES tasks(task_id)
);

CREATE TABLE resources (
    resource_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    resource_type ENUM('Human', 'Equipment', 'Financial') NOT NULL,
    availability DECIMAL(5,2) DEFAULT 100.00, -- percentage availability
    cost_per_unit DECIMAL(10,2) DEFAULT 0.00
);

CREATE TABLE resource_allocations (
    allocation_id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    resource_id INT NOT NULL,
    allocation_percent DECIMAL(5,2) DEFAULT 0.00,
    FOREIGN KEY (task_id) REFERENCES tasks(task_id),
    FOREIGN KEY (resource_id) REFERENCES resources(resource_id)
);

CREATE TABLE budgets (
    budget_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    description VARCHAR(255),
    estimated_cost DECIMAL(15,2),
    actual_cost DECIMAL(15,2) DEFAULT 0.00,
    FOREIGN KEY (project_id) REFERENCES projects(project_id)
);

CREATE TABLE progress_reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    task_id INT,
    status ENUM('Not Started', 'In Progress', 'Completed', 'Delayed') DEFAULT 'Not Started',
    progress_percent INT DEFAULT 0,
    report_date DATE DEFAULT CURRENT_DATE,
    remarks TEXT,
    FOREIGN KEY (project_id) REFERENCES projects(project_id),
    FOREIGN KEY (task_id) REFERENCES tasks(task_id)
);

CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    message TEXT,
    type ENUM('Info', 'Warning', 'Error'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (project_id) REFERENCES projects(project_id)
);
