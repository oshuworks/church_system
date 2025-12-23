<?php
// projects.php - API endpoint for projects management (GET all projects)
include '../includes/db.php';
header('Content-Type: application/json');

// Handle GET request to fetch all projects
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $conn->prepare("
            SELECT p.*, 
                   COUNT(DISTINCT t.task_id) as task_count,
                   COUNT(DISTINCT CASE WHEN t.progress_percent = 100 THEN t.task_id END) as completed_tasks,
                   COUNT(DISTINCT CASE WHEN t.end_date < CURDATE() AND t.progress_percent < 100 THEN t.task_id END) as overdue_tasks,
                   CASE 
                       WHEN (COUNT(DISTINCT CASE WHEN t.progress_percent = 100 THEN t.task_id END) = COUNT(DISTINCT t.task_id) AND COUNT(DISTINCT t.task_id) > 0) THEN 3
                       WHEN (COUNT(DISTINCT CASE WHEN t.end_date < CURDATE() AND t.progress_percent < 100 THEN t.task_id END) > 0) OR (p.end_date < CURDATE() AND COUNT(DISTINCT CASE WHEN t.progress_percent = 100 THEN t.task_id END) < COUNT(DISTINCT t.task_id)) THEN 1
                       ELSE 2
                   END as sort_priority,
                   CASE 
                       WHEN (COUNT(DISTINCT CASE WHEN t.progress_percent = 100 THEN t.task_id END) = COUNT(DISTINCT t.task_id) AND COUNT(DISTINCT t.task_id) > 0) THEN 
                           COALESCE(-TO_DAYS(p.end_date), 0)
                       ELSE 
                           COALESCE(TO_DAYS(p.end_date), 0)
                   END as sort_date
            FROM projects p
            LEFT JOIN tasks t ON p.project_id = t.project_id
            GROUP BY p.project_id
            ORDER BY sort_priority ASC, sort_date ASC, p.created_at DESC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $projects = [];
        
        while ($row = $result->fetch_assoc()) {
            // Calculate overall progress
            $overall_progress = 0;
            if ($row['task_count'] > 0) {
                $overall_progress = round(($row['completed_tasks'] / $row['task_count']) * 100);
            }
            $row['overall_progress'] = $overall_progress;
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