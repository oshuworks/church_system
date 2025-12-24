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




-- Insert Budget Categories relevant to Church Youth Ministry
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

-- Insert Projects for KKB Ministry
INSERT INTO projects (project_name, project_description, start_date, end_date, status, priority, budget_allocated) VALUES
('KKB Summer Camp 2024', 'Annual youth summer camp with spiritual growth activities', '2024-04-01', '2024-05-31', 'planning', 'high', 150000.00),
('Sunday School Renovation', 'Renovation of Sunday school classrooms and facilities', '2024-02-01', '2024-03-31', 'active', 'medium', 75000.00),
('Youth Leadership Training', 'Quarterly leadership training for KKB officers', '2024-01-15', '2024-12-15', 'active', 'high', 50000.00),
('Community Feeding Program', 'Weekly community feeding program for underprivileged families', '2024-01-01', '2024-12-31', 'active', 'high', 200000.00),
('Christmas Outreach Concert', 'Christmas concert outreach to the community', '2024-10-01', '2024-12-25', 'planning', 'medium', 100000.00),
('Bible Study Materials', 'Production and distribution of Bible study materials', '2024-01-01', '2024-06-30', 'active', 'medium', 30000.00),
('Youth Fellowship Events', 'Regular monthly youth fellowship activities', '2024-01-01', '2024-12-31', 'active', 'medium', 80000.00),
('Mission Trip Preparation', 'Preparation for local mission trips', '2024-03-01', '2024-08-31', 'planning', 'low', 40000.00);

-- Insert Tasks for each Project
INSERT INTO tasks (project_id, task_name, task_description, start_date, end_date, estimated_hours, actual_hours, status, priority, progress_percentage) VALUES
-- Summer Camp 2024 Tasks
(1, 'Camp Site Booking', 'Reserve and pay for camp venue', '2024-04-01', '2024-04-15', 20, 15, 'completed', 'high', 75.00),
(1, 'Speaker Coordination', 'Coordinate with guest speakers and pastors', '2024-04-10', '2024-04-30', 30, 10, 'in_progress', 'high', 33.33),
(1, 'Food Catering', 'Arrange meals for camp participants', '2024-04-20', '2024-05-10', 40, 0, 'not_started', 'medium', 0.00),
(1, 'Transportation Logistics', 'Arrange transportation to and from camp', '2024-05-01', '2024-05-20', 25, 0, 'not_started', 'medium', 0.00),
(1, 'Registration Management', 'Handle participant registration and payments', '2024-04-01', '2024-05-25', 50, 20, 'in_progress', 'high', 40.00),

-- Sunday School Renovation Tasks
(2, 'Classroom Design', 'Design new classroom layout and decorations', '2024-02-01', '2024-02-15', 30, 30, 'completed', 'medium', 100.00),
(2, 'Painting and Decorating', 'Paint walls and install educational decorations', '2024-02-10', '2024-03-10', 80, 60, 'in_progress', 'medium', 75.00),
(2, 'Furniture Installation', 'Install new tables, chairs, and storage', '2024-02-20', '2024-03-20', 40, 10, 'in_progress', 'medium', 25.00),
(2, 'Audio-Visual Setup', 'Install projector, screen, and sound system', '2024-03-01', '2024-03-25', 25, 0, 'not_started', 'low', 0.00),

-- Leadership Training Tasks
(3, 'Curriculum Development', 'Develop leadership training curriculum', '2024-01-15', '2024-02-15', 60, 60, 'completed', 'high', 100.00),
(3, 'Trainer Recruitment', 'Recruit and coordinate volunteer trainers', '2024-02-01', '2024-02-28', 20, 15, 'completed', 'medium', 75.00),
(3, 'Training Materials', 'Prepare training manuals and resources', '2024-02-15', '2024-03-15', 40, 30, 'in_progress', 'medium', 75.00),
(3, 'Venue Setup', 'Prepare training venue and equipment', '2024-03-01', '2024-03-31', 15, 0, 'not_started', 'low', 0.00),

-- Community Feeding Program Tasks
(4, 'Beneficiary Identification', 'Identify families needing assistance', '2024-01-01', '2024-01-31', 30, 30, 'completed', 'high', 100.00),
(4, 'Food Procurement', 'Purchase food supplies and ingredients', '2024-01-15', '2024-12-15', 100, 25, 'in_progress', 'high', 25.00),
(4, 'Volunteer Coordination', 'Schedule and coordinate volunteers', '2024-01-01', '2024-12-31', 80, 20, 'in_progress', 'medium', 25.00),
(4, 'Distribution Logistics', 'Manage weekly food distribution', '2024-01-01', '2024-12-31', 150, 40, 'in_progress', 'high', 26.67),

-- Christmas Outreach Concert Tasks
(5, 'Venue Booking', 'Reserve concert venue and facilities', '2024-10-01', '2024-10-15', 25, 20, 'completed', 'high', 80.00),
(5, 'Artist Coordination', 'Coordinate with performers and musicians', '2024-10-10', '2024-11-30', 40, 15, 'in_progress', 'high', 37.50),
(5, 'Marketing and Promotion', 'Promote concert to community', '2024-10-15', '2024-12-20', 60, 20, 'in_progress', 'medium', 33.33),
(5, 'Ticket Sales Management', 'Handle ticket distribution and sales', '2024-11-01', '2024-12-25', 35, 10, 'in_progress', 'medium', 28.57),
(5, 'Event Setup', 'Setup venue decorations and equipment', '2024-12-20', '2024-12-24', 30, 0, 'not_started', 'high', 0.00),

-- Bible Study Materials Tasks
(6, 'Content Development', 'Write and edit Bible study materials', '2024-01-01', '2024-03-31', 80, 60, 'completed', 'high', 75.00),
(6, 'Graphic Design', 'Design layouts and cover art', '2024-02-01', '2024-04-30', 40, 25, 'in_progress', 'medium', 62.50),
(6, 'Printing Production', 'Print and bind study materials', '2024-04-01', '2024-05-15', 30, 10, 'in_progress', 'medium', 33.33),
(6, 'Distribution Planning', 'Plan distribution to church members', '2024-05-01', '2024-06-30', 25, 0, 'not_started', 'low', 0.00),

-- Youth Fellowship Events Tasks
(7, 'Event Calendar Planning', 'Plan monthly fellowship activities', '2024-01-01', '2024-01-31', 20, 20, 'completed', 'high', 100.00),
(7, 'Budget Management', 'Manage monthly event budgets', '2024-01-01', '2024-12-31', 40, 25, 'in_progress', 'medium', 62.50),
(7, 'Activity Coordination', 'Coordinate monthly youth activities', '2024-02-01', '2024-12-31', 80, 30, 'in_progress', 'high', 37.50),
(7, 'Venue Management', 'Book and manage event venues', '2024-01-15', '2024-12-15', 50, 20, 'in_progress', 'medium', 40.00),
(7, 'Youth Engagement', 'Maintain youth participation and feedback', '2024-01-01', '2024-12-31', 60, 25, 'in_progress', 'high', 41.67),

-- Mission Trip Preparation Tasks
(8, 'Destination Research', 'Research and select mission locations', '2024-03-01', '2024-04-30', 30, 25, 'completed', 'high', 83.33),
(8, 'Team Recruitment', 'Recruit volunteers for mission trips', '2024-04-01', '2024-06-30', 40, 20, 'in_progress', 'medium', 50.00),
(8, 'Fundraising Campaign', 'Organize fundraising for trip expenses', '2024-04-15', '2024-08-15', 60, 15, 'in_progress', 'high', 25.00),
(8, 'Logistics Planning', 'Plan transportation and accommodation', '2024-05-01', '2024-07-31', 35, 10, 'in_progress', 'medium', 28.57),
(8, 'Training Preparation', 'Prepare team for mission work', '2024-06-01', '2024-08-31', 25, 0, 'not_started', 'low', 0.00);

-- Insert Employees (Church Staff and Volunteers)
INSERT INTO employees (employee_name, email, role, department, skills, hourly_rate, max_work_hours_per_day, current_workload, utilization_percentage, status) VALUES
('Rev. Jose Santos', 'jose.santos@jilchurch.org', 'Senior Pastor', 'Pastoral', 'Leadership, Preaching, Counseling', 500.00, 8.00, 6.00, 75.00, 'active'),
('Sister Maria Reyes', 'maria.reyes@jilchurch.org', 'Youth Ministry Head', 'Youth Ministry', 'Youth Counseling, Event Planning, Teaching', 350.00, 8.00, 7.00, 87.50, 'active'),
('Brother Carlos Mendez', 'carlos.mendez@jilchurch.org', 'Music Ministry Leader', 'Music', 'Musical Direction, Guitar, Piano, Vocals', 300.00, 6.00, 4.00, 66.67, 'active'),
('Sister Ana Rodriguez', 'ana.rodriguez@jilchurch.org', 'Sunday School Superintendent', 'Education', 'Teaching, Curriculum Development, Child Care', 250.00, 8.00, 5.00, 62.50, 'active'),
('Brother David Chen', 'david.chen@jilchurch.org', 'Treasurer', 'Administration', 'Accounting, Budget Management, Financial Reporting', 400.00, 8.00, 6.00, 75.00, 'active'),
('Sister Grace Lee', 'grace.lee@jilchurch.org', 'Volunteer Coordinator', 'Operations', 'Volunteer Management, Logistics, Communication', 200.00, 6.00, 3.00, 50.00, 'active'),
('Brother Michael Torres', 'michael.torres@jilchurch.org', 'Facilities Manager', 'Operations', 'Maintenance, Repairs, Security', 280.00, 8.00, 4.00, 50.00, 'active'),
('Sister Sarah Gomez', 'sarah.gomez@jilchurch.org', 'Outreach Coordinator', 'Outreach', 'Community Relations, Evangelism, Social Services', 220.00, 8.00, 5.00, 62.50, 'active'),
('Brother James Park', 'james.park@jilchurch.org', 'IT Support', 'Administration', 'Computer Support, Audio-Visual, Social Media', 320.00, 8.00, 2.00, 25.00, 'active'),
('Sister Rachel Kim', 'rachel.kim@jilchurch.org', 'Administrative Assistant', 'Administration', 'Office Management, Documentation, Scheduling', 180.00, 8.00, 6.00, 75.00, 'active');

-- Insert Equipment (Church Resources)
INSERT INTO equipment (equipment_name, equipment_type, description, hourly_rate, maintenance_cost, total_capacity_hours, used_hours, utilization_percentage, status, location) VALUES
('Church Bus', 'Transportation', '40-seater bus for transportation', 150.00, 5000.00, 200.00, 50.00, 25.00, 'available', 'Church Garage'),
('Projector System', 'AV Equipment', 'HD projector with sound system', 50.00, 1000.00, 160.00, 40.00, 25.00, 'available', 'Main Hall'),
('Sound System', 'AV Equipment', 'Professional audio system for events', 75.00, 2000.00, 200.00, 80.00, 40.00, 'available', 'Main Hall'),
('Kitchen Equipment', 'Food Service', 'Commercial kitchen for food preparation', 100.00, 3000.00, 300.00, 120.00, 40.00, 'available', 'Church Kitchen'),
('Tents and Tables', 'Event Equipment', 'Outdoor event setup equipment', 25.00, 500.00, 100.00, 20.00, 20.00, 'available', 'Storage Room'),
('Musical Instruments', 'Music Equipment', 'Guitars, keyboards, drums for worship', 30.00, 1500.00, 150.00, 60.00, 40.00, 'available', 'Music Room'),
('Photography Equipment', 'Media Equipment', 'Cameras and accessories for documentation', 40.00, 800.00, 80.00, 15.00, 18.75, 'available', 'Media Room'),
('Computers', 'Office Equipment', 'Desktop computers for administrative work', 20.00, 600.00, 400.00, 100.00, 25.00, 'available', 'Office'),
('Generator', 'Power Equipment', 'Backup generator for events', 35.00, 1200.00, 100.00, 10.00, 10.00, 'available', 'Maintenance Room'),
('Printing Press', 'Office Equipment', 'Digital printer for materials production', 15.00, 400.00, 200.00, 30.00, 15.00, 'available', 'Office');

-- Insert Project Budgets
INSERT INTO project_budgets (project_id, category_id, allocated_amount, spent_amount, fiscal_year, status) VALUES
(1, 2, 150000.00, 25000.00, '2024', 'active'),  -- Summer Camp - Events
(2, 5, 75000.00, 35000.00, '2024', 'active'),   -- Sunday School Renovation - Operations
(3, 4, 50000.00, 15000.00, '2024', 'active'),   -- Leadership Training - Training
(4, 3, 200000.00, 45000.00, '2024', 'active'),  -- Feeding Program - Outreach
(5, 2, 100000.00, 0.00, '2024', 'active'),     -- Christmas Concert - Events
(6, 1, 30000.00, 8000.00, '2024', 'active'),    -- Bible Study Materials - Ministry Programs
(7, 1, 80000.00, 20000.00, '2024', 'active'),   -- Youth Fellowship - Ministry Programs
(8, 3, 40000.00, 5000.00, '2024', 'active');   -- Mission Trip - Outreach

-- Insert Task Assignments
INSERT INTO task_assignments (task_id, project_id, assignment_type, resource_id, allocation_percentage, allocated_hours, actual_hours_used, start_date, end_date, status, notes) VALUES
-- Summer Camp Assignments
(1, 1, 'employee', 2, 100.00, 20.00, 15.00, '2024-04-01', '2024-04-15', 'completed', 'Successfully booked camp site'),
(2, 1, 'employee', 1, 50.00, 15.00, 5.00, '2024-04-10', '2024-04-30', 'in_progress', 'Coordinating with guest speakers'),
(2, 1, 'employee', 3, 50.00, 15.00, 5.00, '2024-04-10', '2024-04-30', 'in_progress', 'Music and worship coordination'),
(3, 1, 'employee', 8, 100.00, 40.00, 0.00, '2024-04-20', '2024-05-10', 'not_started', 'Food planning and coordination'),
(4, 1, 'equipment', 1, 100.00, 25.00, 0.00, '2024-05-01', '2024-05-20', 'not_started', 'Bus for transportation'),
(5, 1, 'employee', 6, 100.00, 50.00, 20.00, '2024-04-01', '2024-05-25', 'in_progress', 'Managing registrations'),

-- Sunday School Renovation Assignments
(6, 2, 'employee', 4, 100.00, 30.00, 30.00, '2024-02-01', '2024-02-15', 'completed', 'Classroom design completed'),
(7, 2, 'employee', 7, 100.00, 80.00, 60.00, '2024-02-10', '2024-03-10', 'in_progress', 'Painting in progress'),
(8, 2, 'employee', 7, 100.00, 40.00, 10.00, '2024-02-20', '2024-03-20', 'in_progress', 'Furniture being installed'),
(9, 2, 'equipment', 2, 100.00, 25.00, 0.00, '2024-03-01', '2024-03-25', 'not_started', 'AV setup pending'),

-- Leadership Training Assignments
(10, 3, 'employee', 2, 100.00, 60.00, 60.00, '2024-01-15', '2024-02-15', 'completed', 'Curriculum developed'),
(11, 3, 'employee', 6, 100.00, 20.00, 15.00, '2024-02-01', '2024-02-28', 'completed', 'Trainers recruited'),
(12, 3, 'employee', 4, 100.00, 40.00, 30.00, '2024-02-15', '2024-03-15', 'in_progress', 'Materials preparation'),
(13, 3, 'equipment', 2, 100.00, 15.00, 0.00, '2024-03-01', '2024-03-31', 'not_started', 'Venue equipment needed'),

-- Community Feeding Program Assignments
(14, 4, 'employee', 8, 100.00, 30.00, 30.00, '2024-01-01', '2024-01-31', 'completed', 'Beneficiaries identified'),
(15, 4, 'employee', 5, 50.00, 50.00, 12.50, '2024-01-15', '2024-12-15', 'in_progress', 'Budget management'),
(15, 4, 'employee', 10, 50.00, 50.00, 12.50, '2024-01-15', '2024-12-15', 'in_progress', 'Administrative support'),
(16, 4, 'employee', 6, 100.00, 80.00, 20.00, '2024-01-01', '2024-12-31', 'in_progress', 'Volunteer coordination'),
(17, 4, 'equipment', 4, 100.00, 150.00, 40.00, '2024-01-01', '2024-12-31', 'in_progress', 'Kitchen usage'),

-- Christmas Outreach Concert Assignments
(18, 5, 'employee', 7, 100.00, 25.00, 20.00, '2024-10-01', '2024-10-15', 'completed', 'Venue booked successfully'),
(19, 5, 'employee', 3, 100.00, 40.00, 15.00, '2024-10-10', '2024-11-30', 'in_progress', 'Coordinating performers'),
(20, 5, 'employee', 8, 100.00, 60.00, 20.00, '2024-10-15', '2024-12-20', 'in_progress', 'Community outreach'),
(21, 5, 'employee', 6, 100.00, 35.00, 10.00, '2024-11-01', '2024-12-25', 'in_progress', 'Ticket management'),
(22, 5, 'equipment', 3, 100.00, 30.00, 0.00, '2024-12-20', '2024-12-24', 'not_started', 'Sound system setup'),

-- Bible Study Materials Assignments
(23, 6, 'employee', 4, 100.00, 80.00, 60.00, '2024-01-01', '2024-03-31', 'completed', 'Content developed'),
(24, 6, 'employee', 9, 100.00, 40.00, 25.00, '2024-02-01', '2024-04-30', 'in_progress', 'Design work in progress'),
(25, 6, 'equipment', 10, 100.00, 30.00, 10.00, '2024-04-01', '2024-05-15', 'in_progress', 'Printing materials'),
(26, 6, 'employee', 10, 100.00, 25.00, 0.00, '2024-05-01', '2024-06-30', 'not_started', 'Distribution planning'),

-- Youth Fellowship Events Assignments
(27, 7, 'employee', 2, 100.00, 20.00, 20.00, '2024-01-01', '2024-01-31', 'completed', 'Event calendar planned'),
(28, 7, 'employee', 5, 100.00, 40.00, 25.00, '2024-01-01', '2024-12-31', 'in_progress', 'Budget management'),
(29, 7, 'employee', 2, 100.00, 80.00, 30.00, '2024-02-01', '2024-12-31', 'in_progress', 'Activity coordination'),
(30, 7, 'employee', 6, 100.00, 50.00, 20.00, '2024-01-15', '2024-12-15', 'in_progress', 'Venue bookings'),
(31, 7, 'employee', 8, 100.00, 60.00, 25.00, '2024-01-01', '2024-12-31', 'in_progress', 'Youth engagement'),

-- Mission Trip Preparation Assignments
(32, 8, 'employee', 1, 100.00, 30.00, 25.00, '2024-03-01', '2024-04-30', 'completed', 'Destinations researched'),
(33, 8, 'employee', 6, 100.00, 40.00, 20.00, '2024-04-01', '2024-06-30', 'in_progress', 'Team recruitment'),
(34, 8, 'employee', 5, 100.00, 60.00, 15.00, '2024-04-15', '2024-08-15', 'in_progress', 'Fundraising efforts'),
(35, 8, 'employee', 7, 100.00, 35.00, 10.00, '2024-05-01', '2024-07-31', 'in_progress', 'Logistics planning'),
(36, 8, 'employee', 2, 100.00, 25.00, 0.00, '2024-06-01', '2024-08-31', 'not_started', 'Training preparation');

-- Insert Resource Utilization Monitoring
INSERT INTO resource_utilization (resource_type, resource_id, project_id, date, total_hours_available, hours_used, utilization_percentage) VALUES
-- Employee utilization for current month
('employee', 1, 1, '2024-03-15', 8.00, 6.00, 75.00),
('employee', 2, 1, '2024-03-15', 8.00, 7.00, 87.50),
('employee', 3, 1, '2024-03-15', 6.00, 4.00, 66.67),
('employee', 4, 2, '2024-03-15', 8.00, 5.00, 62.50),
('employee', 5, 4, '2024-03-15', 8.00, 6.00, 75.00),
('employee', 6, 1, '2024-03-15', 6.00, 3.00, 50.00),
('employee', 7, 2, '2024-03-15', 8.00, 4.00, 50.00),
('employee', 8, 4, '2024-03-15', 8.00, 5.00, 62.50),

-- Equipment utilization for current month
('equipment', 1, 1, '2024-03-15', 8.00, 2.00, 25.00),
('equipment', 2, 3, '2024-03-15', 8.00, 2.00, 25.00),
('equipment', 3, 1, '2024-03-15', 8.00, 3.20, 40.00),
('equipment', 4, 4, '2024-03-15', 8.00, 3.20, 40.00);

-- Insert Resource Allocation Alerts
INSERT INTO resource_alerts (alert_type, resource_type, resource_id, project_id, task_id, severity, message, threshold_value, current_value, status) VALUES
('overallocation', 'employee', 2, 1, 2, 'medium', 'Youth Ministry Head approaching maximum workload', 8.00, 7.00, 'active'),
('underutilization', 'employee', 9, NULL, NULL, 'low', 'IT Support underutilized - available for more tasks', 4.00, 2.00, 'active'),
('capacity_warning', 'equipment', 1, 1, 4, 'medium', 'Church Bus capacity may be exceeded for summer camp', 40.00, 35.00, 'active'),
('overallocation', 'budget', 4, 4, NULL, 'high', 'Feeding program budget usage exceeding monthly allocation', 16666.67, 45000.00, 'active'),
('conflict', 'employee', 2, 1, 5, 'medium', 'Resource conflict between speaker coordination and registration management', 100.00, 100.00, 'active');

-- Insert Budget Expenses
INSERT INTO budget_expenses (project_id, budget_id, task_id, expense_description, amount, expense_date, expense_type, approved_by, status) VALUES
(1, 1, 1, 'Camp site deposit', 25000.00, '2024-03-15', 'services', 'Rev. Jose Santos', 'approved'),
(2, 2, 7, 'Paint and supplies', 15000.00, '2024-03-01', 'materials', 'Sister Maria Reyes', 'approved'),
(2, 2, 8, 'New classroom furniture', 20000.00, '2024-03-10', 'materials', 'Brother David Chen', 'approved'),
(3, 3, 10, 'Training curriculum printing', 8000.00, '2024-02-20', 'materials', 'Sister Maria Reyes', 'approved'),
(3, 3, 11, 'Trainer honorariums', 7000.00, '2024-02-28', 'labor', 'Rev. Jose Santos', 'approved'),
(4, 4, 14, 'Food supplies for March', 15000.00, '2024-03-15', 'materials', 'Brother David Chen', 'approved'),
(4, 4, 17, 'Kitchen utilities', 5000.00, '2024-03-10', 'services', 'Sister Sarah Gomez', 'approved'),
(6, 6, NULL, 'Bible study materials printing', 8000.00, '2024-02-15', 'materials', 'Sister Maria Reyes', 'approved'),
(7, 7, NULL, 'Youth fellowship venue rental', 10000.00, '2024-03-01', 'services', 'Sister Grace Lee', 'approved'),
(7, 7, NULL, 'Snacks and refreshments', 10000.00, '2024-03-05', 'materials', 'Sister Rachel Kim', 'approved'),
(8, 8, NULL, 'Mission trip preliminary expenses', 5000.00, '2024-03-20', 'services', 'Sister Sarah Gomez', 'approved');
