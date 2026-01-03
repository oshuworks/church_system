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
    COALESCE(AVG(t.progress_percentage), 0) AS avg_progress,
    (SELECT COUNT(*) 
     FROM task_assignments ta 
     WHERE ta.project_id = p.project_id 
     AND ta.status = 'assigned') as total_assignments,
    (SELECT COUNT(DISTINCT CASE WHEN ta.assignment_type = 'employee' THEN ta.resource_id END)
     FROM task_assignments ta 
     WHERE ta.project_id = p.project_id 
     AND ta.status = 'assigned') as employees_assigned,
    (SELECT COUNT(DISTINCT CASE WHEN ta.assignment_type = 'equipment' THEN ta.resource_id END)
     FROM task_assignments ta 
     WHERE ta.project_id = p.project_id 
     AND ta.status = 'assigned') as equipment_assigned,
    (SELECT COUNT(DISTINCT CASE WHEN ta.assignment_type = 'budget' THEN ta.resource_id END)
     FROM task_assignments ta 
     WHERE ta.project_id = p.project_id 
     AND ta.status = 'assigned') as budgets_assigned
FROM projects p
LEFT JOIN project_budgets pb ON pb.project_id = p.project_id
LEFT JOIN tasks t ON t.project_id = p.project_id
GROUP BY 
    p.project_id, p.project_name, p.project_description, p.start_date, p.end_date, p.status, p.priority, p.budget_allocated
ORDER BY p.priority DESC, p.project_name ASC";

$stmt = runQuery($mysqli, $sql);
$projects = fetchAllAssoc($stmt);

respond(['projects' => $projects]);

