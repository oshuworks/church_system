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
    ru.utilization_percentage AS latest_utilization,
    ru.workload_level,
    ru.date AS utilization_date
FROM equipment eq
LEFT JOIN resource_utilization ru
    ON ru.resource_type = 'equipment'
   AND ru.resource_id = eq.equipment_id
   AND ru.date = (
        SELECT MAX(r2.date)
        FROM resource_utilization r2
        WHERE r2.resource_type = 'equipment'
          AND r2.resource_id = eq.equipment_id
   )
$where
ORDER BY eq.equipment_name ASC";

$stmt = runQuery($mysqli, $sql, $types ? [$types, $params] : []);
$equipment = fetchAllAssoc($stmt);

respond(['equipment' => $equipment]);

