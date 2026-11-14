<?php
// Shared header with the home link, notification menu, and logout.
$portalAudience = $portalAudience ?? '';
$homeHref = $homeHref ?? 'userdashboard.php';
?>
<header class="topbar">
    <a class="brand" href="<?= htmlspecialchars($homeHref, ENT_QUOTES, 'UTF-8') ?>" aria-label="UTA IT Support Portal Home">
        <span class="page-name"><strong>UT <img src="images/UTA_logomark.png" width="24" height="24" alt=""> IT Support Portal<?= $portalAudience !== '' ? ' · ' . htmlspecialchars($portalAudience, ENT_QUOTES, 'UTF-8') : '' ?></strong></span>
    </a>
    <div class="top-actions">
        <button id="hamburger" class="three-lines" type="button" aria-label="Open menu" aria-expanded="false">
            <img src="images/hamburger-menu-icon.png" width="24" height="24" alt="">
        </button>
    </div>
    <div class="dropdown-menu">
        <button id="notificationsButton" class="bell-button" type="button" aria-expanded="false" aria-controls="notificationPanel" aria-label="View notifications">
            <span class="bell-icon">🔔</span>
            <span id="notificationsBadge" class="count-badge is-empty">0</span>
        </button>
        <div id="notificationPanel" class="notification-panel"></div>
        <button id="logoutButton" class="logout-button" type="button">Logout</button>
    </div>
</header>
