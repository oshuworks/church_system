<?php
require_once __DIR__ . '/bootstrap.php';

$projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : 0;

// Tasks (optionally filtered by project)
$taskSql = "SELECT task_id, project_id, task_name, status, priority FROM tasks";
$taskParams = [];
$taskTypes = '';
if ($projectId > 0) {
    $taskSql .= " WHERE project_id = ?";
    $taskTypes = 'i';
    $taskParams[] = $projectId;
}
$taskSql .= " ORDER BY task_name ASC";
$tasks = fetchAllAssoc(runQuery($mysqli, $taskSql, $taskTypes ? [$taskTypes, $taskParams] : []));

// Employees (active only)
$employees = fetchAllAssoc(runQuery($mysqli, "SELECT employee_id AS id, employee_name AS name, role, department, utilization_percentage, status FROM employees WHERE status = 'active' ORDER BY employee_name ASC"));

// Equipment (non-retired)
$equipment = fetchAllAssoc(runQuery($mysqli, "SELECT equipment_id AS id, equipment_name AS name, equipment_type AS type, utilization_percentage, status FROM equipment WHERE status != 'retired' ORDER BY equipment_name ASC"));

// Budgets for project
$budgets = [];
if ($projectId > 0) {
    $budgets = fetchAllAssoc(runQuery($mysqli, "SELECT pb.budget_id AS id, bc.category_name AS name, pb.remaining_amount, pb.allocated_amount, pb.spent_amount FROM project_budgets pb JOIN budget_categories bc ON bc.category_id = pb.category_id WHERE pb.project_id = ? ORDER BY bc.category_name ASC", ['i', [$projectId]]));
}

respond([
    'tasks' => $tasks,
    'employees' => $employees,
    'equipment' => $equipment,
    'budgets' => $budgets,
]);

