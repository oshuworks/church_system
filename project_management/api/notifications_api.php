<?php
// notifications_api.php - API endpoint for resource utilization notifications
include '../includes/db.php';
header('Content-Type: application/json');

// Handle GET request to fetch notifications
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_id = $_GET['user_id'] ?? null;
    $type = $_GET['type'] ?? null; // over_utilization, under_utilization, system
    $unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] ? true : false;
    $deadline_only = isset($_GET['deadline_only']) && $_GET['deadline_only'] ? true : false; // Filter for deadline notifications only

    try {
        $where_conditions = [];
        $params = [];
        $types = "";

        if ($type) {
            // use actual column name in WHERE
            $where_conditions[] = "a.alert_type = ?";
            $params[] = $type;
            $types .= "s";
        }

        // Filter for deadline notifications only if requested
        if ($deadline_only) {
            $where_conditions[] = "a.alert_type IN ('project_due_date', 'project_near_deadline', 'task_due_date', 'task_near_deadline', 'subtask_due_date', 'subtask_near_deadline')";
        }

        if ($unread_only) {
            // map legacy "unread" to status = 'active'
            $where_conditions[] = "a.status = 'active'";
        }

        $where_clause = "";
        if (!empty($where_conditions)) {
            $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        }

        $sql = "
            SELECT a.alert_id AS notification_id, a.alert_type AS notification_type, a.resource_type, COALESCE(e.employee_name, eq.equipment_name, NULL) AS resource_name, a.resource_id, p.project_name AS project_name, a.severity, a.message, a.status, (a.status <> 'active') AS is_read, a.created_at,
                   CASE 
                       WHEN a.alert_type LIKE '%due_date' THEN 'danger'
                       WHEN a.alert_type LIKE '%near_deadline' THEN 'warning'
                       WHEN a.alert_type = 'overallocation' THEN 'danger'
                       WHEN a.alert_type = 'underutilization' THEN 'info'
                       WHEN a.alert_type = 'capacity_warning' THEN 'warning'
                       ELSE 'secondary'
                   END as badge_class
            FROM resource_alerts a
            LEFT JOIN employees e ON a.resource_type = 'employee' AND a.resource_id = e.employee_id
            LEFT JOIN equipment eq ON a.resource_type = 'equipment' AND a.resource_id = eq.equipment_id
            LEFT JOIN projects p ON a.project_id = p.project_id
            {$where_clause}
            ORDER BY a.created_at DESC
            LIMIT 50
        ";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

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
    $resource_id = isset($_POST['resource_id']) && $_POST['resource_id'] !== '' ? intval($_POST['resource_id']) : null;
    $notification_type = $_POST['notification_type'] ?? null;
    $message = $_POST['message'] ?? '';
    $project_id = isset($_POST['project_id']) && $_POST['project_id'] !== '' ? intval($_POST['project_id']) : null;
    $task_id = isset($_POST['task_id']) && $_POST['task_id'] !== '' ? intval($_POST['task_id']) : null;

    if (!$notification_type || !$message) {
        http_response_code(400);
        echo json_encode(['error' => 'Notification type and message are required']);
        exit;
    }

    // Validate alert type (map to resource_alerts)
    $valid_types = [
        'overallocation', 'underutilization', 'conflict', 'capacity_warning', 'project_due_date', 'project_near_deadline', 'task_due_date', 'task_near_deadline', 'subtask_due_date', 'subtask_near_deadline'
    ];
    if (!in_array($notification_type, $valid_types)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid alert type']);
        exit;
    }

    try {
        // Check if similar active alert already exists (avoid duplicates)
        $stmt = $conn->prepare(
            "SELECT alert_id FROM resource_alerts WHERE resource_type = ? AND resource_id <=> ? AND alert_type = ? AND message = ? AND status = 'active'"
        );
        if ($stmt === false) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $res_type_for_check = $_POST['resource_type'] ?? ($resource_id ? 'employee' : null);
        $stmt->bind_param("siss", $res_type_for_check, $resource_id, $notification_type, $message);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Similar alert already exists']);
            exit;
        }

        // Default severity if not supplied
        $severity = $_POST['severity'] ?? 'medium';
        $resource_type = $_POST['resource_type'] ?? ($resource_id ? 'employee' : null);

        // Insert new alert
        $stmt = $conn->prepare(
            "INSERT INTO resource_alerts (alert_type, resource_type, resource_id, project_id, task_id, severity, message, threshold_value, current_value, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')"
        );

        if ($stmt === false) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        $threshold_value = isset($_POST['threshold_value']) && $_POST['threshold_value'] !== '' ? floatval($_POST['threshold_value']) : null;
        $current_value = isset($_POST['current_value']) && $_POST['current_value'] !== '' ? floatval($_POST['current_value']) : null;

        $stmt->bind_param("ssiiissdd", $notification_type, $resource_type, $resource_id, $project_id, $task_id, $severity, $message, $threshold_value, $current_value);

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Alert created successfully',
                'notification_id' => $conn->insert_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create alert']);
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle PUT request to mark notifications as read (parse input for PUT)
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    parse_str(file_get_contents('php://input'), $put_vars);
    $notification_id = $put_vars['notification_id'] ?? null;
    $mark_all_read = isset($put_vars['mark_all_read']) && $put_vars['mark_all_read'] ? true : false;

    if (!$notification_id && !$mark_all_read) {
        http_response_code(400);
        echo json_encode(['error' => 'Notification ID or mark_all_read is required']);
        exit;
    }

    try {
        if ($mark_all_read) {
            // Mark all active alerts as acknowledged (closest to 'read')
            $stmt = $conn->prepare("UPDATE resource_alerts SET status = 'acknowledged' WHERE status = 'active'");
            $message = 'All alerts acknowledged';
        } else {
            // Mark single alert as acknowledged
            $stmt = $conn->prepare("UPDATE resource_alerts SET status = 'acknowledged' WHERE alert_id = ?");
            if ($stmt === false) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            $stmt->bind_param("i", $notification_id);
            $message = 'Alert acknowledged';
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update alert']);
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle DELETE request to remove notifications (parse input for DELETE)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str(file_get_contents('php://input'), $del_vars);
    $notification_id = $del_vars['notification_id'] ?? null;
    $clear_all = isset($del_vars['clear_all']) && $del_vars['clear_all'] ? true : false;

    if (!$notification_id && !$clear_all) {
        http_response_code(400);
        echo json_encode(['error' => 'Notification ID or clear_all is required']);
        exit;
    }

    try {
        if ($clear_all) {
            // Remove resolved alerts or clear all acknowledged alerts
            $stmt = $conn->prepare("DELETE FROM resource_alerts WHERE status = 'resolved'");
            $message = 'All resolved alerts cleared';
        } else {
            $stmt = $conn->prepare("DELETE FROM resource_alerts WHERE alert_id = ?");
            if ($stmt === false) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            $stmt->bind_param("i", $notification_id);
            $message = 'Alert deleted';
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete alert']);
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
