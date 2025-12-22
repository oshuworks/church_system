<?php
// budget_allocation.php - API endpoint for budget allocation management
include '../includes/db.php';
header('Content-Type: application/json');

// Handle GET request to fetch budget allocation details
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $project_id = $_GET['project_id'] ?? null;
    $budget_id = $_GET['budget_id'] ?? null;

    // Handle request for detailed budget allocations by budget ID
    if ($budget_id) {
        $stmt = $conn->prepare("
            SELECT ba.*, t.name as task_name, r.name as resource_name,
                   p.name as project_name, b.description as budget_description
            FROM budget_allocations ba
            JOIN tasks t ON ba.task_id = t.task_id
            JOIN budgets b ON ba.budget_id = b.budget_id
            JOIN projects p ON b.project_id = p.project_id
            LEFT JOIN resources r ON ba.resource_id = r.resource_id
            WHERE ba.budget_id = ?
            ORDER BY t.name
        ");
        $stmt->bind_param("i", $budget_id);
        $stmt->execute();
        $allocations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['budget_allocations' => $allocations]);
        exit;
    }

    // Handle request for budgets by project ID
    if ($project_id) {
        $stmt = $conn->prepare("
            SELECT b.*, p.name as project_name,
                   COALESCE(SUM(ra.allocation_percent * r.cost_per_unit / 100), 0) as allocated_amount,
                   (b.estimated_cost - COALESCE(SUM(ra.allocation_percent * r.cost_per_unit / 100), 0)) as remaining_budget
            FROM budgets b
            JOIN projects p ON b.project_id = p.project_id
            LEFT JOIN resource_allocations ra ON b.project_id = ra.task_id
            LEFT JOIN resources r ON ra.resource_id = r.resource_id
            WHERE b.project_id = ?
            GROUP BY b.budget_id, b.project_id, b.description, b.estimated_cost, b.actual_cost, p.name
            ORDER BY b.description
        ");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $budgets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['budgets' => $budgets]);
        exit;
    }

    // Handle request for all budgets (no specific project or budget ID)
    $stmt = $conn->prepare("
        SELECT b.*, p.name as project_name,
               (b.estimated_cost - COALESCE(SUM(ra.allocation_percent * r.cost_per_unit / 100), 0)) as remaining_budget
        FROM budgets b
        JOIN projects p ON b.project_id = p.project_id
        LEFT JOIN resource_allocations ra ON b.project_id = ra.task_id AND ra.resource_id IN (SELECT resource_id FROM resources WHERE resource_type = 'Financial')
        LEFT JOIN resources r ON ra.resource_id = r.resource_id
        GROUP BY b.budget_id, b.project_id, b.description, b.estimated_cost, b.actual_cost, p.name
        ORDER BY p.name, b.description
    ");
    $stmt->execute();
    $budgets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['budgets' => $budgets]);
    exit;
}

// Handle POST request for detailed budget allocation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $budget_id = $_POST['budget_id'] ?? null;
    $task_id = $_POST['task_id'] ?? null;
    $resource_id = $_POST['resource_id'] ?? null;
    $allocated_amount = $_POST['allocated_amount'] ?? 0;
    $spent_amount = $_POST['spent_amount'] ?? 0;

    // Check if this is a general budget creation or detailed allocation
    if ($budget_id && $task_id) {
        // This is a detailed budget allocation to a specific task/resource
        if (!$allocated_amount) {
            http_response_code(400);
            echo json_encode(['error' => 'Allocated amount is required']);
            exit;
        }

        try {
            // Check if budget exists
            $stmt = $conn->prepare("SELECT budget_id, project_id FROM budgets WHERE budget_id = ?");
            $stmt->bind_param("i", $budget_id);
            $stmt->execute();
            $budget = $stmt->get_result()->fetch_assoc();

            if (!$budget) {
                http_response_code(404);
                echo json_encode(['error' => 'Budget not found']);
                exit;
            }

            // Check if task exists
            $stmt = $conn->prepare("SELECT task_id FROM tasks WHERE task_id = ?");
            $stmt->bind_param("i", $task_id);
            $stmt->execute();
            $task = $stmt->get_result()->fetch_assoc();

            if (!$task) {
                http_response_code(404);
                echo json_encode(['error' => 'Task not found']);
                exit;
            }

            // Check if resource exists (if provided)
            if ($resource_id) {
                $stmt = $conn->prepare("SELECT resource_id FROM resources WHERE resource_id = ?");
                $stmt->bind_param("i", $resource_id);
                $stmt->execute();
                $resource = $stmt->get_result()->fetch_assoc();

                if (!$resource) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Resource not found']);
                    exit;
                }
            }

            // Check current total allocation for this budget
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(allocated_amount), 0) as total_allocated
                FROM budget_allocations
                WHERE budget_id = ?
            ");
            $stmt->bind_param("i", $budget_id);
            $stmt->execute();
            $total_result = $stmt->get_result()->fetch_assoc();
            $current_total_allocation = $total_result['total_allocated'];

            // Check if new allocation would exceed budget
            $stmt = $conn->prepare("SELECT estimated_cost FROM budgets WHERE budget_id = ?");
            $stmt->bind_param("i", $budget_id);
            $stmt->execute();
            $budget_info = $stmt->get_result()->fetch_assoc();
            $budget_limit = $budget_info['estimated_cost'];

            if ($current_total_allocation + $allocated_amount > $budget_limit) {
                http_response_code(400);
                echo json_encode([
                    'error' => "Budget allocation would exceed budget limit of ₱" . number_format($budget_limit, 2) .
                              ". Current total: ₱" . number_format($current_total_allocation, 2) .
                              ", requested: ₱" . number_format($allocated_amount, 2)
                ]);
                exit;
            }

            // Insert budget allocation
            if ($resource_id) {
                $stmt = $conn->prepare("INSERT INTO budget_allocations (budget_id, task_id, resource_id, allocated_amount, spent_amount) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iiidd", $budget_id, $task_id, $resource_id, $allocated_amount, $spent_amount);
            } else {
                $stmt = $conn->prepare("INSERT INTO budget_allocations (budget_id, task_id, allocated_amount, spent_amount) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iidd", $budget_id, $task_id, $allocated_amount, $spent_amount);
            }

            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Budget allocation created successfully',
                    'budget_allocation_id' => $conn->insert_id
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create budget allocation']);
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        // This is a general budget creation (existing logic)
        $project_id = $_POST['project_id'] ?? null;
        $description = $_POST['description'] ?? '';
        $estimated_cost = $_POST['estimated_cost'] ?? 0;
        $actual_cost = $_POST['actual_cost'] ?? 0;

        if (!$project_id || !$description || !$estimated_cost) {
            http_response_code(400);
            echo json_encode(['error' => 'Project ID, description, and estimated cost are required']);
            exit;
        }

        try {
            // Check if project exists
            $stmt = $conn->prepare("SELECT project_id, name FROM projects WHERE project_id = ?");
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            $project = $stmt->get_result()->fetch_assoc();

            if (!$project) {
                http_response_code(404);
                echo json_encode(['error' => 'Project not found']);
                exit;
            }

            // Insert new budget allocation
            $stmt = $conn->prepare("INSERT INTO budgets (project_id, description, estimated_cost, actual_cost) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isdd", $project_id, $description, $estimated_cost, $actual_cost);

            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Budget allocation created successfully',
                    'budget_id' => $conn->insert_id
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create budget allocation']);
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    }
    exit;
}

// Handle PUT request for updating budget allocation
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $budget_id = $_POST['budget_id'] ?? null;
    $description = $_POST['description'] ?? null;
    $estimated_cost = $_POST['estimated_cost'] ?? null;
    $actual_cost = $_POST['actual_cost'] ?? null;

    if (!$budget_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Budget ID is required']);
        exit;
    }

    // Build dynamic update query
    $update_fields = [];
    $params = [];
    $types = "";

    if ($description !== null) {
        $update_fields[] = "description = ?";
        $params[] = $description;
        $types .= "s";
    }
    if ($estimated_cost !== null) {
        $update_fields[] = "estimated_cost = ?";
        $params[] = $estimated_cost;
        $types .= "d";
    }
    if ($actual_cost !== null) {
        $update_fields[] = "actual_cost = ?";
        $params[] = $actual_cost;
        $types .= "d";
    }

    if (empty($update_fields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        exit;
    }

    $params[] = $budget_id;
    $types .= "i";

    $sql = "UPDATE budgets SET " . implode(", ", $update_fields) . " WHERE budget_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Budget allocation updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update budget allocation']);
    }
    exit;
}

// Handle DELETE request for removing budget allocation
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $budget_id = $_POST['budget_id'] ?? null;

    if (!$budget_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Budget ID is required']);
        exit;
    }

    try {
        $stmt = $conn->prepare("DELETE FROM budgets WHERE budget_id = ?");
        $stmt->bind_param("i", $budget_id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Budget allocation removed successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Budget allocation not found']);
            }
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to remove budget allocation']);
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
