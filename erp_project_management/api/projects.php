<?php
// projects.php - API endpoint for projects management (GET all projects)
include '../includes/db.php';
header('Content-Type: application/json');

// Handle GET request to fetch all projects
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Determine task schema columns dynamically
        $tasksColsRes = $conn->query("SHOW COLUMNS FROM tasks");
        $taskCols = [];
        while ($tc = $tasksColsRes->fetch_assoc()) { $taskCols[] = $tc['Field']; }
        
        // Determine which progress column exists
        if (in_array('progress_percent', $taskCols)) {
            $progressCol = 't.progress_percent';
        } else if (in_array('progress_percentage', $taskCols)) {
            $progressCol = 't.progress_percentage';
        } else {
            // Fallback if neither exists
            $progressCol = '0';
        }
        
        // Build query dynamically with the correct column name
        $sql = "
            SELECT p.*, 
                   COUNT(DISTINCT t.task_id) as task_count,
                   COUNT(DISTINCT CASE WHEN $progressCol = 100 THEN t.task_id END) as completed_tasks,
                   COUNT(DISTINCT CASE WHEN t.end_date < CURDATE() AND $progressCol < 100 THEN t.task_id END) as overdue_tasks,
                   COALESCE(AVG($progressCol), 0) as avg_progress,
                   CASE 
                       WHEN (COUNT(DISTINCT CASE WHEN $progressCol = 100 THEN t.task_id END) = COUNT(DISTINCT t.task_id) AND COUNT(DISTINCT t.task_id) > 0) THEN 3
                       WHEN (COUNT(DISTINCT CASE WHEN t.end_date < CURDATE() AND $progressCol < 100 THEN t.task_id END) > 0) OR (p.end_date < CURDATE() AND COUNT(DISTINCT CASE WHEN $progressCol = 100 THEN t.task_id END) < COUNT(DISTINCT t.task_id)) THEN 1
                       ELSE 2
                   END as sort_priority,
                   CASE 
                       WHEN (COUNT(DISTINCT CASE WHEN $progressCol = 100 THEN t.task_id END) = COUNT(DISTINCT t.task_id) AND COUNT(DISTINCT t.task_id) > 0) THEN 
                           COALESCE(-TO_DAYS(p.end_date), 0)
                       ELSE 
                           COALESCE(TO_DAYS(p.end_date), 0)
                   END as sort_date
            FROM projects p
            LEFT JOIN tasks t ON p.project_id = t.project_id
            GROUP BY p.project_id
            ORDER BY sort_priority ASC, sort_date ASC, p.created_at DESC
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $projects = [];
        
        // Get project columns for normalization
        $colsRes = $conn->query("SHOW COLUMNS FROM projects");
        $projectCols = [];
        while ($c = $colsRes->fetch_assoc()) { $projectCols[] = $c['Field']; }
        
        while ($row = $result->fetch_assoc()) {
            // Normalize project fields to stable keys for the frontend
            if (in_array('project_name', $projectCols)) {
                $row['name'] = $row['project_name'] ?? $row['name'] ?? '';
                $row['description'] = $row['project_description'] ?? $row['description'] ?? '';
                $row['budget'] = isset($row['budget_allocated']) ? $row['budget_allocated'] : ($row['budget'] ?? null);
            } else {
                // assume newer schema
                $row['name'] = $row['name'] ?? $row['project_name'] ?? '';
                $row['description'] = $row['description'] ?? $row['project_description'] ?? '';
                $row['budget'] = $row['budget'] ?? $row['budget_allocated'] ?? null;
            }
            
            // Calculate overall progress as average of all task progress percentages
            $row['overall_progress'] = round($row['avg_progress'] ?? 0);
            $projects[] = $row;
        }
        
        echo json_encode(['success' => true, 'projects' => $projects]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch projects: ' . $e->getMessage()]);
    }
    exit;
}

// Method not allowed
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>