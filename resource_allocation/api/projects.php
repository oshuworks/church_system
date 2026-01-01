<?php
require_once __DIR__ . '/bootstrap.php';

$sql = "
SELECT 
    p.project_id,
    p.project_name,
    p.project_description,
    p.start_date,
    p.end_date,
    p.status,
    p.priority,
    p.budget_allocated,
    COALESCE(SUM(pb.spent_amount), 0) AS total_spent,
    COALESCE(AVG(t.progress_percentage), 0) AS avg_progress
FROM projects p
LEFT JOIN project_budgets pb ON pb.project_id = p.project_id
LEFT JOIN tasks t ON t.project_id = p.project_id
GROUP BY 
    p.project_id, p.project_name, p.project_description, p.start_date, p.end_date, p.status, p.priority, p.budget_allocated
ORDER BY p.priority DESC, p.project_name ASC";

$stmt = runQuery($mysqli, $sql);
$projects = fetchAllAssoc($stmt);

respond(['projects' => $projects]);

