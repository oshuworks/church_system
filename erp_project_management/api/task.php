<?php
include '../includes/db.php';
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
// Insert or update logic here
echo json_encode(['status' => 'success']);
?>