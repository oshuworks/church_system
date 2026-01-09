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
    -- Calculate dynamic utilization from current assignments
    (SELECT COUNT(*) 
     FROM task_assignments ta 
     WHERE ta.resource_id = e.employee_id 
     AND ta.assignment_type = 'employee' 
     AND ta.status IN ('assigned', 'in_progress')
     AND ta.start_date <= CURDATE()
     AND ta.end_date >= CURDATE()) as current_assignments,
    (SELECT SUM(
        CASE 
            WHEN ta.status = 'completed' THEN ta.actual_hours_used
            WHEN ta.start_date > CURDATE() THEN 0
            WHEN ta.end_date < CURDATE() THEN 
                GREATEST(ta.actual_hours_used, 
                    -- Calculate workdays between start and end dates
                    (5 * FLOOR(DATEDIFF(LEAST(ta.end_date, CURDATE()), ta.start_date) / 7) + 
                     LEAST(DATEDIFF(LEAST(ta.end_date, CURDATE()), ta.start_date) % 7, 5)) * 
                    e.max_work_hours_per_day * (ta.allocation_percentage / 100))
            ELSE 
                GREATEST(ta.actual_hours_used, 
                    -- Calculate workdays from start to today
                    (5 * FLOOR(DATEDIFF(CURDATE(), ta.start_date) / 7) + 
                     LEAST(DATEDIFF(CURDATE(), ta.start_date) % 7, 5) + 1) * 
                    e.max_work_hours_per_day * (ta.allocation_percentage / 100))
        END
     )
     FROM task_assignments ta 
     WHERE ta.resource_id = e.employee_id 
     AND ta.assignment_type = 'employee' 
     AND ta.status IN ('assigned', 'in_progress', 'completed')
     AND ta.start_date <= CURDATE()
     AND ta.end_date >= CURDATE()) as calculated_hours_used,
    (SELECT GROUP_CONCAT(DISTINCT p.project_name SEPARATOR ', ')
     FROM task_assignments ta
     JOIN projects p ON p.project_id = ta.project_id
     WHERE ta.resource_id = e.employee_id 
     AND ta.assignment_type = 'employee' 
     AND ta.status IN ('assigned', 'in_progress')
     AND ta.start_date <= CURDATE()
     AND ta.end_date >= CURDATE()) as assigned_projects
FROM employees e
$where
ORDER BY e.employee_name ASC";

$stmt = runQuery($mysqli, $sql, $types ? [$types, $params] : []);
$employees = fetchAllAssoc($stmt);

// Calculate workload level for each employee
foreach ($employees as &$employee) {
    $totalHours = $employee['max_work_hours_per_day'] * 5; // 5-day work week
    $hoursUsed = $employee['calculated_hours_used'] ?? 0;
    
    if ($totalHours <= 0) {
        $utilizationPercentage = 0;
        $workloadLevel = 'underutilized';
    } else {
        $utilizationPercentage = ($hoursUsed / $totalHours) * 100;
        
        if ($utilizationPercentage < 60) {
            $workloadLevel = 'underutilized';
        } elseif ($utilizationPercentage >= 60 && $utilizationPercentage <= 85) {
            $workloadLevel = 'optimal';
        } elseif ($utilizationPercentage > 85 && $utilizationPercentage <= 100) {
            $workloadLevel = 'overloaded';
        } else {
            $workloadLevel = 'critical';
        }
    }
    
    $employee['latest_utilization'] = round($utilizationPercentage, 2);
    $employee['workload_level'] = $workloadLevel;
    $employee['utilization_date'] = date('Y-m-d');
}

respond(['employees' => $employees]);

