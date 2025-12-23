<?php
// project.php - API endpoint for single project operations (GET, POST, PUT, DELETE)
include '../includes/db.php';
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
                   dt.name as dependency_task_name,
                   CASE 
                       WHEN t.progress_percent = 100 THEN 3
                       WHEN t.end_date < CURDATE() AND t.progress_percent < 100 THEN 1
                       ELSE 2
                   END as sort_priority,
                   CASE 
                       WHEN t.progress_percent = 100 THEN 
                           COALESCE(-TO_DAYS(t.end_date), 0)
                       ELSE 
                           COALESCE(TO_DAYS(t.end_date), 0)
                   END as sort_date
            FROM tasks t
            LEFT JOIN tasks dt ON t.dependency_task_id = dt.task_id
            WHERE t.project_id = ?
            ORDER BY 
                sort_priority ASC,
                sort_date ASC,
                t.start_date ASC,
                t.name ASC
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
    $tasks = $data['tasks'] ?? [];
    
    if (!$name) {
        http_response_code(400);
        echo json_encode(['error' => 'Project name is required']);
        exit;
    }
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Insert project
        $stmt = $conn->prepare("INSERT INTO projects (name, description, start_date, end_date, budget) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssd", $name, $description, $start_date, $end_date, $budget);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create project');
        }
        
        $project_id = $conn->insert_id;
        
        // Insert tasks if provided
        if (!empty($tasks) && is_array($tasks)) {
            $task_ids = [];
            $main_tasks = [];
            $sub_tasks = [];
            
            // Separate main tasks and sub-tasks
            foreach ($tasks as $index => $task) {
                if (empty($task['dependency_task_index'])) {
                    $main_tasks[$index] = $task;
                } else {
                    $sub_tasks[$index] = $task;
                }
            }
            
            // Insert main tasks first
            foreach ($main_tasks as $index => $task) {
                $task_stmt = $conn->prepare("INSERT INTO tasks (project_id, name, description, start_date, end_date, progress_percent) VALUES (?, ?, ?, ?, ?, 0)");
                $task_stmt->bind_param("issss", $project_id, $task['name'], $task['description'], $task['start_date'], $task['end_date']);
                if ($task_stmt->execute()) {
                    $task_ids[$index] = $conn->insert_id;
                }
                $task_stmt->close();
            }
            
            // Insert sub-tasks with dependencies
            foreach ($sub_tasks as $index => $task) {
                $parent_index = $task['dependency_task_index'];
                $dependency_task_id = isset($task_ids[$parent_index]) ? $task_ids[$parent_index] : null;
                
                $task_stmt = $conn->prepare("INSERT INTO tasks (project_id, name, description, start_date, end_date, dependency_task_id, progress_percent) VALUES (?, ?, ?, ?, ?, ?, 0)");
                $task_stmt->bind_param("issssi", $project_id, $task['name'], $task['description'], $task['start_date'], $task['end_date'], $dependency_task_id);
                if ($task_stmt->execute()) {
                    $task_ids[$index] = $conn->insert_id;
                }
                $task_stmt->close();
            }
        }
        
        // Commit transaction
        $conn->commit();
        echo json_encode(['success' => true, 'project_id' => $project_id, 'message' => 'Project created successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
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
        // Start transaction for cascade deletion
        $conn->begin_transaction();
        
        // Delete all subtasks first (tasks that have parent_task_id)
        $stmt = $conn->prepare("DELETE FROM tasks WHERE project_id = ? AND parent_task_id IS NOT NULL");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        
        // Delete all parent tasks
        $stmt = $conn->prepare("DELETE FROM tasks WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        
        // Delete the project
        $stmt = $conn->prepare("DELETE FROM projects WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        
        if ($stmt->execute()) {
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Project and all associated tasks deleted successfully']);
        } else {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete project']);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Method not allowed
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>