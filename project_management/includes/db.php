<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "erp_project_management";

// First, connect without database to create it if needed
$conn_temp = new mysqli($servername, $username, $password);

// Check connection
if ($conn_temp->connect_error) {
    die("Connection failed: " . $conn_temp->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn_temp->query($sql) === TRUE) {
    // Database created or already exists
} else {
    die("Error creating database: " . $conn_temp->error);
}

// Close temporary connection
$conn_temp->close();

// Now connect to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// Function to check if table exists and create if needed
function ensureTablesExist($conn) {
    // Check if projects table exists
    $result = $conn->query("SHOW TABLES LIKE 'projects'");
    if ($result->num_rows == 0) {
        // Create resource-allocation–style schema (compatible with resource_allocation SQL)

        // Projects
        $conn->query("CREATE TABLE IF NOT EXISTS projects (
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
        )");

        // Employees
        $conn->query("CREATE TABLE IF NOT EXISTS employees (
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
        )");

        // Equipment
        $conn->query("CREATE TABLE IF NOT EXISTS equipment (
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
        )");

        // Tasks
        $conn->query("CREATE TABLE IF NOT EXISTS tasks (
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
            dependency_task_id INT DEFAULT NULL,
            parent_task_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
            FOREIGN KEY (dependency_task_id) REFERENCES tasks(task_id) ON DELETE SET NULL,
            FOREIGN KEY (parent_task_id) REFERENCES tasks(task_id) ON DELETE CASCADE
        )");

        // Task assignments (keeps compatibility with resource_allocations semantics)
        $conn->query("CREATE TABLE IF NOT EXISTS task_assignments (
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
        )");

        // Resource utilization
        $conn->query("CREATE TABLE IF NOT EXISTS resource_utilization (
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
        )");

        // Resource alerts
        $conn->query("CREATE TABLE IF NOT EXISTS resource_alerts (
            alert_id INT AUTO_INCREMENT PRIMARY KEY,
            alert_type ENUM('overallocation', 'underutilization', 'conflict', 'capacity_warning', 'project_due_date', 'project_near_deadline', 'task_due_date', 'task_near_deadline', 'subtask_due_date', 'subtask_near_deadline') NOT NULL,
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
        )");

        // Budget tables
        $conn->query("CREATE TABLE IF NOT EXISTS budget_categories (
            category_id INT AUTO_INCREMENT PRIMARY KEY,
            category_name VARCHAR(100) NOT NULL,
            description TEXT,
            parent_category_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (parent_category_id) REFERENCES budget_categories(category_id)
        )");

        $conn->query("CREATE TABLE IF NOT EXISTS project_budgets (
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
        )");

        $conn->query("CREATE TABLE IF NOT EXISTS budget_expenses (
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
        )");

        // Create a compatibility view for legacy notifications API
        $conn->query("DROP VIEW IF EXISTS resource_notifications_view");
        $conn->query("CREATE VIEW resource_notifications_view AS
            SELECT alert_id AS notification_id, alert_type AS notification_type, resource_id, project_id, message, (status = 'active') AS is_unread, created_at
            FROM resource_alerts");
    }

    // Make sure legacy compatibility columns exist (virtual/generated columns)
    // Projects: add name and budget aliases if they don't exist
    $r = $conn->query("SHOW COLUMNS FROM projects LIKE 'name'");
    if ($r->num_rows == 0) {
        $conn->query("ALTER TABLE projects ADD COLUMN name VARCHAR(255) GENERATED ALWAYS AS (project_name) VIRTUAL");
    }
    $r = $conn->query("SHOW COLUMNS FROM projects LIKE 'budget'");
    if ($r->num_rows == 0) {
        $conn->query("ALTER TABLE projects ADD COLUMN budget DECIMAL(15,2) GENERATED ALWAYS AS (budget_allocated) VIRTUAL");
    }

    // Tasks: add name and progress_percent generated columns if they don't exist (for backward compatibility)
    $r = $conn->query("SHOW COLUMNS FROM tasks LIKE 'name'");
    if ($r->num_rows == 0) {
        $conn->query("ALTER TABLE tasks ADD COLUMN name VARCHAR(255) GENERATED ALWAYS AS (task_name) VIRTUAL");
    }
    $r = $conn->query("SHOW COLUMNS FROM tasks LIKE 'progress_percent'");
    if ($r->num_rows == 0) {
        $conn->query("ALTER TABLE tasks ADD COLUMN progress_percent INT GENERATED ALWAYS AS (CAST(progress_percentage AS SIGNED)) VIRTUAL");
    }
}

// Ensure tables exist
ensureTablesExist($conn);

// (No-op migration hooks kept for backward compatibility)

?>
