<?php
// budget_notifications.php - API endpoint for checking and creating budget notifications
include '../includes/db.php';
header('Content-Type: application/json');

// Define budget alert thresholds
define('BUDGET_APPROACHING_PERCENT', 80); // Alert when spent reaches 80% of budget
define('BUDGET_EXCEEDED_PERCENT', 100);   // Alert when spent exceeds budget

// Function to check and create budget notifications
function checkAndCreateBudgetNotifications($conn) {
    $today = date('Y-m-d');
    $notificationsCreated = 0;
    error_log("DEBUG: Budget notifications check starting. Today: {$today}");
    
    try {
        // Check for projects with budget approaching the limit
        $stmt = $conn->prepare("
            SELECT p.project_id, p.project_name, p.budget_allocated,
                   COALESCE(SUM(CASE WHEN be.status != 'cancelled' THEN be.amount ELSE 0 END), 0) as total_spent,
                   ROUND((COALESCE(SUM(CASE WHEN be.status != 'cancelled' THEN be.amount ELSE 0 END), 0) / p.budget_allocated * 100), 2) as budget_percentage
            FROM projects p
            LEFT JOIN budget_expenses be ON p.project_id = be.project_id
            WHERE p.budget_allocated > 0
            GROUP BY p.project_id, p.project_name, p.budget_allocated
            HAVING budget_percentage >= ? AND budget_percentage < ?
        ");
        $approaching_threshold = BUDGET_APPROACHING_PERCENT;
        $exceeded_threshold = BUDGET_EXCEEDED_PERCENT;
        $stmt->bind_param("ii", $approaching_threshold, $exceeded_threshold);
        $stmt->execute();
        $approachingBudgets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($approachingBudgets as $project) {
            // Check if notification already exists TODAY (prevent duplicates - once per day)
            $checkStmt = $conn->prepare("
                SELECT notification_id FROM resource_notifications 
                WHERE project_id = ? 
                AND notification_type = 'budget_approaching' 
                AND DATE(created_at) = ?
            ");
            $checkStmt->bind_param("is", $project['project_id'], $today);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows == 0) {
                $insertStmt = $conn->prepare("
                    INSERT INTO resource_notifications (project_id, notification_type, message, is_read)
                    VALUES (?, 'budget_approaching', ?, FALSE)
                ");
                $remaining = $project['budget_allocated'] - $project['total_spent'];
                $message = "⚠️ Project '{$project['project_name']}' budget is approaching the limit! " . 
                          number_format($project['budget_percentage'], 2) . "% spent (₱" . 
                          number_format($project['total_spent'], 2) . " of ₱" . 
                          number_format($project['budget_allocated'], 2) . "). Remaining: ₱" . 
                          number_format($remaining, 2);
                $insertStmt->bind_param("is", $project['project_id'], $message);
                $insertStmt->execute();
                $notificationsCreated++;
            }
        }
        
        // Check for projects with budget exceeded
        $stmt = $conn->prepare("
            SELECT p.project_id, p.project_name, p.budget_allocated,
                   COALESCE(SUM(CASE WHEN be.status != 'cancelled' THEN be.amount ELSE 0 END), 0) as total_spent,
                   ROUND((COALESCE(SUM(CASE WHEN be.status != 'cancelled' THEN be.amount ELSE 0 END), 0) / p.budget_allocated * 100), 2) as budget_percentage
            FROM projects p
            LEFT JOIN budget_expenses be ON p.project_id = be.project_id
            WHERE p.budget_allocated > 0
            GROUP BY p.project_id, p.project_name, p.budget_allocated
            HAVING budget_percentage >= ?
        ");
        $exceeded_threshold = BUDGET_EXCEEDED_PERCENT;
        $stmt->bind_param("i", $exceeded_threshold);
        $stmt->execute();
        $exceededBudgets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($exceededBudgets as $project) {
            // Check if notification already exists TODAY (prevent duplicates - once per day)
            $checkStmt = $conn->prepare("
                SELECT notification_id FROM resource_notifications 
                WHERE project_id = ? 
                AND notification_type = 'budget_exceeded' 
                AND DATE(created_at) = ?
            ");
            $checkStmt->bind_param("is", $project['project_id'], $today);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows == 0) {
                $insertStmt = $conn->prepare("
                    INSERT INTO resource_notifications (project_id, notification_type, message, is_read)
                    VALUES (?, 'budget_exceeded', ?, FALSE)
                ");
                $overage = $project['total_spent'] - $project['budget_allocated'];
                $message = "🚨 Project '{$project['project_name']}' has EXCEEDED its budget! " . 
                          number_format($project['budget_percentage'], 2) . "% spent (₱" . 
                          number_format($project['total_spent'], 2) . " of ₱" . 
                          number_format($project['budget_allocated'], 2) . "). Over budget by: ₱" . 
                          number_format($overage, 2);
                $insertStmt->bind_param("is", $project['project_id'], $message);
                $insertStmt->execute();
                $notificationsCreated++;
            }
        }
        
        return $notificationsCreated;
        
    } catch (Exception $e) {
        throw new Exception('Error checking budgets: ' . $e->getMessage());
    }
}

// Handle GET request - check and create budget notifications
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $notificationsCreated = checkAndCreateBudgetNotifications($conn);
        echo json_encode([
            'success' => true,
            'message' => "Checked budgets and created {$notificationsCreated} new notification(s)",
            'notifications_created' => $notificationsCreated
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Method not allowed
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>
