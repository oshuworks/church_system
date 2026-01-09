<?php
// task.php - API endpoint for single task operations (GET, POST, PUT, DELETE)
include '../includes/db.php';
header('Content-Type: application/json');

// Helper function to get task schema info
function getTaskSchemaInfo($conn) {
    $colsRes = $conn->query("SHOW COLUMNS FROM tasks");
    $taskCols = [];
    while ($tc = $colsRes->fetch_assoc()) { $taskCols[] = $tc['Field']; }
    
    if (in_array('progress_percent', $taskCols)) {
        return [
            'progress_col' => 'progress_percent',
            'name_col' => 'name',
            'desc_col' => 'description'
        ];
    } else if (in_array('progress_percentage', $taskCols)) {
        return [
            'progress_col' => 'progress_percentage',
            'name_col' => 'task_name',
            'desc_col' => 'task_description'
        ];
    } else {
        return [
            'progress_col' => 'progress_percent',
            'name_col' => 'name',
            'desc_col' => 'description'
        ];
    }
}

// Function to calculate and update parent task progress from subtasks
function updateParentTaskProgress($conn, $parent_task_id) {
    $schema = getTaskSchemaInfo($conn);
    $progressCol = $schema['progress_col'];
    
    // Get all subtasks
    $stmt = $conn->prepare("SELECT {$progressCol} as progress FROM tasks WHERE parent_task_id = ?");
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
            $total_progress += $subtask['progress'];
        }
        $new_progress = round($total_progress / count($subtasks));
    }
    
    // Update parent task progress
    $updateStmt = $conn->prepare("UPDATE tasks SET {$progressCol} = ? WHERE task_id = ?");
    $updateStmt->bind_param("ii", $new_progress, $parent_task_id);
    $updateStmt->execute();
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
        $schema = getTaskSchemaInfo($conn);
        $progressCol = $schema['progress_col'];
        $nameCol = $schema['name_col'];
        $descCol = $schema['desc_col'];
        
        $stmt = $conn->prepare("
            SELECT t.*, 
                   p.name as project_name,
                   dt.{$nameCol} as dependency_task_name
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
        
        // Normalize task fields
        $task['name'] = $task['name'] ?? $task['task_name'] ?? '';
        $task['description'] = $task['description'] ?? $task['task_description'] ?? '';
        if (!isset($task['progress_percent'])) {
            $task['progress_percent'] = $task['progress_percentage'] ?? 0;
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
    
    // Validate task dates are within project timeline
    if ($start_date || $end_date) {
        $stmt = $conn->prepare("SELECT start_date, end_date FROM projects WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $project_result = $stmt->get_result()->fetch_assoc();
        
        if ($project_result) {
            $project_start = $project_result['start_date'];
            $project_end = $project_result['end_date'];
            
            // Validate start date
            if ($start_date) {
                if ($project_start && strtotime($start_date) < strtotime($project_start)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Task start date cannot be before project start date']);
                    exit;
                }
                if ($project_end && strtotime($start_date) > strtotime($project_end)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Task start date cannot be after project end date']);
                    exit;
                }
            }
            
            // Validate end date
            if ($end_date) {
                if ($project_start && strtotime($end_date) < strtotime($project_start)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Task end date cannot be before project start date']);
                    exit;
                }
                if ($project_end && strtotime($end_date) > strtotime($project_end)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Task end date cannot be after project end date']);
                    exit;
                }
            }
        }
    }
    
    // Validate dependency (check if dependency task exists and belongs to same project)
    if ($dependency_task_id) {
        $stmt = $conn->prepare("SELECT project_id, parent_task_id FROM tasks WHERE task_id = ?");
        $stmt->bind_param("i", $dependency_task_id);
        $stmt->execute();
        $dep_result = $stmt->get_result()->fetch_assoc();
        
        if (!$dep_result || $dep_result['project_id'] != $project_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid dependency task']);
            exit;
        }
        
        // If this is a subtask, it can only depend on parent tasks (not other subtasks)
        if ($parent_task_id && $dep_result['parent_task_id']) {
            http_response_code(400);
            echo json_encode(['error' => 'Subtasks can only depend on parent tasks, not other subtasks']);
            exit;
        }
        
        // Prevent circular dependencies - check if dependency task depends on this task
        if ($dependency_task_id == $parent_task_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Task cannot depend on its parent task']);
            exit;
        }
    }
    
    // Validate parent task (check if parent task exists and belongs to same project)
    if ($parent_task_id) {
        $stmt = $conn->prepare("SELECT project_id, parent_task_id, start_date, end_date FROM tasks WHERE task_id = ?");
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
        
        // Validate that subtask dates are within parent task dates
        if ($start_date || $end_date) {
            $parent_start = $parent_result['start_date'];
            $parent_end = $parent_result['end_date'];
            
            if ($start_date && $parent_start && strtotime($start_date) < strtotime($parent_start)) {
                http_response_code(400);
                echo json_encode(['error' => 'Subtask start date cannot be before parent task start date']);
                exit;
            }
            
            if ($end_date && $parent_end && strtotime($end_date) > strtotime($parent_end)) {
                http_response_code(400);
                echo json_encode(['error' => 'Subtask end date cannot be after parent task end date']);
                exit;
            }
            
            if ($start_date && $parent_end && strtotime($start_date) > strtotime($parent_end)) {
                http_response_code(400);
                echo json_encode(['error' => 'Subtask start date cannot be after parent task end date']);
                exit;
            }
        }
    }
    
    try {
        $schema = getTaskSchemaInfo($conn);
        $progressCol = $schema['progress_col'];
        $nameCol = $schema['name_col'];
        $descCol = $schema['desc_col'];
        
        $stmt = $conn->prepare("
            INSERT INTO tasks ({$nameCol}, {$descCol}, project_id, start_date, end_date, dependency_task_id, parent_task_id, {$progressCol}) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssissiii", $name, $description, $project_id, $start_date, $end_date, $dependency_task_id, $parent_task_id, $progress_percent);
        
        if ($stmt->execute()) {
            $new_task_id = $conn->insert_id;
            
            // If this is a subtask, update parent task progress
            if ($parent_task_id) {
                updateParentTaskProgress($conn, $parent_task_id);
            }
            
            // Create notification for new task
            $notificationStmt = $conn->prepare("
                INSERT INTO resource_notifications (project_id, notification_type, message, is_read)
                VALUES (?, 'system_alert', ?, FALSE)
            ");
            if ($notificationStmt) {
                $taskType = $parent_task_id ? 'Subtask' : 'Task';
                $message = "➕ {$taskType} created: '{$name}' in project";
                $notificationStmt->bind_param("is", $project_id, $message);
                $notificationStmt->execute();
                $notificationStmt->close();
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
    
    // Get current task info for validation
    $stmt = $conn->prepare("SELECT project_id, parent_task_id, start_date as current_start_date, end_date as current_end_date FROM tasks WHERE task_id = ?");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $current_task = $stmt->get_result()->fetch_assoc();
    
    if (!$current_task) {
        http_response_code(404);
        echo json_encode(['error' => 'Task not found']);
        exit;
    }
    
    // Use provided project_id or get from current task
    if (!$project_id) {
        $project_id = $current_task['project_id'];
    }
    
    // Validate task dates are within project timeline (if dates are being updated and it's not a subtask)
    if (($start_date !== null || $end_date !== null) && !$current_task['parent_task_id']) {
        $stmt = $conn->prepare("SELECT start_date, end_date FROM projects WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $project_result = $stmt->get_result()->fetch_assoc();
        
        if ($project_result) {
            $project_start = $project_result['start_date'];
            $project_end = $project_result['end_date'];
            
            // Get dates to validate (use provided or current)
            $validate_start_date = $start_date !== null ? $start_date : $current_task['current_start_date'];
            $validate_end_date = $end_date !== null ? $end_date : $current_task['current_end_date'];
            
            // Validate start date
            if ($validate_start_date) {
                if ($project_start && strtotime($validate_start_date) < strtotime($project_start)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Task start date cannot be before project start date']);
                    exit;
                }
                if ($project_end && strtotime($validate_start_date) > strtotime($project_end)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Task start date cannot be after project end date']);
                    exit;
                }
            }
            
            // Validate end date
            if ($validate_end_date) {
                if ($project_start && strtotime($validate_end_date) < strtotime($project_start)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Task end date cannot be before project start date']);
                    exit;
                }
                if ($project_end && strtotime($validate_end_date) > strtotime($project_end)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Task end date cannot be after project end date']);
                    exit;
                }
            }
        }
    }
    
    // Prevent circular dependencies
    if ($dependency_task_id == $task_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Task cannot depend on itself']);
        exit;
    }
    
    // Validate dependency
    if ($dependency_task_id) {
        $stmt = $conn->prepare("SELECT project_id, parent_task_id FROM tasks WHERE task_id = ?");
        $stmt->bind_param("i", $dependency_task_id);
        $stmt->execute();
        $dep_result = $stmt->get_result()->fetch_assoc();
        
        if (!$dep_result) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid dependency task']);
            exit;
        }
        
        if ($dep_result['project_id'] != $project_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Dependency task must belong to the same project']);
            exit;
        }
        
        // If current task is a subtask, it can only depend on parent tasks (not other subtasks)
        if ($current_task['parent_task_id'] && $dep_result['parent_task_id']) {
            http_response_code(400);
            echo json_encode(['error' => 'Subtasks can only depend on parent tasks, not other subtasks']);
            exit;
        }
    }
    
    // Validate parent task dates if this is a subtask and dates are being updated
    if ($current_task['parent_task_id'] && ($start_date !== null || $end_date !== null)) {
        $stmt = $conn->prepare("SELECT start_date, end_date FROM tasks WHERE task_id = ?");
        $stmt->bind_param("i", $current_task['parent_task_id']);
        $stmt->execute();
        $parent_result = $stmt->get_result()->fetch_assoc();
        
        if ($parent_result) {
            // Get dates to validate (use provided or current)
            $validate_start_date = $start_date !== null ? $start_date : $current_task['current_start_date'];
            $validate_end_date = $end_date !== null ? $end_date : $current_task['current_end_date'];
            
            $parent_start = $parent_result['start_date'];
            $parent_end = $parent_result['end_date'];
            
            if ($validate_start_date && $parent_start && strtotime($validate_start_date) < strtotime($parent_start)) {
                http_response_code(400);
                echo json_encode(['error' => 'Subtask start date cannot be before parent task start date']);
                exit;
            }
            
            if ($validate_end_date && $parent_end && strtotime($validate_end_date) > strtotime($parent_end)) {
                http_response_code(400);
                echo json_encode(['error' => 'Subtask end date cannot be after parent task end date']);
                exit;
            }
            
            if ($validate_start_date && $parent_end && strtotime($validate_start_date) > strtotime($parent_end)) {
                http_response_code(400);
                echo json_encode(['error' => 'Subtask start date cannot be after parent task end date']);
                exit;
            }
        }
    }
    
    try {
        $schema = getTaskSchemaInfo($conn);
        $progressCol = $schema['progress_col'];
        $nameCol = $schema['name_col'];
        $descCol = $schema['desc_col'];
        
        // Build dynamic update query
        $update_fields = [];
        $params = [];
        $types = '';
        
        if ($name) {
            $update_fields[] = "{$nameCol} = ?";
            $params[] = $name;
            $types .= 's';
        }
        if ($description !== null) {
            $update_fields[] = "{$descCol} = ?";
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
            $update_fields[] = "{$progressCol} = ?";
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
            
            // Create notification for task update
            $notificationStmt = $conn->prepare("
                INSERT INTO resource_notifications (project_id, notification_type, message, is_read)
                VALUES (?, 'system_alert', ?, FALSE)
            ");
            if ($notificationStmt) {
                $taskType = $current_task['parent_task_id'] ? 'Subtask' : 'Task';
                $message = "✏️ {$taskType} updated: '{$name}'";
                $notificationStmt->bind_param("is", $project_id, $message);
                $notificationStmt->execute();
                $notificationStmt->close();
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
        // Start transaction for cascade deletion
        $conn->begin_transaction();
        
        // Get parent_task_id before deletion (to update parent progress)
        $stmt = $conn->prepare("SELECT parent_task_id FROM tasks WHERE task_id = ?");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $task_result = $stmt->get_result()->fetch_assoc();
        $parent_task_id = $task_result['parent_task_id'] ?? null;
        
        // Delete all subtasks first (cascade delete)
        $stmt = $conn->prepare("DELETE FROM tasks WHERE parent_task_id = ?");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        
        // Remove dependency references to this task (set to NULL)
        $stmt = $conn->prepare("UPDATE tasks SET dependency_task_id = NULL WHERE dependency_task_id = ?");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        
        // Delete the task itself
        $stmt = $conn->prepare("DELETE FROM tasks WHERE task_id = ?");
        $stmt->bind_param("i", $task_id);
        
        if ($stmt->execute()) {
            // Update parent task progress if this was a subtask
            if ($parent_task_id) {
                updateParentTaskProgress($conn, $parent_task_id);
            }
            
            // Get project_id for notification
            $project_id = $current_task['project_id'] ?? null;
            
            // Create notification for task deletion
            if ($project_id) {
                $notificationStmt = $conn->prepare("
                    INSERT INTO resource_notifications (project_id, notification_type, message, is_read)
                    VALUES (?, 'system_alert', ?, FALSE)
                ");
                if ($notificationStmt) {
                    $message = "🗑️ Task deleted from project";
                    $notificationStmt->bind_param("is", $project_id, $message);
                    $notificationStmt->execute();
                    $notificationStmt->close();
                }
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Task and all associated subtasks deleted successfully']);
        } else {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete task']);
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
