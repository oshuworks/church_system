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

CREATE TABLE budget_allocations (
    budget_allocation_id INT AUTO_INCREMENT PRIMARY KEY,
    budget_id INT NOT NULL,
    task_id INT NOT NULL,
    resource_id INT,
    allocated_amount DECIMAL(15,2) DEFAULT 0.00,
    spent_amount DECIMAL(15,2) DEFAULT 0.00,
    FOREIGN KEY (budget_id) REFERENCES budgets(budget_id),
    FOREIGN KEY (task_id) REFERENCES tasks(task_id),
    FOREIGN KEY (resource_id) REFERENCES resources(resource_id)
);

CREATE TABLE resource_notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    resource_id INT,
    project_id INT,
    notification_type ENUM('over_utilization', 'under_utilization', 'balancing_recommendation', 'system_alert') NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resource_id) REFERENCES resources(resource_id),
    FOREIGN KEY (project_id) REFERENCES projects(project_id)
);




INSERT INTO projects (name, description, start_date, end_date, budget) VALUES
('Youth Leadership Development Program', 'A training project aimed at equipping KKB members with leadership and organizational skills for church and community service.', '2025-01-10', '2025-03-30', 50000.00),
('Community Outreach and Evangelism Drive', 'Monthly outreach activities organized by KKB to engage youth and local residents through evangelism, feeding, and volunteer programs.', '2025-02-01', '2025-06-01', 80000.00),
('KKB Worship Night 2025', 'An annual event gathering youth from nearby extensions for worship, fellowship, and talent showcases.', '2025-04-15', '2025-04-20', 60000.00),
('KKB Digital Ministry Upgrade', 'A project to enhance KKB online presence, including website development, multimedia equipment procurement, and livestream setup.', '2025-05-01', '2025-08-30', 120000.00);


INSERT INTO tasks (project_id, name, description, start_date, end_date, dependency_task_id, progress_percent) VALUES
(1, 'Recruitment and Orientation', 'Identify and onboard new KKB youth leaders.', '2025-01-10', '2025-01-20', NULL, 100),
(1, 'Leadership Workshops', 'Conduct training sessions on servant leadership and project management.', '2025-01-21', '2025-03-15', 1, 85),
(2, 'Barangay Evangelism', 'Coordinate outreach activities in nearby barangays.', '2025-02-01', '2025-04-30', NULL, 70),
(2, 'Feeding Program', 'Organize feeding drives for children and families.', '2025-03-01', '2025-05-30', 3, 60),
(3, 'Event Planning', 'Plan and coordinate the worship night logistics and program flow.', '2025-04-01', '2025-04-10', NULL, 100),
(3, 'Stage and Technical Setup', 'Set up sound, lights, and multimedia equipment.', '2025-04-11', '2025-04-14', 5, 90),
(4, 'Website Development', 'Develop the official KKB extension website.', '2025-05-01', '2025-06-30', NULL, 50),
(4, 'Livestream Setup', 'Set up new livestream system and test with ministry events.', '2025-07-01', '2025-08-15', 7, 40);


INSERT INTO resources (name, resource_type, availability, cost_per_unit) VALUES
('John Dela Cruz', 'Human', 100.00, 0.00),
('Maria Santos', 'Human', 80.00, 0.00),
('Sound System Set', 'Equipment', 60.00, 15000.00),
('Multimedia Laptop', 'Equipment', 50.00, 45000.00),
('Projector and Screen', 'Equipment', 70.00, 25000.00),
('Youth Ministry Budget Fund', 'Financial', 100.00, 50000.00);


INSERT INTO resource_allocations (task_id, resource_id, allocation_percent) VALUES
(1, 1, 50.00),
(2, 2, 60.00),
(3, 1, 40.00),
(4, 6, 80.00),
(5, 2, 50.00),
(6, 3, 70.00),
(7, 4, 60.00),
(8, 5, 70.00);


INSERT INTO budgets (project_id, description, estimated_cost, actual_cost) VALUES
(1, 'Training materials, meals, and venue rental', 50000.00, 48000.00),
(2, 'Outreach materials and transportation', 80000.00, 75500.00),
(3, 'Sound system, stage decor, and lighting', 60000.00, 61000.00),
(4, 'Website, streaming setup, and software licenses', 120000.00, 118500.00);


INSERT INTO progress_reports (project_id, task_id, status, progress_percent, report_date, remarks) VALUES
(1, 1, 'Completed', 100, '2025-01-25', 'All participants successfully oriented.'),
(1, 2, 'In Progress', 85, '2025-03-10', 'Workshop ongoing; strong attendance.'),
(2, 3, 'In Progress', 70, '2025-04-15', 'Barangay outreach conducted successfully.'),
(2, 4, 'In Progress', 60, '2025-05-20', 'Feeding program awaiting more volunteers.'),
(3, 5, 'Completed', 100, '2025-04-13', 'Program flow finalized and approved.'),
(3, 6, 'Completed', 90, '2025-04-18', 'Setup completed ahead of schedule.'),
(4, 7, 'In Progress', 50, '2025-07-01', 'Website 50% done with functional pages.'),
(4, 8, 'In Progress', 40, '2025-08-10', 'Livestream testing ongoing.');



INSERT INTO budget_allocations (budget_id, task_id, resource_id, allocated_amount, spent_amount) VALUES
(1, 1, 6, 15000.00, 14800.00),
(1, 2, 6, 35000.00, 33200.00),
(2, 3, 6, 20000.00, 19500.00),
(2, 4, 6, 60000.00, 56000.00),
(3, 5, 6, 10000.00, 10000.00),
(3, 6, 3, 30000.00, 29000.00),
(3, 6, 4, 20000.00, 22000.00),
(4, 7, 4, 50000.00, 48500.00),
(4, 8, 3, 20000.00, 19500.00),
(4, 8, 5, 50000.00, 50500.00);