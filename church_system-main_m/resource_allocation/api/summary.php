<?php
require_once __DIR__ . '/bootstrap.php';

// Project counts
$projectSql = "SELECT 
    COUNT(*) AS total_projects,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_projects,
    SUM(CASE WHEN status = 'planning' THEN 1 ELSE 0 END) AS planning_projects,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_projects
  FROM projects";
$projectCounts = fetchAllAssoc(runQuery($mysqli, $projectSql))[0] ?? [];

// Task counts
$taskSql = "SELECT 
    COUNT(*) AS total_tasks,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_tasks,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_tasks,
    SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) AS blocked_tasks
  FROM tasks";
$taskCounts = fetchAllAssoc(runQuery($mysqli, $taskSql))[0] ?? [];

// Resource counts
$resourceCounts = [
    'employees' => (fetchAllAssoc(runQuery($mysqli, "SELECT COUNT(*) AS total FROM employees"))[0]['total'] ?? 0),
    'equipment' => (fetchAllAssoc(runQuery($mysqli, "SELECT COUNT(*) AS total FROM equipment"))[0]['total'] ?? 0),
];

// Budget aggregates
$budgetSql = "SELECT 
    COALESCE(SUM(allocated_amount), 0) AS total_allocated,
    COALESCE(SUM(spent_amount), 0) AS total_spent
  FROM project_budgets";
$budgetTotals = fetchAllAssoc(runQuery($mysqli, $budgetSql))[0] ?? [];

// Alerts by severity
$alertsSql = "SELECT 
    SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) AS critical,
    SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) AS high,
    SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) AS medium,
    SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) AS low
  FROM resource_alerts
  WHERE status = 'active'";
$alertCounts = fetchAllAssoc(runQuery($mysqli, $alertsSql))[0] ?? [];

// Utilization distribution across latest entries
$utilizationSql = "SELECT workload_level, COUNT(*) AS total
  FROM resource_utilization
  WHERE date = (SELECT MAX(date) FROM resource_utilization)
  GROUP BY workload_level";
$utilizationRows = fetchAllAssoc(runQuery($mysqli, $utilizationSql));
$utilization = ['underutilized' => 0, 'optimal' => 0, 'overloaded' => 0, 'critical' => 0];
foreach ($utilizationRows as $row) {
    $utilization[$row['workload_level']] = (int)$row['total'];
}

respond([
    'projects' => $projectCounts,
    'tasks' => $taskCounts,
    'resources' => $resourceCounts,
    'budget' => $budgetTotals,
    'alerts' => $alertCounts,
    'utilization' => $utilization,
]);

