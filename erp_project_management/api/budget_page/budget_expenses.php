<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * Budget Expenses API
 * Fetches project summary or detailed expenses
 */

$allProjects = isset($_GET['all']) && $_GET['all'] === 'true';

if ($allProjects) {
    // Aggregates data for the "Project Financial Health" table
    // Use project_budgets for allocated amounts (sum per project) and budget_expenses for actual spent
    $sql = "SELECT 
                p.project_id, 
                p.project_name, 
                p.status,
                COALESCE((
                    SELECT SUM(allocated_amount) 
                    FROM project_budgets pb 
                    WHERE pb.project_id = p.project_id
                ), COALESCE(p.budget_allocated, 0), 0) as budget,
                COALESCE((
                    SELECT SUM(amount) 
                    FROM budget_expenses be 
                    WHERE be.project_id = p.project_id
                ), 0) as actual
            FROM projects p
            ORDER BY p.project_name ASC";

    $stmt = runQuery($mysqli, $sql);
    $projects = fetchAllAssoc($stmt);

    // Return the data in the format budgeting.js expects
    respond([
        'success' => true,
        'projects' => $projects
    ]);
    exit;
}

// Default mode: List specific expenses
$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$whereSql = $projectId > 0 ? "WHERE be.project_id = $projectId" : "";

$sql = "SELECT be.*, p.project_name 
        FROM budget_expenses be 
        JOIN projects p ON be.project_id = p.project_id 
        $whereSql 
        ORDER BY be.expense_date DESC";

$stmt = runQuery($mysqli, $sql);
$expenses = fetchAllAssoc($stmt);

respond([
    'success' => true,
    'expenses' => $expenses
]);