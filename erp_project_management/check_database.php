<?php
// Quick database check script
header('Content-Type: text/plain');

$dbConfig = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'name' => 'erp_project_management',
];

echo "=== Database Connection Check ===\n\n";

// Test connection
$mysqli = @new mysqli($dbConfig['host'], $dbConfig['user'], $dbConfig['pass'], $dbConfig['name']);

if ($mysqli->connect_errno) {
    echo "❌ FAILED: Database connection failed\n";
    echo "Error: " . $mysqli->connect_error . "\n";
    echo "\nSOLUTION: Run http://localhost/church_system-main_m/config/setup.php\n";
    exit;
}

echo "✅ SUCCESS: Connected to database '{$dbConfig['name']}'\n\n";

// Check tables
$tables = ['employees', 'equipment', 'projects', 'tasks', 'project_budgets', 'resource_utilization', 'resource_alerts'];
echo "=== Table Check ===\n";

foreach ($tables as $table) {
    $result = $mysqli->query("SELECT COUNT(*) as count FROM `$table`");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "✅ $table: {$row['count']} records\n";
    } else {
        echo "❌ $table: Table not found or error\n";
    }
}

// Check specific data
echo "\n=== Sample Data Check ===\n";

$checks = [
    'First Employee' => "SELECT employee_name FROM employees LIMIT 1",
    'First Equipment' => "SELECT equipment_name FROM equipment LIMIT 1",
    'First Project' => "SELECT project_name FROM projects LIMIT 1",
    'First Task' => "SELECT task_name FROM tasks LIMIT 1",
];

foreach ($checks as $label => $query) {
    $result = $mysqli->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        echo "✅ $label: " . reset($row) . "\n";
    } else {
        echo "❌ $label: No data\n";
    }
}

echo "\n=== Summary ===\n";
echo "If all checks pass, visit: http://localhost/church_system-main_m/resource_allocation/resources.php\n";
echo "If tables are missing or empty, run: http://localhost/church_system-main_m/config/setup.php\n";

$mysqli->close();
?>
