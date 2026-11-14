<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'user') {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit();
}

if (!csrfValid('csrf_token', $_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['error' => 'Your session expired. Reload the page and try again.']);
    exit();
}

$userId = (int) $_SESSION['user_id'];
$ticketId = (int) ($_POST['id'] ?? 0);

if ($ticketId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ticket']);
    exit();
}

$conn->begin_transaction();

try {
    $statement = $conn->prepare(
        "SELECT id, title, assignedTo
         FROM tickets
         WHERE id = ? AND user_id = ? AND status IN ('new', 'open', 'in_progress')
         FOR UPDATE"
    );
    $statement->bind_param('ii', $ticketId, $userId);
    $statement->execute();
    $ticket = $statement->get_result()->fetch_assoc();

    if (!$ticket) {
        $conn->rollback();
        http_response_code(404);
        echo json_encode(['error' => 'Ticket not found, or it is already resolved or canceled.']);
        exit();
    }

    $update = $conn->prepare(
        "UPDATE tickets SET status = 'canceled'
         WHERE id = ? AND user_id = ? AND status IN ('new', 'open', 'in_progress')"
    );
    $update->bind_param('ii', $ticketId, $userId);
    $update->execute();
    addTicketComment(
        $conn,
        $ticketId,
        $userId,
        'System',
        'system',
        'The user canceled this ticket.'
    );

    $assignedTo = (string) ($ticket['assignedTo'] ?? '');
    if ($assignedTo !== '' && ctype_digit($assignedTo)) {
        $title = shortTitle((string) $ticket['title']);
        notifyUser(
            $conn,
            (int) $assignedTo,
            $ticketId,
            'Ticket #' . $ticketId . ' (' . $title . ') was canceled by the user.'
        );
    }

    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => 'Could not cancel ticket.']);
    exit();
}

echo json_encode(['success' => true]);
