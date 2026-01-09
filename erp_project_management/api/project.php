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
        if (in_array('project_name', $projectCols)) {
            $project['name'] = $project['project_name'] ?? $project['name'] ?? '';
            $project['description'] = $project['project_description'] ?? $project['description'] ?? '';
            $project['budget'] = isset($project['budget_allocated']) ? $project['budget_allocated'] : ($project['budget'] ?? null);
        } else {
            $project['name'] = $project['name'] ?? $project['project_name'] ?? '';
            $project['description'] = $project['description'] ?? $project['project_description'] ?? '';
            $project['budget'] = $project['budget'] ?? $project['budget_allocated'] ?? null;
        }
        
        // Determine task schema columns and build task query dynamically to be compatible with variants
        $tasksColsRes = $conn->query("SHOW COLUMNS FROM tasks");
        $taskCols = [];
        while ($tc = $tasksColsRes->fetch_assoc()) { $taskCols[] = $tc['Field']; }

        if (in_array('progress_percent', $taskCols)) {
            $progressCol = 't.progress_percent';
        } else if (in_array('progress_percentage', $taskCols)) {
            $progressCol = 't.progress_percentage';
        } else {
            $progressCol = '0';
        }

        if (in_array('task_name', $taskCols)) {
            $taskNameOrder = 't.task_name';
        } else if (in_array('name', $taskCols)) {
            $taskNameOrder = 't.name';
        } else {
            $taskNameOrder = "''";
        }

        $depJoinParts = [];
        if (in_array('dependency_task_id', $taskCols)) { $depJoinParts[] = 't.dependency_task_id = dt.task_id'; }
        if (in_array('dependency_id', $taskCols)) { $depJoinParts[] = 't.dependency_id = dt.task_id'; }
        $depJoin = !empty($depJoinParts) ? implode(' OR ', $depJoinParts) : '1=0';

        if (in_array('name', $taskCols)) {
            $depNameCol = 'dt.name';
        } else if (in_array('task_name', $taskCols)) {
            $depNameCol = 'dt.task_name';
        } else {
            $depNameCol = 'NULL';
        }

        $sql = sprintf("SELECT t.*, %s as dependency_task_name, CASE WHEN %s = 100 THEN 3 WHEN t.end_date < CURDATE() AND %s < 100 THEN 1 ELSE 2 END as sort_priority, CASE WHEN %s = 100 THEN COALESCE(-TO_DAYS(t.end_date), 0) ELSE COALESCE(TO_DAYS(t.end_date), 0) END as sort_date FROM tasks t LEFT JOIN tasks dt ON (%s) WHERE t.project_id = ? ORDER BY sort_priority ASC, sort_date ASC, t.start_date ASC, %s ASC", $depNameCol, $progressCol, $progressCol, $progressCol, $depJoin, $taskNameOrder);
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $tasks_result = $stmt->get_result();
        $tasks = [];

        while ($row = $tasks_result->fetch_assoc()) {
            $row['name'] = $row['name'] ?? $row['task_name'] ?? '';
            $row['description'] = $row['description'] ?? $row['task_description'] ?? '';
            if (!isset($row['progress_percent'])) { $row['progress_percent'] = $row['progress_percentage'] ?? 0; }
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
        
        // Detect column names for compatibility
        $colsRes = $conn->query("SHOW COLUMNS FROM projects");
        if (!$colsRes) {
            throw new Exception('Failed to introspect projects table: ' . $conn->error);
        }
        $columns = [];
        while ($c = $colsRes->fetch_assoc()) { $columns[] = $c['Field']; }

        if (in_array('project_name', $columns)) {
            // Database uses project_name, project_description, budget_allocated
            $col_name = 'project_name';
            $col_description = 'project_description';
            $col_budget = 'budget_allocated';
        } else {
            // Database uses name, description, budget
            $col_name = 'name';
            $col_description = 'description';
            $col_budget = 'budget';
        }
        
        // Insert project
        $insertQuery = sprintf("INSERT INTO projects (%s, %s, start_date, end_date, %s) VALUES (?, ?, ?, ?, ?)", 
            $col_name, $col_description, $col_budget);
        $stmt = $conn->prepare($insertQuery);
        if ($stmt === false) {
            throw new Exception('DB prepare failed: ' . $conn->error);
        }
        $stmt->bind_param("ssssd", $name, $description, $start_date, $end_date, $budget);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create project: ' . $stmt->error);
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
                $progress = $task['progress_percent'] ?? 0;
                $task_stmt = $conn->prepare("INSERT INTO tasks (project_id, name, description, start_date, end_date, progress_percent) VALUES (?, ?, ?, ?, ?, ?)");
                $task_stmt->bind_param("issssi", $project_id, $task['name'], $task['description'], $task['start_date'], $task['end_date'], $progress);
                if ($task_stmt->execute()) {
                    $task_ids[$index] = $conn->insert_id;
                }
                $task_stmt->close();
            }
            
            // Insert sub-tasks with dependencies
            foreach ($sub_tasks as $index => $task) {
                $parent_index = $task['dependency_task_index'];
                $dependency_task_id = isset($task_ids[$parent_index]) ? $task_ids[$parent_index] : null;
                $progress = $task['progress_percent'] ?? 0;
                
                $task_stmt = $conn->prepare("INSERT INTO tasks (project_id, name, description, start_date, end_date, dependency_task_id, progress_percent) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $task_stmt->bind_param("issssii", $project_id, $task['name'], $task['description'], $task['start_date'], $task['end_date'], $dependency_task_id, $progress);
                if ($task_stmt->execute()) {
                    $task_ids[$index] = $conn->insert_id;
                }
                $task_stmt->close();
            }
        }
        
        // Sync budget to project_budgets table if budget was provided
        if ($budget !== null && $budget > 0) {
            try {
                // Get default category or create 'General' category
                $catStmt = $conn->query("SELECT category_id FROM budget_categories WHERE name = 'General' LIMIT 1");
                if ($catStmt && $catStmt->num_rows > 0) {
                    $catRow = $catStmt->fetch_assoc();
                    $category_id = $catRow['category_id'];
                } else {
                    // Create General category if it doesn't exist
                    $conn->query("INSERT INTO budget_categories (name, description) VALUES ('General', 'General project expenses')");
                    $category_id = $conn->insert_id;
                }
                
                $budgetVal = (float)$budget;
                $insertBudget = $conn->prepare("INSERT INTO project_budgets (project_id, category_id, allocated_amount, spent_amount, fiscal_year, status) VALUES (?, ?, ?, 0, YEAR(CURDATE()), 'active')");
                $insertBudget->bind_param("iid", $project_id, $category_id, $budgetVal);
                $insertBudget->execute();
            } catch (Exception $e) {
                error_log("Warning: Failed to sync project_budgets on create: " . $e->getMessage());
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
        // Detect column names for compatibility
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
            // Sync budget to project_budgets table if budget was updated
            if ($budget !== null) {
                try {
                    // Check if project_budgets entry exists for this project
                    $checkBudget = $conn->prepare("SELECT budget_id, allocated_amount FROM project_budgets WHERE project_id = ? LIMIT 1");
                    $checkBudget->bind_param("i", $project_id);
                    $checkBudget->execute();
                    $budgetResult = $checkBudget->get_result();
                    
                    $budgetVal = (float)$budget;
                    
                    if ($budgetResult->num_rows > 0) {
                        // Update existing project_budgets entry
                        $budgetRow = $budgetResult->fetch_assoc();
                        $updateBudget = $conn->prepare("UPDATE project_budgets SET allocated_amount = ?, updated_at = NOW() WHERE budget_id = ?");
                        $updateBudget->bind_param("di", $budgetVal, $budgetRow['budget_id']);
                        $updateBudget->execute();
                    } else {
                        // Create new project_budgets entry
                        // Get default category or create 'General' category
                        $catStmt = $conn->query("SELECT category_id FROM budget_categories WHERE name = 'General' LIMIT 1");
                        if ($catStmt && $catStmt->num_rows > 0) {
                            $catRow = $catStmt->fetch_assoc();
                            $category_id = $catRow['category_id'];
                        } else {
                            // Create General category if it doesn't exist
                            $conn->query("INSERT INTO budget_categories (name, description) VALUES ('General', 'General project expenses')");
                            $category_id = $conn->insert_id;
                        }
                        
                        $insertBudget = $conn->prepare("INSERT INTO project_budgets (project_id, category_id, allocated_amount, spent_amount, fiscal_year, status) VALUES (?, ?, ?, 0, YEAR(CURDATE()), 'active')");
                        $insertBudget->bind_param("iid", $project_id, $category_id, $budgetVal);
                        $insertBudget->execute();
                    }
                } catch (Exception $e) {
                    error_log("Warning: Failed to sync project_budgets: " . $e->getMessage());
                }
            }
            
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
        
        // Determine project name column dynamically
        $colsRes = $conn->query("SHOW COLUMNS FROM projects");
        $projectCols = [];
        while ($c = $colsRes->fetch_assoc()) { $projectCols[] = $c['Field']; }
        
        $nameCol = in_array('project_name', $projectCols) ? 'project_name' : 'name';
        
        // Get project name for notification before deletion
        $nameStmt = $conn->prepare("SELECT $nameCol FROM projects WHERE project_id = ?");
        $nameStmt->bind_param("i", $project_id);
        $nameStmt->execute();
        $projectResult = $nameStmt->get_result()->fetch_assoc();
        $projectName = $projectResult[$nameCol] ?? 'Unknown Project';
        $nameStmt->close();
        
        // Delete the project - this will cascade to all related records
        // due to the ON DELETE CASCADE constraints in the database schema
        $stmt = $conn->prepare("DELETE FROM projects WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        
        if ($stmt->execute()) {
            // Create notification for project deletion
            // Note: We insert notification after project deletion to avoid FK issues
            // Since we're using CASCADE, all related records are already deleted
            $notificationStmt = $conn->prepare("
                INSERT INTO resource_notifications (notification_type, message, is_read)
                VALUES ('system_alert', ?, FALSE)
            ");
            if ($notificationStmt) {
                $message = "🗑️ Project deleted: '{$projectName}'";
                $notificationStmt->bind_param("s", $message);
                $notificationStmt->execute();
                $notificationStmt->close();
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Project and all associated tasks deleted successfully']);
        } else {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete project: ' . $stmt->error]);
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