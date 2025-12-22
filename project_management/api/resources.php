<?php
// resources.php - API endpoint for fetching all resources
include '../includes/db.php';
header('Content-Type: application/json');

// Handle GET request to fetch all resources
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Fetch all resources with basic information
        $stmt = $conn->prepare("
            SELECT resource_id, name, resource_type, availability, cost_per_unit
            FROM resources
            ORDER BY name
        ");
        $stmt->execute();
        $resources = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['resources' => $resources]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch resources: ' . $e->getMessage()]);
    }
    exit;
}

// Handle POST request for creating new resource
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $resource_type = $_POST['resource_type'] ?? '';
    $availability = $_POST['availability'] ?? 100;
    $cost_per_unit = $_POST['cost_per_unit'] ?? 0;

    if (!$name || !$resource_type) {
        http_response_code(400);
        echo json_encode(['error' => 'Resource name and type are required']);
        exit;
    }

    // Validate resource type
    $valid_types = ['Human', 'Equipment', 'Financial'];
    if (!in_array($resource_type, $valid_types)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid resource type. Must be Human, Equipment, or Financial']);
        exit;
    }

    // Validate availability
    if ($availability < 0 || $availability > 100) {
        http_response_code(400);
        echo json_encode(['error' => 'Availability must be between 0 and 100']);
        exit;
    }

    try {
        $stmt = $conn->prepare("INSERT INTO resources (name, resource_type, availability, cost_per_unit) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssdd", $name, $resource_type, $availability, $cost_per_unit);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'resource_id' => $conn->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create resource']);
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
