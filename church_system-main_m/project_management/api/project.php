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
        
        // Normalize project fields to stable keys for the frontend
        $colsRes = $conn->query("SHOW COLUMNS FROM projects");
        $projectCols = [];
        while ($c = $colsRes->fetch_assoc()) { $projectCols[] = $c['Field']; }
        // Map name/description/budget to stable keys 'name','description','budget'
        if (in_array('project_name', $projectCols)) {
            $project['name'] = $project['project_name'] ?? $project['name'] ?? '';
            $project['description'] = $project['project_description'] ?? $project['description'] ?? '';
            $project['budget'] = isset($project['budget_allocated']) ? $project['budget_allocated'] : ($project['budget'] ?? null);
        } else {
            // assume newer schema
            $project['name'] = $project['name'] ?? $project['project_name'] ?? '';
            $project['description'] = $project['description'] ?? $project['project_description'] ?? '';
            $project['budget'] = $project['budget'] ?? $project['budget_allocated'] ?? null;
        }
        
        // Determine task schema columns and build the task query dynamically to be compatible with variants
        $tasksColsRes = $conn->query("SHOW COLUMNS FROM tasks");
        $taskCols = [];
        while ($tc = $tasksColsRes->fetch_assoc()) { $taskCols[] = $tc['Field']; }

        if (in_array('progress_percent', $taskCols)) {
            $progressCol = 't.progress_percent';
            $nameCol = 't.name';
            $descCol = 't.description';
        } else if (in_array('progress_percentage', $taskCols)) {
            $progressCol = 't.progress_percentage';
            $nameCol = 't.task_name';
            $descCol = 't.task_description';
        } else {
            // Default to progress_percent if neither exists (shouldn't happen)
            $progressCol = 't.progress_percent';
            $nameCol = 't.name';
            $descCol = 't.description';
        }

        // Fetch tasks with dependency information
        $stmt = $conn->prepare("
            SELECT t.*, 
                   dt.{$nameCol} as dependency_task_name,
                   CASE 
                       WHEN {$progressCol} = 100 THEN 3
                       WHEN t.end_date < CURDATE() AND {$progressCol} < 100 THEN 1
                       ELSE 2
                   END as sort_priority,
                   CASE 
                       WHEN {$progressCol} = 100 THEN 
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
                {$nameCol} ASC
        ");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $tasks_result = $stmt->get_result();
        $tasks = [];
        
        while ($row = $tasks_result->fetch_assoc()) {
            // Normalize task fields to stable keys for the frontend
            $row['name'] = $row['name'] ?? $row['task_name'] ?? '';
            $row['description'] = $row['description'] ?? $row['task_description'] ?? '';
            if (!isset($row['progress_percent'])) {
                $row['progress_percent'] = $row['progress_percentage'] ?? 0;
            }
            $tasks[] = $row;
        }
        
        // Calculate project statistics
        $total_tasks = count($tasks);
        $completed_tasks = count(array_filter($tasks, function($t) { return ($t['progress_percent'] ?? 0) == 100; }));
        
        // Calculate overall progress as average of all task progress percentages
        $total_progress = 0;
        foreach ($tasks as $task) {
            $total_progress += ($task['progress_percent'] ?? 0);
        }
        $overall_progress = $total_tasks > 0 ? round($total_progress / $total_tasks) : 0;
        
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
        // Detect 'projects' table columns to be compatible with both schema variants
        $colsRes = $conn->query("SHOW COLUMNS FROM projects");
        if (!$colsRes) {
            throw new Exception('Failed to introspect projects table: ' . $conn->error);
        }
        $columns = [];
        while ($c = $colsRes->fetch_assoc()) {
            $columns[] = $c['Field'];
        }

        // Default to short names
        if (in_array('project_name', $columns)) {
            // Older/alternate schema uses project_name/project_description/budget_allocated
            $col_name = 'project_name';
            $col_description = 'project_description';
            $col_start = 'start_date';
            $col_end = 'end_date';
            $col_budget = 'budget_allocated';
        } else {
            // Newer schema uses name/description/budget
            $col_name = 'name';
            $col_description = 'description';
            $col_start = 'start_date';
            $col_end = 'end_date';
            $col_budget = 'budget';
        }

        if ($budget === null) {
            $insertQuery = sprintf("INSERT INTO projects (%s, %s, %s, %s, %s) VALUES (?, ?, ?, ?, NULL)", $col_name, $col_description, $col_start, $col_end, $col_budget);
            $stmt = $conn->prepare($insertQuery);
            if ($stmt === false) {
                throw new Exception('DB prepare failed: ' . $conn->error);
            }
            $stmt->bind_param("ssss", $name, $description, $start_date, $end_date);
        } else {
            $insertQuery = sprintf("INSERT INTO projects (%s, %s, %s, %s, %s) VALUES (?, ?, ?, ?, ?)", $col_name, $col_description, $col_start, $col_end, $col_budget);
            $stmt = $conn->prepare($insertQuery);
            if ($stmt === false) {
                throw new Exception('DB prepare failed: ' . $conn->error);
            }
            $budgetVal = (float)$budget;
            $stmt->bind_param("ssssd", $name, $description, $start_date, $end_date, $budgetVal);
        }

        if (!$stmt->execute()) {
            throw new Exception('Failed to create project: ' . $stmt->error);
        }

        $project_id = $conn->insert_id;
        
        // Insert tasks if provided
        if (!empty($tasks) && is_array($tasks)) {
            $task_ids = [];
            $main_tasks = [];
            $sub_tasks = [];

            // Detect task table column names to be compatible with different schema variants
            $tasksColsRes = $conn->query("SHOW COLUMNS FROM tasks");
            if (!$tasksColsRes) {
                throw new Exception('Failed to introspect tasks table: ' . $conn->error);
            }
            $taskCols = [];
            while ($tc = $tasksColsRes->fetch_assoc()) {
                $taskCols[] = $tc['Field'];
            }

            // Determine column names
            $t_name = in_array('task_name', $taskCols) ? 'task_name' : (in_array('name', $taskCols) ? 'name' : null);
            $t_description = in_array('task_description', $taskCols) ? 'task_description' : (in_array('description', $taskCols) ? 'description' : null);
            $t_start = in_array('start_date', $taskCols) ? 'start_date' : null;
            $t_end = in_array('end_date', $taskCols) ? 'end_date' : null;
            $t_dependency = in_array('dependency_task_id', $taskCols) ? 'dependency_task_id' : (in_array('dependency_id', $taskCols) ? 'dependency_id' : null);
            $t_progress = in_array('progress_percentage', $taskCols) ? 'progress_percentage' : (in_array('progress_percent', $taskCols) ? 'progress_percent' : null);

            if (!$t_name || !$t_description || !$t_start || !$t_end || !$t_progress) {
                // If the expected columns are not present, throw a helpful error
                throw new Exception('Incompatible tasks table schema: missing required columns');
            }

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
                $insertSql = sprintf("INSERT INTO tasks (project_id, %s, %s, %s, %s, %s) VALUES (?, ?, ?, ?, ?, 0)",
                    $t_name, $t_description, $t_start, $t_end, $t_progress);
                $task_stmt = $conn->prepare($insertSql);
                if ($task_stmt === false) {
                    throw new Exception('DB prepare failed: ' . $conn->error);
                }
                $taskName = $task['name'] ?? $task['task_name'] ?? '';
                $taskDesc = $task['description'] ?? $task['task_description'] ?? '';
                $taskStart = $task['start_date'] ?? null;
                $taskEnd = $task['end_date'] ?? null;
                $task_stmt->bind_param('issss', $project_id, $taskName, $taskDesc, $taskStart, $taskEnd);
                if ($task_stmt->execute()) {
                    $task_ids[$index] = $conn->insert_id;
                } else {
                    // Continue on failure but log
                    error_log('Failed to insert task: ' . $task_stmt->error);
                }
                $task_stmt->close();
            }

            // Insert sub-tasks with dependencies
            foreach ($sub_tasks as $index => $task) {
                $parent_index = $task['dependency_task_index'];
                $dependency_task_id = isset($task_ids[$parent_index]) ? $task_ids[$parent_index] : null;

                $insertSql = sprintf("INSERT INTO tasks (project_id, %s, %s, %s, %s, %s, %s) VALUES (?, ?, ?, ?, ?, ?, 0)",
                    $t_name, $t_description, $t_start, $t_end, $t_dependency, $t_progress);
                $task_stmt = $conn->prepare($insertSql);
                if ($task_stmt === false) {
                    throw new Exception('DB prepare failed: ' . $conn->error);
                }
                $taskName = $task['name'] ?? $task['task_name'] ?? '';
                $taskDesc = $task['description'] ?? $task['task_description'] ?? '';
                $taskStart = $task['start_date'] ?? null;
                $taskEnd = $task['end_date'] ?? null;
                $task_stmt->bind_param('issssi', $project_id, $taskName, $taskDesc, $taskStart, $taskEnd, $dependency_task_id);
                if ($task_stmt->execute()) {
                    $task_ids[$index] = $conn->insert_id;
                } else {
                    error_log('Failed to insert sub-task: ' . $task_stmt->error);
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
        // Detect the correct column names for compatibility with multiple schemas
        $colsRes = $conn->query("SHOW COLUMNS FROM projects");
        if (!$colsRes) {
            throw new Exception('Failed to introspect projects table: ' . $conn->error);
        }
        $columns = [];
        while ($c = $colsRes->fetch_assoc()) { $columns[] = $c['Field']; }

        if (in_array('project_name', $columns)) {
            $col_name = 'project_name';
            $col_description = 'project_description';
            $col_start = 'start_date';
            $col_end = 'end_date';
            $col_budget = 'budget_allocated';
        } else {
            $col_name = 'name';
            $col_description = 'description';
            $col_start = 'start_date';
            $col_end = 'end_date';
            $col_budget = 'budget';
        }

        if ($budget === null) {
            $updateQuery = sprintf("UPDATE projects SET %s = ?, %s = ?, %s = ?, %s = ?, %s = NULL WHERE project_id = ?", $col_name, $col_description, $col_start, $col_end, $col_budget);
            $stmt = $conn->prepare($updateQuery);
            if ($stmt === false) {
                throw new Exception('DB prepare failed: ' . $conn->error);
            }
            $stmt->bind_param("ssssi", $name, $description, $start_date, $end_date, $project_id);
        } else {
            $updateQuery = sprintf("UPDATE projects SET %s = ?, %s = ?, %s = ?, %s = ?, %s = ? WHERE project_id = ?", $col_name, $col_description, $col_start, $col_end, $col_budget);
            $stmt = $conn->prepare($updateQuery);
            if ($stmt === false) {
                throw new Exception('DB prepare failed: ' . $conn->error);
            }
            $budgetVal = (float)$budget;
            $stmt->bind_param("ssssdi", $name, $description, $start_date, $end_date, $budgetVal, $project_id);
        }
        if ($stmt->execute()) {
            // Return updated project for convenience
            echo json_encode(['success' => true, 'message' => 'Project updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update project: ' . $stmt->error]);
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