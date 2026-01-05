<?php
// Shared bootstrap for API endpoints: database connection and helpers.
header('Content-Type: application/json');

$dbConfig = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'name' => 'erp_project_management',
];

$mysqli = @new mysqli($dbConfig['host'], $dbConfig['user'], $dbConfig['pass'], $dbConfig['name']);

if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed', 'details' => $mysqli->connect_error]);
    exit;
}

$mysqli->set_charset('utf8mb4');

/**
 * Send a JSON response and exit.
 */
function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

/**
 * Safely prepare and execute a statement with optional parameters.
 */
function runQuery(mysqli $mysqli, string $sql, array $params = []): mysqli_stmt
{
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        respond(['error' => 'Failed to prepare statement', 'details' => $mysqli->error], 500);
    }

    if (!empty($params)) {
        [$types, $values] = $params;
        $stmt->bind_param($types, ...$values);
    }

    if (!$stmt->execute()) {
        respond(['error' => 'Query execution failed', 'details' => $stmt->error], 500);
    }

    return $stmt;
}

/**
 * Fetch all rows as associative arrays from a prepared statement.
 */
function fetchAllAssoc(mysqli_stmt $stmt): array
{
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

