<?php
// task.php - API endpoint for single task operations (GET, POST, PUT, DELETE)
include '../includes/db.php';
header('Content-Type: application/json');

// Handle GET request - fetch single task
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $task_id = $_GET['task_id'] ?? null;
    
    if (!$task_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID is required']);
        exit;
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT t.*, 
                   p.name as project_name,
                   dt.name as dependency_task_name
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.project_id
            LEFT JOIN tasks dt ON t.dependency_task_id = dt.task_id
            WHERE t.task_id = ?
        ");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $task = $result->fetch_assoc();
        
        if (!$task) {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found']);
            exit;
        }
        
        echo json_encode(['success' => true, 'task' => $task]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch task: ' . $e->getMessage()]);
    }
    exit;
}

// Handle POST request - create new task
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Support both JSON and form data
    if (empty($data)) {
        $data = $_POST;
    }
    
    $name = $data['name'] ?? '';
    $description = $data['description'] ?? '';
    $project_id = $data['project_id'] ?? null;
    $start_date = $data['start_date'] ?? null;
    $end_date = $data['end_date'] ?? null;
    $dependency_task_id = $data['dependency_task_id'] ?? null;
    $progress_percent = $data['progress_percent'] ?? 0;
    
    if (!$name || !$project_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Task name and project ID are required']);
        exit;
    }
    
    // Validate dates
    if ($start_date && $end_date && strtotime($start_date) > strtotime($end_date)) {
        http_response_code(400);
        echo json_encode(['error' => 'Start date must be before end date']);
        exit;
    }
    
    // Validate dependency (check if dependency task exists and belongs to same project)
    if ($dependency_task_id) {
        $stmt = $conn->prepare("SELECT project_id FROM tasks WHERE task_id = ?");
        $stmt->bind_param("i", $dependency_task_id);
        $stmt->execute();
        $dep_result = $stmt->get_result()->fetch_assoc();
        
        if (!$dep_result || $dep_result['project_id'] != $project_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid dependency task']);
            exit;
        }
    }
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO tasks (name, description, project_id, start_date, end_date, dependency_task_id, progress_percent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssissii", $name, $description, $project_id, $start_date, $end_date, $dependency_task_id, $progress_percent);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'task_id' => $conn->insert_id, 'message' => 'Task created successfully']);
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

// Handle PUT request - update task
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $task_id = $data['task_id'] ?? null;
    $name = $data['name'] ?? '';
    $description = $data['description'] ?? '';
    $project_id = $data['project_id'] ?? null;
    $start_date = $data['start_date'] ?? null;
    $end_date = $data['end_date'] ?? null;
    $dependency_task_id = $data['dependency_task_id'] ?? null;
    $progress_percent = $data['progress_percent'] ?? null;
    
    if (!$task_id || !$name) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID and name are required']);
        exit;
    }
    
    // Validate dates
    if ($start_date && $end_date && strtotime($start_date) > strtotime($end_date)) {
        http_response_code(400);
        echo json_encode(['error' => 'Start date must be before end date']);
        exit;
    }
    
    // Prevent circular dependencies
    if ($dependency_task_id == $task_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Task cannot depend on itself']);
        exit;
    }
    
    // Validate dependency
    if ($dependency_task_id) {
        $stmt = $conn->prepare("SELECT project_id FROM tasks WHERE task_id = ?");
        $stmt->bind_param("i", $dependency_task_id);
        $stmt->execute();
        $dep_result = $stmt->get_result()->fetch_assoc();
        
        if (!$dep_result) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid dependency task']);
            exit;
        }
        
        // Get current task's project_id if not provided
        if (!$project_id) {
            $stmt = $conn->prepare("SELECT project_id FROM tasks WHERE task_id = ?");
            $stmt->bind_param("i", $task_id);
            $stmt->execute();
            $current_task = $stmt->get_result()->fetch_assoc();
            $project_id = $current_task['project_id'];
        }
        
        if ($dep_result['project_id'] != $project_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Dependency task must belong to the same project']);
            exit;
        }
    }
    
    try {
        // Build dynamic update query
        $update_fields = [];
        $params = [];
        $types = '';
        
        if ($name) {
            $update_fields[] = "name = ?";
            $params[] = $name;
            $types .= 's';
        }
        if ($description !== null) {
            $update_fields[] = "description = ?";
            $params[] = $description;
            $types .= 's';
        }
        if ($start_date !== null) {
            $update_fields[] = "start_date = ?";
            $params[] = $start_date;
            $types .= 's';
        }
        if ($end_date !== null) {
            $update_fields[] = "end_date = ?";
            $params[] = $end_date;
            $types .= 's';
        }
        if ($dependency_task_id !== null) {
            $update_fields[] = "dependency_task_id = ?";
            $params[] = $dependency_task_id;
            $types .= 'i';
        }
        if ($progress_percent !== null) {
            $update_fields[] = "progress_percent = ?";
            $params[] = $progress_percent;
            $types .= 'i';
        }
        
        if (empty($update_fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            exit;
        }
        
        $params[] = $task_id;
        $types .= 'i';
        
        $sql = "UPDATE tasks SET " . implode(", ", $update_fields) . " WHERE task_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Task updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update task']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle DELETE request - delete task
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $task_id = $data['task_id'] ?? $_GET['task_id'] ?? null;
    
    if (!$task_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID is required']);
        exit;
    }
    
    try {
        // Check if task is a dependency for other tasks
        $stmt = $conn->prepare("SELECT COUNT(*) as dependent_count FROM tasks WHERE dependency_task_id = ?");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['dependent_count'] > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete task that other tasks depend on. Please update or delete dependent tasks first.']);
            exit;
        }
        
        $stmt = $conn->prepare("DELETE FROM tasks WHERE task_id = ?");
        $stmt->bind_param("i", $task_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Task deleted successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete task']);
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
