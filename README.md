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

| File | Purpose |
| --- | --- |
| `Start Website.command` | Starts the database if needed and opens the site in the browser |
| `index.php` | Login page for students, faculty, and IT staff |
| `login.php` | Checks the email and password and opens the matching dashboard |
| `userdashboard.php` | Page where students and faculty create, view, comment on, and cancel tickets |
| `IT_Staff_Dashboard.php` | Page where IT staff search, sort, assign, comment on, resolve, and reopen tickets |
| `config.php` | Connects to the ticket database and prepares the ticket tables |
| `users_staff.sql` | Creates the account and ticket tables and inserts the test accounts |
| `css/userdashboard.css` | Shared layout, colors, and light/dark mode for every page |
| `includes/topbar.php` | Shared header with the home link, notification menu, and logout |
| `includes/theme_toggle.php` | Shared light and dark mode switch |
| `js/script.js` | Checks the login form and shows or hides the password |
| `js/usertheme.js` | Switches between light and dark mode and remembers the choice |
| `js/userdashboard-menu.js` | Opens the header menu, notifications, and logout |
| `js/userdashboard.js` | Loads a user's tickets and handles comments and cancellation |
| `php/submit-ticket.php` | Saves a new ticket and sets its due date from the priority |
| `php/get-tickets.php` | Returns the signed-in user's tickets for one status |
| `php/get-ticket-counts.php` | Counts the signed-in user's tickets in each status |
| `php/cancel-ticket.php` | Cancels one of the signed-in user's open tickets |
| `php/add-comment.php` | Saves a comment written by the ticket owner |
| `php/notifications.php` | Shows ticket updates and marks them as read |
| `php/logout.php` | Ends the session and returns to the login page |
| `php/ticket_lib.php` | Shared rules for status, due dates, comments, assignments, and notifications |
| `migrations/001_add_ticket_user_id.sql` | Adds the ticket owner column on an older tickets table |
| `images/UTA_logomark.png` | UTA mark shown in the header |
| `images/hamburger-menu-icon.png` | Icon for the header menu |
