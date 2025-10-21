<?php
include '../includes/db.php';
header('Content-Type: application/json');
$result = $conn->query("SELECT * FROM projects");
$projects = [];
while ($row = $result->fetch_assoc()) {
    $projects[] = $row;
}
echo json_encode($projects);
?>