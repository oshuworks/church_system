<?php
include '../includes/db.php';
header('Content-Type: application/json');

$analytics = isset($_GET['analytics']) && $_GET['analytics'] === 'true';

try {
    $result = $conn->query("SELECT * FROM projects");
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $projects = [];

    while ($row = $result->fetch_assoc()) {
        if ($analytics) {
            // Calculate overall progress for each project based on tasks
            $projectId = intval($row['project_id']);
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total_tasks,
                    AVG(progress_percent) as avg_progress
                FROM tasks 
                WHERE project_id = ?
            ");
            
            if ($stmt) {
                $stmt->bind_param("i", $projectId);
                $stmt->execute();
                $taskResult = $stmt->get_result();
                $taskData = $taskResult->fetch_assoc();
                $stmt->close();
                
                $row['overall_progress'] = ($taskData && $taskData['total_tasks'] > 0) 
                    ? round(floatval($taskData['avg_progress'])) 
                    : 0;
            } else {
                $row['overall_progress'] = 0;
            }
        }
        
        $projects[] = $row;
    }

    if ($analytics) {
        echo json_encode(['projects' => $projects, 'success' => true]);
    } else {
        echo json_encode($projects);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'success' => false]);
}
?>