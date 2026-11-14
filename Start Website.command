#!/bin/bash
# Double-click this file to open the IT ticket site in your browser.
cd "$(dirname "$0")" || exit 1

PORT=8080
URL="http://127.0.0.1:${PORT}"
XAMPP="/Applications/XAMPP/xamppfiles"
MYSQLADMIN="${XAMPP}/bin/mysqladmin"

if ! "${MYSQLADMIN}" --host=127.0.0.1 --user=root --password= ping >/dev/null 2>&1; then
    echo "Starting the ticket database..."
    if ! "${XAMPP}/xampp" startmysql; then
        echo "The database did not start. Install is still using the MySQL database from XAMPP."
        echo "Open XAMPP once and start MySQL, then double-click this file again."
        read -r -p "Press Return to close..."
        exit 1
    fi
fi

if lsof -nP -iTCP:"${PORT}" -sTCP:LISTEN >/dev/null 2>&1; then
    echo "The site is already running at ${URL}"
    open "${URL}"
    exit 0
fi

echo "Opening ${URL}"
echo "Leave this window open while you use the site. Press Control-C to stop it."
(sleep 1 && open "${URL}") &
exec php -S "127.0.0.1:${PORT}" -t "$(pwd)"
