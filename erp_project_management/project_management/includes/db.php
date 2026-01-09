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

/**
 * Function to check if table exists and create if needed
 */
function ensureTablesExist($conn) {
    // 1. Projects
    $conn->query("CREATE TABLE IF NOT EXISTS projects (
        project_id INT AUTO_INCREMENT PRIMARY KEY,
        project_name VARCHAR(255) NOT NULL,
        description TEXT,
        start_date DATE,
        end_date DATE,
        budget DECIMAL(15,2),
        status ENUM('planned', 'active', 'completed', 'on-hold') DEFAULT 'planned',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // 2. Resources
    $conn->query("CREATE TABLE IF NOT EXISTS resources (
        resource_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        resource_type ENUM('Human', 'Equipment', 'Financial') NOT NULL,
        availability DECIMAL(5,2) DEFAULT 100.00,
        cost_per_unit DECIMAL(10,2) DEFAULT 0.00
    )");

    // 3. Tasks (Basic structure first to avoid FK loops)
    $conn->query("CREATE TABLE IF NOT EXISTS tasks (
        task_id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        start_date DATE,
        end_date DATE,
        progress_percent INT DEFAULT 0,
        FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE
    )");

    // 4. Budgets
    $conn->query("CREATE TABLE IF NOT EXISTS budgets (
        budget_id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        description VARCHAR(255),
        estimated_cost DECIMAL(15,2),
        actual_cost DECIMAL(15,2) DEFAULT 0.00,
        FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE
    )");

    // 5. Resource allocations
    $conn->query("CREATE TABLE IF NOT EXISTS resource_allocations (
        allocation_id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        resource_id INT NOT NULL,
        allocation_percent DECIMAL(5,2) DEFAULT 0.00,
        FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE,
        FOREIGN KEY (resource_id) REFERENCES resources(resource_id) ON DELETE CASCADE
    )");

    // 6. Resource notifications (for utilization and deadline alerts)
    $conn->query("CREATE TABLE IF NOT EXISTS resource_notifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        resource_id INT DEFAULT NULL,
        project_id INT DEFAULT NULL,
        notification_type ENUM(
            'over_utilization', 'under_utilization', 'balancing_recommendation', 'system_alert',
            'project_due_date', 'project_near_deadline',
            'task_due_date', 'task_near_deadline',
            'subtask_due_date', 'subtask_near_deadline'
        ) NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (resource_id) REFERENCES resources(resource_id) ON DELETE SET NULL,
        FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE
    )");
}

// Run initial table creation
ensureTablesExist($conn);

/**
 * SAFE MIGRATION SECTION
 * These checks prevent "Unknown Column" or "Duplicate Column" fatal errors.
 */

// Add dependency_task_id to tasks if missing
$checkDep = $conn->query("SHOW COLUMNS FROM tasks LIKE 'dependency_task_id'");
if ($checkDep->num_rows == 0) {
    $conn->query("ALTER TABLE tasks ADD COLUMN dependency_task_id INT DEFAULT NULL AFTER end_date");
    $conn->query("ALTER TABLE tasks ADD FOREIGN KEY (dependency_task_id) REFERENCES tasks(task_id) ON DELETE SET NULL");
}

// Add parent_task_id to tasks if missing
$checkParent = $conn->query("SHOW COLUMNS FROM tasks LIKE 'parent_task_id'");
if ($checkParent->num_rows == 0) {
    $conn->query("ALTER TABLE tasks ADD COLUMN parent_task_id INT DEFAULT NULL AFTER dependency_task_id");
    $conn->query("ALTER TABLE tasks ADD FOREIGN KEY (parent_task_id) REFERENCES tasks(task_id) ON DELETE CASCADE");
}

// Check for the 'status' column in projects (common cause for dashboard errors)
$checkStatus = $conn->query("SHOW COLUMNS FROM projects LIKE 'status'");
if ($checkStatus->num_rows == 0) {
    $conn->query("ALTER TABLE projects ADD COLUMN status ENUM('planned', 'active', 'completed', 'on-hold') DEFAULT 'planned'");
}

?>