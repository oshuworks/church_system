<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'db.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        handleGet($db, $action);
        break;
    case 'POST':
        handlePost($db, $action);
        break;
    default:
        echo json_encode(["message" => "Method not allowed"]);
        break;
}

function handleGet($db, $action) {
    switch ($action) {
        case 'analyze_workload':
            analyzeWorkload($db);
            break;
        case 'get_balance_recommendations':
            getBalanceRecommendations($db);
            break;
        case 'get_project_distribution':
            getProjectDistribution($db);
            break;
        case 'get_resource_conflicts':
            getResourceConflicts($db);
            break;
        case 'get_capacity_analysis':
            getCapacityAnalysis($db);
            break;
        default:
            echo json_encode(["message" => "Invalid action"]);
            break;
    }
}

function handlePost($db, $action) {
    switch ($action) {
        case 'rebalance_resources':
            rebalanceResources($db);
            break;
        case 'auto_assign':
            autoAssignResources($db);
            break;
        default:
            echo json_encode(["message" => "Invalid action"]);
            break;
    }
}

// Workload Analysis Functions
function analyzeWorkload($db) {
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-t');
    $resource_type = $_GET['resource_type'] ?? 'employee';
    
    if ($resource_type === 'employee') {
        $query = "SELECT e.employee_id, e.employee_name, e.role, e.max_work_hours_per_day,
                        COUNT(ta.assignment_id) as assignment_count,
                        SUM(ta.allocated_hours) as total_allocated_hours,
                        AVG(ru.utilization_percentage) as avg_utilization,
                        MAX(ru.utilization_percentage) as peak_utilization,
                        MIN(ru.utilization_percentage) as min_utilization
                 FROM employees e
                 LEFT JOIN task_assignments ta ON e.employee_id = ta.resource_id AND ta.assignment_type = 'employee' 
                     AND ta.status != 'cancelled' AND ta.start_date <= :end_date AND ta.end_date >= :start_date
                 LEFT JOIN resource_utilization ru ON e.employee_id = ru.resource_id AND ru.resource_type = 'employee' 
                     AND ru.date BETWEEN :start_date AND :end_date
                 WHERE e.status = 'active'
                 GROUP BY e.employee_id
                 ORDER BY avg_utilization DESC";
    } else {
        $query = "SELECT eq.equipment_id, eq.equipment_name, eq.equipment_type, eq.total_capacity_hours,
                        COUNT(ta.assignment_id) as assignment_count,
                        SUM(ta.allocated_hours) as total_allocated_hours,
                        AVG(ru.utilization_percentage) as avg_utilization,
                        MAX(ru.utilization_percentage) as peak_utilization,
                        MIN(ru.utilization_percentage) as min_utilization
                 FROM equipment eq
                 LEFT JOIN task_assignments ta ON eq.equipment_id = ta.resource_id AND ta.assignment_type = 'equipment' 
                     AND ta.status != 'cancelled' AND ta.start_date <= :end_date AND ta.end_date >= :start_date
                 LEFT JOIN resource_utilization ru ON eq.equipment_id = ru.resource_id AND ru.resource_type = 'equipment' 
                     AND ru.date BETWEEN :start_date AND :end_date
                 WHERE eq.status IN ('available', 'in_use')
                 GROUP BY eq.equipment_id
                 ORDER BY avg_utilization DESC";
    }
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $workload_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate workload balance metrics
    $total_resources = count($workload_data);
    $overloaded = 0;
    $underutilized = 0;
    $optimal = 0;
    
    foreach ($workload_data as $resource) {
        $utilization = $resource['avg_utilization'] ?? 0;
        if ($utilization > 85) $overloaded++;
        elseif ($utilization < 60) $underutilized++;
        else $optimal++;
    }
    
    $balance_score = ($optimal / $total_resources) * 100;
    
    echo json_encode([
        'workload_data' => $workload_data,
        'summary' => [
            'total_resources' => $total_resources,
            'overloaded' => $overloaded,
            'underutilized' => $underutilized,
            'optimal' => $optimal,
            'balance_score' => round($balance_score, 2)
        ]
    ]);
}

// Balance Recommendations Functions
function getBalanceRecommendations($db) {
    $recommendations = [];
    
    // Find overloaded employees
    $overloadedQuery = "SELECT e.employee_id, e.employee_name, e.role, AVG(ru.utilization_percentage) as avg_util
                       FROM employees e
                       JOIN resource_utilization ru ON e.employee_id = ru.resource_id AND ru.resource_type = 'employee'
                       WHERE ru.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                       GROUP BY e.employee_id
                       HAVING avg_util > 85";
    $stmt = $db->prepare($overloadedQuery);
    $stmt->execute();
    $overloaded = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($overloaded as $employee) {
        $recommendations[] = [
            'type' => 'overload',
            'severity' => 'high',
            'resource_id' => $employee['employee_id'],
            'resource_name' => $employee['employee_name'],
            'resource_type' => 'employee',
            'message' => "Employee {$employee['employee_name']} is overloaded at " . round($employee['avg_util'], 1) . "% utilization",
            'suggestion' => 'Consider redistributing some tasks or extending deadlines',
            'current_utilization' => $employee['avg_util']
        ];
    }
    
    // Find underutilized employees
    $underutilizedQuery = "SELECT e.employee_id, e.employee_name, e.role, AVG(ru.utilization_percentage) as avg_util,
                          e.skills
                          FROM employees e
                          LEFT JOIN resource_utilization ru ON e.employee_id = ru.resource_id AND ru.resource_type = 'employee'
                          WHERE ru.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) OR ru.date IS NULL
                          GROUP BY e.employee_id
                          HAVING avg_util < 60 OR avg_util IS NULL";
    $stmt = $db->prepare($underutilizedQuery);
    $stmt->execute();
    $underutilized = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($underutilized as $employee) {
        $recommendations[] = [
            'type' => 'underutilization',
            'severity' => 'medium',
            'resource_id' => $employee['employee_id'],
            'resource_name' => $employee['employee_name'],
            'resource_type' => 'employee',
            'message' => "Employee {$employee['employee_name']} is underutilized at " . round($employee['avg_util'] ?? 0, 1) . "% utilization",
            'suggestion' => 'Assign more tasks or consider cross-training opportunities',
            'current_utilization' => $employee['avg_util'] ?? 0,
            'skills' => $employee['skills']
        ];
    }
    
    // Find project imbalances
    $projectQuery = "SELECT p.project_id, p.project_name, 
                            COUNT(ta.assignment_id) as total_assignments,
                            COUNT(DISTINCT ta.resource_id) as unique_resources,
                            SUM(ta.allocated_hours) as total_hours
                     FROM projects p
                     LEFT JOIN task_assignments ta ON p.project_id = ta.project_id AND ta.status != 'cancelled'
                     GROUP BY p.project_id
                     HAVING total_assignments > 0";
    $stmt = $db->prepare($projectQuery);
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $avg_assignments_per_project = array_sum(array_column($projects, 'total_assignments')) / count($projects);
    
    foreach ($projects as $project) {
        if ($project['total_assignments'] > $avg_assignments_per_project * 1.5) {
            $recommendations[] = [
                'type' => 'project_imbalance',
                'severity' => 'medium',
                'project_id' => $project['project_id'],
                'project_name' => $project['project_name'],
                'message' => "Project {$project['project_name']} has high resource concentration",
                'suggestion' => 'Consider resource redistribution or project prioritization',
                'assignments' => $project['total_assignments'],
                'unique_resources' => $project['unique_resources']
            ];
        }
    }
    
    echo json_encode($recommendations);
}

// Project Distribution Analysis
function getProjectDistribution($db) {
    $query = "SELECT p.project_id, p.project_name, p.status as project_status,
                     COUNT(DISTINCT CASE WHEN ta.assignment_type = 'employee' THEN ta.resource_id END) as employee_count,
                     COUNT(DISTINCT CASE WHEN ta.assignment_type = 'equipment' THEN ta.resource_id END) as equipment_count,
                     SUM(CASE WHEN ta.assignment_type = 'employee' THEN ta.allocated_hours ELSE 0 END) as employee_hours,
                     SUM(CASE WHEN ta.assignment_type = 'equipment' THEN ta.allocated_hours ELSE 0 END) as equipment_hours,
                     COUNT(ta.assignment_id) as total_assignments
              FROM projects p
              LEFT JOIN task_assignments ta ON p.project_id = ta.project_id AND ta.status != 'cancelled'
              GROUP BY p.project_id
              ORDER BY total_assignments DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate distribution balance metrics
    $total_projects = count($distribution);
    $total_assignments = array_sum(array_column($distribution, 'total_assignments'));
    $avg_assignments_per_project = $total_assignments / $total_projects;
    
    $variance = 0;
    foreach ($distribution as $project) {
        $variance += pow($project['total_assignments'] - $avg_assignments_per_project, 2);
    }
    $std_deviation = sqrt($variance / $total_projects);
    
    echo json_encode([
        'distribution' => $distribution,
        'metrics' => [
            'total_projects' => $total_projects,
            'total_assignments' => $total_assignments,
            'avg_assignments_per_project' => round($avg_assignments_per_project, 2),
            'std_deviation' => round($std_deviation, 2),
            'balance_coefficient' => $avg_assignments_per_project > 0 ? round($std_deviation / $avg_assignments_per_project, 2) : 0
        ]
    ]);
}

// Resource Conflict Detection
function getResourceConflicts($db) {
    $conflicts = [];
    
    // Check for time-based conflicts
    $conflictQuery = "SELECT ta1.assignment_id as assignment1_id, ta2.assignment_id as assignment2_id,
                             ta1.resource_id, ta1.assignment_type,
                             t1.task_name as task1_name, t2.task_name as task2_name,
                             p1.project_name as project1_name, p2.project_name as project2_name,
                             ta1.start_date as start1, ta1.end_date as end1,
                             ta2.start_date as start2, ta2.end_date as end2
                      FROM task_assignments ta1
                      JOIN task_assignments ta2 ON ta1.resource_id = ta2.resource_id 
                          AND ta1.assignment_type = ta2.assignment_type 
                          AND ta1.assignment_id != ta2.assignment_id
                          AND ta1.status != 'cancelled' AND ta2.status != 'cancelled'
                      JOIN tasks t1 ON ta1.task_id = t1.task_id
                      JOIN tasks t2 ON ta2.task_id = t2.task_id
                      JOIN projects p1 ON ta1.project_id = p1.project_id
                      JOIN projects p2 ON ta2.project_id = p2.project_id
                      WHERE (ta1.start_date <= ta2.end_date AND ta1.end_date >= ta2.start_date)
                      AND (ta1.project_id != ta2.project_id OR ta1.task_id != ta2.task_id)";
    
    $stmt = $db->prepare($conflictQuery);
    $stmt->execute();
    $time_conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($time_conflicts as $conflict) {
        $conflicts[] = [
            'type' => 'time_conflict',
            'severity' => 'high',
            'resource_type' => $conflict['assignment_type'],
            'resource_id' => $conflict['resource_id'],
            'assignment1' => [
                'id' => $conflict['assignment1_id'],
                'task' => $conflict['task1_name'],
                'project' => $conflict['project1_name'],
                'start' => $conflict['start1'],
                'end' => $conflict['end1']
            ],
            'assignment2' => [
                'id' => $conflict['assignment2_id'],
                'task' => $conflict['task2_name'],
                'project' => $conflict['project2_name'],
                'start' => $conflict['start2'],
                'end' => $conflict['end2']
            ],
            'message' => ucfirst($conflict['assignment_type']) . " assigned to overlapping tasks in different projects"
        ];
    }
    
    // Check for capacity conflicts
    $capacityQuery = "SELECT e.employee_id, e.employee_name, e.max_work_hours_per_day,
                             SUM(ta.allocated_hours) as daily_allocation,
                             COUNT(ta.assignment_id) as concurrent_assignments
                      FROM employees e
                      JOIN task_assignments ta ON e.employee_id = ta.resource_id AND ta.assignment_type = 'employee'
                      WHERE ta.status != 'cancelled' 
                      AND ta.start_date <= CURDATE() AND ta.end_date >= CURDATE()
                      GROUP BY e.employee_id
                      HAVING daily_allocation > e.max_work_hours_per_day OR concurrent_assignments > 3";
    
    $stmt = $db->prepare($capacityQuery);
    $stmt->execute();
    $capacity_conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($capacity_conflicts as $conflict) {
        $conflicts[] = [
            'type' => 'capacity_conflict',
            'severity' => 'critical',
            'resource_type' => 'employee',
            'resource_id' => $conflict['employee_id'],
            'resource_name' => $conflict['employee_name'],
            'daily_allocation' => $conflict['daily_allocation'],
            'max_capacity' => $conflict['max_work_hours_per_day'],
            'concurrent_assignments' => $conflict['concurrent_assignments'],
            'message' => "Employee {$conflict['employee_name']} exceeds daily capacity or has too many concurrent assignments"
        ];
    }
    
    echo json_encode($conflicts);
}

// Capacity Analysis
function getCapacityAnalysis($db) {
    $analysis = [];
    
    // Employee capacity analysis
    $employeeQuery = "SELECT e.employee_id, e.employee_name, e.role, e.max_work_hours_per_day,
                             COALESCE(SUM(ta.allocated_hours), 0) as allocated_hours,
                             e.max_work_hours_per_day - COALESCE(SUM(ta.allocated_hours), 0) as available_hours,
                             ROUND((COALESCE(SUM(ta.allocated_hours), 0) / e.max_work_hours_per_day) * 100, 2) as utilization_rate,
                             COUNT(ta.assignment_id) as active_assignments
                      FROM employees e
                      LEFT JOIN task_assignments ta ON e.employee_id = ta.resource_id 
                          AND ta.assignment_type = 'employee' AND ta.status != 'cancelled'
                          AND ta.start_date <= CURDATE() AND ta.end_date >= CURDATE()
                      WHERE e.status = 'active'
                      GROUP BY e.employee_id
                      ORDER BY utilization_rate DESC";
    
    $stmt = $db->prepare($employeeQuery);
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Equipment capacity analysis
    $equipmentQuery = "SELECT eq.equipment_id, eq.equipment_name, eq.equipment_type, eq.total_capacity_hours,
                             COALESCE(SUM(ta.allocated_hours), 0) as allocated_hours,
                             eq.total_capacity_hours - COALESCE(SUM(ta.allocated_hours), 0) as available_hours,
                             ROUND((COALESCE(SUM(ta.allocated_hours), 0) / eq.total_capacity_hours) * 100, 2) as utilization_rate,
                             COUNT(ta.assignment_id) as active_assignments
                      FROM equipment eq
                      LEFT JOIN task_assignments ta ON eq.equipment_id = ta.resource_id 
                          AND ta.assignment_type = 'equipment' AND ta.status != 'cancelled'
                          AND ta.start_date <= CURDATE() AND ta.end_date >= CURDATE()
                      WHERE eq.status IN ('available', 'in_use')
                      GROUP BY eq.equipment_id
                      ORDER BY utilization_rate DESC";
    
    $stmt = $db->prepare($equipmentQuery);
    $stmt->execute();
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate overall capacity metrics
    $total_employee_capacity = array_sum(array_column($employees, 'max_work_hours_per_day'));
    $total_employee_allocated = array_sum(array_column($employees, 'allocated_hours'));
    $total_equipment_capacity = array_sum(array_column($equipment, 'total_capacity_hours'));
    $total_equipment_allocated = array_sum(array_column($equipment, 'allocated_hours'));
    
    $analysis = [
        'employees' => $employees,
        'equipment' => $equipment,
        'summary' => [
            'total_employee_capacity' => $total_employee_capacity,
            'total_employee_allocated' => $total_employee_allocated,
            'employee_utilization_rate' => $total_employee_capacity > 0 ? round(($total_employee_allocated / $total_employee_capacity) * 100, 2) : 0,
            'total_equipment_capacity' => $total_equipment_capacity,
            'total_equipment_allocated' => $total_equipment_allocated,
            'equipment_utilization_rate' => $total_equipment_capacity > 0 ? round(($total_equipment_allocated / $total_equipment_capacity) * 100, 2) : 0,
            'available_employee_hours' => $total_employee_capacity - $total_employee_allocated,
            'available_equipment_hours' => $total_equipment_capacity - $total_equipment_allocated
        ]
    ];
    
    echo json_encode($analysis);
}

// Resource Rebalancing
function rebalanceResources($db) {
    $data = json_decode(file_get_contents("php://input"));
    $rebalancing_strategy = $data->strategy ?? 'utilization_balance';
    
    $rebalanced_assignments = [];
    
    switch ($rebalancing_strategy) {
        case 'utilization_balance':
            $rebalanced_assignments = balanceByUtilization($db, $data);
            break;
        case 'workload_distribution':
            $rebalanced_assignments = distributeWorkload($db, $data);
            break;
        case 'project_priority':
            $rebalanced_assignments = prioritizeByProject($db, $data);
            break;
        default:
            echo json_encode(["message" => "Invalid rebalancing strategy"]);
            return;
    }
    
    echo json_encode([
        "message" => "Resources rebalanced successfully",
        "rebalanced_assignments" => $rebalanced_assignments
    ]);
}

function balanceByUtilization($db, $data) {
    // Get current utilization
    $utilizationQuery = "SELECT e.employee_id, e.employee_name, AVG(ru.utilization_percentage) as avg_util
                         FROM employees e
                         LEFT JOIN resource_utilization ru ON e.employee_id = ru.resource_id AND ru.resource_type = 'employee'
                         WHERE ru.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                         GROUP BY e.employee_id
                         ORDER BY avg_util DESC";
    
    $stmt = $db->prepare($utilizationQuery);
    $stmt->execute();
    $utilization_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $rebalanced = [];
    
    // Simple rebalancing: suggest moving tasks from overloaded to underutilized employees
    $overloaded = array_filter($utilization_data, function($emp) { return $emp['avg_util'] > 85; });
    $underutilized = array_filter($utilization_data, function($emp) { return $emp['avg_util'] < 60; });
    
    foreach ($overloaded as $over_emp) {
        // Get tasks from overloaded employee
        $tasksQuery = "SELECT ta.*, t.task_name, p.project_name 
                       FROM task_assignments ta
                       JOIN tasks t ON ta.task_id = t.task_id
                       JOIN projects p ON ta.project_id = p.project_id
                       WHERE ta.assignment_type = 'employee' AND ta.resource_id = :emp_id 
                       AND ta.status = 'assigned'
                       ORDER BY ta.allocated_hours DESC";
        
        $stmt = $db->prepare($tasksQuery);
        $stmt->bindParam(':emp_id', $over_emp['employee_id']);
        $stmt->execute();
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tasks as $task) {
            if (count($underutilized) > 0) {
                $target_emp = array_shift($underutilized);
                
                $rebalanced[] = [
                    'action' => 'reassign',
                    'from_employee' => $over_emp['employee_name'],
                    'to_employee' => $target_emp['employee_name'],
                    'task' => $task['task_name'],
                    'project' => $task['project_name'],
                    'hours' => $task['allocated_hours'],
                    'reason' => 'Balance utilization: ' . round($over_emp['avg_util'], 1) . '% → ' . round($target_emp['avg_util'], 1) . '%'
                ];
            }
        }
    }
    
    return $rebalanced;
}

// Auto-assignment functionality
function autoAssignResources($db) {
    $data = json_decode(file_get_contents("php://input"));
    $task_id = $data->task_id;
    $assignment_criteria = $data->criteria ?? 'availability';
    
    // Get task details
    $taskQuery = "SELECT * FROM tasks WHERE task_id = :task_id";
    $stmt = $db->prepare($taskQuery);
    $stmt->bindParam(':task_id', $task_id);
    $stmt->execute();
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        echo json_encode(["message" => "Task not found"]);
        return;
    }
    
    $assignments = [];
    
    // Auto-assign employees based on criteria
    if ($assignment_criteria === 'availability') {
        $employeeQuery = "SELECT e.*, COALESCE(SUM(ta.allocated_hours), 0) as current_hours
                          FROM employees e
                          LEFT JOIN task_assignments ta ON e.employee_id = ta.resource_id 
                              AND ta.assignment_type = 'employee' AND ta.status != 'cancelled'
                              AND ta.start_date <= :end_date AND ta.end_date >= :start_date
                          WHERE e.status = 'active'
                          GROUP BY e.employee_id
                          HAVING current_hours < e.max_work_hours_per_day
                          ORDER BY current_hours ASC
                          LIMIT 3";
        
        $stmt = $db->prepare($employeeQuery);
        $stmt->bindParam(':start_date', $task['start_date']);
        $stmt->bindParam(':end_date', $task['end_date']);
        $stmt->execute();
        $available_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($available_employees as $employee) {
            $hours_to_assign = min($task['estimated_hours'] / count($available_employees), 
                                 $employee['max_work_hours_per_day'] - $employee['current_hours']);
            
            if ($hours_to_assign > 0) {
                // Create assignment
                $assignQuery = "INSERT INTO task_assignments (task_id, project_id, assignment_type, resource_id, allocated_hours, start_date, end_date) 
                               VALUES (:task_id, :project_id, 'employee', :resource_id, :allocated_hours, :start_date, :end_date)";
                
                $stmt = $db->prepare($assignQuery);
                $stmt->bindParam(':task_id', $task_id);
                $stmt->bindParam(':project_id', $task['project_id']);
                $stmt->bindParam(':resource_id', $employee['employee_id']);
                $stmt->bindParam(':allocated_hours', $hours_to_assign);
                $stmt->bindParam(':start_date', $task['start_date']);
                $stmt->bindParam(':end_date', $task['end_date']);
                
                if ($stmt->execute()) {
                    $assignments[] = [
                        'resource_type' => 'employee',
                        'resource_name' => $employee['employee_name'],
                        'allocated_hours' => $hours_to_assign,
                        'assignment_id' => $db->lastInsertId()
                    ];
                }
            }
        }
    }
    
    echo json_encode([
        "message" => "Auto-assignment completed",
        "task_id" => $task_id,
        "assignments" => $assignments
    ]);
}
?>
