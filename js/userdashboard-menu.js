// Opens the header menu, notifications, and logout.
function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

function setNotificationBadge(count) {
    const badge = document.getElementById('notificationsBadge');
    if (!badge) {
        return;
    }
    const total = Number(count) || 0;
    badge.textContent = total > 99 ? '99+' : String(total);
    badge.classList.toggle('is-empty', total === 0);
}

function renderNotifications(items) {
    const panel = document.getElementById('notificationPanel');
    if (!panel) {
        return;
    }
    panel.textContent = '';

    if (!Array.isArray(items) || items.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'form-note';
        empty.textContent = 'No notifications yet.';
        panel.appendChild(empty);
        return;
    }

    items.forEach((item) => {
        const row = document.createElement('div');
        row.className = 'notification-item' + (Number(item.is_read) === 0 ? ' unread' : '');

        const message = item.href ? document.createElement('a') : document.createElement('p');
        if (item.href && String(item.href).startsWith('IT_Staff_Dashboard.php?ticket_id=')) {
            message.href = item.href;
        }
        message.textContent = item.message;

        const time = document.createElement('time');
        time.textContent = item.created_label || '';
        row.append(message, time);
        panel.appendChild(row);
    });
}

function refreshNotificationCount() {
    fetch('php/notifications.php')
        .then((response) => {
            if (!response.ok) {
                throw new Error('HTTP error ' + response.status);
            }
            return response.json();
        })
        .then((data) => setNotificationBadge(data.unread))
        .catch(() => setNotificationBadge(0));
}

function openNotifications() {
    const panel = document.getElementById('notificationPanel');
    if (!panel) {
        return;
    }

    fetch('php/notifications.php')
        .then((response) => {
            if (!response.ok) {
                throw new Error('HTTP error ' + response.status);
            }
            return response.json();
        })
        .then((data) => {
            renderNotifications(data.items);
            panel.classList.add('show');
            if (Number(data.unread) > 0) {
                const body = new FormData();
                body.append('csrf_token', csrfToken());
                fetch('php/notifications.php', { method: 'POST', body })
                    .then(() => setNotificationBadge(0))
                    .catch(() => setNotificationBadge(0));
            }
        })
        .catch(() => {
            panel.textContent = '';
            const error = document.createElement('p');
            error.className = 'ticket-error';
            error.textContent = 'Could not load notifications.';
            panel.appendChild(error);
            panel.classList.add('show');
        });
}

document.addEventListener('DOMContentLoaded', () => {
    const hamburger = document.getElementById('hamburger');
    const dropdownMenu = document.querySelector('.dropdown-menu');
    const logoutButton = document.getElementById('logoutButton');
    const notificationsButton = document.getElementById('notificationsButton');
    const notificationPanel = document.getElementById('notificationPanel');

    if (hamburger && dropdownMenu) {
        hamburger.addEventListener('click', (event) => {
            event.stopPropagation();
            const isOpen = dropdownMenu.classList.toggle('show');
            hamburger.setAttribute('aria-expanded', String(isOpen));
            if (!isOpen && notificationPanel) {
                notificationPanel.classList.remove('show');
            }
        });

        document.addEventListener('click', (event) => {
            if (!dropdownMenu.contains(event.target) && !hamburger.contains(event.target)) {
                dropdownMenu.classList.remove('show');
                hamburger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    if (logoutButton) {
        logoutButton.addEventListener('click', () => {
            window.location.href = 'php/logout.php';
        });
    }

    if (notificationsButton && notificationPanel) {
        notificationsButton.addEventListener('click', (event) => {
            event.stopPropagation();
            const willOpen = !notificationPanel.classList.contains('show');
            notificationPanel.classList.toggle('show', willOpen);
            notificationsButton.setAttribute('aria-expanded', String(willOpen));
            if (willOpen) {
                openNotifications();
            }
        });
        refreshNotificationCount();
    }
});
