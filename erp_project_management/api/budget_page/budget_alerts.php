<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * Budget Alerts API
 * Fetches budget-related alerts from resource_alerts and generates dynamic alerts
 * based on budget burn rates (projects over 90% or over 100% budget)
 */

try {
    $alerts = [];
    
    // 1. Fetch alerts from resource_alerts table where resource_type = 'budget'
    try {
        $alertSql = "SELECT 
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
                     WHERE status = 'active' AND resource_type = 'budget'
                     ORDER BY 
                        FIELD(severity, 'critical', 'high', 'medium', 'low'),
                        created_at DESC
                     LIMIT 50";
        $alertRows = fetchAllAssoc(runQuery($mysqli, $alertSql));
        
        foreach ($alertRows as $row) {
            $alerts[] = [
                'id' => $row['alert_id'],
                'type' => $row['alert_type'] ?? 'budget_warning',
                'severity' => $row['severity'] ?? 'medium',
                'message' => $row['message'] ?? 'Budget alert',
                'project_id' => $row['project_id'] ?? null,
                'threshold_value' => $row['threshold_value'] ?? null,
                'current_value' => $row['current_value'] ?? null,
                'created_at' => $row['created_at'] ?? null,
                'source' => 'database'
            ];
        }
    } catch (Exception $e) {
        // Table might not exist, continue with dynamic alerts
        error_log("Budget alerts query failed: " . $e->getMessage());
    }
    
    // 2. Generate dynamic alerts and risk rates for ALL projects with budgets
    // Get projects with budget data and calculate burn rates (include all statuses)
    $projectBudgetSql = "SELECT 
                            p.project_id, 
                            p.project_name,
                            p.status as project_status,
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
                        HAVING budget > 0
                        ORDER BY p.project_name ASC";
    
    $projectBudgets = fetchAllAssoc(runQuery($mysqli, $projectBudgetSql));
    
    foreach ($projectBudgets as $proj) {
        $budget = (float)($proj['budget'] ?? 0);
        $actual = (float)($proj['actual'] ?? 0);
        
        if ($budget <= 0) continue;
        
        $burnRate = ($actual / $budget) * 100;
        $projectId = (int)$proj['project_id'];
        $projectName = $proj['project_name'] ?? 'Unnamed Project';
        
        // Check if we already have an alert for this project from database
        $hasExistingAlert = false;
        foreach ($alerts as $alert) {
            if ($alert['project_id'] === $projectId && $alert['source'] === 'database') {
                $hasExistingAlert = true;
                break;
            }
        }
        
        // Determine severity based on burn rate
        $severity = 'low';
        $alertType = 'within_budget';
        $message = '';
        
        if ($burnRate > 100) {
            // Critical: Over budget
            $severity = 'critical';
            $alertType = 'over_budget';
            $message = "Project \"{$projectName}\" has exceeded its budget by " . number_format($burnRate - 100, 1) . "%";
        } else if ($burnRate >= 90 && $burnRate <= 100) {
            // High: Near budget limit
            $severity = 'high';
            $alertType = 'near_budget_limit';
            $message = "Project \"{$projectName}\" is at " . number_format($burnRate, 1) . "% of budget allocation";
        } else if ($burnRate >= 75 && $burnRate < 90) {
            // Medium: Approaching budget limit
            $severity = 'medium';
            $alertType = 'approaching_budget_limit';
            $message = "Project \"{$projectName}\" has used " . number_format($burnRate, 1) . "% of budget";
        } else {
            // Low: Within safe limits, but still show it
            $severity = 'low';
            $alertType = 'within_budget';
            $message = "Project \"{$projectName}\" has used " . number_format($burnRate, 1) . "% of budget";
        }
        
        // Always add project to alerts list so all projects with budgets are displayed
        $alerts[] = [
            'id' => 'dynamic_' . $projectId . '_' . $alertType,
            'type' => $alertType,
            'severity' => $severity,
            'message' => $message,
            'project_id' => $projectId,
            'project_name' => $projectName,
            'project_status' => $proj['project_status'] ?? 'active',
            'threshold_value' => $budget,
            'current_value' => $actual,
            'burn_rate' => round($burnRate, 1),
            'created_at' => date('Y-m-d H:i:s'),
            'source' => 'dynamic'
        ];
    }
    
    // Sort alerts by severity (critical, high, medium, low) and then by created_at
    usort($alerts, function($a, $b) {
        $severityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        $severityA = $severityOrder[$a['severity']] ?? 99;
        $severityB = $severityOrder[$b['severity']] ?? 99;
        
        if ($severityA !== $severityB) {
            return $severityA - $severityB;
        }
        
        // If same severity, sort by created_at (newest first)
        $dateA = strtotime($a['created_at'] ?? '1970-01-01');
        $dateB = strtotime($b['created_at'] ?? '1970-01-01');
        return $dateB - $dateA;
    });
    
    respond([
        'success' => true,
        'alerts' => $alerts,
        'count' => count($alerts)
    ]);
    
} catch (Exception $e) {
    respond(['error' => 'Failed to fetch budget alerts: ' . $e->getMessage()], 500);
}

