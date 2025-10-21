<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Database configuration
$config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'erp_project_management'
];

// Create connection
$conn = new mysqli($config['host'], $config['username'], $config['password'], $config['database']);

// Check connection
if ($conn->connect_error) {
    sendErrorResponse(500, "Database connection failed: " . $conn->connect_error);
    exit();
}

try {
    // Get all resources with their current allocation percentages
    $query = "SELECT 
                r.*,
                COALESCE(SUM(ra.allocation_percent), 0) as allocated_percent,
                (100 - COALESCE(SUM(ra.allocation_percent), 0)) as available_percent
              FROM resources r
              LEFT JOIN resource_allocations ra ON r.resource_id = ra.resource_id
              GROUP BY r.resource_id";
    
    $result = $conn->query($query);
    
    if ($result === false) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    $resources = [];
    while ($row = $result->fetch_assoc()) {
        // Format the resource data
        $resources[] = [
            'resource_id' => (int)$row['resource_id'],
            'name' => $row['name'],
            'resource_type' => $row['resource_type'],
            'availability' => (float)$row['available_percent'],
            'cost_per_unit' => (float)$row['cost_per_unit'],
            'allocated_percent' => (float)$row['allocated_percent']
        ];
    }
    
    // Send success response
    sendSuccessResponse($resources);
    
} catch (Exception $e) {
    sendErrorResponse(500, $e->getMessage());
}

// Close connection
$conn->close();

/**
 * Send a success response with data
 */
function sendSuccessResponse($data) {
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}

/**
 * Send an error response
 */
function sendErrorResponse($statusCode, $message) {
    http_response_code($statusCode);
    echo json_encode([
        'status' => 'error',
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>