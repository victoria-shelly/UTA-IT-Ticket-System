<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$userId = (int) ($_SESSION['user_id'] ?? 0);
$role = $_SESSION['user_role'] ?? '';
if ($userId < 1 || !in_array($role, ['user', 'staff'], true)) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid('csrf_token', $_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo json_encode(['error' => 'Your session expired. Reload the page and try again.']);
        exit();
    }

    $statement = $conn->prepare(
        'UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0'
    );
    $statement->bind_param('i', $userId);
    $statement->execute();
    echo json_encode(['success' => true]);
    exit();
}

$countStatement = $conn->prepare(
    'SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0'
);
$countStatement->bind_param('i', $userId);
$countStatement->execute();
$unread = (int) ($countStatement->get_result()->fetch_assoc()['total'] ?? 0);

$listStatement = $conn->prepare(
    'SELECT id, ticket_id, message, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT 15'
);
$listStatement->bind_param('i', $userId);
$listStatement->execute();
$rows = $listStatement->get_result()->fetch_all(MYSQLI_ASSOC);

$items = [];
foreach ($rows as $row) {
    $ticketId = (int) ($row['ticket_id'] ?? 0);
    $items[] = [
        'id' => (int) $row['id'],
        'message' => $row['message'],
        'is_read' => (int) $row['is_read'],
        'created_label' => formatWhen($row['created_at']),
        'href' => $role === 'staff' && $ticketId > 0
            ? 'IT_Staff_Dashboard.php?ticket_id=' . $ticketId
            : null,
    ];
}

echo json_encode(['unread' => $unread, 'items' => $items]);
