<?php
// Page where students and faculty create, view, comment on, and cancel their tickets.
session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'user') {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/config.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$displayName = currentUserName($conn);
$createdTicketId = 0;
$createdTicketValue = $_GET['ticket_id'] ?? '';
if (is_string($createdTicketValue) && ctype_digit($createdTicketValue)) {
    $createdTicketId = (int) $createdTicketValue;
}
$flashSuccess = ($_GET['status'] ?? '') === 'success'
    ? ($createdTicketId > 0
        ? 'Ticket #' . $createdTicketId . ' submitted successfully.'
        : 'Ticket submitted successfully.')
    : '';
$flashErrors = [
    'missing' => 'Fill in the title, category, description, and priority before submitting.',
    'invalid' => 'Choose a valid category and priority. Keep the title under 255 characters.',
    'session' => 'Your session expired. Reload the page and try again.',
];
$flashError = $flashErrors[$_GET['error'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e($_SESSION['csrf_token']) ?>">
    <title>UTA IT Support Portal</title>
    <script>
        try {
            if (localStorage.getItem('theme') === 'dark') {
                document.documentElement.classList.add('dark-mode');
            }
        } catch (error) {}
    </script>
    <link rel="stylesheet" href="css/userdashboard.css?v=2">
    <script src="js/userdashboard.js" defer></script>
    <script src="js/userdashboard-menu.js" defer></script>
    <script src="js/usertheme.js" defer></script>
</head>
<body>
    <?php
    $portalAudience = 'Student & Faculty Services';
    $homeHref = 'userdashboard.php';
    include __DIR__ . '/includes/topbar.php';
    ?>

    <main class="page">
        <?php if ($flashSuccess !== ''): ?>
            <p class="banner success" role="status"><?= e($flashSuccess) ?></p>
        <?php endif; ?>
        <?php if ($flashError !== ''): ?>
            <p class="banner error" role="alert"><?= e($flashError) ?></p>
        <?php endif; ?>

        <section class="welcome-row">
            <div>
                <p class="eyebrow"><strong>User Dashboard</strong></p>
                <h1>Welcome to IT Support</h1>
                <p class="signed-in">Signed in as <?= e($displayName) ?></p>
            </div>
            <div class="dropdown">
                <button class="ticket-trigger" type="button" onclick="ticketForm()">
                    Create Ticket <span class="arrow" id="dropDownArrow">▼</span>
                </button>
                <div id="ticketDropDown" class="ticket-form">
                    <h3>Submit a Support Ticket</h3>
                    <form id="supportTicketForm" action="php/submit-ticket.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                        <div class="form-group">
                            <label for="ticketTitle">Title</label>
                            <input type="text" id="ticketTitle" name="title" maxlength="255" required>
                        </div>
                        <div class="category-group">
                            <label for="ticketCategory">Category</label>
                            <select id="ticketCategory" name="category" required>
                                <option value="">Select a category</option>
                                <option value="hardware">Hardware</option>
                                <option value="software">Software</option>
                                <option value="network">Network</option>
                                <option value="account">Account</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ticketDescription">Description</label>
                            <textarea id="ticketDescription" name="description" rows="4" maxlength="5000" placeholder="Describe your issue in detail" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="ticketPriority">Priority</label>
                            <select id="ticketPriority" name="priority" required>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <p class="form-note">The due date is set from the priority: high is 1 day, medium is 4 days, and low is 7 days.</p>
                        <div class="form-actions">
                            <button type="submit" class="submit-button">Submit Ticket</button>
                            <button type="button" class="cancel-button" onclick="ticketForm()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <section class="summary-grid" aria-label="Ticket Summary">
            <article class="summary-card" data-status="new" tabindex="0">
                <span class="summary-label">New</span>
                <strong id="newCount" class="summary-number">0</strong>
                <span class="summary-note">Waiting for IT</span>
            </article>
            <article class="summary-card" data-status="in_progress" tabindex="0">
                <span class="summary-label">In Progress</span>
                <strong id="inProgressCount" class="summary-number">0</strong>
                <span class="summary-note">Assigned to IT</span>
            </article>
            <article class="summary-card" data-status="resolved" tabindex="0">
                <span class="summary-label">Resolved</span>
                <strong id="resolvedCount" class="summary-number">0</strong>
                <span class="summary-note">Finished by IT</span>
            </article>
            <article class="summary-card" data-status="canceled" tabindex="0">
                <span class="summary-label">Canceled</span>
                <strong id="canceledCount" class="summary-number">0</strong>
                <span class="summary-note">Canceled by you</span>
            </article>
        </section>

        <section class="ticket-list-section">
            <h2 id="ticketListHeading">Select a status to view your tickets</h2>
            <div class="ticket-background" id="ticketList"></div>
        </section>

        <?php include __DIR__ . '/includes/theme_toggle.php'; ?>
    </main>
</body>
</html>
