<?php
include '../db.php';
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

$task_id = $data['task_id'];
$resource_id = $data['resource_id'];
$allocation_percent = $data['allocation_percent'];

// Check current allocation for this resource
$sql = "SELECT SUM(allocation_percent) as total FROM resource_allocations WHERE resource_id = $resource_id";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$current_total = $row['total'] ?? 0;

// Prevent over-allocation
if ($current_total + $allocation_percent > 100) {
    echo json_encode(['status' => 'error', 'message' => 'Resource over-allocated']);
    exit;
}

// Insert allocation
$stmt = $conn->prepare("INSERT INTO resource_allocations (task_id, resource_id, allocation_percent) VALUES (?, ?, ?)");
$stmt->bind_param("iid", $task_id, $resource_id, $allocation_percent);
$stmt->execute();

// Update resource availability
$new_availability = 100 - ($current_total + $allocation_percent);
$conn->query("UPDATE resources SET availability = $new_availability WHERE resource_id = $resource_id");

echo json_encode(['status' => 'success']);
?>
