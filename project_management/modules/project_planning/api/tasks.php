<?php
// tasks.php - API endpoint for tasks management
include '../../includes/db.php';
header('Content-Type: application/json');

// Handle GET request to fetch all tasks
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $project_id = $_GET['project_id'] ?? null;
        
        if ($project_id) {
            // Fetch tasks for specific project with dependency information
            $stmt = $conn->prepare("
                SELECT t.task_id, t.task_name AS name, t.task_description AS description, t.start_date, t.end_date, t.progress_percentage AS progress_percent,
                       p.project_name as project_name,
                       dt.task_name as dependency_task_name
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.project_id
                LEFT JOIN tasks dt ON t.dependency_task_id = dt.task_id
                WHERE t.project_id = ?
                ORDER BY t.start_date, t.task_name
            ");
            $stmt->bind_param("i", $project_id);
        } else {
            // Fetch all tasks with project information
            $stmt = $conn->prepare("
                SELECT t.task_id, t.task_name AS name, t.task_description AS description, t.start_date, t.end_date, t.progress_percentage AS progress_percent,
                       p.project_name as project_name,
                       dt.task_name as dependency_task_name
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.project_id
                LEFT JOIN tasks dt ON t.dependency_task_id = dt.task_id
                ORDER BY p.project_name, t.start_date, t.task_name
            ");
        }
        
        $stmt->execute();
        $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['success' => true, 'tasks' => $tasks]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch tasks: ' . $e->getMessage()]);
    }
    exit;
}

// Handle POST request for creating new task
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $project_id = $_POST['project_id'] ?? null;
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;

    if (!$name || !$project_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Task name and project ID are required']);
        exit;
    }

    try {
        $stmt = $conn->prepare("INSERT INTO tasks (task_name, task_description, project_id, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiss", $name, $description, $project_id, $start_date, $end_date);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'task_id' => $conn->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create task']);
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Method not allowed
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>

