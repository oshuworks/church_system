<?php
require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : 0;
    $taskId = isset($_GET['task_id']) ? (int) $_GET['task_id'] : 0;
    $assignmentType = isset($_GET['assignment_type']) ? trim($_GET['assignment_type']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';

    $conditions = [];
    $types = '';
    $params = [];

    if ($projectId > 0) {
        $conditions[] = "ta.project_id = ?";
        $types .= 'i';
        $params[] = $projectId;
    }
    if ($taskId > 0) {
        $conditions[] = "ta.task_id = ?";
        $types .= 'i';
        $params[] = $taskId;
    }
    if (!empty($assignmentType) && $assignmentType !== 'all') {
        $conditions[] = "ta.assignment_type = ?";
        $types .= 's';
        $params[] = $assignmentType;
    }
    if (!empty($status)) {
        $conditions[] = "ta.status = ?";
        $types .= 's';
        $params[] = $status;
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $sql = "
    SELECT 
        ta.assignment_id,
        ta.assignment_type,
        ta.resource_id,
        ta.project_id,
        ta.task_id,
        ta.allocation_percentage,
        ta.allocated_hours,
        COALESCE(ta.actual_hours_used, 0) as actual_hours_used,
        ta.start_date,
        ta.end_date,
        ta.status,
        ta.notes,
        t.task_name,
        p.project_name,
        CASE 
            WHEN ta.assignment_type = 'employee' THEN e.employee_name
            WHEN ta.assignment_type = 'equipment' THEN eq.equipment_name
            WHEN ta.assignment_type = 'budget' THEN CONCAT('Budget: ', bc.category_name)
        END AS resource_name,
        -- Get resource capacity for accurate calculations
        CASE 
            WHEN ta.assignment_type = 'employee' THEN e.max_work_hours_per_day
            WHEN ta.assignment_type = 'equipment' THEN 8 -- Standard 8 hours for equipment
            ELSE 8
        END as daily_capacity_hours,
        -- Calculate elapsed hours based on workdays and individual capacity
        CASE 
            WHEN ta.status IN ('completed', 'cancelled') THEN COALESCE(ta.actual_hours_used, 0)
            WHEN ta.start_date > CURDATE() THEN 0
            WHEN ta.end_date < CURDATE() THEN 
                GREATEST(COALESCE(ta.actual_hours_used, 0), 
                    -- Calculate workdays between start and end dates
                    (5 * FLOOR(DATEDIFF(LEAST(ta.end_date, CURDATE()), ta.start_date) / 7) + 
                     LEAST(DATEDIFF(LEAST(ta.end_date, CURDATE()), ta.start_date) % 7, 5)) * 
                    CASE 
                        WHEN ta.assignment_type = 'employee' THEN e.max_work_hours_per_day
                        WHEN ta.assignment_type = 'equipment' THEN 8
                        ELSE 8
                    END * (ta.allocation_percentage / 100))
            ELSE 
                GREATEST(COALESCE(ta.actual_hours_used, 0), 
                    -- Calculate workdays from start to today
                    (5 * FLOOR(DATEDIFF(CURDATE(), ta.start_date) / 7) + 
                     LEAST(DATEDIFF(CURDATE(), ta.start_date) % 7, 5) + 1) * 
                    CASE 
                        WHEN ta.assignment_type = 'employee' THEN e.max_work_hours_per_day
                        WHEN ta.assignment_type = 'equipment' THEN 8
                        ELSE 8
                    END * (ta.allocation_percentage / 100))
        END as calculated_hours_used
    FROM task_assignments ta
    JOIN tasks t ON t.task_id = ta.task_id
    JOIN projects p ON p.project_id = ta.project_id
    LEFT JOIN employees e ON e.employee_id = ta.resource_id AND ta.assignment_type = 'employee'
    LEFT JOIN equipment eq ON eq.equipment_id = ta.resource_id AND ta.assignment_type = 'equipment'
    LEFT JOIN project_budgets pb ON pb.budget_id = ta.resource_id AND ta.assignment_type = 'budget'
    LEFT JOIN budget_categories bc ON bc.category_id = pb.category_id
    $where
    ORDER BY ta.start_date DESC";

    $stmt = runQuery($mysqli, $sql, $types ? [$types, $params] : []);
    $rows = fetchAllAssoc($stmt);
    respond(['assignments' => $rows]);
}

if ($method === 'POST') {
    // Get sanitized input from bootstrap
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $input = sanitizeInput($input);

    $required = ['project_id', 'task_id', 'assignment_type', 'resource_id', 'allocation_percentage', 'allocated_hours', 'start_date', 'end_date'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            respond(['error' => "Missing field: $field"], 400);
        }
    }

    $projectId = (int) $input['project_id'];
    $taskId = (int) $input['task_id'];
    $assignmentType = $input['assignment_type'];
    $resourceId = (int) $input['resource_id'];
    $allocationPercentage = (float) $input['allocation_percentage'];
    $allocatedHours = (float) $input['allocated_hours'];
    $startDate = trim($input['start_date']);
    $endDate = trim($input['end_date']);
    $notes = isset($input['notes']) ? trim($input['notes']) : null;

    // Validate allocation percentage range
    if ($allocationPercentage < 1 || $allocationPercentage > 150) {
        respond(['error' => 'Allocation percentage must be between 1 and 150'], 400);
    }

    // Validate allocated hours (skip for budget assignments as it's calculated automatically)
    if ($assignmentType !== 'budget' && $allocatedHours < 0.1) {
        respond(['error' => 'Allocated hours must be at least 0.1'], 400);
    }

    // Validate date format and range
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        respond(['error' => 'Invalid date format. Use YYYY-MM-DD'], 400);
    }

    $startTimestamp = strtotime($startDate);
    $endTimestamp = strtotime($endDate);
    $todayTimestamp = strtotime(date('Y-m-d'));

    if ($startTimestamp < $todayTimestamp - 86400) {
        respond(['error' => 'Start date cannot be in the past'], 400);
    }

    if ($startTimestamp > $endTimestamp) {
        respond(['error' => 'Start date must be before or equal to end date'], 400);
    }

    // Limit assignment duration to 1 year
    if (($endTimestamp - $startTimestamp) > 365 * 86400) {
        respond(['error' => 'Assignment duration cannot exceed 1 year'], 400);
    }

    if (!in_array($assignmentType, ['employee', 'equipment', 'budget'], true)) {
        respond(['error' => 'Invalid assignment_type'], 400);
    }

    // Validate task belongs to project
    $taskCheck = fetchAllAssoc(runQuery($mysqli, "SELECT task_id FROM tasks WHERE task_id = ? AND project_id = ?", ['ii', [$taskId, $projectId]]));
    if (empty($taskCheck)) {
        respond(['error' => 'Task does not belong to the specified project'], 400);
    }

    // Validate resource existence and status
    $resourceName = '';
    if ($assignmentType === 'employee') {
        $res = fetchAllAssoc(runQuery($mysqli, "SELECT employee_name, status, utilization_percentage, max_work_hours_per_day FROM employees WHERE employee_id = ?", ['i', [$resourceId]]));
        if (empty($res)) respond(['error' => 'Employee not found'], 404);
        $row = $res[0];
        $resourceName = $row['employee_name'];
        if ($row['status'] !== 'active') {
            respond(['error' => 'Employee is not active'], 400);
        }
        
        // Calculate PROJECTED utilization after this assignment
        $currentUtilization = (float) $row['utilization_percentage'];
        $projectedUtilization = $currentUtilization + $allocationPercentage;
        
        if ($projectedUtilization > 100) {
            respond(['error' => "Employee utilization would be {$projectedUtilization}% (exceeds 100%). Current: {$currentUtilization}%, New: {$allocationPercentage}%"], 409);
        }
    } elseif ($assignmentType === 'equipment') {
        $res = fetchAllAssoc(runQuery($mysqli, "SELECT equipment_name, status, utilization_percentage, total_capacity_hours FROM equipment WHERE equipment_id = ?", ['i', [$resourceId]]));
        if (empty($res)) respond(['error' => 'Equipment not found'], 404);
        $row = $res[0];
        $resourceName = $row['equipment_name'];
        if ($row['status'] === 'retired') {
            respond(['error' => 'Equipment is retired/unavailable'], 400);
        }
        
        // Calculate PROJECTED utilization after this assignment
        $currentUtilization = (float) $row['utilization_percentage'];
        $projectedUtilization = $currentUtilization + $allocationPercentage;
        
        if ($projectedUtilization > 100) {
            respond(['error' => "Equipment utilization would be {$projectedUtilization}% (exceeds 100%). Current: {$currentUtilization}%, New: {$allocationPercentage}%"], 409);
        }
    } else {
        // Budget assignment validation
        $res = fetchAllAssoc(runQuery($mysqli, "SELECT pb.budget_id, pb.project_id, pb.allocated_amount, pb.spent_amount, pb.remaining_amount, bc.category_name FROM project_budgets pb JOIN budget_categories bc ON bc.category_id = pb.category_id WHERE pb.budget_id = ?", ['i', [$resourceId]]));
        if (empty($res)) respond(['error' => 'Budget not found'], 404);
        $row = $res[0];
        $resourceName = 'Budget: ' . $row['category_name'];
        if ((int) $row['project_id'] !== $projectId) {
            respond(['error' => 'Budget does not belong to this project'], 400);
        }
        
        // For budget assignments, allocated_hours field stores the actual budget amount
        // Validate that the allocated amount doesn't exceed remaining budget
        if ($allocatedHours > (float) $row['remaining_amount']) {
            respond(['error' => "Budget allocation ({$allocatedHours}) exceeds remaining amount ({$row['remaining_amount']})"], 409);
        }
    }

    $insertSql = "
    INSERT INTO task_assignments (task_id, project_id, assignment_type, resource_id, allocation_percentage, allocated_hours, start_date, end_date, status, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'assigned', ?)";
    
    // Insert the assignment
    runQuery($mysqli, $insertSql, ['iisidssss', [$taskId, $projectId, $assignmentType, $resourceId, $allocationPercentage, $allocatedHours, $startDate, $endDate, $notes]]);

    // Update task status to 'in_progress' if it was 'not_started'
    runQuery($mysqli, "UPDATE tasks SET status = 'in_progress' WHERE task_id = ? AND status = 'not_started'", ['i', [$taskId]]);

    // Update equipment utilization_percentage if equipment assignment
    if ($assignmentType === 'equipment') {
        // Calculate new utilization based on all current assignments
        $utilizationSql = "
            SELECT 
                SUM(
                    CASE 
                        WHEN ta.start_date > CURDATE() THEN 0
                        WHEN ta.end_date < CURDATE() THEN 
                            (5 * FLOOR(DATEDIFF(LEAST(ta.end_date, CURDATE()), ta.start_date) / 7) + 
                             LEAST(DATEDIFF(LEAST(ta.end_date, CURDATE()), ta.start_date) % 7, 5)) * 
                            8 * (ta.allocation_percentage / 100)
                        ELSE 
                            (5 * FLOOR(DATEDIFF(CURDATE(), ta.start_date) / 7) + 
                             LEAST(DATEDIFF(CURDATE(), ta.start_date) % 7, 5) + 1) * 
                            8 * (ta.allocation_percentage / 100)
                    END
                ) as total_hours_used
            FROM task_assignments ta 
            WHERE ta.resource_id = ? 
            AND ta.assignment_type = 'equipment' 
            AND ta.status = 'assigned'
            AND ta.start_date <= CURDATE()
            AND ta.end_date >= CURDATE()
        ";
        
        $utilResult = fetchAllAssoc(runQuery($mysqli, $utilizationSql, ['i', [$resourceId]]));
        $hoursUsed = $utilResult[0]['total_hours_used'] ?? 0;
        
        // Get equipment capacity
        $equipmentData = fetchAllAssoc(runQuery($mysqli, "SELECT total_capacity_hours FROM equipment WHERE equipment_id = ?", ['i', [$resourceId]]));
        $totalCapacity = $equipmentData[0]['total_capacity_hours'] ?? 40; // Default 40 hours/week
        
        // Calculate and update utilization percentage
        $newUtilization = $totalCapacity > 0 ? ($hoursUsed / $totalCapacity) * 100 : 0;
        runQuery($mysqli, "UPDATE equipment SET utilization_percentage = ? WHERE equipment_id = ?", ['di', [$newUtilization, $resourceId]]);
    }

    // DO NOT update task actual_hours on assignment creation
    // New assignments start with actual_hours_used = 0, so task actual_hours should remain unchanged
    // Only update actual_hours when assignments are completed or actual hours are manually updated

    respond([
        'message' => 'Assignment created',
        'resource_name' => $resourceName,
        'assignment_type' => $assignmentType
    ], 201);
}

if ($method === 'PUT') {
    // Handle complete or cancel assignment
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $input = sanitizeInput($input);
    
    $assignmentId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $action = $input['action'] ?? '';
    
    if ($assignmentId <= 0) {
        respond(['error' => 'Assignment ID required'], 400);
    }
    
    if (!in_array($action, ['complete', 'cancel'], true)) {
        respond(['error' => 'Invalid action. Use "complete" or "cancel"'], 400);
    }
    
    // Check if assignment exists
    $assignment = fetchAllAssoc(runQuery($mysqli, "SELECT * FROM task_assignments WHERE assignment_id = ?", ['i', [$assignmentId]]));
    if (empty($assignment)) {
        respond(['error' => 'Assignment not found'], 404);
    }
    
    $currentStatus = $assignment[0]['status'];
    
    // Validate status transitions
    if ($action === 'complete' && $currentStatus !== 'assigned') {
        respond(['error' => 'Only assigned assignments can be completed'], 400);
    }
    
    if ($action === 'cancel' && !in_array($currentStatus, ['assigned'], true)) {
        respond(['error' => 'Only assigned assignments can be cancelled'], 400);
    }
    
    $newStatus = $action === 'complete' ? 'completed' : 'cancelled';
    
    // Update assignment status
    runQuery($mysqli, "UPDATE task_assignments SET status = ? WHERE assignment_id = ?", ['si', [$newStatus, $assignmentId]]);

    // Update equipment utilization_percentage if this was an equipment assignment
    if ($assignment[0]['assignment_type'] === 'equipment') {
        $equipmentId = $assignment[0]['resource_id'];
        
        // Calculate new utilization based on all current assignments
        $utilizationSql = "
            SELECT 
                SUM(
                    CASE 
                        WHEN ta.start_date > CURDATE() THEN 0
                        WHEN ta.end_date < CURDATE() THEN 
                            (5 * FLOOR(DATEDIFF(LEAST(ta.end_date, CURDATE()), ta.start_date) / 7) + 
                             LEAST(DATEDIFF(LEAST(ta.end_date, CURDATE()), ta.start_date) % 7, 5)) * 
                            8 * (ta.allocation_percentage / 100)
                        ELSE 
                            (5 * FLOOR(DATEDIFF(CURDATE(), ta.start_date) / 7) + 
                             LEAST(DATEDIFF(CURDATE(), ta.start_date) % 7, 5) + 1) * 
                            8 * (ta.allocation_percentage / 100)
                    END
                ) as total_hours_used
            FROM task_assignments ta 
            WHERE ta.resource_id = ? 
            AND ta.assignment_type = 'equipment' 
            AND ta.status = 'assigned'
            AND ta.start_date <= CURDATE()
            AND ta.end_date >= CURDATE()
        ";
        
        $utilResult = fetchAllAssoc(runQuery($mysqli, $utilizationSql, ['i', [$equipmentId]]));
        $hoursUsed = $utilResult[0]['total_hours_used'] ?? 0;
        
        // Get equipment capacity
        $equipmentData = fetchAllAssoc(runQuery($mysqli, "SELECT total_capacity_hours FROM equipment WHERE equipment_id = ?", ['i', [$equipmentId]]));
        $totalCapacity = $equipmentData[0]['total_capacity_hours'] ?? 40; // Default 40 hours/week
        
        // Calculate and update utilization percentage
        $newUtilization = $totalCapacity > 0 ? ($hoursUsed / $totalCapacity) * 100 : 0;
        runQuery($mysqli, "UPDATE equipment SET utilization_percentage = ? WHERE equipment_id = ?", ['di', [$newUtilization, $equipmentId]]);
    }

    // Get task_id and assignment details for database updates
    $taskId = $assignment[0]['task_id'];
    $allocationPercentage = $assignment[0]['allocation_percentage'];
    $estimatedHours = $assignment[0]['allocated_hours']; // This stores estimated hours for assignments
    
    // Handle budget spending when completing budget assignments
    if ($assignment[0]['assignment_type'] === 'budget' && $action === 'complete') {
        $budgetId = $assignment[0]['resource_id'];
        $allocatedAmount = $assignment[0]['allocated_hours']; // This stores the budget amount
        
        // Update the budget's spent_amount
        runQuery($mysqli, "UPDATE project_budgets SET spent_amount = spent_amount + ? WHERE budget_id = ?", ['di', [$allocatedAmount, $budgetId]]);
        
        // Set actual_hours_used for budget assignments based on allocation percentage
        // This ensures task progress calculation works correctly
        $taskDetails = fetchAllAssoc(runQuery($mysqli, "SELECT estimated_hours FROM tasks WHERE task_id = ?", ['i', [$taskId]]));
        $taskEstimatedHours = $taskDetails[0]['estimated_hours'] ?? 0;
        $actualHoursForBudgetAssignment = ($taskEstimatedHours * $allocationPercentage) / 100;
        
        // Update assignment actual_hours_used for progress tracking
        runQuery($mysqli, "UPDATE task_assignments SET actual_hours_used = ? WHERE assignment_id = ?", 
                 ['di', [$actualHoursForBudgetAssignment, $assignmentId]]);
    }
    
    if ($action === 'complete') {
        // Calculate actual hours based on allocation percentage
        // If assignment was 100% allocation and task estimated 40 hours, then actual = 40 hours
        $taskDetails = fetchAllAssoc(runQuery($mysqli, "SELECT estimated_hours FROM tasks WHERE task_id = ?", ['i', [$taskId]]));
        $taskEstimatedHours = $taskDetails[0]['estimated_hours'] ?? 0;
        
        // Calculate actual hours worked for this assignment
        $actualHoursForAssignment = ($taskEstimatedHours * $allocationPercentage) / 100;
        
        // Update assignment actual_hours_used
        runQuery($mysqli, "UPDATE task_assignments SET actual_hours_used = ? WHERE assignment_id = ?", 
                 ['di', [$actualHoursForAssignment, $assignmentId]]);

        // Update task actual_hours to sum of all assignment actual_hours_used
        $updateTaskSql = "
            UPDATE tasks t 
            SET t.actual_hours = (
                SELECT SUM(COALESCE(ta.actual_hours_used, 0))
                FROM task_assignments ta 
                WHERE ta.task_id = t.task_id
            )
            WHERE t.task_id = ?
        ";
        runQuery($mysqli, $updateTaskSql, ['i', [$taskId]]);

        // Check if task should be marked as completed (100% progress)
        // Only mark as completed if ALL assignments for this task are completed
        $allAssignmentsSql = "
            SELECT 
                SUM(CASE WHEN ta.status = 'completed' THEN ta.allocation_percentage ELSE 0 END) as completed_allocation,
                SUM(ta.allocation_percentage) as total_allocation
            FROM task_assignments ta 
            WHERE ta.task_id = ? 
            AND ta.status != 'deleted'
        ";
        $allAssignments = fetchAllAssoc(runQuery($mysqli, $allAssignmentsSql, ['i', [$taskId]]));
        $completedAllocation = $allAssignments[0]['completed_allocation'] ?? 0;
        $totalAllocation = $allAssignments[0]['total_allocation'] ?? 0;
        
        // Only mark task as completed if 100% of allocations are completed
        if ($totalAllocation > 0 && ($completedAllocation / $totalAllocation) >= 1.0) {
            // Task reached 100% completion
            runQuery($mysqli, "UPDATE tasks SET status = 'completed' WHERE task_id = ?", ['i', [$taskId]]);
            
            // Update project status based on all tasks
            require_once __DIR__ . '/update_project_status.php';
            $projectDetails = fetchAllAssoc(runQuery($mysqli, "SELECT project_id FROM tasks WHERE task_id = ?", ['i', [$taskId]]));
            if (!empty($projectDetails)) {
                updateProjectStatus($mysqli, $projectDetails[0]['project_id']);
            }
        }
    } elseif ($action === 'cancel') {
        // For cancelled assignments, set actual_hours_used to 0
        runQuery($mysqli, "UPDATE task_assignments SET actual_hours_used = 0 WHERE assignment_id = ?", ['i', [$assignmentId]]);

        // Update task actual_hours to sum of remaining assignment actual_hours_used (excluding deleted)
        $updateTaskSql = "
            UPDATE tasks t 
            SET t.actual_hours = (
                SELECT SUM(COALESCE(ta.actual_hours_used, 0))
                FROM task_assignments ta 
                WHERE ta.task_id = t.task_id
                AND ta.status != 'deleted'
            )
            WHERE t.task_id = ?
        ";
        runQuery($mysqli, $updateTaskSql, ['i', [$taskId]]);

        // DO NOT change task status - keep it as 'in_progress' unless no assignments exist
        // Only change task status if there are literally NO assignments left (excluding deleted)
        $totalAssignments = fetchAllAssoc(runQuery($mysqli, 
            "SELECT COUNT(*) as count FROM task_assignments WHERE task_id = ? AND status != 'deleted'", 
            ['i', [$taskId]]
        ));
        
        if ($totalAssignments[0]['count'] == 0) {
            // No assignments at all (excluding deleted), reset task to not_started
            runQuery($mysqli, "UPDATE tasks SET status = 'not_started' WHERE task_id = ?", ['i', [$taskId]]);
        }
        // Otherwise, keep task status as is (usually 'in_progress')
    }
    
    respond([
        'message' => "Assignment {$newStatus} successfully",
        'assignment_id' => $assignmentId,
        'new_status' => $newStatus,
        'task_id' => $taskId,
        'task_updated' => true
    ]);
}

if ($method === 'DELETE') {
    // Handle soft deletion of completed or cancelled assignments
    $assignmentId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $bulkDelete = isset($_GET['bulk']) ? $_GET['bulk'] : '';
    
    if ($bulkDelete !== '') {
        // Bulk soft delete all completed or cancelled assignments
        if (!in_array($bulkDelete, ['completed', 'cancelled'], true)) {
            respond(['error' => 'Invalid bulk delete parameter. Use "completed" or "cancelled"'], 400);
        }
        
        // Count assignments before soft deletion
        $countSql = "SELECT COUNT(*) as count FROM task_assignments WHERE status = ?";
        $countResult = fetchAllAssoc(runQuery($mysqli, $countSql, ['s', [$bulkDelete]]));
        $deletedCount = $countResult[0]['count'] ?? 0;
        
        if ($deletedCount === 0) {
            respond(['error' => "No {$bulkDelete} assignments found to delete"], 404);
        }
        
        // Soft delete: Change status to 'deleted' instead of removing records
        runQuery($mysqli, "UPDATE task_assignments SET status = 'deleted' WHERE status = ?", ['s', [$bulkDelete]]);

        // Restore budget amounts for completed budget assignments
        if ($bulkDelete === 'completed') {
            // Get all completed budget assignments being deleted
            $budgetAssignmentsSql = "
                SELECT ta.resource_id as budget_id, ta.allocated_hours as amount
                FROM task_assignments ta 
                WHERE ta.assignment_type = 'budget' 
                AND ta.status = 'deleted'
                AND ta.resource_id NOT IN (
                    SELECT resource_id FROM task_assignments 
                    WHERE assignment_type = 'budget' 
                    AND status IN ('assigned', 'completed', 'cancelled')
                )
            ";
            $budgetAssignments = fetchAllAssoc(runQuery($mysqli, $budgetAssignmentsSql));
            
            foreach ($budgetAssignments as $budget) {
                // Restore the full allocated amount back to each budget
                runQuery($mysqli, "UPDATE project_budgets SET spent_amount = spent_amount - ? WHERE budget_id = ?", 
                         ['di', [$budget['amount'], $budget['budget_id']]]);
            }
        }

        // Update equipment utilization for all affected equipment
        $affectedEquipmentSql = "
            SELECT DISTINCT ta.resource_id as equipment_id
            FROM task_assignments ta 
            WHERE ta.assignment_type = 'equipment' 
            AND ta.status = 'deleted'
        ";
        $affectedEquipment = fetchAllAssoc(runQuery($mysqli, $affectedEquipmentSql));
        
        foreach ($affectedEquipment as $eq) {
            $equipmentId = $eq['equipment_id'];
            
            // Calculate new utilization based on all current assignments
            $utilizationSql = "
                SELECT 
                    SUM(
                        CASE 
                            WHEN ta.start_date > CURDATE() THEN 0
                            WHEN ta.end_date < CURDATE() THEN 
                                (5 * FLOOR(DATEDIFF(LEAST(ta.end_date, CURDATE()), ta.start_date) / 7) + 
                                 LEAST(DATEDIFF(LEAST(ta.end_date, CURDATE()), ta.start_date) % 7, 5)) * 
                                8 * (ta.allocation_percentage / 100)
                            ELSE 
                                (5 * FLOOR(DATEDIFF(CURDATE(), ta.start_date) / 7) + 
                                 LEAST(DATEDIFF(CURDATE(), ta.start_date) % 7, 5) + 1) * 
                                8 * (ta.allocation_percentage / 100)
                        END
                    ) as total_hours_used
                FROM task_assignments ta 
                WHERE ta.resource_id = ? 
                AND ta.assignment_type = 'equipment' 
                AND ta.status = 'assigned'
                AND ta.start_date <= CURDATE()
                AND ta.end_date >= CURDATE()
            ";
            
            $utilResult = fetchAllAssoc(runQuery($mysqli, $utilizationSql, ['i', [$equipmentId]]));
            $hoursUsed = $utilResult[0]['total_hours_used'] ?? 0;
            
            // Get equipment capacity
            $equipmentData = fetchAllAssoc(runQuery($mysqli, "SELECT total_capacity_hours FROM equipment WHERE equipment_id = ?", ['i', [$equipmentId]]));
            $totalCapacity = $equipmentData[0]['total_capacity_hours'] ?? 40; // Default 40 hours/week
            
            // Calculate and update utilization percentage
            $newUtilization = $totalCapacity > 0 ? ($hoursUsed / $totalCapacity) * 100 : 0;
            runQuery($mysqli, "UPDATE equipment SET utilization_percentage = ? WHERE equipment_id = ?", ['di', [$newUtilization, $equipmentId]]);
        }

        // Update all affected tasks to recalculate actual_hours (excluding deleted assignments)
        $affectedTasksSql = "
            UPDATE tasks t 
            SET t.actual_hours = (
                SELECT SUM(COALESCE(ta.actual_hours_used, 0))
                FROM task_assignments ta 
                WHERE ta.task_id = t.task_id
                AND ta.status != 'deleted'
            )
            WHERE t.task_id IN (
                SELECT DISTINCT task_id FROM task_assignments WHERE status != 'deleted'
            )
        ";
        runQuery($mysqli, $affectedTasksSql);

        // Update project statuses based on remaining non-deleted assignments
        require_once __DIR__ . '/update_project_status.php';
        updateProjectStatus($mysqli);
        
        respond([
            'message' => "All {$bulkDelete} assignments soft deleted successfully",
            'deleted_count' => $deletedCount,
            'status' => $bulkDelete,
            'tasks_updated' => true
        ]);
    } else {
        // Single assignment soft deletion
        if ($assignmentId <= 0) {
            respond(['error' => 'Assignment ID required'], 400);
        }
        
        // Check if assignment exists and get its details
        $assignment = fetchAllAssoc(runQuery($mysqli, "SELECT status, assignment_type, resource_id, project_id, task_id, allocated_hours FROM task_assignments WHERE assignment_id = ?", ['i', [$assignmentId]]));
        if (empty($assignment)) {
            respond(['error' => 'Assignment not found'], 404);
        }
        
        $currentStatus = $assignment[0]['status'];
        $assignmentType = $assignment[0]['assignment_type'];
        $allocatedAmount = $assignment[0]['allocated_hours']; // This stores the budget amount for budget assignments
        
        // Only allow soft deletion of completed or cancelled assignments
        if (!in_array($currentStatus, ['completed', 'cancelled'], true)) {
            respond(['error' => 'Only completed or cancelled assignments can be deleted'], 400);
        }
        
        // Soft delete: Change status to 'deleted' instead of removing record
        runQuery($mysqli, "UPDATE task_assignments SET status = 'deleted' WHERE assignment_id = ?", ['i', [$assignmentId]]);

        // Restore budget amount if this was a completed budget assignment
        if ($assignmentType === 'budget' && $currentStatus === 'completed') {
            $budgetId = $assignment[0]['resource_id'];
            
            // Restore the full allocated amount back to the budget
            runQuery($mysqli, "UPDATE project_budgets SET spent_amount = spent_amount - ? WHERE budget_id = ?", ['di', [$allocatedAmount, $budgetId]]);
        }

        // Update equipment utilization_percentage if this was an equipment assignment
        if ($assignment[0]['assignment_type'] === 'equipment') {
            $equipmentId = $assignment[0]['resource_id'];
            
            // Calculate new utilization based on all current assignments
            $utilizationSql = "
                SELECT 
                    SUM(
                        CASE 
                            WHEN ta.start_date > CURDATE() THEN 0
                            WHEN ta.end_date < CURDATE() THEN 
                                (5 * FLOOR(DATEDIFF(LEAST(ta.end_date, CURDATE()), ta.start_date) / 7) + 
                                 LEAST(DATEDIFF(LEAST(ta.end_date, CURDATE()), ta.start_date) % 7, 5)) * 
                                8 * (ta.allocation_percentage / 100)
                            ELSE 
                                (5 * FLOOR(DATEDIFF(CURDATE(), ta.start_date) / 7) + 
                                 LEAST(DATEDIFF(CURDATE(), ta.start_date) % 7, 5) + 1) * 
                                8 * (ta.allocation_percentage / 100)
                        END
                    ) as total_hours_used
                FROM task_assignments ta 
                WHERE ta.resource_id = ? 
                AND ta.assignment_type = 'equipment' 
                AND ta.status = 'assigned'
                AND ta.start_date <= CURDATE()
                AND ta.end_date >= CURDATE()
            ";
            
            $utilResult = fetchAllAssoc(runQuery($mysqli, $utilizationSql, ['i', [$equipmentId]]));
            $hoursUsed = $utilResult[0]['total_hours_used'] ?? 0;
            
            // Get equipment capacity
            $equipmentData = fetchAllAssoc(runQuery($mysqli, "SELECT total_capacity_hours FROM equipment WHERE equipment_id = ?", ['i', [$equipmentId]]));
            $totalCapacity = $equipmentData[0]['total_capacity_hours'] ?? 40; // Default 40 hours/week
            
            // Calculate and update utilization percentage
            $newUtilization = $totalCapacity > 0 ? ($hoursUsed / $totalCapacity) * 100 : 0;
            runQuery($mysqli, "UPDATE equipment SET utilization_percentage = ? WHERE equipment_id = ?", ['di', [$newUtilization, $equipmentId]]);
        }

        // Update task actual_hours after soft deletion (excluding deleted assignments)
        $taskId = $assignment[0]['task_id'];
        $updateTaskSql = "
            UPDATE tasks t 
            SET t.actual_hours = (
                SELECT SUM(COALESCE(ta.actual_hours_used, 0))
                FROM task_assignments ta 
                WHERE ta.task_id = t.task_id
                AND ta.status != 'deleted'
            )
            WHERE t.task_id = ?
        ";
        runQuery($mysqli, $updateTaskSql, ['i', [$taskId]]);

        // Update project status based on remaining non-deleted assignments
        require_once __DIR__ . '/update_project_status.php';
        $projectDetails = fetchAllAssoc(runQuery($mysqli, "SELECT project_id FROM tasks WHERE task_id = ?", ['i', [$taskId]]));
        if (!empty($projectDetails)) {
            updateProjectStatus($mysqli, $projectDetails[0]['project_id']);
        }
        
        respond([
            'message' => 'Assignment soft deleted successfully',
            'assignment_id' => $assignmentId,
            'deleted_status' => $currentStatus,
            'task_id' => $taskId,
            'task_updated' => true
        ]);
    }
}

respond(['error' => 'Method not allowed'], 405);

