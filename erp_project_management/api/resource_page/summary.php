<?php
require_once __DIR__ . '/bootstrap.php';

// Project counts with dynamic status calculation
$projectSql = "
SELECT 
    COUNT(*) AS total_projects,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_projects,
    SUM(CASE WHEN status = 'planning' THEN 1 ELSE 0 END) AS planning_projects,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_projects
FROM projects";

// First update project statuses based on task completion
require_once __DIR__ . '/update_project_status.php';
updateProjectStatus($mysqli);

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
    COALESCE(SUM(allocated_amount), 0) AS total_allocated
  FROM project_budgets";
$budgetAllocated = fetchAllAssoc(runQuery($mysqli, $budgetSql))[0]['total_allocated'] ?? 0;

// Get actual spent from budget_expenses table (matches budget cost tracking)
$expensesSql = "SELECT COALESCE(SUM(amount), 0) as total_spent FROM budget_expenses";
$expensesData = fetchAllAssoc(runQuery($mysqli, $expensesSql))[0] ?? [];
$budgetSpent = $expensesData['total_spent'] ?? 0;

$budgetTotals = [
    'total_allocated' => $budgetAllocated,
    'total_spent' => $budgetSpent
];

// Alerts by severity
$alertsSql = "SELECT 
    SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) AS critical,
    SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) AS high,
    SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) AS medium,
    SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) AS low
  FROM resource_alerts
  WHERE status = 'active'";
$alertCounts = fetchAllAssoc(runQuery($mysqli, $alertsSql))[0] ?? [];

// Dynamic utilization distribution based on current task assignments
$utilizationSql = "
WITH employee_utilization AS (
    SELECT 
        e.employee_id,
        e.max_work_hours_per_day,
        COALESCE(SUM(
            CASE 
                WHEN ta.status = 'completed' THEN ta.actual_hours_used
                WHEN ta.start_date > CURDATE() THEN 0
                WHEN ta.end_date < CURDATE() THEN 
                    GREATEST(ta.actual_hours_used, 
                        DATEDIFF(LEAST(ta.end_date, CURDATE()), ta.start_date) * 8 * (ta.allocation_percentage / 100))
                ELSE 
                    GREATEST(ta.actual_hours_used, 
                        (DATEDIFF(CURDATE(), ta.start_date) + 1) * 8 * (ta.allocation_percentage / 100))
            END
        ), 0) as calculated_hours_used
    FROM employees e
    LEFT JOIN task_assignments ta ON ta.resource_id = e.employee_id 
        AND ta.assignment_type = 'employee' 
        AND ta.status IN ('assigned', 'in_progress')
        AND ta.start_date <= CURDATE()
        AND ta.end_date >= CURDATE()
    WHERE e.status = 'active'
    GROUP BY e.employee_id, e.max_work_hours_per_day
),
equipment_utilization AS (
    SELECT 
        eq.equipment_id,
        COALESCE(eq.total_capacity_hours, 40) as total_capacity_hours,
        COALESCE(SUM(
            CASE 
                WHEN ta.status = 'completed' THEN ta.actual_hours_used
                WHEN ta.start_date > CURDATE() THEN 0
                WHEN ta.end_date < CURDATE() THEN 
                    GREATEST(ta.actual_hours_used, 
                        DATEDIFF(LEAST(ta.end_date, CURDATE()), ta.start_date) * 8 * (ta.allocation_percentage / 100))
                ELSE 
                    GREATEST(ta.actual_hours_used, 
                        (DATEDIFF(CURDATE(), ta.start_date) + 1) * 8 * (ta.allocation_percentage / 100))
            END
        ), 0) as calculated_hours_used
    FROM equipment eq
    LEFT JOIN task_assignments ta ON ta.resource_id = eq.equipment_id 
        AND ta.assignment_type = 'equipment' 
        AND ta.status IN ('assigned', 'in_progress')
        AND ta.start_date <= CURDATE()
        AND ta.end_date >= CURDATE()
    WHERE eq.status != 'retired'
    GROUP BY eq.equipment_id, eq.total_capacity_hours
),
all_utilization AS (
    SELECT 
        'employee' as resource_type,
        employee_id as resource_id,
        (max_work_hours_per_day * 5) as total_hours_available,
        calculated_hours_used
    FROM employee_utilization
    
    UNION ALL
    
    SELECT 
        'equipment' as resource_type,
        equipment_id as resource_id,
        total_capacity_hours as total_hours_available,
        calculated_hours_used
    FROM equipment_utilization
)
SELECT 
    CASE 
        WHEN (total_hours_available <= 0 OR calculated_hours_used IS NULL) THEN 'underutilized'
        WHEN ((calculated_hours_used / total_hours_available) * 100) < 60 THEN 'underutilized'
        WHEN ((calculated_hours_used / total_hours_available) * 100) >= 60 
             AND ((calculated_hours_used / total_hours_available) * 100) <= 85 THEN 'optimal'
        WHEN ((calculated_hours_used / total_hours_available) * 100) > 85 
             AND ((calculated_hours_used / total_hours_available) * 100) <= 100 THEN 'overloaded'
        WHEN ((calculated_hours_used / total_hours_available) * 100) > 100 THEN 'critical'
        ELSE 'underutilized'
    END as workload_level,
    COUNT(*) as total
FROM all_utilization
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

