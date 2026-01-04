<?php
// deadline_notifications.php - API endpoint for checking and creating deadline notifications
include '../includes/db.php';
header('Content-Type: application/json');

// Define "near deadline" as 1 day (24 hours) before due date
define('NEAR_DEADLINE_DAYS', 1);

// Function to check and create deadline notifications
function checkAndCreateDeadlineNotifications($conn) {
    $today = date('Y-m-d');
    $nearDeadlineDate = date('Y-m-d', strtotime("+".NEAR_DEADLINE_DAYS." days"));
    $notificationsCreated = 0;
    
    try {
        // Check for due projects (end_date = today, not completed)
        $stmt = $conn->prepare("
            SELECT p.project_id, p.project_name as name, p.end_date,
                   COALESCE(SUM(CASE WHEN t.progress_percentage = 100 THEN 1 ELSE 0 END), 0) as completed_tasks,
                   COUNT(t.task_id) as total_tasks
            FROM projects p
            LEFT JOIN tasks t ON p.project_id = t.project_id
            WHERE p.end_date = ? 
            AND p.end_date IS NOT NULL
            GROUP BY p.project_id, p.project_name, p.end_date
            HAVING (total_tasks = 0 OR completed_tasks < total_tasks)
        ");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $dueProjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($dueProjects as $project) {
            // Check if notification already exists for today (prevent duplicates)
            $checkStmt = $conn->prepare("
                SELECT alert_id FROM resource_alerts 
                WHERE project_id = ? 
                AND alert_type = 'project_due_date' 
                AND DATE(created_at) = ?
            ");
            $checkStmt->bind_param("is", $project['project_id'], $today);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows == 0) {
                $insertStmt = $conn->prepare("
                    INSERT INTO resource_alerts (alert_type, resource_type, resource_id, project_id, severity, message, status)
                    VALUES ('project_due_date', 'budget', 0, ?, 'medium', ?, 'active')
                ");
                $message = "⚠️ Project '{$project['name']}' is due today! Please complete all tasks before the deadline.";
                $insertStmt->bind_param("is", $project['project_id'], $message);
                $insertStmt->execute();
                $notificationsCreated++;
            }
        }
        
        // Check for near deadline projects (end_date = today + 1 day, not completed)
        $stmt = $conn->prepare("
            SELECT p.project_id, p.project_name as name, p.end_date,
                   COALESCE(SUM(CASE WHEN t.progress_percentage = 100 THEN 1 ELSE 0 END), 0) as completed_tasks,
                   COUNT(t.task_id) as total_tasks
            FROM projects p
            LEFT JOIN tasks t ON p.project_id = t.project_id
            WHERE p.end_date = ? 
            AND p.end_date IS NOT NULL
            GROUP BY p.project_id, p.project_name, p.end_date
            HAVING (total_tasks = 0 OR completed_tasks < total_tasks)
        ");
        $stmt->bind_param("s", $nearDeadlineDate);
        $stmt->execute();
        $nearDeadlineProjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($nearDeadlineProjects as $project) {
            // Check if notification already exists for today (prevent duplicates)
            $checkStmt = $conn->prepare("
                SELECT alert_id FROM resource_alerts 
                WHERE project_id = ? 
                AND alert_type = 'project_near_deadline' 
                AND DATE(created_at) = ?
            ");
            $checkStmt->bind_param("is", $project['project_id'], $today);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows == 0) {
                $insertStmt = $conn->prepare("
                    INSERT INTO resource_alerts (alert_type, resource_type, resource_id, project_id, severity, message, status)
                    VALUES ('project_near_deadline', 'budget', 0, ?, 'medium', ?, 'active')
                ");
                $daysText = NEAR_DEADLINE_DAYS == 1 ? '1 day' : NEAR_DEADLINE_DAYS . ' days';
                $message = "⏰ Project '{$project['name']}' deadline is approaching! Due in ".$daysText." on ".date('M d, Y', strtotime($project['end_date'])).".";
                $insertStmt->bind_param("is", $project['project_id'], $message);
                $insertStmt->execute();
                $notificationsCreated++;
            }
        }
        
        // Check for due tasks (end_date = today, not completed)
        $stmt = $conn->prepare("
            SELECT t.task_id, t.task_name as name, t.end_date, t.parent_task_id, p.project_name as project_name
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.project_id
            WHERE t.end_date = ? 
            AND t.end_date IS NOT NULL
            AND t.progress_percentage < 100
            AND t.parent_task_id IS NULL
        ");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $dueTasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($dueTasks as $task) {
            // Check if alert already exists for today (prevent duplicates)
            $checkStmt = $conn->prepare("
                SELECT alert_id FROM resource_alerts 
                WHERE project_id = (SELECT project_id FROM tasks WHERE task_id = ?)
                AND alert_type = 'task_due_date' 
                AND message LIKE ?
                AND DATE(created_at) = ?
            ");
            $taskNamePattern = "%{$task['name']}%";
            $checkStmt->bind_param("iss", $task['task_id'], $taskNamePattern, $today);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows == 0) {
                $insertStmt = $conn->prepare("
                    INSERT INTO resource_alerts (alert_type, resource_type, resource_id, project_id, task_id, severity, message, status)
                    VALUES ('task_due_date', 'employee', 0, ?, ?, 'medium', ?, 'active')
                ");
                $projectIdStmt = $conn->prepare("SELECT project_id FROM tasks WHERE task_id = ?");
                $projectIdStmt->bind_param("i", $task['task_id']);
                $projectIdStmt->execute();
                $projectIdResult = $projectIdStmt->get_result()->fetch_assoc();
                $projectId = $projectIdResult['project_id'];
                $message = "⚠️ Task '{$task['name']}' in project '{$task['project_name']}' is due today! Please complete it before the deadline.";
                $insertStmt->bind_param("iis", $projectId, $task['task_id'], $message);
                $insertStmt->execute();
                $notificationsCreated++;
            }
        }
        
        // Check for near deadline tasks (end_date = today + X days, not completed)
        $stmt = $conn->prepare("
            SELECT t.task_id, t.task_name as name, t.end_date, t.parent_task_id, p.project_name as project_name
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.project_id
            WHERE t.end_date = ? 
            AND t.end_date IS NOT NULL
            AND t.progress_percentage < 100
            AND t.parent_task_id IS NULL
        ");
        $stmt->bind_param("s", $nearDeadlineDate);
        $stmt->execute();
        $nearDeadlineTasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($nearDeadlineTasks as $task) {
            // Check if alert already exists for today (prevent duplicates)
            $checkStmt = $conn->prepare("
                SELECT alert_id FROM resource_alerts 
                WHERE project_id = (SELECT project_id FROM tasks WHERE task_id = ?)
                AND alert_type = 'task_near_deadline' 
                AND message LIKE ?
                AND DATE(created_at) = ?
            ");
            $taskNamePattern = "%{$task['name']}%";
            $checkStmt->bind_param("iss", $task['task_id'], $taskNamePattern, $today);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows == 0) {
                $insertStmt = $conn->prepare("
                    INSERT INTO resource_alerts (alert_type, resource_type, resource_id, project_id, task_id, severity, message, status)
                    VALUES ('task_near_deadline', 'employee', 0, ?, ?, 'medium', ?, 'active')
                ");
                $projectIdStmt = $conn->prepare("SELECT project_id FROM tasks WHERE task_id = ?");
                $projectIdStmt->bind_param("i", $task['task_id']);
                $projectIdStmt->execute();
                $projectIdResult = $projectIdStmt->get_result()->fetch_assoc();
                $projectId = $projectIdResult['project_id'];
                $daysText = NEAR_DEADLINE_DAYS == 1 ? '1 day' : NEAR_DEADLINE_DAYS . ' days';
                $message = "⏰ Task '{$task['name']}' in project '{$task['project_name']}' deadline is approaching! Due in ".$daysText." on ".date('M d, Y', strtotime($task['end_date'])).".";
                $insertStmt->bind_param("iis", $projectId, $task['task_id'], $message);
                $insertStmt->execute();
                $notificationsCreated++;
            }
        }
        
        // Check for due subtasks (end_date = today, not completed)
        $stmt = $conn->prepare("
            SELECT t.task_id, t.task_name as name, t.end_date, t.parent_task_id, pt.task_name as parent_task_name, p.project_name as project_name
            FROM tasks t
            LEFT JOIN tasks pt ON t.parent_task_id = pt.task_id
            LEFT JOIN projects p ON t.project_id = p.project_id
            WHERE t.end_date = ? 
            AND t.end_date IS NOT NULL
            AND t.progress_percentage < 100
            AND t.parent_task_id IS NOT NULL
        ");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $dueSubtasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($dueSubtasks as $subtask) {
            // Check if alert already exists for today (prevent duplicates)
            $checkStmt = $conn->prepare("
                SELECT alert_id FROM resource_alerts 
                WHERE project_id = (SELECT project_id FROM tasks WHERE task_id = ?)
                AND alert_type = 'subtask_due_date' 
                AND message LIKE ?
                AND DATE(created_at) = ?
            ");
            $subtaskNamePattern = "%{$subtask['name']}%";
            $checkStmt->bind_param("iss", $subtask['task_id'], $subtaskNamePattern, $today);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows == 0) {
                $insertStmt = $conn->prepare("
                    INSERT INTO resource_alerts (alert_type, resource_type, resource_id, project_id, task_id, severity, message, status)
                    VALUES ('subtask_due_date', 'employee', 0, ?, ?, 'medium', ?, 'active')
                ");
                $projectIdStmt = $conn->prepare("SELECT project_id FROM tasks WHERE task_id = ?");
                $projectIdStmt->bind_param("i", $subtask['task_id']);
                $projectIdStmt->execute();
                $projectIdResult = $projectIdStmt->get_result()->fetch_assoc();
                $projectId = $projectIdResult['project_id'];
                $message = "⚠️ Subtask '{$subtask['name']}' of task '{$subtask['parent_task_name']}' in project '{$subtask['project_name']}' is due today! Please complete it before the deadline.";
                $insertStmt->bind_param("iis", $projectId, $subtask['task_id'], $message);
                $insertStmt->execute();
                $notificationsCreated++;
            }
        }
        
        // Check for near deadline subtasks (end_date = today + 3 days, not completed)
        $stmt = $conn->prepare("
            SELECT t.task_id, t.name, t.end_date, t.parent_task_id, pt.name as parent_task_name, p.name as project_name
            FROM tasks t
            LEFT JOIN tasks pt ON t.parent_task_id = pt.task_id
            LEFT JOIN projects p ON t.project_id = p.project_id
            WHERE t.end_date = ? 
            AND t.end_date IS NOT NULL
            AND t.progress_percentage < 100
            AND t.parent_task_id IS NOT NULL
        ");
        $stmt->bind_param("s", $nearDeadlineDate);
        $stmt->execute();
        $nearDeadlineSubtasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($nearDeadlineSubtasks as $subtask) {
            // Check if alert already exists for today (prevent duplicates)
            $checkStmt = $conn->prepare("
                SELECT alert_id FROM resource_alerts 
                WHERE project_id = (SELECT project_id FROM tasks WHERE task_id = ?)
                AND alert_type = 'subtask_near_deadline' 
                AND message LIKE ?
                AND DATE(created_at) = ?
            ");
            $subtaskNamePattern = "%{$subtask['name']}%";
            $checkStmt->bind_param("iss", $subtask['task_id'], $subtaskNamePattern, $today);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows == 0) {
                $insertStmt = $conn->prepare("
                    INSERT INTO resource_alerts (alert_type, resource_type, resource_id, project_id, task_id, severity, message, status)
                    VALUES ('subtask_near_deadline', 'employee', 0, ?, ?, 'medium', ?, 'active')
                ");
                $projectIdStmt = $conn->prepare("SELECT project_id FROM tasks WHERE task_id = ?");
                $projectIdStmt->bind_param("i", $subtask['task_id']);
                $projectIdStmt->execute();
                $projectIdResult = $projectIdStmt->get_result()->fetch_assoc();
                $projectId = $projectIdResult['project_id'];
                $daysText = NEAR_DEADLINE_DAYS == 1 ? '1 day' : NEAR_DEADLINE_DAYS . ' days';
                $message = "⏰ Subtask '{$subtask['name']}' of task '{$subtask['parent_task_name']}' in project '{$subtask['project_name']}' deadline is approaching! Due in ".$daysText." on ".date('M d, Y', strtotime($subtask['end_date'])).".";
                $insertStmt->bind_param("iis", $projectId, $subtask['task_id'], $message);
                $insertStmt->execute();
                $notificationsCreated++;
            }
        }
        
        return $notificationsCreated;
        
    } catch (Exception $e) {
        throw new Exception('Error checking deadlines: ' . $e->getMessage());
    }
}

// Handle GET request - check and create deadline notifications
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $notificationsCreated = checkAndCreateDeadlineNotifications($conn);
        echo json_encode([
            'success' => true,
            'message' => "Checked deadlines and created {$notificationsCreated} new notification(s)",
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

