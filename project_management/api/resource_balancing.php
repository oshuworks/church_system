<?php
// resource_balancing.php - checks resource utilization and creates alerts for over/under utilization
include '../includes/db.php';
header('Content-Type: application/json');

// Simple endpoint to check resource utilization and generate alerts
if (($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['check_notifications'])) || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_notifications']))) {
    try {
        // Find resource utilizations that exceed 100%
        $sql = "SELECT utilization_id, resource_type, resource_id, project_id, utilization_percentage FROM resource_utilization WHERE utilization_percentage > 100";
        $res = $conn->query($sql);
        if ($res === false) throw new Exception($conn->error);

        $created = 0;

        $insertStmt = $conn->prepare("INSERT INTO resource_alerts (alert_type, resource_type, resource_id, project_id, severity, message, threshold_value, current_value, status) VALUES ('overallocation', ?, ?, ?, 'high', ?, ?, ?, 'active')");
        if ($insertStmt === false) throw new Exception('Prepare failed: ' . $conn->error);

        $checkStmt = $conn->prepare("SELECT alert_id FROM resource_alerts WHERE resource_type = ? AND resource_id <=> ? AND alert_type = 'overallocation' AND status = 'active'");
        if ($checkStmt === false) throw new Exception('Prepare failed: ' . $conn->error);

        while ($row = $res->fetch_assoc()) {
            $resource_type = $row['resource_type'];
            $resource_id = $row['resource_id'];
            $project_id = $row['project_id'];
            $current = floatval($row['utilization_percentage']);

            // Check existing active overallocation alert
            $checkStmt->bind_param('si', $resource_type, $resource_id);
            $checkStmt->execute();
            $checkStmt->store_result();
            if ($checkStmt->num_rows > 0) continue;

            $message = "Overallocation detected: {$current}% utilization";
            $threshold = 100.0;

            // Bind parameters: resource_type (s), resource_id (i), project_id (i), message (s), threshold (d), current (d)
            $insertStmt->bind_param('siisdd', $resource_type, $resource_id, $project_id, $message, $threshold, $current);

            if ($insertStmt->execute()) {
                $created++;
            }
        }

        echo json_encode(['success' => true, 'notifications_created' => $created]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to check resource balancing: ' . $e->getMessage()]);
    }
    exit;
}

// Default response
http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
?>