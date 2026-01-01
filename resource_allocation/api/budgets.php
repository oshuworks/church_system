<?php
require_once __DIR__ . '/bootstrap.php';

$projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : 0;
$conditions = [];
$types = '';
$params = [];

if ($projectId > 0) {
    $conditions[] = "pb.project_id = ?";
    $types .= 'i';
    $params[] = $projectId;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$sql = "
SELECT 
    pb.budget_id,
    pb.project_id,
    pb.category_id,
    pb.allocated_amount,
    pb.spent_amount,
    pb.remaining_amount,
    pb.fiscal_year,
    pb.status,
    bc.category_name
FROM project_budgets pb
JOIN budget_categories bc ON bc.category_id = pb.category_id
$where
ORDER BY pb.project_id ASC, bc.category_name ASC";

$stmt = runQuery($mysqli, $sql, $types ? [$types, $params] : []);
$budgets = fetchAllAssoc($stmt);

respond(['budgets' => $budgets]);

