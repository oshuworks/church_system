<?php
require_once __DIR__ . '/bootstrap.php';

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

$conditions = [];
$params = [];
$types = '';

if ($search !== '') {
    $conditions[] = "(eq.equipment_name LIKE CONCAT('%', ?, '%') OR eq.equipment_type LIKE CONCAT('%', ?, '%') OR eq.location LIKE CONCAT('%', ?, '%'))";
    $types .= 'sss';
    $params = array_merge($params, [$search, $search, $search]);
}

if ($status !== '') {
    $conditions[] = "eq.status = ?";
    $types .= 's';
    $params[] = $status;
}

$where = '';
if (!empty($conditions)) {
    $where = 'WHERE ' . implode(' AND ', $conditions);
}

$sql = "
SELECT 
    eq.equipment_id,
    eq.equipment_name,
    eq.equipment_type,
    eq.description,
    eq.hourly_rate,
    eq.maintenance_cost,
    eq.total_capacity_hours,
    eq.used_hours,
    eq.utilization_percentage,
    eq.status,
    eq.location,
    -- Calculate dynamic utilization from current assignments
    (SELECT COUNT(*) 
     FROM task_assignments ta 
     WHERE ta.resource_id = eq.equipment_id 
     AND ta.assignment_type = 'equipment' 
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
                    8 * (ta.allocation_percentage / 100))
            ELSE 
                GREATEST(ta.actual_hours_used, 
                    -- Calculate workdays from start to today
                    (5 * FLOOR(DATEDIFF(CURDATE(), ta.start_date) / 7) + 
                     LEAST(DATEDIFF(CURDATE(), ta.start_date) % 7, 5) + 1) * 
                    8 * (ta.allocation_percentage / 100))
        END
     )
     FROM task_assignments ta 
     WHERE ta.resource_id = eq.equipment_id 
     AND ta.assignment_type = 'equipment' 
     AND ta.status IN ('assigned', 'in_progress', 'completed')
     AND ta.start_date <= CURDATE()
     AND ta.end_date >= CURDATE()) as calculated_hours_used,
    (SELECT GROUP_CONCAT(DISTINCT p.project_name SEPARATOR ', ')
     FROM task_assignments ta
     JOIN projects p ON p.project_id = ta.project_id
     WHERE ta.resource_id = eq.equipment_id 
     AND ta.assignment_type = 'equipment' 
     AND ta.status IN ('assigned', 'in_progress')
     AND ta.start_date <= CURDATE()
     AND ta.end_date >= CURDATE()) as assigned_projects
FROM equipment eq
$where
ORDER BY eq.equipment_name ASC";

$stmt = runQuery($mysqli, $sql, $types ? [$types, $params] : []);
$equipment = fetchAllAssoc($stmt);

// Calculate workload level for each equipment
foreach ($equipment as &$eq) {
    $totalHours = $eq['total_capacity_hours'] > 0 ? $eq['total_capacity_hours'] : 40; // Default 40 hours/week
    $hoursUsed = $eq['calculated_hours_used'] ?? 0;
    
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
    
    $eq['latest_utilization'] = round($utilizationPercentage, 2);
    $eq['workload_level'] = $workloadLevel;
    $eq['utilization_date'] = date('Y-m-d');
}

respond(['equipment' => $equipment]);

