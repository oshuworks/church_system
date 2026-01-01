<?php
require_once __DIR__ . '/bootstrap.php';

$latestDateSql = "SELECT MAX(date) AS latest FROM resource_utilization";
$latestDate = fetchAllAssoc(runQuery($mysqli, $latestDateSql))[0]['latest'] ?? null;

if (!$latestDate) {
    respond(['employees' => [], 'equipment' => []]);
}

$utilSql = "
SELECT resource_type, resource_id, project_id, date, total_hours_available, hours_used, utilization_percentage, workload_level
FROM resource_utilization
WHERE date = ?";

$stmt = runQuery($mysqli, $utilSql, ['s', [$latestDate]]);
$rows = fetchAllAssoc($stmt);

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

foreach ($rows as $row) {
    $entry = [
        'resource_id' => (int) $row['resource_id'],
        'project_id' => $row['project_id'],
        'utilization_percentage' => (float) $row['utilization_percentage'],
        'workload_level' => $row['workload_level'],
    ];
    if ($row['resource_type'] === 'employee') {
        $employees[$row['workload_level']][] = $entry;
    } else {
        $equipment[$row['workload_level']][] = $entry;
    }
}

respond([
    'latest_date' => $latestDate,
    'employees' => $employees,
    'equipment' => $equipment,
]);

