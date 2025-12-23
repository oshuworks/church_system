<?php
// notifications_api.php - API endpoint for resource utilization notifications
include '../includes/db.php';
header('Content-Type: application/json');

// Handle GET request to fetch notifications
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_id = $_GET['user_id'] ?? null;
    $type = $_GET['type'] ?? null; // over_utilization, under_utilization, system
    $unread_only = $_GET['unread_only'] ?? false;
    $deadline_only = $_GET['deadline_only'] ?? false; // Filter for deadline notifications only

    try {
        $where_conditions = [];
        $params = [];
        $types = "";

        if ($type) {
            $where_conditions[] = "notification_type = ?";
            $params[] = $type;
            $types .= "s";
        }

        // Filter for deadline notifications only if requested
        if ($deadline_only) {
            $where_conditions[] = "notification_type IN ('project_due_date', 'project_near_deadline', 'task_due_date', 'task_near_deadline', 'subtask_due_date', 'subtask_near_deadline')";
        }

        if ($unread_only) {
            $where_conditions[] = "is_read = FALSE";
        }

        $where_clause = "";
        if (!empty($where_conditions)) {
            $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        }

        $sql = "
            SELECT n.*, r.name as resource_name, r.resource_type,
                   p.name as project_name,
                   CASE 
                       WHEN n.notification_type LIKE '%due_date' THEN 'danger'
                       WHEN n.notification_type LIKE '%near_deadline' THEN 'warning'
                       WHEN n.notification_type = 'over_utilization' THEN 'danger'
                       WHEN n.notification_type = 'under_utilization' THEN 'info'
                       WHEN n.notification_type = 'balancing_recommendation' THEN 'primary'
                       ELSE 'secondary'
                   END as badge_class
            FROM resource_notifications n
            LEFT JOIN resources r ON n.resource_id = r.resource_id
            LEFT JOIN projects p ON n.project_id = p.project_id
            {$where_clause}
            ORDER BY n.created_at DESC
            LIMIT 50
        ";

        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['notifications' => $notifications]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch notifications: ' . $e->getMessage()]);
    }
    exit;
}

// Handle POST request to create notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resource_id = $_POST['resource_id'] ?? null;
    $notification_type = $_POST['notification_type'] ?? null;
    $message = $_POST['message'] ?? '';
    $project_id = $_POST['project_id'] ?? null;

    if (!$notification_type || !$message) {
        http_response_code(400);
        echo json_encode(['error' => 'Notification type and message are required']);
        exit;
    }

    // Validate notification type
    $valid_types = [
        'over_utilization', 'under_utilization', 'balancing_recommendation', 'system_alert',
        'project_due_date', 'project_near_deadline',
        'task_due_date', 'task_near_deadline',
        'subtask_due_date', 'subtask_near_deadline'
    ];
    if (!in_array($notification_type, $valid_types)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid notification type']);
        exit;
    }

    try {
        // Check if similar notification already exists (avoid duplicates)
        $stmt = $conn->prepare("
            SELECT notification_id FROM resource_notifications
            WHERE resource_id = ? AND notification_type = ? AND message = ? AND is_read = FALSE
        ");
        $stmt->bind_param("iss", $resource_id, $notification_type, $message);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            echo json_encode(['success' => true, 'message' => 'Similar notification already exists', 'notification_id' => $existing['notification_id']]);
            exit;
        }

        // Insert new notification
        $stmt = $conn->prepare("
            INSERT INTO resource_notifications (resource_id, project_id, notification_type, message, is_read)
            VALUES (?, ?, ?, ?, FALSE)
        ");

        if ($resource_id) {
            $stmt->bind_param("iiss", $resource_id, $project_id, $notification_type, $message);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO resource_notifications (project_id, notification_type, message, is_read)
                VALUES (?, ?, ?, FALSE)
            ");
            $stmt->bind_param("iss", $project_id, $notification_type, $message);
        }

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Notification created successfully',
                'notification_id' => $conn->insert_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create notification']);
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle PUT request to mark notifications as read
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $notification_id = $_POST['notification_id'] ?? null;
    $mark_all_read = $_POST['mark_all_read'] ?? false;

    if (!$notification_id && !$mark_all_read) {
        http_response_code(400);
        echo json_encode(['error' => 'Notification ID or mark_all_read is required']);
        exit;
    }

    try {
        if ($mark_all_read) {
            $stmt = $conn->prepare("UPDATE resource_notifications SET is_read = TRUE WHERE is_read = FALSE");
            $message = 'All notifications marked as read';
        } else {
            $stmt = $conn->prepare("UPDATE resource_notifications SET is_read = TRUE WHERE notification_id = ?");
            $stmt->bind_param("i", $notification_id);
            $message = 'Notification marked as read';
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update notification']);
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle DELETE request to remove notifications
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $notification_id = $_POST['notification_id'] ?? null;
    $clear_all = $_POST['clear_all'] ?? false;

    if (!$notification_id && !$clear_all) {
        http_response_code(400);
        echo json_encode(['error' => 'Notification ID or clear_all is required']);
        exit;
    }

    try {
        if ($clear_all) {
            $stmt = $conn->prepare("DELETE FROM resource_notifications WHERE is_read = TRUE");
            $message = 'All read notifications cleared';
        } else {
            $stmt = $conn->prepare("DELETE FROM resource_notifications WHERE notification_id = ?");
            $stmt->bind_param("i", $notification_id);
            $message = 'Notification deleted';
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete notification']);
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
