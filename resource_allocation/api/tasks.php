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

$sql = "
SELECT 
    t.task_id,
    t.project_id,
    t.task_name,
    t.task_description,
    t.start_date,
    t.end_date,
    t.estimated_hours,
    t.actual_hours,
    t.status,
    t.priority,
    t.progress_percentage,
    p.project_name,
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
        WHERE ta.task_id = ? AND ta.status = 'assigned'";
    
    $assignStmt = runQuery($mysqli, $assignedResourcesSql, ['i', [$task['task_id']]]);
    $assignments = fetchAllAssoc($assignStmt);
    
    $resourceList = [];
    foreach ($assignments as $assignment) {
        $resourceList[] = $assignment['resource_name'] . ' (' . $assignment['allocation_percentage'] . '%)';
    }
    
    $task['assigned_resources'] = !empty($resourceList) ? implode(', ', $resourceList) : 'None';
}

respond(['tasks' => $tasks]);

