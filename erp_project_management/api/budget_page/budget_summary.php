<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * Budget Summary API
 * Provides high-level financial metrics and alert counts specifically for the Budget module.
 */

try {
    // 1. Project stats
    $projectSql = "SELECT 
                    COUNT(*) as total_projects,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_projects
                   FROM projects";
    $projectResult = runQuery($mysqli, $projectSql);
    $projectData = fetchAllAssoc($projectResult);
    $projectData = !empty($projectData) ? $projectData[0] : ['total_projects' => 0, 'active_projects' => 0];

    // 2. Budget vs Actual totals
    // Read directly from projects table for allocated amounts
    // Handle both budget_allocated and budget column names for compatibility
    $colCheck = $mysqli->query("SHOW COLUMNS FROM projects LIKE 'budget%'");
    $hasBudgetAllocated = false;
    $hasBudget = false;
    while ($col = $colCheck->fetch_assoc()) {
        if ($col['Field'] === 'budget_allocated') $hasBudgetAllocated = true;
        if ($col['Field'] === 'budget') $hasBudget = true;
    }
    
    if ($hasBudgetAllocated) {
        $budgetCol = "budget_allocated";
    } else if ($hasBudget) {
        $budgetCol = "budget";
    } else {
        $budgetCol = "0";
    }
    
    $budgetSql = "SELECT COALESCE(SUM($budgetCol), 0) as total_allocated FROM projects";
    $budgetResult = runQuery($mysqli, $budgetSql);
    $budgetData = fetchAllAssoc($budgetResult);
    $totalAllocated = !empty($budgetData) ? (float)($budgetData[0]['total_allocated'] ?? 0) : 0;
    
    // Get actual spent from budget_expenses table (source of truth)
    $expensesSql = "SELECT COALESCE(SUM(amount), 0) as total_spent FROM budget_expenses";
    $expensesResult = runQuery($mysqli, $expensesSql);
    $expensesData = fetchAllAssoc($expensesResult);
    $totalSpent = !empty($expensesData) ? (float)($expensesData[0]['total_spent'] ?? 0) : 0;

    // 3. Alert counts specifically for budget issues (handle case where table might not exist)
    $alerts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
    try {
        $alertSql = "SELECT 
                        severity, 
                        COUNT(*) as count 
                     FROM resource_alerts 
                     WHERE status = 'active' AND resource_type = 'budget'
                     GROUP BY severity";
        $alertRows = fetchAllAssoc(runQuery($mysqli, $alertSql));
        foreach ($alertRows as $row) {
            if (isset($row['severity']) && isset($alerts[$row['severity']])) {
                $alerts[$row['severity']] = (int)$row['count'];
            }
        }
    } catch (Exception $e) {
        // Table might not exist, use default values
        error_log("Budget alerts query failed: " . $e->getMessage());
    }

    respond([
        'projects' => $projectData,
        'budget' => [
            'total_allocated' => $totalAllocated,
            'total_spent' => $totalSpent,
            'variance' => $totalAllocated - $totalSpent,
            'utilization_percent' => $totalAllocated > 0 
                ? round(($totalSpent / $totalAllocated) * 100, 1) 
                : 0
        ],
        'alerts' => $alerts,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    respond(['error' => 'Failed to fetch budget summary: ' . $e->getMessage()], 500);
}