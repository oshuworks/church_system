<?php
require_once __DIR__ . '/bootstrap.php';

$limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 25;

$sql = "SELECT 
    alert_id,
    alert_type,
    resource_type,
    resource_id,
    project_id,
    task_id,
    severity,
    message,
    threshold_value,
    current_value,
    status,
    created_at
  FROM resource_alerts
  WHERE status = 'active'
  ORDER BY 
    FIELD(severity, 'critical', 'high', 'medium', 'low'),
    created_at DESC
  LIMIT ?";

$stmt = runQuery($mysqli, $sql, ['i', [$limit]]);
$alerts = fetchAllAssoc($stmt);

respond(['alerts' => $alerts]);

