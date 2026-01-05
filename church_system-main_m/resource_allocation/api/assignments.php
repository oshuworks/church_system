<?php
require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : 0;
    $taskId = isset($_GET['task_id']) ? (int) $_GET['task_id'] : 0;

    $conditions = [];
    $types = '';
    $params = [];

    if ($projectId > 0) {
        $conditions[] = "ta.project_id = ?";
        $types .= 'i';
        $params[] = $projectId;
    }
    if ($taskId > 0) {
        $conditions[] = "ta.task_id = ?";
        $types .= 'i';
        $params[] = $taskId;
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $sql = "
    SELECT 
        ta.assignment_id,
        ta.assignment_type,
        ta.resource_id,
        ta.project_id,
        ta.task_id,
        ta.allocation_percentage,
        ta.allocated_hours,
        ta.actual_hours_used,
        ta.start_date,
        ta.end_date,
        ta.status,
        ta.notes,
        t.task_name,
        p.project_name,
        CASE 
            WHEN ta.assignment_type = 'employee' THEN e.employee_name
            WHEN ta.assignment_type = 'equipment' THEN eq.equipment_name
            WHEN ta.assignment_type = 'budget' THEN CONCAT('Budget: ', bc.category_name)
        END AS resource_name
    FROM task_assignments ta
    JOIN tasks t ON t.task_id = ta.task_id
    JOIN projects p ON p.project_id = ta.project_id
    LEFT JOIN employees e ON e.employee_id = ta.resource_id AND ta.assignment_type = 'employee'
    LEFT JOIN equipment eq ON eq.equipment_id = ta.resource_id AND ta.assignment_type = 'equipment'
    LEFT JOIN project_budgets pb ON pb.budget_id = ta.resource_id AND ta.assignment_type = 'budget'
    LEFT JOIN budget_categories bc ON bc.category_id = pb.category_id
    $where
    ORDER BY ta.start_date DESC";

    $stmt = runQuery($mysqli, $sql, $types ? [$types, $params] : []);
    $rows = fetchAllAssoc($stmt);
    respond(['assignments' => $rows]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $required = ['project_id', 'task_id', 'assignment_type', 'resource_id', 'allocation_percentage', 'allocated_hours', 'start_date', 'end_date'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            respond(['error' => "Missing field: $field"], 400);
        }
    }

    $projectId = (int) $input['project_id'];
    $taskId = (int) $input['task_id'];
    $assignmentType = $input['assignment_type'];
    $resourceId = (int) $input['resource_id'];
    $allocationPercentage = (float) $input['allocation_percentage'];
    $allocatedHours = (float) $input['allocated_hours'];
    $startDate = $input['start_date'];
    $endDate = $input['end_date'];
    $notes = isset($input['notes']) ? trim($input['notes']) : null;

    if (!in_array($assignmentType, ['employee', 'equipment', 'budget'], true)) {
        respond(['error' => 'Invalid assignment_type'], 400);
    }

    // Validate task belongs to project
    $taskCheck = fetchAllAssoc(runQuery($mysqli, "SELECT task_id FROM tasks WHERE task_id = ? AND project_id = ?", ['ii', [$taskId, $projectId]]));
    if (empty($taskCheck)) {
        respond(['error' => 'Task does not belong to the specified project'], 400);
    }

    // Validate resource existence and status
    $resourceName = '';
    if ($assignmentType === 'employee') {
        $res = fetchAllAssoc(runQuery($mysqli, "SELECT employee_name, status, utilization_percentage FROM employees WHERE employee_id = ?", ['i', [$resourceId]]));
        if (empty($res)) respond(['error' => 'Employee not found'], 404);
        $row = $res[0];
        $resourceName = $row['employee_name'];
        if ($row['status'] !== 'active') {
            respond(['error' => 'Employee is not active'], 400);
        }
        if ((float) $row['utilization_percentage'] >= 100) {
            respond(['error' => 'Employee is already at or above capacity'], 409);
        }
    } elseif ($assignmentType === 'equipment') {
        $res = fetchAllAssoc(runQuery($mysqli, "SELECT equipment_name, status, utilization_percentage FROM equipment WHERE equipment_id = ?", ['i', [$resourceId]]));
        if (empty($res)) respond(['error' => 'Equipment not found'], 404);
        $row = $res[0];
        $resourceName = $row['equipment_name'];
        if ($row['status'] === 'retired') {
            respond(['error' => 'Equipment is retired/unavailable'], 400);
        }
        if ((float) $row['utilization_percentage'] >= 110) {
            respond(['error' => 'Equipment is over capacity'], 409);
        }
    } else {
        $res = fetchAllAssoc(runQuery($mysqli, "SELECT pb.budget_id, pb.project_id, pb.remaining_amount, bc.category_name FROM project_budgets pb JOIN budget_categories bc ON bc.category_id = pb.category_id WHERE pb.budget_id = ?", ['i', [$resourceId]]));
        if (empty($res)) respond(['error' => 'Budget not found'], 404);
        $row = $res[0];
        $resourceName = 'Budget: ' . $row['category_name'];
        if ((int) $row['project_id'] !== $projectId) {
            respond(['error' => 'Budget does not belong to this project'], 400);
        }
        if ((float) $row['remaining_amount'] <= 0) {
            respond(['error' => 'Budget has no remaining amount'], 409);
        }
    }

    $insertSql = "
    INSERT INTO task_assignments (task_id, project_id, assignment_type, resource_id, allocation_percentage, allocated_hours, start_date, end_date, status, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'assigned', ?)";
    runQuery($mysqli, $insertSql, ['iisidddds', [$taskId, $projectId, $assignmentType, $resourceId, $allocationPercentage, $allocatedHours, $startDate, $endDate, $notes]]);

    respond([
        'message' => 'Assignment created',
        'resource_name' => $resourceName,
        'assignment_type' => $assignmentType
    ], 201);
}

respond(['error' => 'Method not allowed'], 405);

