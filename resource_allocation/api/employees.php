<?php
require_once __DIR__ . '/bootstrap.php';

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

$conditions = [];
$params = [];
$types = '';

if ($search !== '') {
    $conditions[] = "(e.employee_name LIKE CONCAT('%', ?, '%') OR e.role LIKE CONCAT('%', ?, '%') OR e.department LIKE CONCAT('%', ?, '%'))";
    $types .= 'sss';
    $params = array_merge($params, [$search, $search, $search]);
}

if ($status !== '') {
    $conditions[] = "e.status = ?";
    $types .= 's';
    $params[] = $status;
}

$where = '';
if (!empty($conditions)) {
    $where = 'WHERE ' . implode(' AND ', $conditions);
}

$sql = "
SELECT 
    e.employee_id,
    e.employee_name,
    e.email,
    e.role,
    e.department,
    e.skills,
    e.hourly_rate,
    e.max_work_hours_per_day,
    e.current_workload,
    e.utilization_percentage,
    e.status,
    ru.utilization_percentage AS latest_utilization,
    ru.workload_level,
    ru.date AS utilization_date,
    (SELECT COUNT(*) 
     FROM task_assignments ta 
     WHERE ta.resource_id = e.employee_id 
     AND ta.assignment_type = 'employee' 
     AND ta.status = 'assigned') as current_assignments,
    (SELECT GROUP_CONCAT(DISTINCT p.project_name SEPARATOR ', ')
     FROM task_assignments ta
     JOIN projects p ON p.project_id = ta.project_id
     WHERE ta.resource_id = e.employee_id 
     AND ta.assignment_type = 'employee' 
     AND ta.status = 'assigned') as assigned_projects
FROM employees e
LEFT JOIN resource_utilization ru
    ON ru.resource_type = 'employee'
   AND ru.resource_id = e.employee_id
   AND ru.date = (
        SELECT MAX(r2.date)
        FROM resource_utilization r2
        WHERE r2.resource_type = 'employee'
          AND r2.resource_id = e.employee_id
   )
$where
ORDER BY e.employee_name ASC";

$stmt = runQuery($mysqli, $sql, $types ? [$types, $params] : []);
$employees = fetchAllAssoc($stmt);

respond(['employees' => $employees]);

