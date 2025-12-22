<?php
// resource_balancing.php - API endpoint for automated resource balancing
include '../includes/db.php';
header('Content-Type: application/json');

// Handle GET request for resource balancing recommendations
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $resource_id = $_GET['resource_id'] ?? null;
        $project_id = $_GET['project_id'] ?? null;

        if ($resource_id) {
            // Get balancing recommendations for a specific resource
            $recommendations = getResourceBalancingRecommendations($resource_id);
            echo json_encode(['balancing_recommendations' => $recommendations]);
            exit;
        }

        if ($project_id) {
            // Get balancing recommendations for all resources in a project
            // TODO: Implement project-specific balancing recommendations
            $recommendations = getSystemResourceBalancingRecommendations();
            echo json_encode(['project_balancing_recommendations' => $recommendations]);
            exit;
        }

        // Get overall system balancing recommendations
        $recommendations = getSystemResourceBalancingRecommendations();
        echo json_encode(['system_balancing_recommendations' => $recommendations]);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get balancing recommendations: ' . $e->getMessage()]);
        exit;
    }
}

    // Check and create utilization notifications
    if (isset($_GET['check_notifications'])) {
        $notifications_created = checkAndCreateUtilizationNotifications();
        echo json_encode([
            'success' => true,
            'message' => "{$notifications_created} utilization notifications created",
            'notifications_created' => $notifications_created
        ]);
        exit;
    }

// Handle POST request for automated resource balancing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resource_id = $_POST['resource_id'] ?? null;
    $auto_balance = $_POST['auto_balance'] ?? false;

    if (!$resource_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Resource ID is required']);
        exit;
    }

    if ($auto_balance) {
        // Automatically balance resource allocation
        $result = autoBalanceResourceAllocation($resource_id);
        echo json_encode($result);
        exit;
    }

    // Manual balancing based on provided parameters
    $task_id = $_POST['task_id'] ?? null;
    $new_allocation = $_POST['new_allocation'] ?? null;

    if (!$task_id || $new_allocation === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID and new allocation percentage are required for manual balancing']);
        exit;
    }

    $result = manualBalanceResourceAllocation($resource_id, $task_id, $new_allocation);
    echo json_encode($result);
    exit;
}

// Function to get resource balancing recommendations
function getResourceBalancingRecommendations($resource_id) {
    global $conn;

    try {
        // Get current resource allocations
        $stmt = $conn->prepare("
            SELECT ra.*, t.name as task_name, t.start_date, t.end_date,
                   p.name as project_name, p.project_id
            FROM resource_allocations ra
            JOIN tasks t ON ra.task_id = t.task_id
            JOIN projects p ON t.project_id = p.project_id
            WHERE ra.resource_id = ?
            ORDER BY t.start_date
        ");
        $stmt->bind_param("i", $resource_id);
        $stmt->execute();
        $allocations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Calculate total allocation
        $total_allocation = array_sum(array_column($allocations, 'allocation_percent'));

        // Get resource details
        $stmt = $conn->prepare("SELECT * FROM resources WHERE resource_id = ?");
        $stmt->bind_param("i", $resource_id);
        $stmt->execute();
        $resource = $stmt->get_result()->fetch_assoc();

        $recommendations = [
            'resource' => $resource,
            'current_allocations' => $allocations,
            'total_allocation' => $total_allocation,
            'balancing_needed' => $total_allocation > 100 || $total_allocation < 80,
            'recommendations' => []
        ];

        // Generate recommendations
        if ($total_allocation > 100) {
            // Over-allocated - suggest reductions
            $recommendations['recommendations'][] = [
                'type' => 'reduce_allocation',
                'message' => 'Resource is over-allocated by ' . ($total_allocation - 100) . '%. Consider reducing allocation to high-priority tasks.',
                'priority' => 'high'
            ];

            // Sort allocations by priority and suggest reductions
            $sorted_allocations = sortAllocationsByPriority($allocations);
            $recommendations['suggested_reductions'] = suggestAllocationReductions($sorted_allocations, $total_allocation - 100);

        } elseif ($total_allocation < 80) {
            // Under-allocated - suggest increases
            $recommendations['recommendations'][] = [
                'type' => 'increase_allocation',
                'message' => 'Resource is under-allocated by ' . (80 - $total_allocation) . '%. Consider allocating to additional tasks.',
                'priority' => 'medium'
            ];

            // Find available tasks for allocation
            $stmt = $conn->prepare("
                SELECT t.*, p.name as project_name
                FROM tasks t
                JOIN projects p ON t.project_id = p.project_id
                WHERE t.task_id NOT IN (SELECT task_id FROM resource_allocations WHERE resource_id = ?)
                AND t.progress_percent < 100
                ORDER BY t.start_date
            ");
            $stmt->bind_param("i", $resource_id);
            $stmt->execute();
            $available_tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            $recommendations['suggested_tasks'] = array_slice($available_tasks, 0, 3); // Top 3 suggestions

        } else {
            $recommendations['recommendations'][] = [
                'type' => 'optimal_allocation',
                'message' => 'Resource allocation is well-balanced.',
                'priority' => 'low'
            ];
        }

        return $recommendations;

    } catch (Exception $e) {
        throw new Exception('Failed to get resource balancing recommendations: ' . $e->getMessage());
    }
}

// Function to sort allocations by priority
function sortAllocationsByPriority($allocations) {
    // Sort by allocation percentage (descending) and start date (ascending)
    usort($allocations, function($a, $b) {
        if ($a['allocation_percent'] == $b['allocation_percent']) {
            return strtotime($a['start_date']) - strtotime($b['start_date']);
        }
        return $b['allocation_percent'] - $a['allocation_percent'];
    });

    return $allocations;
}

// Function to suggest allocation reductions
function suggestAllocationReductions($allocations, $reduction_needed) {
    $suggestions = [];
    $remaining_reduction = $reduction_needed;

    foreach ($allocations as $allocation) {
        if ($remaining_reduction <= 0) break;

        $max_reduction = min($allocation['allocation_percent'] * 0.5, $remaining_reduction); // Max 50% reduction per task
        if ($max_reduction > 0) {
            $suggestions[] = [
                'task_id' => $allocation['task_id'],
                'task_name' => $allocation['task_name'],
                'project_name' => $allocation['project_name'],
                'current_allocation' => $allocation['allocation_percent'],
                'suggested_reduction' => round($max_reduction, 2),
                'new_allocation' => round($allocation['allocation_percent'] - $max_reduction, 2)
            ];
            $remaining_reduction -= $max_reduction;
        }
    }

    return $suggestions;
}

// Function to get system-wide balancing recommendations
function getSystemResourceBalancingRecommendations() {
    global $conn;

    try {
        // Get all resources with their allocations
        $stmt = $conn->prepare("
            SELECT r.resource_id, r.name, r.resource_type,
                   COALESCE(SUM(ra.allocation_percent), 0) as total_allocation,
                   COUNT(ra.allocation_id) as allocation_count
            FROM resources r
            LEFT JOIN resource_allocations ra ON r.resource_id = ra.resource_id
            GROUP BY r.resource_id, r.name, r.resource_type
            ORDER BY r.name
        ");
        $stmt->execute();
        $resources = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $recommendations = [
            'over_allocated' => [],
            'under_allocated' => [],
            'well_allocated' => [],
            'summary' => [
                'total_resources' => count($resources),
                'over_allocated_count' => 0,
                'under_allocated_count' => 0,
                'well_allocated_count' => 0
            ]
        ];

        foreach ($resources as $resource) {
            $total_allocation = floatval($resource['total_allocation']);

            if ($total_allocation > 100) {
                $recommendations['over_allocated'][] = $resource;
                $recommendations['summary']['over_allocated_count']++;
            } elseif ($total_allocation < 80) {
                $recommendations['under_allocated'][] = $resource;
                $recommendations['summary']['under_allocated_count']++;
            } else {
                $recommendations['well_allocated'][] = $resource;
                $recommendations['summary']['well_allocated_count']++;
            }
        }

        return $recommendations;

    } catch (Exception $e) {
        throw new Exception('Failed to get system balancing recommendations: ' . $e->getMessage());
    }
}

// Function to auto-balance resource allocation
function autoBalanceResourceAllocation($resource_id) {
    global $conn;

    try {
        // Get current allocations
        $recommendations = getResourceBalancingRecommendations($resource_id);

        if (!$recommendations['balancing_needed']) {
            return ['success' => false, 'message' => 'No balancing needed for this resource'];
        }

        // Start transaction
        $conn->begin_transaction();

        try {
            if ($recommendations['total_allocation'] > 100) {
                // Reduce allocations based on suggestions
                foreach ($recommendations['suggested_reductions'] as $reduction) {
                    $new_allocation = $reduction['new_allocation'];
                    $task_id = $reduction['task_id'];

                    $stmt = $conn->prepare("UPDATE resource_allocations SET allocation_percent = ? WHERE resource_id = ? AND task_id = ?");
                    $stmt->bind_param("dii", $new_allocation, $resource_id, $task_id);
                    $stmt->execute();
                }
            }

            $conn->commit();

            return [
                'success' => true,
                'message' => 'Resource allocation automatically balanced',
                'new_total_allocation' => calculateTotalAllocation($resource_id)
            ];

        } catch (Exception $e) {
            $conn->rollback();
            throw new Exception('Failed to balance resource allocation: ' . $e->getMessage());
        }

    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Failed to auto-balance resource allocation: ' . $e->getMessage()];
    }
}

// Function to calculate total allocation for a resource
function calculateTotalAllocation($resource_id) {
    global $conn;

    try {
        $stmt = $conn->prepare("SELECT SUM(allocation_percent) as total FROM resource_allocations WHERE resource_id = ?");
        $stmt->bind_param("i", $resource_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        return floatval($result['total'] || 0);

    } catch (Exception $e) {
        return 0;
    }
}

// Function for manual balancing
function manualBalanceResourceAllocation($resource_id, $task_id, $new_allocation) {
    global $conn;

    try {
        // Check current total allocation
        $current_total = calculateTotalAllocation($resource_id);

        // Get current allocation for this task
        $stmt = $conn->prepare("SELECT allocation_percent FROM resource_allocations WHERE resource_id = ? AND task_id = ?");
        $stmt->bind_param("ii", $resource_id, $task_id);
        $stmt->execute();
        $current_allocation = $stmt->get_result()->fetch_assoc();

        if (!$current_allocation) {
            return ['success' => false, 'error' => 'No existing allocation found for this resource and task'];
        }

        $difference = $new_allocation - $current_allocation['allocation_percent'];
        $new_total = $current_total + $difference;

        if ($new_total > 100) {
            return ['success' => false, 'error' => 'New allocation would exceed 100%. Current total: ' . $current_total . '%, requested: ' . $new_allocation . '%'];
        }

        if ($new_total < 0) {
            return ['success' => false, 'error' => 'New allocation would result in negative total allocation'];
        }

        // Update allocation
        $stmt = $conn->prepare("UPDATE resource_allocations SET allocation_percent = ? WHERE resource_id = ? AND task_id = ?");
        $stmt->bind_param("dii", $new_allocation, $resource_id, $task_id);

        if ($stmt->execute()) {
            return [
                'success' => true,
                'message' => 'Resource allocation updated successfully',
                'new_total_allocation' => $new_total
            ];
        } else {
            return ['success' => false, 'error' => 'Failed to update resource allocation'];
        }

    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Failed to update resource allocation: ' . $e->getMessage()];
    }
}

// Function to check and create utilization notifications
function checkAndCreateUtilizationNotifications() {
    global $conn;

    try {
        // Get all resources with their current utilization
        $stmt = $conn->prepare("
            SELECT r.resource_id, r.name, r.resource_type,
                   COALESCE(SUM(ra.allocation_percent), 0) as total_allocation
            FROM resources r
            LEFT JOIN resource_allocations ra ON r.resource_id = ra.resource_id
            GROUP BY r.resource_id, r.name, r.resource_type
        ");
        $stmt->execute();
        $resources = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $notifications_created = 0;

        foreach ($resources as $resource) {
            $total_allocation = floatval($resource['total_allocation']);
            $resource_id = $resource['resource_id'];
            $resource_name = $resource['name'];
            $resource_type = $resource['resource_type'];

            // Check for over-utilization (>90%)
            if ($total_allocation > 90) {
                $message = "{$resource_name} ({$resource_type}) is over-utilized at " . round($total_allocation, 1) . "%. Consider redistributing workload.";
                createNotificationIfNotExists($resource_id, null, 'over_utilization', $message);
                $notifications_created++;
            }

            // Check for under-utilization (<50%)
            elseif ($total_allocation < 50 && $total_allocation > 0) {
                $message = "{$resource_name} ({$resource_type}) is under-utilized at " . round($total_allocation, 1) . "%. Consider assigning to additional tasks.";
                createNotificationIfNotExists($resource_id, null, 'under_utilization', $message);
                $notifications_created++;
            }

            // Check for zero utilization (completely unused)
            elseif ($total_allocation == 0) {
                $message = "{$resource_name} ({$resource_type}) is not allocated to any tasks. Consider utilizing this resource.";
                createNotificationIfNotExists($resource_id, null, 'under_utilization', $message);
                $notifications_created++;
            }
        }

        return $notifications_created;

    } catch (Exception $e) {
        return 0;
    }
}

// Function to create notification if it doesn't already exist
function createNotificationIfNotExists($resource_id, $project_id, $type, $message) {
    global $conn;

    try {
        // Check if similar notification already exists and is unread
        $stmt = $conn->prepare("
            SELECT notification_id FROM resource_notifications
            WHERE resource_id = ? AND notification_type = ? AND message = ? AND is_read = FALSE
        ");
        $stmt->bind_param("iss", $resource_id, $type, $message);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if (!$existing) {
            // Create new notification
            $stmt = $conn->prepare("
                INSERT INTO resource_notifications (resource_id, project_id, notification_type, message, is_read)
                VALUES (?, ?, ?, ?, FALSE)
            ");

            if ($resource_id) {
                $stmt->bind_param("iiss", $resource_id, $project_id, $type, $message);
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO resource_notifications (project_id, notification_type, message, is_read)
                    VALUES (?, ?, ?, FALSE)
                ");
                $stmt->bind_param("iss", $project_id, $type, $message);
            }

            $stmt->execute();
        }

    } catch (Exception $e) {
        // Silently fail for notification creation
    }
}

// Method not allowed
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>
