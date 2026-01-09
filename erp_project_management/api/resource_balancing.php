<?php
// resource_balancing.php - compute resource utilization and create notifications
include '../includes/db.php';
header('Content-Type: application/json');

// Basic thresholds
$UNDERUTILIZATION_THRESHOLD_PERCENT = 25.0; // If utilization < 25% -> under-utilized

// Only support check_notifications=true for now
if (isset($_GET['check_notifications']) && $_GET['check_notifications'] === 'true') {
    try {
        $created = 0;
        $today = date('Y-m-d');

        // Get all resources
        $res = $conn->query("SELECT resource_id, name, availability FROM resources");
        if (!$res) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch resources: ' . $conn->error]);
            exit;
        }

        while ($row = $res->fetch_assoc()) {
            $resource_id = (int)$row['resource_id'];
            $name = $row['name'];
            $availability = (float)$row['availability'];

            // Sum allocation_percent for ACTIVE tasks (where start_date <= today <= end_date) or tasks with NULL dates
            $stmt = $conn->prepare("SELECT COALESCE(SUM(allocation_percent),0) as total_alloc
                FROM resource_allocations ra
                LEFT JOIN tasks t ON ra.task_id = t.task_id
                WHERE ra.resource_id = ? AND (
                    (t.start_date IS NULL AND t.end_date IS NULL) OR
                    (t.start_date IS NULL AND t.end_date >= ? ) OR
                    (t.end_date IS NULL AND t.start_date <= ? ) OR
                    (t.start_date <= ? AND t.end_date >= ?)
                )");
            if ($stmt === false) {
                http_response_code(500);
                echo json_encode(['error' => 'DB prepare failed: ' . $conn->error]);
                exit;
            }
            $stmt->bind_param('issss', $resource_id, $today, $today, $today, $today);
            if (!$stmt->execute()) {
                http_response_code(500);
                echo json_encode(['error' => 'DB execute failed: ' . $stmt->error]);
                exit;
            }
            $allocRes = $stmt->get_result()->fetch_assoc();
            $util = (float)$allocRes['total_alloc'];

            // Determine over or under utilization
            if ($util > $availability) {
                // Over-utilization
                $message = sprintf('%s is over-allocated: %.2f%% allocated (availability %.2f%%)', $name, $util, $availability);
                $notification_type = 'over_utilization';
            } elseif ($util < $UNDERUTILIZATION_THRESHOLD_PERCENT) {
                // Under-utilization
                $message = sprintf('%s is under-utilized: %.2f%% allocated (availability %.2f%%)', $name, $util, $availability);
                $notification_type = 'under_utilization';
            } else {
                // Nothing to do
                continue;
            }

            // Avoid duplicate active notifications (same resource, same message, unread)
            $chk = $conn->prepare("SELECT notification_id FROM resource_notifications WHERE resource_id = ? AND notification_type = ? AND message = ? AND is_read = FALSE");
            if ($chk === false) {
                http_response_code(500);
                echo json_encode(['error' => 'DB prepare failed: ' . $conn->error]);
                exit;
            }
            $chk->bind_param('iss', $resource_id, $notification_type, $message);
            if (!$chk->execute()) {
                http_response_code(500);
                echo json_encode(['error' => 'DB execute failed: ' . $chk->error]);
                exit;
            }
            $exists = $chk->get_result()->fetch_assoc();
            if ($exists) {
                continue; // skip duplicates
            }

            // Insert notification
            $ins = $conn->prepare("INSERT INTO resource_notifications (resource_id, notification_type, message, is_read) VALUES (?, ?, ?, FALSE)");
            if ($ins === false) {
                http_response_code(500);
                echo json_encode(['error' => 'DB prepare failed: ' . $conn->error]);
                exit;
            }
            $ins->bind_param('iss', $resource_id, $notification_type, $message);
            if (!$ins->execute()) {
                // Log and continue on failure
                error_log('Failed to insert notification: ' . $ins->error);
                continue;
            }
            $created++;
        }

        echo json_encode(['success' => true, 'notifications_created' => $created]);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Exception: ' . $e->getMessage()]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
?>