<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'user') {
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../userdashboard.php');
    exit();
}

if (!csrfValid('csrf_token', $_POST['csrf_token'] ?? null)) {
    header('Location: ../userdashboard.php?error=session');
    exit();
}

$userId = (int) $_SESSION['user_id'];
$title = trim((string) ($_POST['title'] ?? ''));
$category = trim((string) ($_POST['category'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$priority = trim((string) ($_POST['priority'] ?? ''));

if ($title === '' || $category === '' || $description === '' || $priority === '') {
    header('Location: ../userdashboard.php?error=missing');
    exit();
}

if (
    strlen($title) > 255
    || strlen($description) > 5000
    || !in_array($category, TICKET_CATEGORIES, true)
    || !in_array($priority, TICKET_PRIORITIES, true)
) {
    header('Location: ../userdashboard.php?error=invalid');
    exit();
}

$status = 'new';
$dueDays = match ($priority) {
    'high' => 1,
    'medium' => 4,
    default => 7,
};
$statement = $conn->prepare(
    'INSERT INTO tickets (user_id, title, category, description, priority, status, dueDate)
     VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))'
);
$statement->bind_param('isssssi', $userId, $title, $category, $description, $priority, $status, $dueDays);
$statement->execute();

header('Location: ../userdashboard.php?status=success&ticket_id=' . $conn->insert_id);
exit();
