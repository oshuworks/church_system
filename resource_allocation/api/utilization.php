<?php
require_once __DIR__ . '/bootstrap.php';

// Function to calculate utilization percentage and workload level
function calculateUtilization($totalHoursAvailable, $hoursUsed) {
    if ($totalHoursAvailable <= 0) return ['percentage' => 0, 'level' => 'underutilized'];
    
    $percentage = ($hoursUsed / $totalHoursAvailable) * 100;
    
    if ($percentage < 60) {
        $level = 'underutilized';
    } elseif ($percentage >= 60 && $percentage <= 85) {
        $level = 'optimal';
    } elseif ($percentage > 85 && $percentage <= 100) {
        $level = 'overloaded';
    } else {
        $level = 'critical';
    }
    
    return ['percentage' => round($percentage, 2), 'level' => $level];
}

// Get current date
$today = date('Y-m-d');

// Calculate employee utilization from current assignments
$employeeSql = "
SELECT 
    e.employee_id as resource_id,
    e.max_work_hours_per_day,
    e.status,
    COUNT(ta.assignment_id) as assignment_count,
    SUM(ta.allocation_percentage) as total_allocation,
    -- Calculate actual hours worked based on assignment dates and allocation
    SUM(
        CASE 
            WHEN ta.start_date > CURDATE() THEN 0
            WHEN ta.end_date < CURDATE() THEN 
                -- Calculate workdays between start and end dates
                (5 * FLOOR(DATEDIFF(LEAST(ta.end_date, CURDATE()), ta.start_date) / 7) + 
                 LEAST(DATEDIFF(LEAST(ta.end_date, CURDATE()), ta.start_date) % 7, 5)) * 
                e.max_work_hours_per_day * (ta.allocation_percentage / 100)
            ELSE 
                -- Calculate workdays from start to today
                (5 * FLOOR(DATEDIFF(CURDATE(), ta.start_date) / 7) + 
                 LEAST(DATEDIFF(CURDATE(), ta.start_date) % 7, 5) + 1) * 
                e.max_work_hours_per_day * (ta.allocation_percentage / 100)
        END
    ) as calculated_hours_used
FROM employees e
LEFT JOIN task_assignments ta ON ta.resource_id = e.employee_id 
    AND ta.assignment_type = 'employee' 
    AND ta.status = 'assigned'
    AND ta.start_date <= CURDATE()
    AND ta.end_date >= CURDATE()
WHERE e.status = 'active'
GROUP BY e.employee_id, e.max_work_hours_per_day, e.status";

$employeeStmt = runQuery($mysqli, $employeeSql);
$employeeRows = fetchAllAssoc($employeeStmt);

// Calculate equipment utilization from current assignments
$equipmentSql = "
SELECT 
    eq.equipment_id as resource_id,
    eq.total_capacity_hours,
    eq.status,
    COUNT(ta.assignment_id) as assignment_count,
    SUM(ta.allocation_percentage) as total_allocation,
    -- Calculate actual hours used based on assignment dates and allocation
    SUM(
        CASE 
            WHEN ta.start_date > CURDATE() THEN 0
            WHEN ta.end_date < CURDATE() THEN 
                -- Calculate workdays between start and end dates (8 hours/day standard)
                (5 * FLOOR(DATEDIFF(LEAST(ta.end_date, CURDATE()), ta.start_date) / 7) + 
                 LEAST(DATEDIFF(LEAST(ta.end_date, CURDATE()), ta.start_date) % 7, 5)) * 
                8 * (ta.allocation_percentage / 100)
            ELSE 
                -- Calculate workdays from start to today (8 hours/day standard)
                (5 * FLOOR(DATEDIFF(CURDATE(), ta.start_date) / 7) + 
                 LEAST(DATEDIFF(CURDATE(), ta.start_date) % 7, 5) + 1) * 
                8 * (ta.allocation_percentage / 100)
        END
    ) as calculated_hours_used
FROM equipment eq
LEFT JOIN task_assignments ta ON ta.resource_id = eq.equipment_id 
    AND ta.assignment_type = 'equipment' 
    AND ta.status = 'assigned'
    AND ta.start_date <= CURDATE()
    AND ta.end_date >= CURDATE()
WHERE eq.status IN ('available', 'in_use')
GROUP BY eq.equipment_id, eq.total_capacity_hours, eq.status";

$equipmentStmt = runQuery($mysqli, $equipmentSql);
$equipmentRows = fetchAllAssoc($equipmentStmt);

$bucket = function () {
    return [
        'underutilized' => [],
        'optimal' => [],
        'overloaded' => [],
        'critical' => [],
    ];
};

$employees = $bucket();
$equipment = $bucket();

// Process employee utilization
foreach ($employeeRows as $row) {
    $totalHours = $row['max_work_hours_per_day'] * 5; // 5-day work week
    $hoursUsed = $row['calculated_hours_used'] ?? 0;
    $utilization = calculateUtilization($totalHours, $hoursUsed);
    
    $entry = [
        'resource_id' => (int) $row['resource_id'],
        'project_id' => null, // Can be enhanced to track primary project
        'utilization_percentage' => $utilization['percentage'],
        'workload_level' => $utilization['level'],
        'total_hours_available' => $totalHours,
        'hours_used' => $hoursUsed,
        'assignment_count' => (int) $row['assignment_count'],
        'total_allocation' => (float) $row['total_allocation'] ?? 0
    ];
    
    $employees[$utilization['level']][] = $entry;
}

// Process equipment utilization
foreach ($equipmentRows as $row) {
    $totalHours = $row['total_capacity_hours'] > 0 ? $row['total_capacity_hours'] : 40; // Default 40 hours/week
    $hoursUsed = $row['calculated_hours_used'] ?? 0;
    $utilization = calculateUtilization($totalHours, $hoursUsed);
    
    $entry = [
        'resource_id' => (int) $row['resource_id'],
        'project_id' => null, // Can be enhanced to track primary project
        'utilization_percentage' => $utilization['percentage'],
        'workload_level' => $utilization['level'],
        'total_hours_available' => $totalHours,
        'hours_used' => $hoursUsed,
        'assignment_count' => (int) $row['assignment_count'],
        'total_allocation' => (float) $row['total_allocation'] ?? 0
    ];
    
    $equipment[$utilization['level']][] = $entry;
}

respond([
    'latest_date' => $today,
    'employees' => $employees,
    'equipment' => $equipment,
    'calculation_method' => 'dynamic_from_assignments'
]);

