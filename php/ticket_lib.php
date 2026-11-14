<?php

const TICKET_PRIORITIES = ['low', 'medium', 'high'];
const TICKET_CATEGORIES = ['hardware', 'software', 'network', 'account', 'other'];

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function statusGroup(string $status): string
{
    return match ($status) {
        'open', 'new' => 'new',
        'in_progress' => 'in_progress',
        'closed', 'resolved' => 'resolved',
        'cancelled', 'canceled' => 'canceled',
        default => $status,
    };
}

function statusesForGroup(string $group): array
{
    return match ($group) {
        'new' => ['new', 'open'],
        'in_progress' => ['in_progress'],
        'resolved' => ['resolved', 'closed'],
        'canceled' => ['canceled', 'cancelled'],
        default => [],
    };
}

function statusLabel(string $status): string
{
    return match (statusGroup($status)) {
        'new' => 'New',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'canceled' => 'Canceled',
        default => ucwords(str_replace('_', ' ', $status)),
    };
}

function priorityLabel(string $priority): string
{
    return ucfirst($priority);
}

function formatWhen(?string $value): string
{
    if ($value === null || $value === '') {
        return 'Not set';
    }

    $date = date_create($value);
    return $date ? $date->format('M j, Y g:i A') : $value;
}

function ticketDueDate(string $priority, ?string $submittedAt = null): string
{
    $days = match ($priority) {
        'high' => 1,
        'medium' => 4,
        default => 7,
    };
    $start = $submittedAt ? new DateTimeImmutable($submittedAt) : new DateTimeImmutable('now');

    return $start->modify('+' . $days . ' days')->format('Y-m-d H:i:s');
}

function shortTitle(string $title): string
{
    if (function_exists('mb_strlen') && mb_strlen($title) > 80) {
        return mb_substr($title, 0, 77) . '...';
    }

    if (strlen($title) > 80) {
        return substr($title, 0, 77) . '...';
    }

    return $title;
}

function csrfValid(string $sessionKey, mixed $submitted): bool
{
    $sessionToken = $_SESSION[$sessionKey] ?? '';

    return is_string($sessionToken)
        && $sessionToken !== ''
        && is_string($submitted)
        && hash_equals($sessionToken, $submitted);
}

function currentUserName(mysqli $conn): string
{
    if (!empty($_SESSION['user_name']) && is_string($_SESSION['user_name'])) {
        return $_SESSION['user_name'];
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId < 1) {
        return 'User';
    }

    $statement = $conn->prepare('SELECT name FROM users_staff WHERE id = ?');
    $statement->bind_param('i', $userId);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    $name = trim((string) ($row['name'] ?? 'User'));
    $_SESSION['user_name'] = $name !== '' ? $name : 'User';

    return $_SESSION['user_name'];
}

function notifyUser(mysqli $conn, int $userId, int $ticketId, string $message): void
{
    if ($userId < 1 || trim($message) === '') {
        return;
    }

    $statement = $conn->prepare(
        'INSERT INTO notifications (user_id, ticket_id, message) VALUES (?, ?, ?)'
    );
    $statement->bind_param('iis', $userId, $ticketId, $message);
    $statement->execute();
}

function addTicketComment(
    mysqli $conn,
    int $ticketId,
    ?int $authorId,
    string $authorName,
    string $role,
    string $body,
    bool $isResolution = false
): void {
    $resolution = $isResolution ? 1 : 0;
    $statement = $conn->prepare(
        'INSERT INTO ticket_comments
            (ticket_id, author_id, author_name, author_role, body, is_resolution)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $statement->bind_param('iisssi', $ticketId, $authorId, $authorName, $role, $body, $resolution);
    $statement->execute();
}

function commentsForTicket(mysqli $conn, int $ticketId): array
{
    $statement = $conn->prepare(
        'SELECT author_name, author_role, body, is_resolution, created_at
         FROM ticket_comments
         WHERE ticket_id = ?
         ORDER BY created_at ASC, id ASC'
    );
    $statement->bind_param('i', $ticketId);
    $statement->execute();

    return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
}

function staffMembers(mysqli $conn): array
{
    $result = $conn->query(
        "SELECT id, name FROM users_staff WHERE role = 'staff' ORDER BY name ASC"
    );

    return $result->fetch_all(MYSQLI_ASSOC);
}

function assigneeName(mysqli $conn, ?string $assignedTo): string
{
    if ($assignedTo === null || $assignedTo === '' || !ctype_digit($assignedTo)) {
        return '';
    }

    $staffId = (int) $assignedTo;
    $statement = $conn->prepare(
        "SELECT name FROM users_staff WHERE id = ? AND role = 'staff'"
    );
    $statement->bind_param('i', $staffId);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();

    return trim((string) ($row['name'] ?? ''));
}

function lockTicket(mysqli $conn, int $ticketId): ?array
{
    $statement = $conn->prepare(
        'SELECT id, user_id, title, status, assignedTo
         FROM tickets
         WHERE id = ?
         FOR UPDATE'
    );
    $statement->bind_param('i', $ticketId);
    $statement->execute();
    $ticket = $statement->get_result()->fetch_assoc();

    return $ticket ?: null;
}

function handleStaffTicketAction(mysqli $conn): string
{
    if (($_SESSION['user_role'] ?? '') !== 'staff') {
        http_response_code(403);
        exit('Not authorized.');
    }

    if (!csrfValid('staff_dashboard_csrf', $_POST['csrf_token'] ?? null)) {
        return 'The ticket update could not be validated. Reload the page and try again.';
    }

    $ticketId = (int) ($_POST['ticket_id'] ?? 0);
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if ($ticketId < 1 || !in_array($action, ['assign', 'resolve', 'reopen', 'comment'], true)) {
        return 'The ticket update could not be validated. Please try again.';
    }

    $staffId = (int) ($_SESSION['user_id'] ?? 0);
    $staffName = currentUserName($conn);
    $notice = '';

    $conn->begin_transaction();
    $fail = static function (string $message) use ($conn): string {
        $conn->rollback();
        return $message;
    };

    try {
        $ticket = lockTicket($conn, $ticketId);
        if (!$ticket) {
            return $fail('Ticket not found.');
        }

        $group = statusGroup((string) $ticket['status']);
        if ($group === 'canceled') {
            return $fail('Canceled tickets stay canceled. The user can submit a new ticket.');
        }

        $ownerId = (int) ($ticket['user_id'] ?? 0);
        $title = shortTitle((string) $ticket['title']);
        $currentAssignee = (string) ($ticket['assignedTo'] ?? '');

        if ($action === 'assign') {
            if ($group === 'resolved') {
                return $fail('Reopen this ticket before changing who it is assigned to.');
            }

            $newStaffId = trim((string) ($_POST['staff_id'] ?? ''));
            if ($newStaffId === '' && $currentAssignee === '') {
                return $fail('Select a staff member to assign this ticket.');
            }
            if ($newStaffId !== '' && !ctype_digit($newStaffId)) {
                return $fail('Select a valid IT staff member.');
            }
            if ($newStaffId !== '' && $newStaffId === $currentAssignee) {
                return $fail('That staff member is already assigned. Choose someone else or unassign the ticket.');
            }

            $oldName = assigneeName($conn, $currentAssignee);
            if ($newStaffId === '') {
                $statement = $conn->prepare(
                    "UPDATE tickets SET assignedTo = NULL, status = 'new' WHERE id = ?"
                );
                $statement->bind_param('i', $ticketId);
                $statement->execute();
                $from = $oldName !== '' ? $oldName : 'the previous assignee';
                addTicketComment(
                    $conn,
                    $ticketId,
                    $staffId,
                    'System',
                    'system',
                    'This ticket has been unassigned from ' . $from . '.'
                );
                notifyUser($conn, $ownerId, $ticketId, 'Your ticket #' . $ticketId . ' (' . $title . ') is no longer assigned.');
            } else {
                $newId = (int) $newStaffId;
                $lookup = $conn->prepare(
                    "SELECT id, name FROM users_staff WHERE id = ? AND role = 'staff'"
                );
                $lookup->bind_param('i', $newId);
                $lookup->execute();
                $member = $lookup->get_result()->fetch_assoc();
                if (!$member) {
                    return $fail('Select a valid IT staff member.');
                }

                $assignedTo = (string) $member['id'];
                $statement = $conn->prepare(
                    "UPDATE tickets SET assignedTo = ?, status = 'in_progress' WHERE id = ?"
                );
                $statement->bind_param('si', $assignedTo, $ticketId);
                $statement->execute();

                $newName = (string) $member['name'];
                $comment = $oldName !== ''
                    ? 'This ticket has been reassigned from ' . $oldName . ' to ' . $newName . '.'
                    : 'This ticket has been assigned to ' . $newName . '.';
                addTicketComment($conn, $ticketId, $staffId, 'System', 'system', $comment);
                notifyUser($conn, $ownerId, $ticketId, 'Your ticket #' . $ticketId . ' (' . $title . ') was assigned to ' . $newName . '.');
                if ($newId !== $staffId) {
                    notifyUser($conn, $newId, $ticketId, 'Ticket #' . $ticketId . ' (' . $title . ') was assigned to you.');
                }
            }

            $notice = 'assigned';
        } elseif ($action === 'resolve') {
            if (!in_array($group, ['new', 'in_progress'], true)) {
                return $fail('Only new or in-progress tickets can be resolved.');
            }

            $note = trim((string) ($_POST['resolution'] ?? ''));
            if ($note === '') {
                return $fail('Add a resolution note before resolving the ticket.');
            }
            if (strlen($note) > 2000) {
                return $fail('The resolution note must be 2000 characters or fewer.');
            }

            if ($currentAssignee === '') {
                $assignedTo = (string) $staffId;
                $statement = $conn->prepare(
                    "UPDATE tickets SET status = 'resolved', assignedTo = ? WHERE id = ?"
                );
                $statement->bind_param('si', $assignedTo, $ticketId);
            } else {
                $statement = $conn->prepare(
                    "UPDATE tickets SET status = 'resolved' WHERE id = ?"
                );
                $statement->bind_param('i', $ticketId);
            }
            $statement->execute();
            addTicketComment($conn, $ticketId, $staffId, $staffName, 'staff', $note, true);
            notifyUser($conn, $ownerId, $ticketId, 'Your ticket #' . $ticketId . ' (' . $title . ') was resolved.');
            $notice = 'resolved';
        } elseif ($action === 'reopen') {
            if ($group !== 'resolved') {
                return $fail('Only resolved tickets can be reopened.');
            }

            $nextStatus = $currentAssignee !== '' ? 'in_progress' : 'new';
            $statement = $conn->prepare('UPDATE tickets SET status = ? WHERE id = ?');
            $statement->bind_param('si', $nextStatus, $ticketId);
            $statement->execute();
            addTicketComment(
                $conn,
                $ticketId,
                $staffId,
                'System',
                'system',
                'This ticket has been reopened by ' . $staffName . '.'
            );
            notifyUser($conn, $ownerId, $ticketId, 'Your ticket #' . $ticketId . ' (' . $title . ') was reopened.');
            $notice = 'reopened';
        } else {
            $body = trim((string) ($_POST['comment'] ?? ''));
            if ($body === '') {
                return $fail('Enter a comment before submitting.');
            }
            if (strlen($body) > 2000) {
                return $fail('Comments must be 2000 characters or fewer.');
            }

            addTicketComment($conn, $ticketId, $staffId, $staffName, 'staff', $body);
            notifyUser($conn, $ownerId, $ticketId, 'New comment on ticket #' . $ticketId . ' (' . $title . ').');
            $notice = 'comment';
        }

        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        return 'The ticket could not be updated. Please try again.';
    }

    $params = [
        'ticket_id' => $ticketId,
        'notice' => $notice,
    ];
    foreach (['status', 'sort', 'q', 'mine'] as $key) {
        $value = $_POST[$key] ?? '';
        if (is_string($value) && $value !== '') {
            $params[$key] = $value;
        }
    }

    header('Location: IT_Staff_Dashboard.php?' . http_build_query($params));
    exit();
}

function columnExists(mysqli $conn, string $table, string $column): bool
{
    if (
        !in_array($table, ['tickets', 'ticket_comments', 'notifications'], true)
        || !preg_match('/\A[A-Za-z0-9_]+\z/', $column)
    ) {
        return false;
    }

    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");

    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function ensureTicketSchema(mysqli $conn): void
{
    if (!columnExists($conn, 'tickets', 'user_id')) {
        $conn->query('ALTER TABLE tickets ADD COLUMN user_id int(10) unsigned DEFAULT NULL');
    }
    if (!columnExists($conn, 'tickets', 'category')) {
        $conn->query("ALTER TABLE tickets ADD COLUMN category varchar(100) NOT NULL DEFAULT 'other'");
    }
    if (!columnExists($conn, 'tickets', 'dueDate')) {
        $conn->query('ALTER TABLE tickets ADD COLUMN dueDate datetime DEFAULT NULL');
    }
    if (!columnExists($conn, 'tickets', 'assignedTo')) {
        $conn->query('ALTER TABLE tickets ADD COLUMN assignedTo varchar(50) DEFAULT NULL');
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS ticket_comments (
            id INT NOT NULL AUTO_INCREMENT,
            ticket_id INT NOT NULL,
            author_id INT UNSIGNED DEFAULT NULL,
            author_name VARCHAR(100) NOT NULL,
            author_role ENUM('user','staff','system') NOT NULL,
            body TEXT NOT NULL,
            is_resolution TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ticket_comments_ticket_id_idx (ticket_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    if (!columnExists($conn, 'ticket_comments', 'author_id')) {
        $conn->query('ALTER TABLE ticket_comments ADD COLUMN author_id INT UNSIGNED DEFAULT NULL');
    }
    if (!columnExists($conn, 'ticket_comments', 'is_resolution')) {
        $conn->query('ALTER TABLE ticket_comments ADD COLUMN is_resolution TINYINT(1) NOT NULL DEFAULT 0');
    }
    $conn->query(
        "ALTER TABLE ticket_comments
         MODIFY author_role ENUM('user','staff','it','system') NOT NULL"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS notifications (
            id INT NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            ticket_id INT DEFAULT NULL,
            message VARCHAR(255) NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY notifications_user_idx (user_id, is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $conn->query("UPDATE tickets SET status = 'new' WHERE status = 'open'");
    $conn->query("UPDATE tickets SET status = 'resolved' WHERE status = 'closed'");
    $conn->query("UPDATE tickets SET status = 'canceled' WHERE status = 'cancelled'");
    $conn->query("UPDATE tickets SET dueDate = DATE_ADD(created_at, INTERVAL 1 DAY) WHERE priority = 'high' AND dueDate IS NULL");
    $conn->query("UPDATE tickets SET dueDate = DATE_ADD(created_at, INTERVAL 4 DAY) WHERE priority = 'medium' AND dueDate IS NULL");
    $conn->query("UPDATE tickets SET dueDate = DATE_ADD(created_at, INTERVAL 7 DAY) WHERE priority = 'low' AND dueDate IS NULL");
}
