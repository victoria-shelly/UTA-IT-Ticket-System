<?php
// Returns the signed-in user's tickets for one status, including comments.
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
$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$statuses = statusesForGroup($status);

if ($statuses === []) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status']);
    exit();
}

$placeholders = implode(',', array_fill(0, count($statuses), '?'));
$types = 'i' . str_repeat('s', count($statuses));
$params = array_merge([$userId], $statuses);

$sql = "SELECT t.id, t.title, t.category, t.description, t.priority, t.status,
               t.dueDate, t.created_at, s.name AS assigned_name
        FROM tickets t
        LEFT JOIN users_staff s ON s.id = CAST(t.assignedTo AS UNSIGNED) AND s.role = 'staff'
        WHERE t.user_id = ? AND t.status IN ($placeholders)
        ORDER BY t.created_at DESC";

$statement = $conn->prepare($sql);
$statement->bind_param($types, ...$params);
$statement->execute();
$tickets = $statement->get_result()->fetch_all(MYSQLI_ASSOC);

$commentsByTicket = [];
$ticketIds = array_map(static fn(array $ticket): int => (int) $ticket['id'], $tickets);
if ($ticketIds !== []) {
    $idList = implode(',', $ticketIds);
    $commentResult = $conn->query(
        "SELECT ticket_id, author_name, author_role, body, is_resolution, created_at
         FROM ticket_comments
         WHERE ticket_id IN ($idList)
         ORDER BY created_at ASC, id ASC"
    );
    while ($comment = $commentResult->fetch_assoc()) {
        $comment['is_resolution'] = (int) $comment['is_resolution'];
        $commentsByTicket[(int) $comment['ticket_id']][] = $comment;
    }
}

foreach ($tickets as &$ticket) {
    $ticket['comments'] = $commentsByTicket[(int) $ticket['id']] ?? [];
}
unset($ticket);

echo json_encode($tickets);
