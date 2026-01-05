<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * Budget vs. Actual Report API (Requirement 3c)
 * Generates detailed financial reports per project category.
 */

$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

if ($projectId === 0) {
    respond(['error' => 'Project ID required to generate report'], 400);
}

// SQL to calculate variance and burn rate
$sql = "SELECT 
            bc.category_name,
            pb.allocated_amount as budget,
            pb.spent_amount as actual,
            (pb.allocated_amount - pb.spent_amount) as variance,
            CASE 
                WHEN pb.allocated_amount > 0 THEN (pb.spent_amount / pb.allocated_amount) * 100 
                ELSE 0 
            END as burn_rate_percentage,
            CASE
                WHEN pb.allocated_amount > 0 THEN 100 - ((pb.spent_amount / pb.allocated_amount) * 100)
                ELSE 0
            END as remaining_percentage
        FROM project_budgets pb
        JOIN budget_categories bc ON pb.category_id = bc.category_id
        WHERE pb.project_id = ?
        ORDER BY bc.category_name ASC";

$stmt = runQuery($mysqli, $sql, ['i', [$projectId]]);
$report = fetchAllAssoc($stmt);

// Calculate high-level summary totals
$totalBudget = array_sum(array_column($report, 'budget'));
$totalActual = array_sum(array_column($report, 'actual'));
$totalVariance = $totalBudget - $totalActual;
$overallBurnRate = $totalBudget > 0 ? ($totalActual / $totalBudget) * 100 : 0;

respond([
    'project_id' => $projectId,
    'generated_at' => date('Y-m-d H:i:s'),
    'report' => $report,
    'summary' => [
        'total_budget' => $totalBudget,
        'total_actual' => $totalActual,
        'total_variance' => $totalVariance,
        'overall_burn_rate' => round($overallBurnRate, 2),
        'status' => ($overallBurnRate > 100) ? 'over_budget' : (($overallBurnRate > 90) ? 'near_limit' : 'under_budget')
    ]
]);