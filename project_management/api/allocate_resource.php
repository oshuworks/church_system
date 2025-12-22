<?php
// allocate_resource.php - Enhanced resource allocation API
include '../includes/db.php';
header('Content-Type: application/json');

// Handle POST request for resource allocation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $task_id = $_POST['task_id'] ?? null;
    $resource_id = $_POST['resource_id'] ?? null;
    $allocation_percent = $_POST['allocation_percent'] ?? 0;

    // Validate required fields
    if (!$task_id || !$resource_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID, Resource ID are required']);
        exit;
    }

    // Validate allocation percentage
    if ($allocation_percent < 0 || $allocation_percent > 100) {
        http_response_code(400);
        echo json_encode(['error' => 'Allocation percentage must be between 0 and 100']);
        exit;
    }

    try {
        // Check if task exists
        $stmt = $conn->prepare("SELECT task_id, name FROM tasks WHERE task_id = ?");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $task = $stmt->get_result()->fetch_assoc();

        if (!$task) {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found']);
            exit;
        }

        // Check if resource exists
        $stmt = $conn->prepare("SELECT resource_id, name, availability FROM resources WHERE resource_id = ?");
        $stmt->bind_param("i", $resource_id);
        $stmt->execute();
        $resource = $stmt->get_result()->fetch_assoc();

        if (!$resource) {
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
            exit;
        }

        // Check if allocation already exists
        $stmt = $conn->prepare("SELECT allocation_id, allocation_percent FROM resource_allocations WHERE task_id = ? AND resource_id = ?");
        $stmt->bind_param("ii", $task_id, $resource_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        // Check current total allocation for this resource
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(allocation_percent), 0) as total_allocation
            FROM resource_allocations
            WHERE resource_id = ?
        ");
        $stmt->bind_param("i", $resource_id);
        $stmt->execute();
        $total_result = $stmt->get_result()->fetch_assoc();
        $current_total_allocation = $total_result['total_allocation'];

        // Check if new allocation would exceed 100%
        if ($existing) {
            // For updates, check if the new allocation would exceed 100%
            $new_total = $current_total_allocation - $existing['allocation_percent'] + $allocation_percent;
            if ($new_total > 100) {
                http_response_code(400);
                echo json_encode([
                    'error' => "Resource allocation would exceed 100%. Current total: {$current_total_allocation}%, requested: {$allocation_percent}%"
                ]);
                exit;
            }
        } else {
            // For new allocations, check if it would exceed 100%
            if ($current_total_allocation + $allocation_percent > 100) {
                http_response_code(400);
                echo json_encode([
                    'error' => "Resource allocation would exceed 100%. Current total: {$current_total_allocation}%, requested: {$allocation_percent}%"
                ]);
                exit;
            }
        }

        if ($existing) {
            // Update existing allocation
            $stmt = $conn->prepare("UPDATE resource_allocations SET allocation_percent = ? WHERE task_id = ? AND resource_id = ?");
            $stmt->bind_param("dii", $allocation_percent, $task_id, $resource_id);

            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Resource allocation updated successfully',
                    'allocation' => [
                        'task_id' => $task_id,
                        'resource_id' => $resource_id,
                        'allocation_percent' => $allocation_percent,
                        'task_name' => $task['name'],
                        'resource_name' => $resource['name']
                    ]
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update resource allocation']);
            }
        } else {
            // Insert new allocation
            $stmt = $conn->prepare("INSERT INTO resource_allocations (task_id, resource_id, allocation_percent) VALUES (?, ?, ?)");
            $stmt->bind_param("iid", $task_id, $resource_id, $allocation_percent);

            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Resource allocated successfully',
                    'allocation' => [
                        'task_id' => $task_id,
                        'resource_id' => $resource_id,
                        'allocation_percent' => $allocation_percent,
                        'task_name' => $task['name'],
                        'resource_name' => $resource['name']
                    ]
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create resource allocation']);
            }
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle GET request to fetch allocations for a specific task or resource
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $task_id = $_GET['task_id'] ?? null;
    $resource_id = $_GET['resource_id'] ?? null;

    if (!$task_id && !$resource_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID or Resource ID is required']);
        exit;
    }

    try {
        $where_clause = "";
        $params = [];
        $types = "";

        if ($task_id) {
            $where_clause = "ra.task_id = ?";
            $params[] = $task_id;
            $types .= "i";
        } else {
            $where_clause = "ra.resource_id = ?";
            $params[] = $resource_id;
            $types .= "i";
        }

        $stmt = $conn->prepare("
            SELECT ra.*, t.name as task_name, r.name as resource_name, r.resource_type,
                   p.name as project_name
            FROM resource_allocations ra
            JOIN tasks t ON ra.task_id = t.task_id
            JOIN resources r ON ra.resource_id = r.resource_id
            LEFT JOIN projects p ON t.project_id = p.project_id
            WHERE $where_clause
            ORDER BY t.name
        ");

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $allocations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['allocations' => $allocations]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle DELETE request to remove allocation
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $task_id = $_POST['task_id'] ?? null;
    $resource_id = $_POST['resource_id'] ?? null;

    if (!$task_id || !$resource_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID and Resource ID are required']);
        exit;
    }

    try {
        $stmt = $conn->prepare("DELETE FROM resource_allocations WHERE task_id = ? AND resource_id = ?");
        $stmt->bind_param("ii", $task_id, $resource_id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Resource allocation removed successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Allocation not found']);
            }
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to remove resource allocation']);
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
