<?php
// task.php - API endpoint for single task operations (GET, POST, PUT, DELETE)
include '../includes/db.php';
header('Content-Type: application/json');

// Function to calculate and update parent task progress from subtasks
function updateParentTaskProgress($conn, $parent_task_id) {
    // Get all subtasks
    $stmt = $conn->prepare("SELECT progress_percent FROM tasks WHERE parent_task_id = ?");
    $stmt->bind_param("i", $parent_task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $subtasks = $result->fetch_all(MYSQLI_ASSOC);
    
    if (empty($subtasks)) {
        // No subtasks, set progress to 0
        $new_progress = 0;
    } else {
        // Calculate average progress of all subtasks
        $total_progress = 0;
        foreach ($subtasks as $subtask) {
            $total_progress += $subtask['progress_percent'];
        }
        $new_progress = round($total_progress / count($subtasks));
    }
    
    // Update parent task progress
    $stmt = $conn->prepare("UPDATE tasks SET progress_percent = ? WHERE task_id = ?");
    $stmt->bind_param("ii", $new_progress, $parent_task_id);
    $stmt->execute();
}

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
    $parent_task_id = $data['parent_task_id'] ?? null;
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
    
    // Validate parent task (check if parent task exists and belongs to same project)
    if ($parent_task_id) {
        $stmt = $conn->prepare("SELECT project_id, parent_task_id FROM tasks WHERE task_id = ?");
        $stmt->bind_param("i", $parent_task_id);
        $stmt->execute();
        $parent_result = $stmt->get_result()->fetch_assoc();
        
        if (!$parent_result || $parent_result['project_id'] != $project_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid parent task']);
            exit;
        }
        
        // Prevent nested subtasks (a subtask cannot have a parent that is itself a subtask)
        if ($parent_result['parent_task_id']) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot add subtask to a task that is already a subtask']);
            exit;
        }
    }
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO tasks (name, description, project_id, start_date, end_date, dependency_task_id, parent_task_id, progress_percent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssissiii", $name, $description, $project_id, $start_date, $end_date, $dependency_task_id, $parent_task_id, $progress_percent);
        
        if ($stmt->execute()) {
            $new_task_id = $conn->insert_id;
            
            // If this is a subtask, update parent task progress
            if ($parent_task_id) {
                updateParentTaskProgress($conn, $parent_task_id);
            }
            
            echo json_encode(['success' => true, 'task_id' => $new_task_id, 'message' => 'Task created successfully']);
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
    $parent_task_id = $data['parent_task_id'] ?? null;
    $progress_percent = $data['progress_percent'] ?? null;
    
    if (!$task_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID is required']);
        exit;
    }
    
    // If name is not provided, fetch it from database (for progress-only updates)
    if (!$name) {
        $stmt = $conn->prepare("SELECT name FROM tasks WHERE task_id = ?");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if ($result) {
            $name = $result['name'];
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found']);
            exit;
        }
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
        if ($parent_task_id !== null) {
            $update_fields[] = "parent_task_id = ?";
            $params[] = $parent_task_id;
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
        
        // Get current parent_task_id before update (to update old parent if changed)
        $stmt = $conn->prepare("SELECT parent_task_id FROM tasks WHERE task_id = ?");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $current_task = $stmt->get_result()->fetch_assoc();
        $old_parent_task_id = $current_task['parent_task_id'] ?? null;
        
        $params[] = $task_id;
        $types .= 'i';
        
        $sql = "UPDATE tasks SET " . implode(", ", $update_fields) . " WHERE task_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            // Update parent task progress if this task is a subtask and progress changed
            $new_parent_task_id = $parent_task_id !== null ? $parent_task_id : $old_parent_task_id;
            if ($new_parent_task_id && $progress_percent !== null) {
                updateParentTaskProgress($conn, $new_parent_task_id);
            }
            
            // If parent changed, update both old and new parent progress
            if ($parent_task_id !== null && $parent_task_id != $old_parent_task_id) {
                if ($old_parent_task_id) {
                    updateParentTaskProgress($conn, $old_parent_task_id);
                }
                if ($parent_task_id) {
                    updateParentTaskProgress($conn, $parent_task_id);
                }
            }
            
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
        // Get parent_task_id before deletion (to update parent progress)
        $stmt = $conn->prepare("SELECT parent_task_id FROM tasks WHERE task_id = ?");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $task_result = $stmt->get_result()->fetch_assoc();
        $parent_task_id = $task_result['parent_task_id'] ?? null;
        
        // Check if task has subtasks
        $stmt = $conn->prepare("SELECT COUNT(*) as subtask_count FROM tasks WHERE parent_task_id = ?");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $subtask_result = $stmt->get_result()->fetch_assoc();
        
        if ($subtask_result['subtask_count'] > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete task that has subtasks. Please delete or move subtasks first.']);
            exit;
        }
        
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
            // Update parent task progress if this was a subtask
            if ($parent_task_id) {
                updateParentTaskProgress($conn, $parent_task_id);
            }
            
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
