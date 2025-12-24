CREATE DATABASE IF NOT EXISTS erp_project_management;
USE erp_project_management;

-- Projects Table (for multi-project resource management)
CREATE TABLE projects (
    project_id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(255) NOT NULL,
    project_description TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('planning', 'active', 'on_hold', 'completed', 'cancelled') DEFAULT 'planning',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    budget_allocated DECIMAL(15,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tasks Table (for detailed task management)
CREATE TABLE tasks (
    task_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    task_name VARCHAR(255) NOT NULL,
    task_description TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    estimated_hours DECIMAL(8,2) DEFAULT 0.00,
    actual_hours DECIMAL(8,2) DEFAULT 0.00,
    status ENUM('not_started', 'in_progress', 'completed', 'blocked', 'cancelled') DEFAULT 'not_started',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    progress_percentage DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE
);

-- Employees Table (for human resources)
CREATE TABLE employees (
    employee_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    role VARCHAR(100) NOT NULL,
    department VARCHAR(100),
    skills TEXT,
    hourly_rate DECIMAL(10,2) DEFAULT 0.00,
    max_work_hours_per_day DECIMAL(4,2) DEFAULT 8.00,
    current_workload DECIMAL(5,2) DEFAULT 0.00,
    utilization_percentage DECIMAL(5,2) DEFAULT 0.00,
    status ENUM('active', 'inactive', 'on_leave') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Equipment Table (for equipment resources)
CREATE TABLE equipment (
    equipment_id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_name VARCHAR(255) NOT NULL,
    equipment_type VARCHAR(100) NOT NULL,
    description TEXT,
    hourly_rate DECIMAL(10,2) DEFAULT 0.00,
    maintenance_cost DECIMAL(10,2) DEFAULT 0.00,
    total_capacity_hours DECIMAL(8,2) DEFAULT 0.00,
    used_hours DECIMAL(8,2) DEFAULT 0.00,
    utilization_percentage DECIMAL(5,2) DEFAULT 0.00,
    status ENUM('available', 'in_use', 'maintenance', 'reserved', 'retired') DEFAULT 'available',
    location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Task Assignments Table (linking tasks to resources)
CREATE TABLE task_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    project_id INT NOT NULL,
    assignment_type ENUM('employee', 'equipment', 'budget') NOT NULL,
    resource_id INT NOT NULL,
    allocation_percentage DECIMAL(5,2) DEFAULT 100.00,
    allocated_hours DECIMAL(8,2) DEFAULT 0.00,
    actual_hours_used DECIMAL(8,2) DEFAULT 0.00,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'assigned',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE
);

-- Resource Utilization Monitoring Table
CREATE TABLE resource_utilization (
    utilization_id INT AUTO_INCREMENT PRIMARY KEY,
    resource_type ENUM('employee', 'equipment') NOT NULL,
    resource_id INT NOT NULL,
    project_id INT,
    date DATE NOT NULL,
    total_hours_available DECIMAL(8,2) NOT NULL,
    hours_used DECIMAL(8,2) DEFAULT 0.00,
    utilization_percentage DECIMAL(5,2) DEFAULT 0.00,
    workload_level ENUM('underutilized', 'optimal', 'overloaded', 'critical') GENERATED ALWAYS AS (
        CASE 
            WHEN utilization_percentage < 60 THEN 'underutilized'
            WHEN utilization_percentage >= 60 AND utilization_percentage <= 85 THEN 'optimal'
            WHEN utilization_percentage > 85 AND utilization_percentage <= 100 THEN 'overloaded'
            WHEN utilization_percentage > 100 THEN 'critical'
        END
    ) STORED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_resource_date (resource_type, resource_id, date),
    INDEX idx_project_date (project_id, date)
);

-- Resource Allocation Alerts Table
CREATE TABLE resource_alerts (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    alert_type ENUM('overallocation', 'underutilization', 'conflict', 'capacity_warning') NOT NULL,
    resource_type ENUM('employee', 'equipment', 'budget') NOT NULL,
    resource_id INT NOT NULL,
    project_id INT,
    task_id INT,
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    message TEXT NOT NULL,
    threshold_value DECIMAL(10,2),
    current_value DECIMAL(10,2),
    status ENUM('active', 'acknowledged', 'resolved') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    INDEX idx_resource_status (resource_type, resource_id, status),
    INDEX idx_severity_status (severity, status)
);

-- Budget Categories Table
CREATE TABLE budget_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    description TEXT,
    parent_category_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_category_id) REFERENCES budget_categories(category_id)
);

-- Project Budget Table
CREATE TABLE project_budgets (
    budget_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    category_id INT NOT NULL,
    allocated_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    spent_amount DECIMAL(15,2) DEFAULT 0.00,
    remaining_amount DECIMAL(15,2) GENERATED ALWAYS AS (allocated_amount - spent_amount) STORED,
    fiscal_year VARCHAR(10),
    status ENUM('active', 'frozen', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES budget_categories(category_id)
);

-- Budget Expenses Table
CREATE TABLE budget_expenses (
    expense_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    budget_id INT NOT NULL,
    task_id INT,
    expense_description VARCHAR(255) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    expense_date DATE NOT NULL,
    expense_type ENUM('labor', 'equipment', 'materials', 'services', 'other') NOT NULL,
    approved_by VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    FOREIGN KEY (budget_id) REFERENCES project_budgets(budget_id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE SET NULL
);



-- Insert Budget Categories
INSERT INTO budget_categories (category_name, description, parent_category_id) VALUES
('Ministry Programs', 'Core youth ministry programs and activities', NULL),
('Events', 'Special events and gatherings', NULL),
('Outreach', 'Community outreach and evangelism', NULL),
('Training & Development', 'Leadership training and skill development', NULL),
('Operations', 'Administrative and operational expenses', NULL),
('Sunday School', 'Sunday school teaching materials and supplies', 1),
('Youth Fellowship', 'Regular youth fellowship meetings', 1),
('Bible Study', 'Bible study materials and resources', 1),
('Worship Services', 'Youth-led worship services', 1),
('Summer Camp', 'Annual youth summer camp program', 2),
('Christmas Concert', 'Christmas outreach concert', 2),
('Youth Conference', 'Regional youth conference attendance', 2),
('Community Service', 'Local community service projects', 3),
('Mission Trips', 'Local and international mission trips', 3),
('Feeding Program', 'Community feeding program', 3),
('Leadership Training', 'Youth leader development program', 4),
('Skills Workshop', 'Practical skills workshops', 4),
('Bible College Support', 'Support for youth attending bible college', 4),
('Office Supplies', 'General office supplies', 5),
('Transportation', 'Vehicle and transportation costs', 5),
('Communication', 'Phone, internet, and communication costs', 5);

-- Insert Projects
INSERT INTO projects (project_name, project_description, start_date, end_date, status, priority, budget_allocated) VALUES
('Summer Camp 2024', 'Annual youth summer camp with spiritual growth activities', '2024-04-01', '2024-05-31', 'active', 'high', 150000.00),
('Leadership Training Program', 'Quarterly leadership training for youth leaders', '2024-01-15', '2024-12-15', 'active', 'high', 50000.00),
('Community Feeding Program', 'Weekly community feeding program', '2024-01-01', '2024-12-31', 'active', 'high', 200000.00),
('Christmas Concert', 'Christmas outreach concert event', '2024-10-01', '2024-12-25', 'planning', 'medium', 100000.00),
('Bible Study Series', 'Monthly Bible study sessions', '2024-01-01', '2024-12-31', 'active', 'medium', 30000.00),
('Youth Fellowship Events', 'Regular monthly fellowship activities', '2024-01-01', '2024-12-31', 'active', 'medium', 80000.00);

-- Insert Tasks
INSERT INTO tasks (project_id, task_name, task_description, start_date, end_date, estimated_hours, actual_hours, status, priority, progress_percentage) VALUES
-- Summer Camp Tasks
(1, 'Camp Site Booking', 'Reserve and pay for camp venue', '2024-04-01', '2024-04-15', 20, 20, 'completed', 'high', 100.00),
(1, 'Speaker Coordination', 'Coordinate with guest speakers', '2024-04-10', '2024-04-30', 30, 25, 'in_progress', 'high', 83.33),
(1, 'Food Catering', 'Arrange meals for camp participants', '2024-04-20', '2024-05-10', 40, 30, 'in_progress', 'medium', 75.00),
(1, 'Transportation Logistics', 'Arrange transportation', '2024-05-01', '2024-05-20', 25, 10, 'in_progress', 'medium', 40.00),

-- Leadership Training Tasks
(2, 'Curriculum Development', 'Develop training curriculum', '2024-01-15', '2024-02-15', 60, 60, 'completed', 'high', 100.00),
(2, 'Trainer Recruitment', 'Recruit volunteer trainers', '2024-02-01', '2024-02-28', 20, 20, 'completed', 'medium', 100.00),
(2, 'Training Materials', 'Prepare training manuals', '2024-02-15', '2024-03-15', 40, 35, 'in_progress', 'medium', 87.50),

-- Community Feeding Tasks
(3, 'Beneficiary Identification', 'Identify families needing assistance', '2024-01-01', '2024-01-31', 30, 30, 'completed', 'high', 100.00),
(3, 'Food Procurement', 'Purchase food supplies', '2024-01-15', '2024-12-15', 100, 80, 'in_progress', 'high', 80.00),
(3, 'Volunteer Coordination', 'Schedule and coordinate volunteers', '2024-01-01', '2024-12-31', 80, 60, 'in_progress', 'medium', 75.00),

-- Christmas Concert Tasks
(4, 'Venue Booking', 'Reserve concert venue', '2024-10-01', '2024-10-15', 25, 15, 'in_progress', 'high', 60.00),
(4, 'Artist Coordination', 'Coordinate with performers', '2024-10-10', '2024-11-30', 40, 20, 'in_progress', 'high', 50.00),

-- Bible Study Tasks
(5, 'Content Development', 'Write Bible study materials', '2024-01-01', '2024-03-31', 80, 70, 'in_progress', 'high', 87.50),
(5, 'Session Planning', 'Plan monthly study sessions', '2024-02-01', '2024-12-31', 40, 30, 'in_progress', 'medium', 75.00),

-- Youth Fellowship Tasks
(6, 'Event Calendar Planning', 'Plan monthly activities', '2024-01-01', '2024-01-31', 20, 20, 'completed', 'high', 100.00),
(6, 'Activity Coordination', 'Coordinate youth activities', '2024-02-01', '2024-12-31', 80, 50, 'in_progress', 'high', 62.50);

-- Insert Employees with different utilization states
INSERT INTO employees (employee_name, email, role, department, skills, hourly_rate, max_work_hours_per_day, current_workload, utilization_percentage, status) VALUES
-- UNDERUTILIZED employees (less than 60% utilization)
('John Smith', 'john.smith@church.org', 'IT Support', 'Administration', 'Computer Support, Audio-Visual', 320.00, 8.00, 2.00, 25.00, 'active'),
('Mary Johnson', 'mary.johnson@church.org', 'Administrative Assistant', 'Administration', 'Office Management, Documentation', 180.00, 8.00, 3.00, 38.00, 'active'),
('Robert Davis', 'robert.davis@church.org', 'Facilities Assistant', 'Operations', 'Maintenance, Repairs', 220.00, 8.00, 4.00, 50.00, 'active'),

-- OPTIMAL utilization employees (60-85% utilization)
('Rev. Jose Santos', 'jose.santos@church.org', 'Senior Pastor', 'Pastoral', 'Leadership, Preaching, Counseling', 500.00, 8.00, 5.00, 63.00, 'active'),
('Sister Maria Reyes', 'maria.reyes@church.org', 'Youth Ministry Head', 'Youth Ministry', 'Youth Counseling, Event Planning', 350.00, 8.00, 6.00, 75.00, 'active'),
('Brother Carlos Mendez', 'carlos.mendez@church.org', 'Music Ministry Leader', 'Music', 'Musical Direction, Guitar, Piano', 300.00, 6.00, 5.00, 83.00, 'active'),
('Sister Ana Rodriguez', 'ana.rodriguez@church.org', 'Sunday School Superintendent', 'Education', 'Teaching, Curriculum Development', 250.00, 8.00, 7.00, 88.00, 'active'),

-- OVERLOADED employees (86-100% utilization)
('Brother David Chen', 'david.chen@church.org', 'Treasurer', 'Administration', 'Accounting, Budget Management', 400.00, 8.00, 8.00, 100.00, 'active'),
('Sister Grace Lee', 'grace.lee@church.org', 'Volunteer Coordinator', 'Operations', 'Volunteer Management, Logistics', 200.00, 6.00, 6.00, 100.00, 'active'),
('Sister Sarah Gomez', 'sarah.gomez@church.org', 'Outreach Coordinator', 'Outreach', 'Community Relations, Evangelism', 220.00, 8.00, 8.00, 100.00, 'active'),

-- CRITICAL utilization employees (over 100% - overallocated)
('Michael Torres', 'michael.torres@church.org', 'Facilities Manager', 'Operations', 'Maintenance, Repairs, Security', 280.00, 8.00, 9.00, 113.00, 'active'),
('James Park', 'james.park@church.org', 'IT Support Specialist', 'Administration', 'Computer Support, Audio-Visual, Social Media', 320.00, 8.00, 10.00, 125.00, 'active'),
('Rachel Kim', 'rachel.kim@church.org', 'Administrative Assistant', 'Administration', 'Office Management, Scheduling', 180.00, 8.00, 9.00, 113.00, 'active');

-- Insert Equipment with different utilization states
INSERT INTO equipment (equipment_name, equipment_type, description, hourly_rate, maintenance_cost, total_capacity_hours, used_hours, utilization_percentage, status, location) VALUES
-- UNDERUTILIZED equipment (less than 60% utilization)
('Church Bus', 'Transportation', '40-seater bus for transportation', 150.00, 5000.00, 200.00, 20.00, 10.00, 'available', 'Church Garage'),
('Photography Equipment', 'Media Equipment', 'Cameras and accessories', 40.00, 800.00, 80.00, 15.00, 19.00, 'available', 'Media Room'),
('Generator', 'Power Equipment', 'Backup generator for events', 35.00, 1200.00, 100.00, 25.00, 25.00, 'available', 'Maintenance Room'),
('Printing Press', 'Office Equipment', 'Digital printer for materials', 15.00, 400.00, 200.00, 50.00, 25.00, 'available', 'Office'),
('Tents and Tables', 'Event Equipment', 'Outdoor event setup equipment', 25.00, 500.00, 100.00, 30.00, 30.00, 'available', 'Storage Room'),

-- OPTIMAL utilization equipment (60-85% utilization)
('Projector System', 'AV Equipment', 'HD projector with sound system', 50.00, 1000.00, 160.00, 100.00, 63.00, 'available', 'Main Hall'),
('Sound System', 'AV Equipment', 'Professional audio system', 75.00, 2000.00, 200.00, 140.00, 70.00, 'available', 'Main Hall'),
('Musical Instruments', 'Music Equipment', 'Guitars, keyboards, drums', 30.00, 1500.00, 150.00, 110.00, 73.00, 'available', 'Music Room'),
('Computers', 'Office Equipment', 'Desktop computers for admin work', 20.00, 600.00, 400.00, 300.00, 75.00, 'available', 'Office'),

-- OVERLOADED equipment (86-100% utilization)
('Kitchen Equipment', 'Food Service', 'Commercial kitchen for food preparation', 100.00, 3000.00, 300.00, 280.00, 93.00, 'in_use', 'Church Kitchen'),

-- CRITICAL utilization equipment (over 100% - overused)
('Additional Sound System', 'AV Equipment', 'Secondary sound system for multiple events', 75.00, 2000.00, 200.00, 220.00, 110.00, 'in_use', 'Fellowship Hall');

-- Insert Project Budgets
INSERT INTO project_budgets (project_id, category_id, allocated_amount, spent_amount, fiscal_year, status) VALUES
(1, 2, 150000.00, 75000.00, '2024', 'active'),  -- Summer Camp - Events
(2, 4, 50000.00, 35000.00, '2024', 'active'),   -- Leadership Training - Training
(3, 3, 200000.00, 120000.00, '2024', 'active'), -- Feeding Program - Outreach
(4, 2, 100000.00, 20000.00, '2024', 'active'),  -- Christmas Concert - Events
(5, 1, 30000.00, 15000.00, '2024', 'active'),   -- Bible Study - Ministry Programs
(6, 1, 80000.00, 40000.00, '2024', 'active');   -- Youth Fellowship - Ministry Programs

-- Insert Task Assignments
INSERT INTO task_assignments (task_id, project_id, assignment_type, resource_id, allocation_percentage, allocated_hours, actual_hours_used, start_date, end_date, status, notes) VALUES
-- Summer Camp Assignments
(1, 1, 'employee', 4, 100.00, 20.00, 20.00, '2024-04-01', '2024-04-15', 'completed', 'Camp site booked successfully'),
(2, 1, 'employee', 5, 100.00, 30.00, 25.00, '2024-04-10', '2024-04-30', 'in_progress', 'Speaker coordination ongoing'),
(3, 1, 'employee', 6, 100.00, 40.00, 30.00, '2024-04-20', '2024-05-10', 'in_progress', 'Food planning in progress'),
(4, 1, 'equipment', 1, 100.00, 25.00, 10.00, '2024-05-01', '2024-05-20', 'in_progress', 'Bus transportation arranged'),

-- Leadership Training Assignments
(5, 2, 'employee', 4, 100.00, 60.00, 60.00, '2024-01-15', '2024-02-15', 'completed', 'Curriculum developed'),
(6, 2, 'employee', 6, 100.00, 20.00, 20.00, '2024-02-01', '2024-02-28', 'completed', 'Trainers recruited'),
(7, 2, 'employee', 7, 100.00, 40.00, 35.00, '2024-02-15', '2024-03-15', 'in_progress', 'Materials preparation'),

-- Community Feeding Assignments
(8, 3, 'employee', 8, 100.00, 30.00, 30.00, '2024-01-01', '2024-01-31', 'completed', 'Beneficiaries identified'),
(9, 3, 'employee', 9, 100.00, 100.00, 80.00, '2024-01-15', '2024-12-15', 'in_progress', 'Food procurement ongoing'),
(10, 3, 'employee', 6, 100.00, 80.00, 60.00, '2024-01-01', '2024-12-31', 'in_progress', 'Volunteer coordination'),
(11, 3, 'equipment', 11, 100.00, 150.00, 140.00, '2024-01-01', '2024-12-31', 'in_progress', 'Kitchen heavily used'),

-- Christmas Concert Assignments
(12, 4, 'employee', 7, 100.00, 25.00, 15.00, '2024-10-01', '2024-10-15', 'in_progress', 'Venue booking in progress'),
(13, 4, 'employee', 6, 100.00, 40.00, 20.00, '2024-10-10', '2024-11-30', 'in_progress', 'Artist coordination'),

-- Bible Study Assignments
(14, 5, 'employee', 7, 100.00, 80.00, 70.00, '2024-01-01', '2024-03-31', 'in_progress', 'Content development'),
(15, 5, 'employee', 4, 100.00, 40.00, 30.00, '2024-02-01', '2024-12-31', 'in_progress', 'Session planning'),

-- Youth Fellowship Assignments
(16, 6, 'employee', 4, 100.00, 20.00, 20.00, '2024-01-01', '2024-01-31', 'completed', 'Event calendar planned'),
(17, 6, 'employee', 5, 100.00, 80.00, 50.00, '2024-02-01', '2024-12-31', 'in_progress', 'Activity coordination');

-- Insert Resource Utilization Monitoring with different workload levels
INSERT INTO resource_utilization (resource_type, resource_id, project_id, date, total_hours_available, hours_used, utilization_percentage) VALUES
-- UNDERUTILIZED employees (less than 60%)
('employee', 1, NULL, '2024-03-15', 8.00, 2.00, 25.00),  -- John Smith
('employee', 2, NULL, '2024-03-15', 8.00, 3.00, 38.00), -- Mary Johnson
('employee', 3, NULL, '2024-03-15', 8.00, 4.00, 50.00),  -- Robert Davis

-- OPTIMAL utilization employees (60-85%)
('employee', 4, 1, '2024-03-15', 8.00, 5.00, 63.00),  -- Rev. Jose Santos
('employee', 5, 1, '2024-03-15', 8.00, 6.00, 75.00),   -- Sister Maria Reyes
('employee', 6, 1, '2024-03-15', 6.00, 5.00, 83.00),   -- Brother Carlos Mendez
('employee', 7, 2, '2024-03-15', 8.00, 7.00, 88.00),  -- Sister Ana Rodriguez

-- OVERLOADED employees (86-100%)
('employee', 8, 3, '2024-03-15', 8.00, 8.00, 100.00), -- Brother David Chen
('employee', 9, 3, '2024-03-15', 6.00, 6.00, 100.00),  -- Sister Grace Lee
('employee', 10, 3, '2024-03-15', 8.00, 8.00, 100.00), -- Sister Sarah Gomez

-- CRITICAL utilization employees (over 100%)
('employee', 11, 1, '2024-03-15', 8.00, 9.00, 113.00), -- Michael Torres
('employee', 12, 1, '2024-03-15', 8.00, 10.00, 125.00), -- James Park
('employee', 13, 1, '2024-03-15', 8.00, 9.00, 113.00), -- Rachel Kim

-- UNDERUTILIZED equipment (less than 60%)
('equipment', 1, NULL, '2024-03-15', 8.00, 1.00, 13.00),   -- Church Bus
('equipment', 2, NULL, '2024-03-15', 8.00, 1.00, 13.00),  -- Photography Equipment
('equipment', 3, NULL, '2024-03-15', 8.00, 2.00, 25.00),   -- Generator
('equipment', 4, NULL, '2024-03-15', 8.00, 2.00, 25.00),   -- Printing Press
('equipment', 5, NULL, '2024-03-15', 8.00, 2.00, 25.00),  -- Tents and Tables

-- OPTIMAL utilization equipment (60-85%)
('equipment', 6, 2, '2024-03-15', 8.00, 5.00, 63.00),    -- Projector System
('equipment', 7, 1, '2024-03-15', 8.00, 6.00, 75.00),    -- Sound System
('equipment', 8, 1, '2024-03-15', 8.00, 6.00, 75.00),    -- Musical Instruments
('equipment', 9, NULL, '2024-03-15', 8.00, 6.00, 75.00),  -- Computers

-- OVERLOADED equipment (86-100%)
('equipment', 10, 3, '2024-03-15', 8.00, 7.00, 88.00),    -- Kitchen Equipment

-- CRITICAL utilization equipment (over 100%)
('equipment', 11, 1, '2024-03-15', 8.00, 9.00, 113.00);   -- Additional Sound System

-- Insert Resource Allocation Alerts for different scenarios
INSERT INTO resource_alerts (alert_type, resource_type, resource_id, project_id, task_id, severity, message, threshold_value, current_value, status) VALUES
-- UNDERUTILIZATION alerts
('underutilization', 'employee', 1, NULL, NULL, 'low', 'IT Support severely underutilized - available for more tasks', 5.00, 2.00, 'active'),
('underutilization', 'employee', 2, NULL, NULL, 'low', 'Administrative Assistant underutilized - can take on more work', 5.00, 3.00, 'active'),
('underutilization', 'equipment', 1, NULL, NULL, 'low', 'Church Bus underutilized - available for transportation needs', 5.00, 1.00, 'active'),
('underutilization', 'equipment', 2, NULL, NULL, 'low', 'Photography Equipment underutilized - available for documentation', 5.00, 1.00, 'active'),

-- OVERALLOCATION alerts for OVERLOADED resources
('overallocation', 'employee', 8, 3, NULL, 'medium', 'Treasurer approaching maximum workload - consider delegating', 7.00, 8.00, 'active'),
('overallocation', 'employee', 9, 3, NULL, 'medium', 'Volunteer Coordinator at near capacity - review workload', 5.00, 6.00, 'active'),
('overallocation', 'employee', 10, 3, NULL, 'high', 'Outreach Coordinator at maximum capacity', 7.00, 8.00, 'active'),
('overallocation', 'equipment', 10, 3, NULL, 'medium', 'Kitchen Equipment near maximum usage', 7.00, 7.00, 'active'),

-- CRITICAL overallocation alerts
('overallocation', 'employee', 11, 1, NULL, 'critical', 'Facilities Manager critically overallocated - immediate action required', 7.00, 9.00, 'active'),
('overallocation', 'employee', 12, 1, NULL, 'critical', 'IT Support Specialist critically overallocated - redistribute workload', 7.00, 10.00, 'active'),
('overallocation', 'employee', 13, 1, NULL, 'critical', 'Administrative Assistant critically overallocated - urgent rebalancing needed', 7.00, 9.00, 'active'),
('overallocation', 'equipment', 11, 1, NULL, 'critical', 'Additional Sound System over capacity - risk of equipment failure', 7.00, 9.00, 'active'),

-- CAPACITY WARNING alerts
('capacity_warning', 'budget', 1, 1, NULL, 'medium', 'Summer Camp budget usage approaching 50% - monitor spending', 75000.00, 75000.00, 'active'),
('capacity_warning', 'budget', 3, 3, NULL, 'high', 'Feeding Program budget usage at 60% - careful budget management needed', 120000.00, 120000.00, 'active'),

-- CONFLICT alerts
('conflict', 'employee', 5, 1, 2, 'medium', 'Resource conflict between speaker coordination and food planning', 100.00, 100.00, 'active'),
('conflict', 'employee', 6, 1, 3, 'medium', 'Resource conflict between food planning and volunteer coordination', 100.00, 100.00, 'active');

-- Insert Budget Expenses
INSERT INTO budget_expenses (project_id, budget_id, task_id, expense_description, amount, expense_date, expense_type, approved_by, status) VALUES
(1, 1, 1, 'Camp site deposit', 25000.00, '2024-03-15', 'services', 'Rev. Jose Santos', 'approved'),
(1, 1, 2, 'Speaker honorariums', 15000.00, '2024-03-20', 'labor', 'Sister Maria Reyes', 'approved'),
(1, 1, 3, 'Food supplies advance', 35000.00, '2024-03-25', 'materials', 'Brother David Chen', 'approved'),
(2, 2, 5, 'Training curriculum printing', 15000.00, '2024-02-20', 'materials', 'Sister Maria Reyes', 'approved'),
(2, 2, 6, 'Trainer honorariums', 20000.00, '2024-02-28', 'labor', 'Rev. Jose Santos', 'approved'),
(3, 3, 8, 'Food supplies - Q1', 50000.00, '2024-03-15', 'materials', 'Brother David Chen', 'approved'),
(3, 3, 9, 'Food supplies - Q2 advance', 70000.00, '2024-03-20', 'materials', 'Brother David Chen', 'approved'),
(5, 5, 12, 'Bible study materials printing', 15000.00, '2024-02-15', 'materials', 'Sister Maria Reyes', 'approved'),
(6, 6, 14, 'Event venue rentals', 20000.00, '2024-03-01', 'services', 'Sister Grace Lee', 'approved'),
(6, 6, 15, 'Activity supplies', 20000.00, '2024-03-05', 'materials', 'Sister Rachel Kim', 'approved');
