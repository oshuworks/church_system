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
    -- Use actual assignment dates, not today's date, for consistency with project timeline approach
    -- Since we now store allocation_percentage in actual_hours_used for completed assignments,
    -- we need to handle both completed and active assignments differently
    SUM(
        CASE 
            WHEN ta.status = 'completed' THEN 
                -- For completed assignments, actual_hours_used contains the allocation percentage
                -- Convert it back to actual hours: (allocation_percentage/100) * work_days * daily_hours
                CASE 
                    WHEN ta.start_date > ta.end_date THEN 0
                    ELSE 
                        (5 * FLOOR(DATEDIFF(ta.end_date, ta.start_date) / 7) + 
                         LEAST(DATEDIFF(ta.end_date, ta.start_date) % 7, 5)) * 
                        e.max_work_hours_per_day * (ta.actual_hours_used / 100)
                END
            WHEN ta.status = 'assigned' THEN 
                -- For active assignments, calculate based on the assignment duration and allocation percentage
                CASE 
                    WHEN ta.start_date > ta.end_date THEN 0
                    ELSE 
                        (5 * FLOOR(DATEDIFF(ta.end_date, ta.start_date) / 7) + 
                         LEAST(DATEDIFF(ta.end_date, ta.start_date) % 7, 5)) * 
                        e.max_work_hours_per_day * (ta.allocation_percentage / 100)
                END
            ELSE 0
        END
    ) as calculated_hours_used
FROM employees e
LEFT JOIN task_assignments ta ON ta.resource_id = e.employee_id 
    AND ta.assignment_type = 'employee' 
    AND ta.status IN ('assigned', 'completed')
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
    -- Use actual assignment dates, not today's date, for consistency with project timeline approach
    -- Since we now store allocation_percentage in actual_hours_used for completed assignments,
    -- we need to handle both completed and active assignments differently
    SUM(
        CASE 
            WHEN ta.status = 'completed' THEN 
                -- For completed assignments, actual_hours_used contains the allocation percentage
                -- Convert it back to actual hours: (allocation_percentage/100) * work_days * daily_hours
                CASE 
                    WHEN ta.start_date > ta.end_date THEN 0
                    ELSE 
                        (5 * FLOOR(DATEDIFF(ta.end_date, ta.start_date) / 7) + 
                         LEAST(DATEDIFF(ta.end_date, ta.start_date) % 7, 5)) * 
                        8 * (ta.actual_hours_used / 100)
                END
            WHEN ta.status = 'assigned' THEN 
                -- For active assignments, calculate based on the assignment duration and allocation percentage
                CASE 
                    WHEN ta.start_date > ta.end_date THEN 0
                    ELSE 
                        (5 * FLOOR(DATEDIFF(ta.end_date, ta.start_date) / 7) + 
                         LEAST(DATEDIFF(ta.end_date, ta.start_date) % 7, 5)) * 
                        8 * (ta.allocation_percentage / 100)
                END
            ELSE 0
        END
    ) as calculated_hours_used
FROM equipment eq
LEFT JOIN task_assignments ta ON ta.resource_id = eq.equipment_id 
    AND ta.assignment_type = 'equipment' 
    AND ta.status IN ('assigned', 'completed')
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

