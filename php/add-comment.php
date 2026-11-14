<?php
// Saves a comment written by the ticket owner.
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
$ticketId = (int) ($_POST['ticket_id'] ?? 0);
$body = trim((string) ($_POST['body'] ?? ''));

if ($ticketId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ticket']);
    exit();
}

if ($body === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Enter a comment before submitting.']);
    exit();
}

if (strlen($body) > 2000) {
    http_response_code(400);
    echo json_encode(['error' => 'Comments must be 2000 characters or fewer.']);
    exit();
}

$conn->begin_transaction();

try {
    $statement = $conn->prepare(
        "SELECT id, title, assignedTo, status
         FROM tickets
         WHERE id = ? AND user_id = ?
         FOR UPDATE"
    );
    $statement->bind_param('ii', $ticketId, $userId);
    $statement->execute();
    $ticket = $statement->get_result()->fetch_assoc();

    if (!$ticket) {
        $conn->rollback();
        http_response_code(404);
        echo json_encode(['error' => 'Ticket not found.']);
        exit();
    }

    $group = statusGroup((string) $ticket['status']);
    if (!in_array($group, ['new', 'in_progress'], true)) {
        $conn->rollback();
        http_response_code(409);
        echo json_encode(['error' => 'Comments can be added to new or in-progress tickets.']);
        exit();
    }

    addTicketComment($conn, $ticketId, $userId, currentUserName($conn), 'user', $body);

    $assignedTo = (string) ($ticket['assignedTo'] ?? '');
    if ($assignedTo !== '' && ctype_digit($assignedTo)) {
        $title = shortTitle((string) $ticket['title']);
        notifyUser(
            $conn,
            (int) $assignedTo,
            $ticketId,
            'New comment on ticket #' . $ticketId . ' (' . $title . ').'
        );
    }

    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => 'Could not save the comment.']);
    exit();
}

echo json_encode(['success' => true]);
