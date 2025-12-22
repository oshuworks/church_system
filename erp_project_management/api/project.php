<?php
include '../includes/db.php';
header('Content-Type: application/json');
$project_id = $_GET['project_id'];
$project = $conn->query("SELECT * FROM projects WHERE project_id = $project_id")->fetch_assoc();
$tasks = [];
$result = $conn->query("SELECT * FROM tasks WHERE project_id = $project_id");
while ($row = $result->fetch_assoc()) {
    $tasks[] = $row;
}
echo json_encode(['project' => $project, 'tasks' => $tasks]);
?>