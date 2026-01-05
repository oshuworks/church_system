<?php
require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    if (!isset($input['project_id']) || !isset($input['amount'])) {
        respond(['error' => "Missing project_id or amount"], 400);
    }

    $projectId   = (int)$input['project_id'];
    $amount      = (float)$input['amount'];
    $description = $input['description'] ?? $input['notes'] ?? '';
    $type        = $input['cost_type'] ?? $input['category_id'] ?? 'other';

    // ✅ FIX: Use null instead of 0 for task_id to satisfy Foreign Key constraint
    $taskId = !empty($input['task_id']) ? (int)$input['task_id'] : null;

    // 1. Map category to your DB IDs
    $categoryMap = ['labor' => 1, 'materials' => 2, 'equipment' => 2, 'travel' => 3, 'other' => 3];
    $catId = $categoryMap[$type] ?? 1;

    // 2. Lookup budget_id from project_budgets
    $budgetQuery = "SELECT budget_id FROM project_budgets WHERE project_id = ? AND category_id = ? LIMIT 1";
    $budgetResult = runQuery($mysqli, $budgetQuery, ['ii', [$projectId, $catId]]);
    $budgetData = fetchAllAssoc($budgetResult);

    if (empty($budgetData)) {
        $fallbackQuery = "SELECT budget_id FROM project_budgets WHERE project_id = ? LIMIT 1";
        $fallbackResult = runQuery($mysqli, $fallbackQuery, ['i', [$projectId]]);
        $fallbackData = fetchAllAssoc($fallbackResult);
        
        if (empty($fallbackData)) {
            respond(['error' => "No budget found. Please assign a budget to this project first."], 400);
            exit;
        }
        $budgetId = $fallbackData[0]['budget_id'];
    } else {
        $budgetId = $budgetData[0]['budget_id'];
    }

    // 3. INSERT INTO budget_expenses
    // ✅ Change: The type definition for runQuery needs to handle 'i' or null. 
    $sql = "INSERT INTO budget_expenses (project_id, budget_id, task_id, expense_description, amount, expense_type, expense_date) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    try {
        // Using 'i' for taskId even if null works in most runQuery wrappers, 
        // but passing the actual null variable is key.
        runQuery($mysqli, $sql, ['iiisds', [$projectId, $budgetId, $taskId, $description, $amount, $type]]);

        // 4. UPDATE TOTALS
        $updateSql = "UPDATE project_budgets 
                      SET spent_amount = spent_amount + ?, 
                          remaining_amount = allocated_amount - (spent_amount + ?)
                      WHERE budget_id = ?";
        
        runQuery($mysqli, $updateSql, ['ddi', [$amount, $amount, $budgetId]]);

        respond(['success' => true, 'message' => 'Expense logged successfully!']);

    } catch (Exception $e) {
        respond(['success' => false, 'error' => "Database error: " . $e->getMessage()], 500);
    }
}

if ($method === 'GET') {
    $projectId = (int)($_GET['project_id'] ?? 0);
    $sql = "SELECT be.*, p.project_name, t.task_name 
            FROM budget_expenses be
            LEFT JOIN projects p ON be.project_id = p.project_id
            LEFT JOIN tasks t ON be.task_id = t.task_id";
    if ($projectId > 0) $sql .= " WHERE be.project_id = $projectId";
    $sql .= " ORDER BY be.expense_date DESC";
    respond(['costs' => fetchAllAssoc(runQuery($mysqli, $sql))]);
}