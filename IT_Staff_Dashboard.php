<?php
session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'staff') {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/config.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['staff_dashboard_csrf'])) {
    $_SESSION['staff_dashboard_csrf'] = bin2hex(random_bytes(32));
}

function requestValue(string $key): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

$actionError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actionError = handleStaffTicketAction($conn);
}

$status = requestValue('status');
if (!in_array($status, ['new', 'in_progress', 'resolved', 'canceled'], true)) {
    $status = '';
}
$sort = requestValue('sort') === 'priority' ? 'priority' : 'submitted';
$mine = requestValue('mine') === '1' ? '1' : '';
$q = requestValue('q');
if (strlen($q) > 100) {
    $q = substr($q, 0, 100);
}

$selectedTicketId = 0;
$ticketValue = requestValue('ticket_id');
if (ctype_digit($ticketValue)) {
    $selectedTicketId = (int) $ticketValue;
}

$filters = [
    'status' => $status,
    'sort' => $sort,
    'q' => $q,
    'mine' => $mine,
];

function staffHref(array $filters, array $overrides = []): string
{
    $params = array_merge($filters, $overrides);
    $params = array_filter(
        $params,
        static fn($value): bool => $value !== '' && $value !== null
    );

    $query = http_build_query($params);
    return 'IT_Staff_Dashboard.php' . ($query !== '' ? '?' . $query : '');
}

function hiddenFilters(array $filters): void
{
    foreach (['status', 'sort', 'q', 'mine'] as $key) {
        if (($filters[$key] ?? '') === '') {
            continue;
        }
        echo '<input type="hidden" name="' . e($key) . '" value="' . e((string) $filters[$key]) . '">';
    }
}

$staffId = (int) ($_SESSION['user_id'] ?? 0);
$displayName = currentUserName($conn);
$notices = [
    'assigned' => 'Assignment updated.',
    'resolved' => 'Ticket resolved and the user was notified.',
    'reopened' => 'Ticket reopened.',
    'comment' => 'Comment sent.',
];
$notice = $notices[requestValue('notice')] ?? '';

$counts = ['new' => 0, 'in_progress' => 0, 'resolved' => 0, 'canceled' => 0];
$countResult = $conn->query('SELECT status, COUNT(*) AS total FROM tickets GROUP BY status');
while ($row = $countResult->fetch_assoc()) {
    $group = statusGroup((string) $row['status']);
    if (isset($counts[$group])) {
        $counts[$group] += (int) $row['total'];
    }
}

$tickets = [];
$showList = $status !== '' || $q !== '' || $mine === '1';
if ($showList) {
    $where = [];
    $types = '';
    $params = [];

    $statuses = $status !== '' ? statusesForGroup($status) : [];
    if ($statuses !== []) {
        $where[] = 't.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
        $types .= str_repeat('s', count($statuses));
        foreach ($statuses as $groupStatus) {
            $params[] = $groupStatus;
        }
    }
    if ($mine === '1') {
        $where[] = 't.assignedTo = ?';
        $types .= 's';
        $params[] = (string) $staffId;
    }
    if ($q !== '') {
        $like = '%' . addcslashes($q, '\\%_') . '%';
        $where[] = '(t.title LIKE ? OR t.description LIKE ? OR u.name LIKE ? OR CAST(t.id AS CHAR) = ?)';
        $types .= 'ssss';
        $idText = ctype_digit($q) ? $q : '';
        array_push($params, $like, $like, $like, $idText);
    }

    $order = $sort === 'priority'
        ? "ORDER BY FIELD(t.priority, 'high', 'medium', 'low'), t.created_at DESC"
        : 'ORDER BY t.created_at DESC';

    $sql = "SELECT t.id, t.title, t.category, t.priority, t.status, t.dueDate, t.created_at,
                   u.name AS created_by, s.name AS assignee_name
            FROM tickets t
            LEFT JOIN users_staff u ON u.id = t.user_id
            LEFT JOIN users_staff s ON s.id = CAST(t.assignedTo AS UNSIGNED) AND s.role = 'staff'
            " . ($where !== [] ? 'WHERE ' . implode(' AND ', $where) : '') . "
            $order";
    $statement = $conn->prepare($sql);
    if ($types !== '') {
        $statement->bind_param($types, ...$params);
    }
    $statement->execute();
    $tickets = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
}

$selectedTicket = null;
$comments = [];
if ($selectedTicketId > 0) {
    $detailStatement = $conn->prepare(
        'SELECT t.id, t.user_id, t.title, t.category, t.description, t.priority, t.status,
                t.dueDate, t.assignedTo, t.created_at, u.name AS created_by
         FROM tickets t
         LEFT JOIN users_staff u ON u.id = t.user_id
         WHERE t.id = ?'
    );
    $detailStatement->bind_param('i', $selectedTicketId);
    $detailStatement->execute();
    $selectedTicket = $detailStatement->get_result()->fetch_assoc() ?: null;
    if ($selectedTicket) {
        $comments = commentsForTicket($conn, $selectedTicketId);
    }
}

$staffList = staffMembers($conn);
$statusLabels = [
    'new' => 'New',
    'in_progress' => 'In Progress',
    'resolved' => 'Resolved',
    'canceled' => 'Canceled',
];
$listHeading = 'Select a status to view tickets';
if ($status !== '') {
    $listHeading = $statusLabels[$status] . ' Tickets';
} elseif ($q !== '') {
    $listHeading = 'Search Results';
} elseif ($mine === '1') {
    $listHeading = 'Tickets Assigned to You';
}

$selectedGroup = $selectedTicket ? statusGroup((string) $selectedTicket['status']) : '';
$currentAssignee = $selectedTicket ? (string) ($selectedTicket['assignedTo'] ?? '') : '';
$assigneeLabel = $selectedTicket ? assigneeName($conn, $currentAssignee) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e($_SESSION['csrf_token']) ?>">
    <title>IT Staff Dashboard</title>
    <script>
        try {
            if (localStorage.getItem('theme') === 'dark') {
                document.documentElement.classList.add('dark-mode');
            }
        } catch (error) {}
    </script>
    <link rel="stylesheet" href="css/userdashboard.css?v=2">
    <script src="js/userdashboard-menu.js" defer></script>
    <script src="js/usertheme.js" defer></script>
</head>
<body>
    <?php
    $portalAudience = 'IT Staff';
    $homeHref = 'IT_Staff_Dashboard.php';
    include __DIR__ . '/includes/topbar.php';
    ?>

    <main class="page">
        <?php if ($notice !== ''): ?>
            <p class="banner success" role="status"><?= e($notice) ?></p>
        <?php endif; ?>
        <?php if ($actionError !== ''): ?>
            <p class="banner error" role="alert"><?= e($actionError) ?></p>
        <?php endif; ?>

        <section class="welcome-row">
            <div>
                <p class="eyebrow"><strong>IT Staff Dashboard</strong></p>
                <h1>Welcome to IT Support</h1>
                <p class="signed-in">Signed in as <?= e($displayName) ?></p>
            </div>
        </section>

        <form class="toolbar" method="get" action="IT_Staff_Dashboard.php">
            <?php if ($status !== ''): ?>
                <input type="hidden" name="status" value="<?= e($status) ?>">
            <?php endif; ?>
            <?php if ($selectedTicketId > 0): ?>
                <input type="hidden" name="ticket_id" value="<?= $selectedTicketId ?>">
            <?php endif; ?>
            <div class="field">
                <label for="ticketSearch">Search</label>
                <input id="ticketSearch" type="search" name="q" value="<?= e($q) ?>" maxlength="100" placeholder="Title, description, name, or ticket number">
            </div>
            <div class="field">
                <label for="ticketSort">Sort</label>
                <select id="ticketSort" name="sort">
                    <option value="submitted" <?= $sort === 'submitted' ? 'selected' : '' ?>>Date submitted</option>
                    <option value="priority" <?= $sort === 'priority' ? 'selected' : '' ?>>Priority</option>
                </select>
            </div>
            <label class="check-field" for="assignedToMe">
                <input id="assignedToMe" type="checkbox" name="mine" value="1" <?= $mine === '1' ? 'checked' : '' ?>>
                Assigned to me
            </label>
            <button type="submit">Apply</button>
            <?php if ($q !== '' || $mine === '1' || $sort === 'priority'): ?>
                <a class="text-button" href="<?= e(staffHref($filters, ['q' => '', 'mine' => '', 'sort' => 'submitted', 'ticket_id' => $selectedTicketId > 0 ? (string) $selectedTicketId : ''])) ?>">Clear</a>
            <?php endif; ?>
        </form>

        <section class="summary-grid" aria-label="Ticket Summary">
            <?php
            $notes = [
                'new' => 'Waiting for IT',
                'in_progress' => 'Assigned to IT',
                'resolved' => 'Finished by IT',
                'canceled' => 'Canceled by user',
            ];
            foreach ($statusLabels as $key => $label):
                $cardFilters = $filters;
                $cardFilters['status'] = $key;
                unset($cardFilters['ticket_id']);
            ?>
                <a class="summary-card<?= $status === $key ? ' active' : '' ?>" href="<?= e(staffHref([], $cardFilters)) ?>">
                    <span class="summary-label"><?= e($label) ?></span>
                    <strong class="summary-number"><?= (int) $counts[$key] ?></strong>
                    <span class="summary-note"><?= e($notes[$key]) ?></span>
                </a>
            <?php endforeach; ?>
        </section>

        <section class="ticket-list-section">
            <h2 id="ticketListHeading"><?= e($listHeading) ?></h2>
            <div class="ticket-background" id="ticketList">
                <?php if (!$showList): ?>
                    <p class="form-note">Choose New, In Progress, Resolved, or Canceled. You can also search or show tickets assigned to you.</p>
                <?php elseif ($tickets === []): ?>
                    <p class="form-note">No tickets here.</p>
                <?php else: ?>
                    <?php foreach ($tickets as $ticket): ?>
                        <?php $ticketGroup = statusGroup((string) $ticket['status']); ?>
                        <a class="ticket-link" href="<?= e(staffHref($filters, ['ticket_id' => (string) $ticket['id']])) ?>">
                            <article class="ticket-item<?= (int) $ticket['id'] === $selectedTicketId ? ' is-selected' : '' ?>">
                                <strong>#<?= (int) $ticket['id'] ?> <?= e($ticket['title']) ?></strong>
                                <span class="ticket-meta">
                                    <?= e($ticket['created_by'] ?? 'Unknown user') ?>
                                    · <?= e(priorityLabel((string) $ticket['priority'])) ?> priority
                                    · <?= e(statusLabel($ticketGroup)) ?>
                                    <?php if (!empty($ticket['assignee_name'])): ?>
                                        · <?= e($ticket['assignee_name']) ?>
                                    <?php endif; ?>
                                </span>
                                <span class="ticket-meta">Submitted <?= e(formatWhen($ticket['created_at'])) ?> · Due <?= e(formatWhen($ticket['dueDate'])) ?></span>
                            </article>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($selectedTicketId > 0 && !$selectedTicket): ?>
            <p class="banner error" role="alert">Ticket not found.</p>
        <?php endif; ?>

        <?php if ($selectedTicket): ?>
            <section class="ticket-detail" id="ticketDetail">
                <h2>#<?= (int) $selectedTicket['id'] ?> <?= e($selectedTicket['title']) ?></h2>
                <p class="ticket-meta">
                    Created by <?= e($selectedTicket['created_by'] ?? 'Unknown user') ?>
                    · <?= e(ucfirst((string) $selectedTicket['category'])) ?>
                    · <?= e(priorityLabel((string) $selectedTicket['priority'])) ?> priority
                    · <?= e(statusLabel($selectedGroup)) ?>
                </p>
                <p class="ticket-meta">
                    Submitted <?= e(formatWhen($selectedTicket['created_at'])) ?>
                    · Due <?= e(formatWhen($selectedTicket['dueDate'])) ?>
                    · Assigned to <?= $assigneeLabel !== '' ? e($assigneeLabel) : 'nobody' ?>
                </p>
                <p class="ticket-description"><?= nl2br(e($selectedTicket['description'])) ?></p>

                <?php if ($selectedGroup === 'canceled'): ?>
                    <p class="form-note">This ticket was canceled by the user. Canceled tickets stay canceled.</p>
                <?php endif; ?>

                <?php if ($selectedGroup !== 'canceled'): ?>
                    <form method="post" action="<?= e(staffHref($filters, ['ticket_id' => (string) $selectedTicket['id']])) ?>" class="detail-form">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['staff_dashboard_csrf']) ?>">
                        <input type="hidden" name="ticket_id" value="<?= (int) $selectedTicket['id'] ?>">
                        <input type="hidden" name="action" value="assign">
                        <?php hiddenFilters($filters); ?>
                        <label for="staffId">Assigned to</label>
                        <select id="staffId" name="staff_id">
                            <?php if ($currentAssignee !== ''): ?>
                                <option value="">Unassign</option>
                            <?php else: ?>
                                <option value="">Select IT staff</option>
                            <?php endif; ?>
                            <?php foreach ($staffList as $member): ?>
                                <?php if ((string) $member['id'] === $currentAssignee) { continue; } ?>
                                <option value="<?= (int) $member['id'] ?>"><?= e($member['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="inline-button">Save assignment</button>
                    </form>
                <?php endif; ?>

                <h3 class="section-label">Comments</h3>
                <div class="comment-list">
                    <?php if ($comments === []): ?>
                        <p class="form-note">No comments yet.</p>
                    <?php endif; ?>
                    <?php foreach ($comments as $comment): ?>
                        <?php
                        $commentClass = 'comment';
                        if (($comment['author_role'] ?? '') === 'system') {
                            $commentClass .= ' system';
                        }
                        if ((int) ($comment['is_resolution'] ?? 0) === 1) {
                            $commentClass .= ' resolution';
                        }
                        $roleLabel = (int) ($comment['is_resolution'] ?? 0) === 1
                            ? 'Resolution note'
                            : match ($comment['author_role'] ?? '') {
                                'staff' => 'IT Staff',
                                'system' => 'System',
                                'user' => 'User',
                                default => ucfirst((string) ($comment['author_role'] ?? 'User')),
                            };
                        ?>
                        <article class="<?= e($commentClass) ?>">
                            <div class="comment-head">
                                <strong><?= e($comment['author_name']) ?> · <?= e($roleLabel) ?></strong>
                                <time><?= e(formatWhen($comment['created_at'])) ?></time>
                            </div>
                            <p><?= nl2br(e($comment['body'])) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if (in_array($selectedGroup, ['new', 'in_progress'], true)): ?>
                    <form method="post" action="<?= e(staffHref($filters, ['ticket_id' => (string) $selectedTicket['id']])) ?>" class="detail-form">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['staff_dashboard_csrf']) ?>">
                        <input type="hidden" name="ticket_id" value="<?= (int) $selectedTicket['id'] ?>">
                        <input type="hidden" name="action" value="comment">
                        <?php hiddenFilters($filters); ?>
                        <label for="staffComment">Comment to the user</label>
                        <textarea id="staffComment" name="comment" rows="3" maxlength="2000" placeholder="Ask for more detail or share an update"></textarea>
                        <button type="submit" class="inline-button">Submit comment</button>
                    </form>

                    <form method="post" action="<?= e(staffHref($filters, ['ticket_id' => (string) $selectedTicket['id']])) ?>" class="detail-form">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['staff_dashboard_csrf']) ?>">
                        <input type="hidden" name="ticket_id" value="<?= (int) $selectedTicket['id'] ?>">
                        <input type="hidden" name="action" value="resolve">
                        <?php hiddenFilters($filters); ?>
                        <label for="resolutionNote">Resolution note</label>
                        <textarea id="resolutionNote" name="resolution" rows="3" maxlength="2000" placeholder="Describe how the issue was resolved" required></textarea>
                        <button type="submit" class="inline-button">Resolve ticket</button>
                    </form>
                <?php endif; ?>

                <?php if ($selectedGroup === 'resolved'): ?>
                    <form method="post" action="<?= e(staffHref($filters, ['ticket_id' => (string) $selectedTicket['id']])) ?>" class="detail-form">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['staff_dashboard_csrf']) ?>">
                        <input type="hidden" name="ticket_id" value="<?= (int) $selectedTicket['id'] ?>">
                        <input type="hidden" name="action" value="reopen">
                        <?php hiddenFilters($filters); ?>
                        <button type="submit" class="inline-button">Reopen ticket</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php include __DIR__ . '/includes/theme_toggle.php'; ?>
    </main>
</body>
</html>
