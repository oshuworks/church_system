<?php
// tasks.php - API endpoint for tasks management
include '../includes/db.php';
header('Content-Type: application/json');

// Handle GET request to fetch all tasks
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Fetch all tasks with project information
        $stmt = $conn->prepare("
            SELECT t.*, p.name as project_name, p.project_id
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.project_id
            ORDER BY p.name, t.name
        ");
        $stmt->execute();
        $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['tasks' => $tasks]);

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
        $stmt = $conn->prepare("INSERT INTO tasks (name, description, project_id, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
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
