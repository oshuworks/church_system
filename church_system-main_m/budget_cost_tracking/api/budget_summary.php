<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * Budget Summary API
 * Provides high-level financial metrics and alert counts specifically for the Budget module.
 */

// 1. Project stats
$projectSql = "SELECT 
                COUNT(*) as total_projects,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_projects
               FROM projects";
$projectData = fetchAllAssoc(runQuery($mysqli, $projectSql))[0];

// 2. Budget vs Actual totals
$budgetSql = "SELECT 
                SUM(allocated_amount) as total_allocated,
                SUM(spent_amount) as total_spent
              FROM project_budgets";
$budgetData = fetchAllAssoc(runQuery($mysqli, $budgetSql))[0];

// 3. Alert counts specifically for budget issues
$alertSql = "SELECT 
                severity, 
                COUNT(*) as count 
             FROM resource_alerts 
             WHERE status = 'active' AND resource_type = 'budget'
             GROUP BY severity";
$alertRows = fetchAllAssoc(runQuery($mysqli, $alertSql));
$alerts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
foreach ($alertRows as $row) {
    $alerts[$row['severity']] = (int)$row['count'];
}

respond([
    'projects' => $projectData,
    'budget' => [
        'total_allocated' => (float)($budgetData['total_allocated'] ?? 0),
        'total_spent' => (float)($budgetData['total_spent'] ?? 0),
        'variance' => (float)(($budgetData['total_allocated'] ?? 0) - ($budgetData['total_spent'] ?? 0)),
        'utilization_percent' => ($budgetData['total_allocated'] > 0) 
            ? round(($budgetData['total_spent'] / $budgetData['total_allocated']) * 100, 1) 
            : 0
    ],
    'alerts' => $alerts,
    'timestamp' => date('Y-m-d H:i:s')
]);