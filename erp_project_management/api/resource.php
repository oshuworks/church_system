<?php
// resource.php - API endpoint for resource management
include '../includes/db.php';
header('Content-Type: application/json');

// Handle GET request to fetch resource details
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $resource_id = $_GET['resource_id'] ?? null;

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

    // Validate allocation percentage
    if ($allocation_percent < 0 || $allocation_percent > 100) {
        http_response_code(400);
        echo json_encode(['error' => 'Allocation percentage must be between 0 and 100']);
        exit;
    }

    // Check if allocation already exists
    $stmt = $conn->prepare("SELECT allocation_id FROM resource_allocations WHERE resource_id = ? AND task_id = ?");
    $stmt->bind_param("ii", $resource_id, $task_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

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

// Handle DELETE request for removing resource allocation
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $resource_id = $_POST['resource_id'] ?? null;
    $task_id = $_POST['task_id'] ?? null;

    if (!$resource_id || !$task_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Resource ID and Task ID are required']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM resource_allocations WHERE resource_id = ? AND task_id = ?");
    $stmt->bind_param("ii", $resource_id, $task_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Resource allocation removed successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to remove resource allocation']);
    }
    exit;
}

// Method not allowed
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>