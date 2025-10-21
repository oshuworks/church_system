<?php
include '../includes/db.php';
header('Content-Type: application/json');
$project_id = $_GET['project_id'];
$result = $conn->query("SELECT * FROM notifications WHERE project_id = $project_id");
$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
echo json_encode($notifications);
?>
