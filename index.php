<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$loginErrors = [
    'credentials' => 'Login failed. Check your email and password, or contact IT support if you do not have an account.',
    'input' => 'Enter a valid email and password.',
    'session' => 'Your session expired. Reload the page and try again.',
    'account' => 'Your account role is not permitted to access this app.'
];
$loginError = $loginErrors[$_GET['login_error'] ?? ''] ?? '';
unset($_SESSION['login_error']);
$csrfToken = $_SESSION['csrf_token'];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus IT Help Desk Tickets Login Page</title>
    <script>
        try {
            if (localStorage.getItem('theme') === 'dark') {
                document.documentElement.classList.add('dark-mode');
            }
        } catch (error) {}
    </script>
    <link rel="stylesheet" href="css/userdashboard.css?v=2">
    <script src="js/usertheme.js" defer></script>
</head>

<body class="auth-page">
    <main class="login auth-card" id="login-form">
        <p class="eyebrow"><strong>UTA IT Support</strong></p>
        <h1>Sign in</h1>
        <p class="form-note">Students, faculty, and IT staff use this login. You will be sent to the right dashboard.</p>
        <form action="login.php" method="POST" novalidate>
            <?php if ($loginError): ?>
                <p class="banner error" role="alert"><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="Email" required autocomplete="email">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" placeholder="Password" required autocomplete="current-password">
            <button type="button" id="toggle-password" class="secondary-button" aria-controls="password" aria-pressed="false">Show password</button>
            <button type="submit" name="login">Login</button>
        </form>
    </main>

    <?php include __DIR__ . '/includes/theme_toggle.php'; ?>

    <script src="js/script.js"></script>
</body>

</html>
