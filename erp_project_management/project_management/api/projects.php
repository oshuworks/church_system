<?php
// projects.php - API endpoint for projects management
// Clear any previous output buffers to prevent "Unexpected Character" errors
ob_start();

include '../includes/db.php';
header('Content-Type: application/json');

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Detect column names so we can return stable keys for the frontend
        $colsRes = $conn->query("SHOW COLUMNS FROM projects");
        $cols = [];
        while ($c = $colsRes->fetch_assoc()) { $cols[] = $c['Field']; }

        // Compose a select that maps to 'name' and 'description' regardless of schema variant
        if (in_array('project_name', $cols)) {
            $nameCol = 'project_name';
            $descCol = in_array('project_description', $cols) ? 'project_description' : "''";
            $budgetCol = in_array('budget_allocated', $cols) ? 'budget_allocated' : "NULL";
        } else {
            $nameCol = 'name';
            $descCol = in_array('description', $cols) ? 'description' : "''";
            $budgetCol = in_array('budget', $cols) ? 'budget' : "NULL";
        }

        $sql = sprintf("SELECT project_id, %s as name, %s as description, %s as budget, status, created_at FROM projects ORDER BY created_at DESC", $nameCol, $descCol, $budgetCol);

        $result = $conn->query($sql);
        $projects = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $projects[] = $row;
            }
        }

        // Wipe any potential PHP warnings that happened above
        ob_clean(); 
        echo json_encode($projects);

    } catch (Exception $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>