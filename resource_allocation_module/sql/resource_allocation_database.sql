-- Resource Allocation Management Database Schema
-- Finalized SQL for Resource Allocation Management Module ONLY
-- This script creates only Resource Allocation Management tables

CREATE DATABASE IF NOT EXISTS erp_project_management;
USE erp_project_management;

-- Core Projects Table (required for resource allocation)
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

-- Core Tasks Table (required for resource allocation)
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

-- Enhanced Resources Table (Resource Allocation Module)
CREATE TABLE resources (
    resource_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    resource_type ENUM('Human', 'Equipment', 'Financial', 'Material') NOT NULL,
    resource_subtype VARCHAR(50) NULL,
    availability DECIMAL(5,2) DEFAULT 100.00,
    cost_per_unit DECIMAL(10,2) DEFAULT 0.00,
    unit_of_measure VARCHAR(20) NULL,
    current_capacity DECIMAL(10,2) DEFAULT 100.00,
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Human Resources Allocation Table (Resource Allocation Module)
CREATE TABLE human_resources_allocations (
    allocation_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    task_id INT NOT NULL,
    employee_name VARCHAR(255) NOT NULL,
    role_responsibilities TEXT NOT NULL,
    number_of_people INT NOT NULL DEFAULT 1,
    utilization_timeframe VARCHAR(100) NOT NULL,
    allocated_tasks TEXT NOT NULL,
    allocation_percentage DECIMAL(5,2) DEFAULT 0.00,
    hourly_rate DECIMAL(10,2) DEFAULT 0.00,
    start_date DATE,
    end_date DATE,
    status ENUM('active', 'completed', 'pending') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id),
    FOREIGN KEY (task_id) REFERENCES tasks(task_id)
);

-- Material Resources Allocation Table (Resource Allocation Module)
CREATE TABLE material_resources_allocations (
    allocation_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    task_id INT NOT NULL,
    material_description VARCHAR(255) NOT NULL,
    quantity_allocated DECIMAL(10,2) NOT NULL,
    unit_of_measure VARCHAR(20) NOT NULL,
    utilization_timeframe VARCHAR(100) NOT NULL,
    allocated_tasks TEXT NOT NULL,
    unit_cost DECIMAL(10,2) DEFAULT 0.00,
    total_cost DECIMAL(15,2) GENERATED ALWAYS AS (quantity_allocated * unit_cost) STORED,
    supplier VARCHAR(255) NULL,
    delivery_date DATE NULL,
    status ENUM('ordered', 'delivered', 'in_use', 'depleted') DEFAULT 'ordered',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id),
    FOREIGN KEY (task_id) REFERENCES tasks(task_id)
);

-- Equipment Allocation Table (Resource Allocation Module)
CREATE TABLE equipment_allocations (
    allocation_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    task_id INT NOT NULL,
    equipment_description VARCHAR(255) NOT NULL,
    quantity_allocated INT NOT NULL DEFAULT 1,
    utilization_timeframe VARCHAR(100) NOT NULL,
    allocated_tasks TEXT NOT NULL,
    hourly_rate DECIMAL(10,2) DEFAULT 0.00,
    total_hours DECIMAL(8,2) DEFAULT 0.00,
    total_cost DECIMAL(15,2) GENERATED ALWAYS AS (hourly_rate * total_hours) STORED,
    maintenance_schedule VARCHAR(100) NULL,
    operator_required BOOLEAN DEFAULT FALSE,
    operator_name VARCHAR(255) NULL,
    status ENUM('available', 'in_use', 'maintenance', 'reserved') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id),
    FOREIGN KEY (task_id) REFERENCES tasks(task_id)
);

-- Financial Resources Allocation Table (Resource Allocation Module)
CREATE TABLE financial_resources_allocations (
    allocation_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    task_id INT NULL,
    financial_resource_type VARCHAR(255) NOT NULL,
    allocated_budget DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    timeframe VARCHAR(100) NOT NULL,
    purpose TEXT NOT NULL,
    budget_category ENUM('capital', 'operational', 'contingency', 'salary', 'materials', 'equipment', 'other') DEFAULT 'operational',
    spent_amount DECIMAL(15,2) DEFAULT 0.00,
    remaining_budget DECIMAL(15,2) GENERATED ALWAYS AS (allocated_budget - spent_amount) STORED,
    approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by VARCHAR(255) NULL,
    approval_date TIMESTAMP NULL,
    status ENUM('active', 'completed', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id),
    FOREIGN KEY (task_id) REFERENCES tasks(task_id)
);

-- Resource Utilization Tracking Table (Resource Allocation Module)
CREATE TABLE resource_utilization (
    utilization_id INT AUTO_INCREMENT PRIMARY KEY,
    resource_type ENUM('human', 'material', 'equipment', 'financial') NOT NULL,
    resource_id INT NULL,
    allocation_id INT NOT NULL,
    utilization_date DATE NOT NULL,
    utilization_percentage DECIMAL(5,2) DEFAULT 0.00,
    actual_quantity DECIMAL(10,2) DEFAULT 0.00,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (allocation_id) REFERENCES human_resources_allocations(allocation_id),
    FOREIGN KEY (resource_id) REFERENCES resources(resource_id)
);

-- Resource Allocation Constraints Table (Resource Allocation Module)
CREATE TABLE resource_allocation_constraints (
    constraint_id INT AUTO_INCREMENT PRIMARY KEY,
    resource_type ENUM('human', 'material', 'equipment', 'financial') NOT NULL,
    constraint_type ENUM('max_allocation', 'min_allocation', 'budget_limit', 'time_conflict') NOT NULL,
    constraint_value DECIMAL(15,2) NOT NULL,
    constraint_description TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Resource Notifications Table (Resource Allocation Module ONLY)
CREATE TABLE resource_notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    resource_id INT NULL,
    project_id INT NULL,
    allocation_id INT NULL,
    notification_type ENUM('over_utilization', 'under_utilization', 'balancing_recommendation', 'system_alert') NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resource_id) REFERENCES resources(resource_id),
    FOREIGN KEY (project_id) REFERENCES projects(project_id)
);

-- Sample Data for Projects (Resource Allocation Module)
INSERT INTO projects (name, description, start_date, end_date, budget) VALUES
('Youth Leadership Development Program', 'A training project aimed at equipping KKB members with leadership and organizational skills for church and community service.', '2025-01-10', '2025-03-30', 50000.00),
('Community Outreach and Evangelism Drive', 'Monthly outreach activities organized by KKB to engage youth and local residents through evangelism, feeding, and volunteer programs.', '2025-02-01', '2025-06-01', 80000.00),
('KKB Worship Night 2025', 'An annual event gathering youth from nearby extensions for worship, fellowship, and talent showcases.', '2025-04-15', '2025-04-20', 60000.00),
('KKB Digital Ministry Upgrade', 'A project to enhance KKB online presence, including website development, multimedia equipment procurement, and livestream setup.', '2025-05-01', '2025-08-30', 120000.00);

-- Sample Data for Tasks (Resource Allocation Module)
INSERT INTO tasks (project_id, name, description, start_date, end_date, dependency_task_id, progress_percent) VALUES
(1, 'Recruitment and Orientation', 'Identify and onboard new KKB youth leaders.', '2025-01-10', '2025-01-20', NULL, 100),
(1, 'Leadership Workshops', 'Conduct training sessions on servant leadership and project management.', '2025-01-21', '2025-03-15', 1, 85),
(2, 'Barangay Evangelism', 'Coordinate outreach activities in nearby barangays.', '2025-02-01', '2025-04-30', NULL, 70),
(2, 'Feeding Program', 'Organize feeding drives for children and families.', '2025-03-01', '2025-05-30', 3, 60),
(3, 'Event Planning', 'Plan and coordinate the worship night logistics and program flow.', '2025-04-01', '2025-04-10', NULL, 100),
(3, 'Stage and Technical Setup', 'Set up sound, lights, and multimedia equipment.', '2025-04-11', '2025-04-14', 5, 90),
(4, 'Website Development', 'Develop the official KKB extension website.', '2025-05-01', '2025-06-30', NULL, 50),
(4, 'Livestream Setup', 'Set up new livestream system and test with ministry events.', '2025-07-01', '2025-08-15', 7, 40);

-- Sample Data for Resources (Resource Allocation Module)
INSERT INTO resources (name, resource_type, resource_subtype, availability, cost_per_unit, unit_of_measure, current_capacity, status) VALUES
('John Dela Cruz', 'Human', 'Project Manager', 100.00, 0.00, NULL, 100.00, 'active'),
('Maria Santos', 'Human', 'Administrator', 80.00, 0.00, NULL, 80.00, 'active'),
('Sound System Set', 'Equipment', 'Audio', 60.00, 15000.00, 'set', 60.00, 'active'),
('Multimedia Laptop', 'Equipment', 'Computer', 50.00, 45000.00, 'unit', 50.00, 'active'),
('Projector and Screen', 'Equipment', 'Display', 70.00, 25000.00, 'set', 70.00, 'active'),
('Youth Ministry Budget Fund', 'Financial', 'General Fund', 100.00, 50000.00, 'pesos', 100.00, 'active');

-- Sample Data for Human Resources Allocations
INSERT INTO human_resources_allocations 
(project_id, task_id, employee_name, role_responsibilities, number_of_people, utilization_timeframe, allocated_tasks, allocation_percentage, hourly_rate, start_date, end_date) VALUES
(1, 1, 'Pastor Emmanuel Reyes', 'Project Director - KKB Ministry Head', 1, 'Jan 10 - Mar 30, 2025', 'Church project oversight, ministry coordination', 100.00, 500.00, '2025-01-10', '2025-03-30'),
(1, 2, 'Sister Maria Santos', 'Church Administrator', 2, 'Jan 21 - Mar 15, 2025', 'Administrative tasks, volunteer coordination', 80.00, 450.00, '2025-01-21', '2025-03-15'),
(2, 3, 'Brother Carlos Mendez', 'Building Committee Chair', 1, 'Feb 1 - Apr 30, 2025', 'Church construction planning, design review', 75.00, 550.00, '2025-02-01', '2025-04-30'),
(2, 4, 'KKB Youth Volunteers', 'Church Youth Ministry Team', 8, 'Mar 1 - May 30, 2025', 'Site preparation, church building construction', 90.00, 350.00, '2025-03-01', '2025-05-30'),
(3, 5, 'Technical Team', 'Church Sound & Media', 3, 'Apr 1 - Apr 14, 2025', 'Sound system installation, media setup', 85.00, 400.00, '2025-04-01', '2025-04-14'),
(3, 6, 'Maintenance Team', 'Church Facilities Crew', 2, 'Apr 11 - Apr 14, 2025', 'Facility maintenance, utility installation', 80.00, 380.00, '2025-04-11', '2025-04-14'),
(4, 7, 'Design Team', 'Church Interior Designers', 2, 'May 1 - Jun 30, 2025', 'Church interior design, worship space planning', 70.00, 420.00, '2025-05-01', '2025-06-30'),
(4, 8, 'Landscaping Team', 'Church Grounds Keepers', 4, 'Jul 1 - Aug 15, 2025', 'Church landscaping, garden area development', 75.00, 320.00, '2025-07-01', '2025-08-15');

-- Sample Data for Material Resources Allocations
INSERT INTO material_resources_allocations 
(project_id, task_id, material_description, quantity_allocated, unit_of_measure, utilization_timeframe, allocated_tasks, unit_cost, supplier, delivery_date) VALUES
(1, 1, 'Church Foundation Cement', 500, 'bags', 'Jan 15 - Feb 28, 2025', 'Church foundation work, sanctuary concrete mixing', 250.00, 'Christian Builders Supply', '2025-01-15'),
(1, 2, 'Church Structural Steel', 2000, 'kg', 'Feb 1 - Mar 15, 2025', 'Church structural framework, cross reinforcement', 45.00, 'Faith Construction Materials', '2025-02-01'),
(2, 3, 'Church Electrical Cables', 500, 'meters', 'Feb 15 - Apr 30, 2025', 'Church electrical wiring, sound system connections', 25.00, 'Blessed Electrical Depot', '2025-02-15'),
(2, 4, 'Church Plumbing Pipes', 300, 'meters', 'Mar 1 - May 30, 2025', 'Church water system, baptismal pool installation', 35.00, 'Holy Water Plumbing Co.', '2025-03-01'),
(3, 5, 'Church Stained Glass', 50, 'panels', 'Apr 5 - Apr 10, 2025', 'Church window installation, sanctuary partitions', 800.00, 'Sacred Glass Works Ltd.', '2025-04-05'),
(3, 6, 'Church Interior Paint', 100, 'gallons', 'Apr 12 - Apr 14, 2025', 'Church interior painting, worship area finishing', 45.00, 'Divine Paint & Coatings', '2025-04-12'),
(4, 7, 'Church Pews', 20, 'sets', 'May 15 - Jun 30, 2025', 'Church sanctuary seating, worship area furnishing', 1500.00, 'Faith Furniture Plus', '2025-05-15');

-- Sample Data for Equipment Allocations
INSERT INTO equipment_allocations 
(project_id, task_id, equipment_description, quantity_allocated, utilization_timeframe, allocated_tasks, hourly_rate, total_hours, maintenance_schedule, operator_required, operator_name) VALUES
(1, 1, 'Church Site Excavators', 2, 'Jan 10 - Feb 15, 2025', 'Church site excavation, foundation earth moving', 1500.00, 200.00, 'Monthly', TRUE, 'Church Construction Equipment Operator'),
(1, 2, 'Church Building Cranes', 1, 'Feb 1 - Mar 20, 2025', 'Church heavy lifting, cross beam placement', 2000.00, 150.00, 'Weekly', TRUE, 'Certified Church Crane Operator'),
(2, 3, 'Church Concrete Mixers', 3, 'Feb 15 - Apr 30, 2025', 'Church foundation concrete preparation, mixing', 500.00, 300.00, 'Bi-weekly', FALSE, NULL),
(2, 4, 'Church Welding Machines', 4, 'Mar 1 - May 30, 2025', 'Church metal fabrication, cross welding', 300.00, 250.00, 'Monthly', TRUE, 'Church Metalworking Team'),
(3, 5, 'Church Scaffolding Towers', 10, 'Apr 1 - Apr 14, 2025', 'Church height access, sanctuary support structures', 100.00, 100.00, 'Weekly safety inspection', FALSE, NULL);

-- Sample Data for Financial Resources Allocations
INSERT INTO financial_resources_allocations 
(project_id, task_id, financial_resource_type, allocated_budget, timeframe, purpose, budget_category, spent_amount, approval_status, approved_by) VALUES
(1, NULL, 'Church Building Fund', 1000000.00, 'Jan 1 - Dec 31, 2025', 'KKB Church construction startup costs, initial investment', 'capital', 250000.00, 'approved', 'Church Finance Committee'),
(1, 1, 'Church Foundation Work', 500000.00, 'Jan 10 - Mar 30, 2025', 'Church foundation, structural framework for sanctuary', 'materials', 420000.00, 'approved', 'Pastor Emmanuel Reyes'),
(2, 2, 'Church Systems Installation', 300000.00, 'Feb 1 - Jun 1, 2025', 'Church electrical, sound system, plumbing installation', 'materials', 180000.00, 'approved', 'Building Committee Chair'),
(3, 3, 'Church Interior & Worship Area', 400000.00, 'Apr 1 - Aug 30, 2025', 'Church sanctuary interior, pews, worship area finishing', 'materials', 150000.00, 'approved', 'Church Design Coordinator'),
(4, NULL, 'Church Contingency Fund', 200000.00, 'Jan 1 - Dec 31, 2025', 'Church emergency funds, unexpected construction expenses', 'contingency', 50000.00, 'approved', 'Church Finance Committee');

-- Resource Allocation Constraints
INSERT INTO resource_allocation_constraints 
(resource_type, constraint_type, constraint_value, constraint_description) VALUES
('human', 'max_allocation', 100.00, 'Maximum 100% allocation per human resource'),
('material', 'min_allocation', 1.00, 'Minimum 1 unit for material allocation'),
('equipment', 'time_conflict', 1.00, 'Equipment cannot be allocated to conflicting time slots'),
('financial', 'budget_limit', 1000000.00, 'Maximum budget limit per project');

-- Performance Indexes (Resource Allocation Module ONLY)
CREATE INDEX idx_human_allocations_project ON human_resources_allocations(project_id);
CREATE INDEX idx_human_allocations_task ON human_resources_allocations(task_id);
CREATE INDEX idx_material_allocations_project ON material_resources_allocations(project_id);
CREATE INDEX idx_material_allocations_task ON material_resources_allocations(task_id);
CREATE INDEX idx_equipment_allocations_project ON equipment_allocations(project_id);
CREATE INDEX idx_equipment_allocations_task ON equipment_allocations(task_id);
CREATE INDEX idx_financial_allocations_project ON financial_resources_allocations(project_id);
CREATE INDEX idx_financial_allocations_task ON financial_resources_allocations(task_id);
CREATE INDEX idx_utilization_date ON resource_utilization(utilization_date);
CREATE INDEX idx_resources_type ON resources(resource_type);
CREATE INDEX idx_resources_status ON resources(status);
CREATE INDEX idx_resource_notifications_project ON resource_notifications(project_id);
CREATE INDEX idx_resource_notifications_resource ON resource_notifications(resource_id);

-- Resource Allocation Summary View
CREATE VIEW v_resource_allocation_summary AS
SELECT 
    'Human Resources' as resource_category,
    COUNT(*) as total_allocations,
    SUM(number_of_people) as total_resources,
    SUM(allocation_percentage) as total_allocation,
    SUM(hourly_rate * number_of_people * 8 * 20) as estimated_cost -- 8 hours/day, 20 days/month
FROM human_resources_allocations
WHERE status = 'active'

UNION ALL

SELECT 
    'Material Resources' as resource_category,
    COUNT(*) as total_allocations,
    SUM(quantity_allocated) as total_resources,
    AVG(quantity_allocated) as total_allocation,
    SUM(total_cost) as estimated_cost
FROM material_resources_allocations
WHERE status IN ('delivered', 'in_use')

UNION ALL

SELECT 
    'Equipment' as resource_category,
    COUNT(*) as total_allocations,
    SUM(quantity_allocated) as total_resources,
    SUM(total_hours) as total_allocation,
    SUM(total_cost) as estimated_cost
FROM equipment_allocations
WHERE status = 'in_use'

UNION ALL

SELECT 
    'Financial Resources' as resource_category,
    COUNT(*) as total_allocations,
    COUNT(*) as total_resources,
    SUM(allocated_budget) as total_allocation,
    SUM(spent_amount) as estimated_cost
FROM financial_resources_allocations
WHERE status = 'active';

-- Resource Utilization View
CREATE VIEW v_resource_utilization AS
SELECT 
    r.resource_id,
    r.name as resource_name,
    r.resource_type,
    r.availability,
    r.current_capacity,
    COALESCE(SUM(hra.allocation_percentage), 0) as human_allocation,
    COALESCE(SUM(ea.quantity_allocated), 0) as equipment_allocation,
    COALESCE(SUM(mra.quantity_allocated), 0) as material_allocation,
    COALESCE(SUM(fra.allocated_budget), 0) as financial_allocation,
    CASE 
        WHEN r.resource_type = 'Human' THEN COALESCE(SUM(hra.allocation_percentage), 0)
        WHEN r.resource_type = 'Equipment' THEN (COALESCE(SUM(ea.quantity_allocated), 0) / r.current_capacity) * 100
        WHEN r.resource_type = 'Material' THEN (COALESCE(SUM(mra.quantity_allocated), 0) / r.current_capacity) * 100
        WHEN r.resource_type = 'Financial' THEN (COALESCE(SUM(fra.spent_amount), 0) / COALESCE(SUM(fra.allocated_budget), 1)) * 100
        ELSE 0
    END as utilization_percentage,
    CASE 
        WHEN r.resource_type = 'Human' AND COALESCE(SUM(hra.allocation_percentage), 0) > 90 THEN 'critical'
        WHEN r.resource_type = 'Human' AND COALESCE(SUM(hra.allocation_percentage), 0) > 75 THEN 'high'
        WHEN r.resource_type = 'Human' AND COALESCE(SUM(hra.allocation_percentage), 0) > 50 THEN 'medium'
        WHEN r.resource_type = 'Human' AND COALESCE(SUM(hra.allocation_percentage), 0) > 25 THEN 'low'
        WHEN r.resource_type = 'Human' THEN 'underutilized'
        WHEN r.resource_type = 'Equipment' AND (COALESCE(SUM(ea.quantity_allocated), 0) / r.current_capacity) * 100 > 90 THEN 'critical'
        WHEN r.resource_type = 'Equipment' AND (COALESCE(SUM(ea.quantity_allocated), 0) / r.current_capacity) * 100 > 75 THEN 'high'
        WHEN r.resource_type = 'Equipment' AND (COALESCE(SUM(ea.quantity_allocated), 0) / r.current_capacity) * 100 > 50 THEN 'medium'
        WHEN r.resource_type = 'Equipment' AND (COALESCE(SUM(ea.quantity_allocated), 0) / r.current_capacity) * 100 > 25 THEN 'low'
        WHEN r.resource_type = 'Equipment' THEN 'underutilized'
        WHEN r.resource_type = 'Material' AND (COALESCE(SUM(mra.quantity_allocated), 0) / r.current_capacity) * 100 > 90 THEN 'critical'
        WHEN r.resource_type = 'Material' AND (COALESCE(SUM(mra.quantity_allocated), 0) / r.current_capacity) * 100 > 75 THEN 'high'
        WHEN r.resource_type = 'Material' AND (COALESCE(SUM(mra.quantity_allocated), 0) / r.current_capacity) * 100 > 50 THEN 'medium'
        WHEN r.resource_type = 'Material' AND (COALESCE(SUM(mra.quantity_allocated), 0) / r.current_capacity) * 100 > 25 THEN 'low'
        WHEN r.resource_type = 'Material' THEN 'underutilized'
        WHEN r.resource_type = 'Financial' AND (COALESCE(SUM(fra.spent_amount), 0) / COALESCE(SUM(fra.allocated_budget), 1)) * 100 > 90 THEN 'critical'
        WHEN r.resource_type = 'Financial' AND (COALESCE(SUM(fra.spent_amount), 0) / COALESCE(SUM(fra.allocated_budget), 1)) * 100 > 75 THEN 'high'
        WHEN r.resource_type = 'Financial' AND (COALESCE(SUM(fra.spent_amount), 0) / COALESCE(SUM(fra.allocated_budget), 1)) * 100 > 50 THEN 'medium'
        WHEN r.resource_type = 'Financial' AND (COALESCE(SUM(fra.spent_amount), 0) / COALESCE(SUM(fra.allocated_budget), 1)) * 100 > 25 THEN 'low'
        WHEN r.resource_type = 'Financial' THEN 'underutilized'
        ELSE 'unknown'
    END as utilization_status
FROM resources r
LEFT JOIN human_resources_allocations hra ON r.name = hra.employee_name AND hra.status = 'active'
LEFT JOIN equipment_allocations ea ON r.name = ea.equipment_description AND ea.status = 'in_use'
LEFT JOIN material_resources_allocations mra ON r.name = mra.material_description AND mra.status IN ('delivered', 'in_use')
LEFT JOIN financial_resources_allocations fra ON r.name = fra.financial_resource_type AND fra.status = 'active'
GROUP BY r.resource_id, r.name, r.resource_type, r.availability, r.current_capacity;
