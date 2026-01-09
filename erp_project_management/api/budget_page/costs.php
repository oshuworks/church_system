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
    $expenseDate = $input['expense_date'] ?? date('Y-m-d');

    $taskId = !empty($input['task_id']) ? (int)$input['task_id'] : null;

    $categoryMap = ['labor' => 1, 'materials' => 2, 'equipment' => 2, 'travel' => 3, 'other' => 3];
    $catId = $categoryMap[$type] ?? 1;

    // Check if budget exists for this project (regardless of category)
    $budgetQuery = "SELECT budget_id, allocated_amount, spent_amount FROM project_budgets WHERE project_id = ?";
    $budgetResult = runQuery($mysqli, $budgetQuery, ['i', [$projectId]]);
    $budgetData = fetchAllAssoc($budgetResult);
    
    // Debug: Show all budget entries for this project
    error_log("All budget entries for project $projectId: " . json_encode($budgetData));

    if (empty($budgetData)) {
        // No budget exists - check if project has budget allocated
        $projectBudgetQuery = "SELECT COALESCE(budget_allocated, 0) as project_budget FROM projects WHERE project_id = ? LIMIT 1";
        $projectBudgetResult = runQuery($mysqli, $projectBudgetQuery, ['i', [$projectId]]);
        $projectBudgetData = fetchAllAssoc($projectBudgetResult);
        $projectBudget = !empty($projectBudgetData) ? (float)($projectBudgetData[0]['project_budget'] ?? 0) : 0;
        
        if ($projectBudget <= 0) {
            // Project has no budget allocated - show alert
            respond([
                'success' => false,
                'error' => 'This project has no budget allocated yet. Please allocate a budget in Project Management before adding expenses.'
            ], 400);
            exit;
        }
        
        // Calculate existing expenses for this project
        $existingExpensesQuery = "SELECT COALESCE(SUM(amount), 0) as total_spent FROM budget_expenses WHERE project_id = ?";
        $existingExpensesResult = runQuery($mysqli, $existingExpensesQuery, ['i', [$projectId]]);
        $existingExpensesData = fetchAllAssoc($existingExpensesResult);
        $totalSpent = !empty($existingExpensesData) ? (float)($existingExpensesData[0]['total_spent'] ?? 0) : 0;
        
        // Create single project budget entry
        $fiscalYear = date('Y');
        $createBudgetSql = "INSERT INTO project_budgets (project_id, category_id, allocated_amount, spent_amount, fiscal_year, status) 
                           VALUES (?, ?, ?, ?, ?, 'active')";
        runQuery($mysqli, $createBudgetSql, ['iddss', [$projectId, $catId, $projectBudget, $totalSpent, $fiscalYear]]);
        
        // Get the newly created budget_id
        $newBudgetResult = runQuery($mysqli, $budgetQuery, ['i', [$projectId]]);
        $newBudgetData = fetchAllAssoc($newBudgetResult);
        $budgetId = $newBudgetData[0]['budget_id'];
    } else {
        // Multiple budget entries exist - use the first one and clean up duplicates
        $budgetId = $budgetData[0]['budget_id'];
        
        // Calculate totals from all entries
        $totalAllocated = array_sum(array_column($budgetData, 'allocated_amount'));
        $totalSpent = array_sum(array_column($budgetData, 'spent_amount'));
        
        error_log("Project $projectId totals - Allocated: $totalAllocated, Spent: $totalSpent");
        
        // Update the first budget entry with correct totals
        $updateSql = "UPDATE project_budgets SET allocated_amount = ?, spent_amount = ? WHERE budget_id = ?";
        runQuery($mysqli, $updateSql, ['ddi', [$totalAllocated, $totalSpent, $budgetId]]);
        
        // Delete duplicate budget entries
        $duplicateIds = array_map(function($entry) use ($budgetId) {
            return $entry['budget_id'] != $budgetId ? $entry['budget_id'] : null;
        }, $budgetData);
        $duplicateIds = array_filter($duplicateIds);
        
        if (!empty($duplicateIds)) {
            $deleteSql = "DELETE FROM project_budgets WHERE budget_id IN (" . implode(',', $duplicateIds) . ")";
            runQuery($mysqli, $deleteSql);
            error_log("Deleted duplicate budget entries: " . implode(',', $duplicateIds));
        }
    }

    // Check remaining budget using actual expenses from budget_expenses table
    $actualExpensesQuery = "SELECT COALESCE(SUM(amount), 0) as actual_spent FROM budget_expenses WHERE project_id = ?";
    $actualExpensesResult = runQuery($mysqli, $actualExpensesQuery, ['i', [$projectId]]);
    $actualExpensesData = fetchAllAssoc($actualExpensesResult);
    $actualSpent = !empty($actualExpensesData) ? (float)($actualExpensesData[0]['actual_spent'] ?? 0) : 0;
    
    // Get allocated amount from project_budgets
    $allocatedQuery = "SELECT allocated_amount FROM project_budgets WHERE budget_id = ? LIMIT 1";
    $allocatedResult = runQuery($mysqli, $allocatedQuery, ['i', [$budgetId]]);
    $allocatedData = fetchAllAssoc($allocatedResult);
    $allocated = !empty($allocatedData) ? (float)($allocatedData[0]['allocated_amount'] ?? 0) : 0;
    
    $remaining = $allocated - $actualSpent;
    
    // Debug logging
    error_log("Budget Check - Project: $projectId, Allocated: $allocated, Actual Spent: $actualSpent, Remaining: $remaining, Attempted: $amount");
    
    // Prevent expenses that exceed the remaining budget
    if ($amount > $remaining) {
        respond([
            'success' => false,
            'error' => "Expense exceeds remaining budget. Allocated: ₱" . number_format($allocated, 2) . " | Spent: ₱" . number_format($actualSpent, 2) . " | Remaining: ₱" . number_format($remaining, 2) . " | Attempted: ₱" . number_format($amount, 2)
        ], 400);
        exit;
    }

    $sql = "INSERT INTO budget_expenses (project_id, budget_id, task_id, expense_description, amount, expense_type, expense_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    try {
        runQuery($mysqli, $sql, ['iiisdss', [$projectId, $budgetId, $taskId, $description, $amount, $type, $expenseDate]]);

        // Update spent_amount - remaining_amount is auto-calculated by MySQL (GENERATED column)
        $updateSql = "UPDATE project_budgets 
                      SET spent_amount = spent_amount + ?
                      WHERE budget_id = ?";
        
        runQuery($mysqli, $updateSql, ['di', [$amount, $budgetId]]);

        // Trigger budget notification check after adding expense
        try {
            $notifUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/church_system-main_m/api/budget_notifications.php';
            $context = stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]);
            @file_get_contents($notifUrl, false, $context);
        } catch (Exception $e) {
            // Silent fail - notifications are not critical
            error_log("Warning: Budget notification check failed: " . $e->getMessage());
        }

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
