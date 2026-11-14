# UTA-IT-Ticket-System

## Open the site

Double-click `Start Website.command`. It starts the database if needed, starts the
site, and opens http://127.0.0.1:8080 in your browser. Leave that Terminal window
open while you use the site. You do not need to open the XAMPP control panel.
Tickets are still stored in the MySQL database installed with XAMPP.

## Database setup

Create a MySQL/MariaDB database named `users_staff_db`, then import `users_staff.sql`.
The SQL file creates the user and ticket tables for a new database. If you already
have a `tickets` table, run `migrations/001_add_ticket_user_id.sql` once to add
ticket ownership. Existing tickets remain unassigned; new tickets are associated
with the logged-in user.

The database connection settings are in `config.php`. Set them to match your local
database server before running the PHP application.

Students and faculty use the user dashboard to create tickets, view them by status
(New, In Progress, Resolved, and Canceled), comment, and cancel an active ticket.
IT staff use the same visual layout to search, sort by submission time or priority,
assign or unassign tickets, comment, resolve with a note, and reopen a resolved ticket.
Both dashboards share light and dark mode, notifications, and logout.

Staff accounts are redirected to `IT_Staff_Dashboard.php` after login. Due dates are
1 day for high priority, 4 days for medium, and 7 days for low.

## Files

| File | What it does |
| --- | --- |
| `Start Website.command` | Double-click launcher. Starts MySQL if needed, starts the PHP site on port 8080, and opens the browser. |
| `index.php` | Login page. Shows the email and password form and any login error. |
| `login.php` | Checks the email and password, then sends students and faculty to the user dashboard and IT staff to the staff dashboard. |
| `userdashboard.php` | Student and faculty page for creating a ticket and viewing tickets by New, In Progress, Resolved, and Canceled. |
| `IT_Staff_Dashboard.php` | IT staff page for searching, sorting, assigning, commenting, resolving, and reopening tickets. |
| `config.php` | Connects to the `users_staff_db` MySQL database and makes sure the ticket tables are ready. |
| `users_staff.sql` | Creates the database tables for accounts and tickets, and inserts the test accounts. |
| `migrations/001_add_ticket_user_id.sql` | One-time update for an older `tickets` table. Adds the column that records which user created each ticket. |
| `includes/topbar.php` | Shared header with the UTA title, menu button, notifications, and logout. |
| `includes/theme_toggle.php` | Shared light/dark mode switch shown on the login page and both dashboards. |
| `css/userdashboard.css` | Shared layout and colors for the login page, user dashboard, and staff dashboard, including dark mode. |
| `js/script.js` | Login page behavior: show or hide the password, and block submit when email or password is empty. |
| `js/userdashboard.js` | Loads a user's tickets, comments, and cancel action on the user dashboard. |
| `js/userdashboard-menu.js` | Opens the menu, loads notifications, and logs the user out. |
| `js/usertheme.js` | Saves the light or dark choice in the browser and applies it on the next visit. |
| `php/submit-ticket.php` | Saves a new ticket with its category, priority, and due date. |
| `php/get-tickets.php` | Returns the signed-in user's tickets for the selected status. |
| `php/get-ticket-counts.php` | Returns how many of the user's tickets are in each status. |
| `php/add-comment.php` | Saves a comment from the ticket owner on a new or in-progress ticket. |
| `php/cancel-ticket.php` | Cancels the user's active ticket and notifies the assigned staff member. |
| `php/notifications.php` | Lists notifications for the signed-in user or staff member and marks them as read. |
| `php/logout.php` | Ends the session and returns to the login page. |
| `php/ticket_lib.php` | Shared ticket rules: due dates, status groups, comments, notifications, staff assignment, resolve, and reopen. |
| `images/UTA_logomark.png` | UTA mark shown in the header. |
| `images/hamburger-menu-icon.png` | Menu icon in the header. |
