<?php
require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// GET: Fetch budget allocations with category details
if ($method === 'GET') {
    $projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : 0;
    
    $sql = "SELECT pb.*, bc.category_name 
            FROM project_budgets pb 
            JOIN budget_categories bc ON pb.category_id = bc.category_id";
    
    $params = [];
    if ($projectId > 0) {
        $sql .= " WHERE pb.project_id = ?";
        $params = ['i', [$projectId]];
    }
    
    $stmt = runQuery($mysqli, $sql, $params);
    respond(['budgets' => fetchAllAssoc($stmt)]);
}

// POST: Set or Update a budget estimate (Requirement 3a)
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $required = ['project_id', 'category_id', 'allocated_amount'];
    foreach ($required as $field) {
        if (!isset($input[$field])) respond(['error' => "Missing field: $field"], 400);
    }

    $projectId = (int)$input['project_id'];
    $categoryId = (int)$input['category_id'];
    $amount = (float)$input['allocated_amount'];
    $fiscalYear = $input['fiscal_year'] ?? date('Y');

    // Check if category already exists for this project to decide INSERT or UPDATE
    $check = fetchAllAssoc(runQuery($mysqli, "SELECT budget_id FROM project_budgets WHERE project_id = ? AND category_id = ?", ['ii', [$projectId, $categoryId]]));

    if (!empty($check)) {
        $sql = "UPDATE project_budgets SET allocated_amount = ?, remaining_amount = allocated_amount - spent_amount WHERE budget_id = ?";
        runQuery($mysqli, $sql, ['di', [$amount, $check[0]['budget_id']]]);
        respond(['message' => 'Budget updated successfully']);
    } else {
        $sql = "INSERT INTO project_budgets (project_id, category_id, allocated_amount, spent_amount, remaining_amount, fiscal_year, status) 
                VALUES (?, ?, ?, 0, ?, ?, 'active')";
        runQuery($mysqli, $sql, ['iidds', [$projectId, $categoryId, $amount, $amount, $fiscalYear]]);
        respond(['message' => 'Budget estimate created'], 201);
    }
}