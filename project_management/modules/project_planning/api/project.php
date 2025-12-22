<?php
// project.php - API endpoint for single project operations (GET, POST, PUT, DELETE)
include '../../includes/db.php';
header('Content-Type: application/json');

// Handle GET request - fetch single project with tasks
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $project_id = $_GET['project_id'] ?? null;
    
    if (!$project_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Project ID is required']);
        exit;
    }
    
    try {
        // Fetch project details
        $stmt = $conn->prepare("SELECT * FROM projects WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $project = $result->fetch_assoc();
        
        if (!$project) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found']);
            exit;
        }
        
        // Fetch tasks with dependency information
        $stmt = $conn->prepare("
            SELECT t.*, 
                   dt.name as dependency_task_name
            FROM tasks t
            LEFT JOIN tasks dt ON t.dependency_task_id = dt.task_id
            WHERE t.project_id = ?
            ORDER BY t.start_date, t.name
        ");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $tasks_result = $stmt->get_result();
        $tasks = [];
        
        while ($row = $tasks_result->fetch_assoc()) {
            $tasks[] = $row;
        }
        
        // Calculate project statistics
        $total_tasks = count($tasks);
        $completed_tasks = count(array_filter($tasks, function($t) { return $t['progress_percent'] == 100; }));
        $overall_progress = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;
        
        $project['total_tasks'] = $total_tasks;
        $project['completed_tasks'] = $completed_tasks;
        $project['overall_progress'] = $overall_progress;
        
        echo json_encode(['success' => true, 'project' => $project, 'tasks' => $tasks]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch project: ' . $e->getMessage()]);
    }
    exit;
}

// Handle POST request - create new project
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $name = $data['name'] ?? '';
    $description = $data['description'] ?? '';
    $start_date = $data['start_date'] ?? null;
    $end_date = $data['end_date'] ?? null;
    $budget = $data['budget'] ?? null;
    
    if (!$name) {
        http_response_code(400);
        echo json_encode(['error' => 'Project name is required']);
        exit;
    }
    
    try {
        $stmt = $conn->prepare("INSERT INTO projects (name, description, start_date, end_date, budget) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssd", $name, $description, $start_date, $end_date, $budget);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'project_id' => $conn->insert_id, 'message' => 'Project created successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create project']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle PUT request - update project
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $project_id = $data['project_id'] ?? null;
    $name = $data['name'] ?? '';
    $description = $data['description'] ?? '';
    $start_date = $data['start_date'] ?? null;
    $end_date = $data['end_date'] ?? null;
    $budget = $data['budget'] ?? null;
    
    if (!$project_id || !$name) {
        http_response_code(400);
        echo json_encode(['error' => 'Project ID and name are required']);
        exit;
    }
    
    try {
        $stmt = $conn->prepare("UPDATE projects SET name = ?, description = ?, start_date = ?, end_date = ?, budget = ? WHERE project_id = ?");
        $stmt->bind_param("ssssdi", $name, $description, $start_date, $end_date, $budget, $project_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Project updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update project']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle DELETE request - delete project
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $project_id = $data['project_id'] ?? $_GET['project_id'] ?? null;
    
    if (!$project_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Project ID is required']);
        exit;
    }
    
    try {
        // Check if project has tasks
        $stmt = $conn->prepare("SELECT COUNT(*) as task_count FROM tasks WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['task_count'] > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete project with existing tasks. Please delete tasks first.']);
            exit;
        }
        
        $stmt = $conn->prepare("DELETE FROM projects WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Project deleted successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete project']);
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

