<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'user') {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$userId = (int) $_SESSION['user_id'];
$statement = $conn->prepare(
    'SELECT status, COUNT(*) AS total FROM tickets WHERE user_id = ? GROUP BY status'
);
$statement->bind_param('i', $userId);
$statement->execute();
$result = $statement->get_result();

$counts = ['new' => 0, 'in_progress' => 0, 'resolved' => 0, 'canceled' => 0];
while ($row = $result->fetch_assoc()) {
    $group = statusGroup((string) $row['status']);
    if (isset($counts[$group])) {
        $counts[$group] += (int) $row['total'];
    }
}

echo json_encode($counts);
