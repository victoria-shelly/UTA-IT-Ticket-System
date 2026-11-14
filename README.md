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
