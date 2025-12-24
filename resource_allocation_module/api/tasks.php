<?php
// Enable error reporting for debugging but disable display
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set JSON content type and headers
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Custom error handler to catch any PHP errors and return JSON
function handleError($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error: [$errno] $errstr in $errfile on line $errline");
    http_response_code(500);
    echo json_encode(["message" => "Internal server error", "error" => "PHP error occurred"]);
    exit();
}

// Set custom error handler
set_error_handler('handleError');

require_once 'db.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed"]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        handleGet($db, $action);
        break;
    case 'POST':
        handlePost($db, $action);
        break;
    case 'PUT':
        handlePut($db, $action);
        break;
    case 'DELETE':
        handleDelete($db, $action);
        break;
    default:
        echo json_encode(["message" => "Method not allowed"]);
        break;
}

function handleGet($db, $action) {
    switch ($action) {
        case 'get_tasks':
            getTasks($db);
            break;
        case 'get_task':
            getTask($db);
            break;
        case 'get_projects':
            getProjects($db);
            break;
        case 'get_employees':
            getEmployees($db);
            break;
        case 'get_equipment':
            getEquipment($db);
            break;
        case 'get_assignments':
            getAssignments($db);
            break;
        case 'get_utilization':
            getUtilization($db);
            break;
        case 'get_alerts':
            getAlerts($db);
            break;
        case 'get_budget_categories':
            getBudgetCategories($db);
            break;
        case 'get_project_budgets':
            getProjectBudgets($db);
            break;
        default:
            echo json_encode(["message" => "Invalid action"]);
            break;
    }
}

function handlePost($db, $action) {
    switch ($action) {
        case 'create_task':
            createTask($db);
            break;
        case 'create_project':
            createProject($db);
            break;
        case 'create_employee':
            createEmployee($db);
            break;
        case 'create_equipment':
            createEquipment($db);
            break;
        case 'assign_resource':
            assignResource($db);
            break;
        case 'create_budget_category':
            createBudgetCategory($db);
            break;
        case 'allocate_budget':
            allocateBudget($db);
            break;
        case 'add_expense':
            addExpense($db);
            break;
        default:
            echo json_encode(["message" => "Invalid action"]);
            break;
    }
}

function handlePut($db, $action) {
    switch ($action) {
        case 'update_task':
            updateTask($db);
            break;
        case 'update_assignment':
            updateAssignment($db);
            break;
        case 'update_utilization':
            updateUtilization($db);
            break;
        case 'resolve_alert':
            resolveAlert($db);
            break;
        default:
            echo json_encode(["message" => "Invalid action"]);
            break;
    }
}

function handleDelete($db, $action) {
    switch ($action) {
        case 'delete_assignment':
            deleteAssignment($db);
            break;
        case 'delete_task':
            deleteTask($db);
            break;
        default:
            echo json_encode(["message" => "Invalid action"]);
            break;
    }
}

// Task Management Functions
function getTasks($db) {
    $project_id = $_GET['project_id'] ?? null;
    $query = "SELECT t.*, p.project_name FROM tasks t 
              LEFT JOIN projects p ON t.project_id = p.project_id";
    
    if ($project_id) {
        $query .= " WHERE t.project_id = :project_id";
    }
    
    $stmt = $db->prepare($query);
    
    if ($project_id) {
        $stmt->bindParam(':project_id', $project_id);
    }
    
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($tasks);
}

function createTask($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    $query = "INSERT INTO tasks (project_id, task_name, task_description, start_date, end_date, estimated_hours, priority) 
              VALUES (:project_id, :task_name, :task_description, :start_date, :end_date, :estimated_hours, :priority)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':project_id', $data->project_id);
    $stmt->bindParam(':task_name', $data->task_name);
    $stmt->bindParam(':task_description', $data->task_description);
    $stmt->bindParam(':start_date', $data->start_date);
    $stmt->bindParam(':end_date', $data->end_date);
    $stmt->bindParam(':estimated_hours', $data->estimated_hours);
    $stmt->bindParam(':priority', $data->priority);
    
    if ($stmt->execute()) {
        echo json_encode(["message" => "Task created successfully", "task_id" => $db->lastInsertId()]);
    } else {
        echo json_encode(["message" => "Task creation failed"]);
    }
}

// Project Management Functions
function getProjects($db) {
    $query = "SELECT * FROM projects ORDER BY start_date DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($projects);
}

function createProject($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    $query = "INSERT INTO projects (project_name, project_description, start_date, end_date, priority, budget_allocated) 
              VALUES (:project_name, :project_description, :start_date, :end_date, :priority, :budget_allocated)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':project_name', $data->project_name);
    $stmt->bindParam(':project_description', $data->project_description);
    $stmt->bindParam(':start_date', $data->start_date);
    $stmt->bindParam(':end_date', $data->end_date);
    $stmt->bindParam(':priority', $data->priority);
    $stmt->bindParam(':budget_allocated', $data->budget_allocated);
    
    if ($stmt->execute()) {
        echo json_encode(["message" => "Project created successfully", "project_id" => $db->lastInsertId()]);
    } else {
        echo json_encode(["message" => "Project creation failed"]);
    }
}

// Employee Management Functions
function getEmployees($db) {
    $query = "SELECT * FROM employees WHERE status = 'active' ORDER BY employee_name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($employees);
}

function createEmployee($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    $query = "INSERT INTO employees (employee_name, email, role, department, skills, hourly_rate, max_work_hours_per_day) 
              VALUES (:employee_name, :email, :role, :department, :skills, :hourly_rate, :max_work_hours_per_day)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':employee_name', $data->employee_name);
    $stmt->bindParam(':email', $data->email);
    $stmt->bindParam(':role', $data->role);
    $stmt->bindParam(':department', $data->department);
    $stmt->bindParam(':skills', $data->skills);
    $stmt->bindParam(':hourly_rate', $data->hourly_rate);
    $stmt->bindParam(':max_work_hours_per_day', $data->max_work_hours_per_day);
    
    if ($stmt->execute()) {
        echo json_encode(["message" => "Employee created successfully", "employee_id" => $db->lastInsertId()]);
    } else {
        echo json_encode(["message" => "Employee creation failed"]);
    }
}

// Equipment Management Functions
function getEquipment($db) {
    $query = "SELECT * FROM equipment WHERE status = 'available' OR status = 'in_use' ORDER BY equipment_name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($equipment);
}

function createEquipment($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    $query = "INSERT INTO equipment (equipment_name, equipment_type, description, hourly_rate, total_capacity_hours, location) 
              VALUES (:equipment_name, :equipment_type, :description, :hourly_rate, :total_capacity_hours, :location)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':equipment_name', $data->equipment_name);
    $stmt->bindParam(':equipment_type', $data->equipment_type);
    $stmt->bindParam(':description', $data->description);
    $stmt->bindParam(':hourly_rate', $data->hourly_rate);
    $stmt->bindParam(':total_capacity_hours', $data->total_capacity_hours);
    $stmt->bindParam(':location', $data->location);
    
    if ($stmt->execute()) {
        echo json_encode(["message" => "Equipment created successfully", "equipment_id" => $db->lastInsertId()]);
    } else {
        echo json_encode(["message" => "Equipment creation failed"]);
    }
}

// Resource Assignment Functions
function getAssignments($db) {
    $project_id = $_GET['project_id'] ?? null;
    $task_id = $_GET['task_id'] ?? null;
    
    $query = "SELECT ta.*, t.task_name, p.project_name,
                     CASE 
                         WHEN ta.assignment_type = 'employee' THEN e.employee_name
                         WHEN ta.assignment_type = 'equipment' THEN eq.equipment_name
                         ELSE 'Budget'
                     END as resource_name
              FROM task_assignments ta
              LEFT JOIN tasks t ON ta.task_id = t.task_id
              LEFT JOIN projects p ON ta.project_id = p.project_id
              LEFT JOIN employees e ON ta.assignment_type = 'employee' AND ta.resource_id = e.employee_id
              LEFT JOIN equipment eq ON ta.assignment_type = 'equipment' AND ta.resource_id = eq.equipment_id";
    
    $conditions = [];
    if ($project_id) {
        $conditions[] = "ta.project_id = :project_id";
    }
    if ($task_id) {
        $conditions[] = "ta.task_id = :task_id";
    }
    
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $stmt = $db->prepare($query);
    
    if ($project_id) {
        $stmt->bindParam(':project_id', $project_id);
    }
    if ($task_id) {
        $stmt->bindParam(':task_id', $task_id);
    }
    
    $stmt->execute();
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($assignments);
}

function assignResource($db) {
    try {
        $data = json_decode(file_get_contents("php://input"));
        
        if (!$data) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid JSON data"]);
            return;
        }
        
        // Validate required fields
        $required_fields = ['task_id', 'project_id', 'assignment_type', 'resource_id', 'allocated_hours', 'start_date', 'end_date'];
        foreach ($required_fields as $field) {
            if (!isset($data->$field) || empty($data->$field)) {
                http_response_code(400);
                echo json_encode(["message" => "Missing required field: $field"]);
                return;
            }
        }
        
        // Check for overallocation before assigning
        if ($data->assignment_type === 'employee') {
            try {
                $checkQuery = "SELECT SUM(allocated_hours) as total_hours FROM task_assignments 
                               WHERE assignment_type = 'employee' AND resource_id = :resource_id 
                               AND status != 'cancelled' AND ((start_date <= :end_date AND end_date >= :start_date))";
                $stmt = $db->prepare($checkQuery);
                $stmt->bindParam(':resource_id', $data->resource_id);
                $stmt->bindParam(':start_date', $data->start_date);
                $stmt->bindParam(':end_date', $data->end_date);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get employee max hours
                $empQuery = "SELECT max_work_hours_per_day FROM employees WHERE employee_id = :employee_id";
                $empStmt = $db->prepare($empQuery);
                $empStmt->bindParam(':employee_id', $data->resource_id);
                $empStmt->execute();
                $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($employee && ($result['total_hours'] + $data->allocated_hours) > $employee['max_work_hours_per_day'] * 30) { // 30 days assumption
                    echo json_encode(["message" => "Overallocation detected: Employee exceeds maximum work hours"]);
                    return;
                }
            } catch (PDOException $e) {
                error_log("Database error in overallocation check: " . $e->getMessage());
                // Continue with assignment even if check fails
            }
        }
        
        $query = "INSERT INTO task_assignments (task_id, project_id, assignment_type, resource_id, allocation_percentage, allocated_hours, start_date, end_date, notes) 
                  VALUES (:task_id, :project_id, :assignment_type, :resource_id, :allocation_percentage, :allocated_hours, :start_date, :end_date, :notes)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':task_id', $data->task_id);
        $stmt->bindParam(':project_id', $data->project_id);
        $stmt->bindParam(':assignment_type', $data->assignment_type);
        $stmt->bindParam(':resource_id', $data->resource_id);
        $stmt->bindParam(':allocation_percentage', $data->allocation_percentage);
        $stmt->bindParam(':allocated_hours', $data->allocated_hours);
        $stmt->bindParam(':start_date', $data->start_date);
        $stmt->bindParam(':end_date', $data->end_date);
        $stmt->bindParam(':notes', $data->notes);
        
        if ($stmt->execute()) {
            $assignment_id = $db->lastInsertId();
            
            // Update utilization (with error handling)
            try {
                updateResourceUtilization($db, $data->assignment_type, $data->resource_id);
            } catch (Exception $e) {
                error_log("Failed to update utilization: " . $e->getMessage());
            }
            
            // Check for alerts (with error handling)
            try {
                checkResourceAlerts($db, $data->assignment_type, $data->resource_id);
            } catch (Exception $e) {
                error_log("Failed to check alerts: " . $e->getMessage());
            }
            
            echo json_encode(["message" => "Resource assigned successfully", "assignment_id" => $assignment_id]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Resource assignment failed: " . implode(' ', $stmt->errorInfo())]);
        }
    } catch (Exception $e) {
        error_log("Exception in assignResource: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["message" => "Internal server error during assignment"]);
    }
}

// Utilization Monitoring Functions
function getUtilization($db) {
    $date = $_GET['date'] ?? date('Y-m-d');
    $resource_type = $_GET['resource_type'] ?? null;
    
    $query = "SELECT ru.*, 
                     CASE 
                         WHEN ru.resource_type = 'employee' THEN e.employee_name
                         WHEN ru.resource_type = 'equipment' THEN eq.equipment_name
                     END as resource_name,
                     p.project_name
              FROM resource_utilization ru
              LEFT JOIN employees e ON ru.resource_type = 'employee' AND ru.resource_id = e.employee_id
              LEFT JOIN equipment eq ON ru.resource_type = 'equipment' AND ru.resource_id = eq.equipment_id
              LEFT JOIN projects p ON ru.project_id = p.project_id
              WHERE ru.date = :date";
    
    if ($resource_type) {
        $query .= " AND ru.resource_type = :resource_type";
    }
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':date', $date);
    
    if ($resource_type) {
        $stmt->bindParam(':resource_type', $resource_type);
    }
    
    $stmt->execute();
    $utilization = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($utilization);
}

function updateResourceUtilization($db, $resource_type, $resource_id) {
    $today = date('Y-m-d');
    
    if ($resource_type === 'employee') {
        $query = "SELECT SUM(allocated_hours) as total_hours, MAX(max_work_hours_per_day) as capacity 
                  FROM task_assignments ta
                  LEFT JOIN employees e ON ta.resource_id = e.employee_id
                  WHERE ta.assignment_type = 'employee' AND ta.resource_id = :resource_id 
                  AND ta.status != 'cancelled' AND ta.start_date <= :today AND ta.end_date >= :today";
    } else {
        $query = "SELECT SUM(allocated_hours) as total_hours, MAX(total_capacity_hours) as capacity 
                  FROM task_assignments ta
                  LEFT JOIN equipment eq ON ta.resource_id = eq.equipment_id
                  WHERE ta.assignment_type = 'equipment' AND ta.resource_id = :resource_id 
                  AND ta.status != 'cancelled' AND ta.start_date <= :today AND ta.end_date >= :today";
    }
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':resource_id', $resource_id);
    $stmt->bindParam(':today', $today);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_hours = $result['total_hours'] ?? 0;
    $capacity = $result['capacity'] ?? 8;
    $utilization_percentage = $capacity > 0 ? ($total_hours / $capacity) * 100 : 0;
    
    // Update or insert utilization record
    $upsertQuery = "INSERT INTO resource_utilization (resource_type, resource_id, date, total_hours_available, hours_used, utilization_percentage) 
                    VALUES (:resource_type, :resource_id, :date, :capacity, :total_hours, :utilization_percentage)
                    ON DUPLICATE KEY UPDATE hours_used = :total_hours, utilization_percentage = :utilization_percentage";
    
    $stmt = $db->prepare($upsertQuery);
    $stmt->bindParam(':resource_type', $resource_type);
    $stmt->bindParam(':resource_id', $resource_id);
    $stmt->bindParam(':date', $today);
    $stmt->bindParam(':capacity', $capacity);
    $stmt->bindParam(':total_hours', $total_hours);
    $stmt->bindParam(':utilization_percentage', $utilization_percentage);
    $stmt->execute();
}

// Alert Management Functions
function getAlerts($db) {
    $status = $_GET['status'] ?? 'active';
    $severity = $_GET['severity'] ?? null;
    
    $query = "SELECT ra.*, 
                     CASE 
                         WHEN ra.resource_type = 'employee' THEN e.employee_name
                         WHEN ra.resource_type = 'equipment' THEN eq.equipment_name
                     END as resource_name,
                     p.project_name, t.task_name
              FROM resource_alerts ra
              LEFT JOIN employees e ON ra.resource_type = 'employee' AND ra.resource_id = e.employee_id
              LEFT JOIN equipment eq ON ra.resource_type = 'equipment' AND ra.resource_id = eq.equipment_id
              LEFT JOIN projects p ON ra.project_id = p.project_id
              LEFT JOIN tasks t ON ra.task_id = t.task_id
              WHERE ra.status = :status";
    
    if ($severity) {
        $query .= " AND ra.severity = :severity";
    }
    
    $query .= " ORDER BY ra.severity DESC, ra.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status);
    
    if ($severity) {
        $stmt->bindParam(':severity', $severity);
    }
    
    $stmt->execute();
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($alerts);
}

function checkResourceAlerts($db, $resource_type, $resource_id) {
    // Check for overallocation (>100% utilization)
    $utilQuery = "SELECT utilization_percentage FROM resource_utilization 
                  WHERE resource_type = :resource_type AND resource_id = :resource_id 
                  AND date = CURDATE()";
    $stmt = $db->prepare($utilQuery);
    $stmt->bindParam(':resource_type', $resource_type);
    $stmt->bindParam(':resource_id', $resource_id);
    $stmt->execute();
    $utilization = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($utilization && $utilization['utilization_percentage'] > 100) {
        $alertQuery = "INSERT INTO resource_alerts (alert_type, resource_type, resource_id, severity, message, threshold_value, current_value) 
                       VALUES ('overallocation', :resource_type, :resource_id, 'critical', 
                              'Resource utilization exceeds 100%', 100, :utilization)";
        $stmt = $db->prepare($alertQuery);
        $stmt->bindParam(':resource_type', $resource_type);
        $stmt->bindParam(':resource_id', $resource_id);
        $stmt->bindParam(':utilization', $utilization['utilization_percentage']);
        $stmt->execute();
    } elseif ($utilization && $utilization['utilization_percentage'] < 60) {
        $alertQuery = "INSERT INTO resource_alerts (alert_type, resource_type, resource_id, severity, message, threshold_value, current_value) 
                       VALUES ('underutilization', :resource_type, :resource_id, 'medium', 
                              'Resource utilization below 60%', 60, :utilization)";
        $stmt = $db->prepare($alertQuery);
        $stmt->bindParam(':resource_type', $resource_type);
        $stmt->bindParam(':resource_id', $resource_id);
        $stmt->bindParam(':utilization', $utilization['utilization_percentage']);
        $stmt->execute();
    }
}

// Budget Management Functions
function getBudgetCategories($db) {
    $query = "SELECT * FROM budget_categories ORDER BY category_name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($categories);
}

function createBudgetCategory($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    $query = "INSERT INTO budget_categories (category_name, description, parent_category_id) 
              VALUES (:category_name, :description, :parent_category_id)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':category_name', $data->category_name);
    $stmt->bindParam(':description', $data->description);
    $stmt->bindParam(':parent_category_id', $data->parent_category_id);
    
    if ($stmt->execute()) {
        echo json_encode(["message" => "Budget category created successfully", "category_id" => $db->lastInsertId()]);
    } else {
        echo json_encode(["message" => "Budget category creation failed"]);
    }
}

function getProjectBudgets($db) {
    $project_id = $_GET['project_id'] ?? null;
    
    $query = "SELECT pb.*, bc.category_name FROM project_budgets pb 
              LEFT JOIN budget_categories bc ON pb.category_id = bc.category_id";
    
    if ($project_id) {
        $query .= " WHERE pb.project_id = :project_id";
    }
    
    $stmt = $db->prepare($query);
    
    if ($project_id) {
        $stmt->bindParam(':project_id', $project_id);
    }
    
    $stmt->execute();
    $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($budgets);
}

function allocateBudget($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    $query = "INSERT INTO project_budgets (project_id, category_id, allocated_amount, fiscal_year) 
              VALUES (:project_id, :category_id, :allocated_amount, :fiscal_year)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':project_id', $data->project_id);
    $stmt->bindParam(':category_id', $data->category_id);
    $stmt->bindParam(':allocated_amount', $data->allocated_amount);
    $stmt->bindParam(':fiscal_year', $data->fiscal_year);
    
    if ($stmt->execute()) {
        echo json_encode(["message" => "Budget allocated successfully", "budget_id" => $db->lastInsertId()]);
    } else {
        echo json_encode(["message" => "Budget allocation failed"]);
    }
}

function addExpense($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    $query = "INSERT INTO budget_expenses (project_id, budget_id, task_id, expense_description, amount, expense_date, expense_type) 
              VALUES (:project_id, :budget_id, :task_id, :expense_description, :amount, :expense_date, :expense_type)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':project_id', $data->project_id);
    $stmt->bindParam(':budget_id', $data->budget_id);
    $stmt->bindParam(':task_id', $data->task_id);
    $stmt->bindParam(':expense_description', $data->expense_description);
    $stmt->bindParam(':amount', $data->amount);
    $stmt->bindParam(':expense_date', $data->expense_date);
    $stmt->bindParam(':expense_type', $data->expense_type);
    
    if ($stmt->execute()) {
        // Update spent amount in project_budgets
        $updateQuery = "UPDATE project_budgets SET spent_amount = spent_amount + :amount WHERE budget_id = :budget_id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':amount', $data->amount);
        $updateStmt->bindParam(':budget_id', $data->budget_id);
        $updateStmt->execute();
        
        echo json_encode(["message" => "Expense added successfully", "expense_id" => $db->lastInsertId()]);
    } else {
        echo json_encode(["message" => "Expense addition failed"]);
    }
}

// Update Functions
function updateTask($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    $query = "UPDATE tasks SET task_name = :task_name, task_description = :task_description, 
              start_date = :start_date, end_date = :end_date, estimated_hours = :estimated_hours, 
              actual_hours = :actual_hours, status = :status, progress_percentage = :progress_percentage 
              WHERE task_id = :task_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':task_id', $data->task_id);
    $stmt->bindParam(':task_name', $data->task_name);
    $stmt->bindParam(':task_description', $data->task_description);
    $stmt->bindParam(':start_date', $data->start_date);
    $stmt->bindParam(':end_date', $data->end_date);
    $stmt->bindParam(':estimated_hours', $data->estimated_hours);
    $stmt->bindParam(':actual_hours', $data->actual_hours);
    $stmt->bindParam(':status', $data->status);
    $stmt->bindParam(':progress_percentage', $data->progress_percentage);
    
    if ($stmt->execute()) {
        echo json_encode(["message" => "Task updated successfully"]);
    } else {
        echo json_encode(["message" => "Task update failed"]);
    }
}

function updateAssignment($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    $query = "UPDATE task_assignments SET allocation_percentage = :allocation_percentage, 
              allocated_hours = :allocated_hours, actual_hours_used = :actual_hours_used, 
              status = :status, notes = :notes WHERE assignment_id = :assignment_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':assignment_id', $data->assignment_id);
    $stmt->bindParam(':allocation_percentage', $data->allocation_percentage);
    $stmt->bindParam(':allocated_hours', $data->allocated_hours);
    $stmt->bindParam(':actual_hours_used', $data->actual_hours_used);
    $stmt->bindParam(':status', $data->status);
    $stmt->bindParam(':notes', $data->notes);
    
    if ($stmt->execute()) {
        // Update utilization after assignment change
        $assignmentQuery = "SELECT assignment_type, resource_id FROM task_assignments WHERE assignment_id = :assignment_id";
        $assignmentStmt = $db->prepare($assignmentQuery);
        $assignmentStmt->bindParam(':assignment_id', $data->assignment_id);
        $assignmentStmt->execute();
        $assignment = $assignmentStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($assignment) {
            updateResourceUtilization($db, $assignment['assignment_type'], $assignment['resource_id']);
        }
        
        echo json_encode(["message" => "Assignment updated successfully"]);
    } else {
        echo json_encode(["message" => "Assignment update failed"]);
    }
}

function resolveAlert($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    $query = "UPDATE resource_alerts SET status = 'resolved', resolved_at = NOW() WHERE alert_id = :alert_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':alert_id', $data->alert_id);
    
    if ($stmt->execute()) {
        echo json_encode(["message" => "Alert resolved successfully"]);
    } else {
        echo json_encode(["message" => "Alert resolution failed"]);
    }
}

// Delete Functions
function deleteAssignment($db) {
    $assignment_id = $_GET['assignment_id'] ?? null;
    
    if (!$assignment_id) {
        echo json_encode(["message" => "Assignment ID required"]);
        return;
    }
    
    // Get assignment details before deletion for utilization update
    $assignmentQuery = "SELECT assignment_type, resource_id FROM task_assignments WHERE assignment_id = :assignment_id";
    $assignmentStmt = $db->prepare($assignmentQuery);
    $assignmentStmt->bindParam(':assignment_id', $assignment_id);
    $assignmentStmt->execute();
    $assignment = $assignmentStmt->fetch(PDO::FETCH_ASSOC);
    
    $query = "DELETE FROM task_assignments WHERE assignment_id = :assignment_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':assignment_id', $assignment_id);
    
    if ($stmt->execute()) {
        // Update utilization after deletion
        if ($assignment) {
            updateResourceUtilization($db, $assignment['assignment_type'], $assignment['resource_id']);
        }
        
        echo json_encode(["message" => "Assignment deleted successfully"]);
    } else {
        echo json_encode(["message" => "Assignment deletion failed"]);
    }
}

function deleteTask($db) {
    $task_id = $_GET['task_id'] ?? null;
    
    if (!$task_id) {
        echo json_encode(["message" => "Task ID required"]);
        return;
    }
    
    $query = "DELETE FROM tasks WHERE task_id = :task_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':task_id', $task_id);
    
    if ($stmt->execute()) {
        echo json_encode(["message" => "Task deleted successfully"]);
    } else {
        echo json_encode(["message" => "Task deletion failed"]);
    }
}
?>
