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
    p.project_name
FROM tasks t
JOIN projects p ON p.project_id = t.project_id
$where
ORDER BY p.project_name ASC, t.start_date ASC";

$stmt = runQuery($mysqli, $sql, $types ? [$types, $params] : []);
$tasks = fetchAllAssoc($stmt);

respond(['tasks' => $tasks]);

