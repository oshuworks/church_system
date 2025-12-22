<?php
// resource.php - API endpoint for resource management
include '../includes/db.php';
header('Content-Type: application/json');

// Handle GET request to fetch resource details
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $resource_id = $_GET['resource_id'] ?? null;
    $utilization = $_GET['utilization'] ?? null;

    if ($utilization === 'report') {
        // Generate utilization report for all resources
        $stmt = $conn->prepare("
            SELECT r.resource_id, r.name, r.resource_type, r.availability,
                   COALESCE(SUM(ra.allocation_percent), 0) as total_allocation,
                   COUNT(ra.allocation_id) as allocation_count
            FROM resources r
            LEFT JOIN resource_allocations ra ON r.resource_id = ra.resource_id
            GROUP BY r.resource_id, r.name, r.resource_type, r.availability
            ORDER BY r.name
        ");
        $stmt->execute();
        $resources = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Calculate utilization status for each resource
        $utilization_report = array_map(function($resource) {
            $total_allocation = floatval($resource['total_allocation']);
            $resource['utilization_status'] = 'Under-utilized';

            if ($total_allocation > 90) {
                $resource['utilization_status'] = 'Over-utilized';
            } elseif ($total_allocation > 50) {
                $resource['utilization_status'] = 'Well-utilized';
            }

            $resource['total_allocation'] = $total_allocation;
            return $resource;
        }, $resources);

        echo json_encode([
            'utilization_report' => $utilization_report,
            'summary' => [
                'total_resources' => count($utilization_report),
                'over_utilized' => count(array_filter($utilization_report, function($r) { return $r['utilization_status'] === 'Over-utilized'; })),
                'well_utilized' => count(array_filter($utilization_report, function($r) { return $r['utilization_status'] === 'Well-utilized'; })),
                'under_utilized' => count(array_filter($utilization_report, function($r) { return $r['utilization_status'] === 'Under-utilized'; }))
            ]
        ]);
        exit;
    }

    if (!$resource_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Resource ID is required']);
        exit;
    }

    // Fetch resource details
    $stmt = $conn->prepare("SELECT * FROM resources WHERE resource_id = ?");
    $stmt->bind_param("i", $resource_id);
    $stmt->execute();
    $resource = $stmt->get_result()->fetch_assoc();

    if (!$resource) {
        http_response_code(404);
        echo json_encode(['error' => 'Resource not found']);
        exit;
    }

    // Fetch current allocations for this resource
    $stmt = $conn->prepare("
        SELECT ra.*, t.name as task_name, t.start_date, t.end_date, p.name as project_name
        FROM resource_allocations ra
        JOIN tasks t ON ra.task_id = t.task_id
        JOIN projects p ON t.project_id = p.project_id
        WHERE ra.resource_id = ?
        ORDER BY t.start_date
    ");
    $stmt->bind_param("i", $resource_id);
    $stmt->execute();
    $allocations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'resource' => $resource,
        'allocations' => $allocations
    ]);
    exit;
}

// Handle POST request for updating resource allocation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resource_id = $_POST['resource_id'] ?? null;
    $task_id = $_POST['task_id'] ?? null;
    $allocation_percent = $_POST['allocation_percent'] ?? 0;

    if (!$resource_id || !$task_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Resource ID and Task ID are required']);
        exit;
    }

    // Check if allocation already exists
    $stmt = $conn->prepare("SELECT allocation_id FROM resource_allocations WHERE resource_id = ? AND task_id = ?");
    $stmt->bind_param("ii", $resource_id, $task_id);
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
        $stmt = $conn->prepare("UPDATE resource_allocations SET allocation_percent = ? WHERE resource_id = ? AND task_id = ?");
        $stmt->bind_param("dii", $allocation_percent, $resource_id, $task_id);
        $stmt->execute();
    } else {
        // Insert new allocation
        $stmt = $conn->prepare("INSERT INTO resource_allocations (resource_id, task_id, allocation_percent) VALUES (?, ?, ?)");
        $stmt->bind_param("iid", $resource_id, $task_id, $allocation_percent);
        $stmt->execute();
    }

    echo json_encode(['success' => true, 'message' => 'Resource allocation updated successfully']);
    exit;
}

// Handle PUT request for updating resource details
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $resource_id = $_POST['resource_id'] ?? null;

    if (!$resource_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Resource ID is required']);
        exit;
    }

    // Get updated fields
    $name = $_POST['name'] ?? null;
    $resource_type = $_POST['resource_type'] ?? null;
    $availability = $_POST['availability'] ?? null;
    $cost_per_unit = $_POST['cost_per_unit'] ?? null;

    // Build dynamic update query
    $update_fields = [];
    $params = [];
    $types = "";

    if ($name !== null) {
        $update_fields[] = "name = ?";
        $params[] = $name;
        $types .= "s";
    }
    if ($resource_type !== null) {
        $update_fields[] = "resource_type = ?";
        $params[] = $resource_type;
        $types .= "s";
    }
    if ($availability !== null) {
        $update_fields[] = "availability = ?";
        $params[] = $availability;
        $types .= "d";
    }
    if ($cost_per_unit !== null) {
        $update_fields[] = "cost_per_unit = ?";
        $params[] = $cost_per_unit;
        $types .= "d";
    }

    if (empty($update_fields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        exit;
    }

    $params[] = $resource_id;
    $types .= "i";

    $sql = "UPDATE resources SET " . implode(", ", $update_fields) . " WHERE resource_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Resource updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update resource']);
    }
    exit;
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $resource_id = $data['resource_id'] ?? $_GET['resource_id'] ?? null;
    $task_id = $_GET['task_id'] ?? null;

    // If task_id is provided, delete allocation
    if ($task_id) {
        if (!$resource_id || !$task_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Resource ID and Task ID are required']);
            exit;
        }

        try {
            $stmt = $conn->prepare("DELETE FROM resource_allocations WHERE resource_id = ? AND task_id = ?");
            $stmt->bind_param("ii", $resource_id, $task_id);

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
    
    // Otherwise, delete the resource itself
    if (!$resource_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Resource ID is required']);
        exit;
    }

    try {
        // Check if resource has allocations
        $stmt = $conn->prepare("SELECT COUNT(*) as allocation_count FROM resource_allocations WHERE resource_id = ?");
        $stmt->bind_param("i", $resource_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['allocation_count'] > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete resource with existing allocations. Please remove allocations first.']);
            exit;
        }
        
        $stmt = $conn->prepare("DELETE FROM resources WHERE resource_id = ?");
        $stmt->bind_param("i", $resource_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Resource deleted successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete resource']);
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