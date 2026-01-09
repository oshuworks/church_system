<?php
require_once __DIR__ . '/bootstrap.php';

$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : 0;
$conditions = [];
$params = [];
$types = '';

if ($status !== '') {
    $conditions[] = "t.status = ?";
    $types .= 's';
    $params[] = $status;
}

if ($projectId > 0) {
    $conditions[] = "t.project_id = ?";
    $types .= 'i';
    $params[] = $projectId;
}

$where = '';
if (!empty($conditions)) {
    $where = 'WHERE ' . implode(' AND ', $conditions);
}

// Determine which progress column name is used (progress_percent or progress_percentage)
$progressColumn = 'progress_percent'; // Default
$colsRes = $mysqli->query("SHOW COLUMNS FROM tasks LIKE 'progress%'");
if ($colsRes) {
    while ($col = $colsRes->fetch_assoc()) {
        if ($col['Field'] === 'progress_percentage') {
            $progressColumn = 'progress_percentage';
            break;
        }
    }
}

$sql = "
SELECT 
    t.task_id,
    t.project_id,
    t.task_name,
    t.task_description,
    t.start_date,
    t.end_date,
    t.estimated_hours,
    t.status,
    t.priority,
    t.{$progressColumn} as progress_percentage,
    p.project_name,
    -- Calculate progress based ONLY on completed assignments
    -- Progress should only appear when work is actually completed, not when allocated
    -- Task allocation % becomes progress % only when assignment is completed
    CASE 
        WHEN t.status = 'completed' THEN 100
        WHEN t.status = 'cancelled' THEN 0
        WHEN t.status = 'not_started' THEN 0
        WHEN t.status = 'blocked' THEN 0
        WHEN t.{$progressColumn} IS NOT NULL AND t.{$progressColumn} > 0 AND t.status = 'completed' THEN 
            -- Use the progress_percentage column value only if task is marked as completed
            t.{$progressColumn}
        ELSE 
            -- Calculate progress ONLY from completed assignments
            -- Since we now store allocation_percentage in actual_hours_used for completed assignments,
            -- we can directly sum it up for progress
            COALESCE((
                SELECT SUM(COALESCE(ta.actual_hours_used, 0))
                FROM task_assignments ta 
                WHERE ta.task_id = t.task_id 
                AND ta.status = 'completed'
                AND ta.status != 'deleted'
            ), 0)
    END as calculated_progress_percentage,
    (SELECT COUNT(*) 
     FROM task_assignments ta 
     WHERE ta.task_id = t.task_id 
     AND ta.status = 'assigned') as assigned_count
FROM tasks t
JOIN projects p ON p.project_id = t.project_id
$where
ORDER BY p.project_name ASC, t.start_date ASC";

$stmt = runQuery($mysqli, $sql, $types ? [$types, $params] : []);
$tasks = fetchAllAssoc($stmt);

// Add assigned resources separately to avoid complex subquery
foreach ($tasks as &$task) {
    $assignedResourcesSql = "
        SELECT 
            ta.assignment_type,
            ta.resource_id,
            ta.allocation_percentage,
            CASE ta.assignment_type 
                WHEN 'employee' THEN e.employee_name
                WHEN 'equipment' THEN eq.equipment_name
                WHEN 'budget' THEN CONCAT('Budget: ', bc.category_name)
            END as resource_name
        FROM task_assignments ta
        LEFT JOIN employees e ON e.employee_id = ta.resource_id AND ta.assignment_type = 'employee'
        LEFT JOIN equipment eq ON eq.equipment_id = ta.resource_id AND ta.assignment_type = 'equipment'
        LEFT JOIN project_budgets pb ON pb.budget_id = ta.resource_id AND ta.assignment_type = 'budget'
        LEFT JOIN budget_categories bc ON bc.category_id = pb.category_id
        WHERE ta.task_id = ? AND ta.status = 'assigned' AND ta.status != 'deleted'";
    
    $assignStmt = runQuery($mysqli, $assignedResourcesSql, ['i', [$task['task_id']]]);
    $assignments = fetchAllAssoc($assignStmt);
    
    $resourceList = [];
    foreach ($assignments as $assignment) {
        $resourceList[] = $assignment['resource_name'] . ' (' . $assignment['allocation_percentage'] . '%)';
    }
    
    $task['assigned_resources'] = !empty($resourceList) ? implode(', ', $resourceList) : 'None';
}

respond(['tasks' => $tasks]);

