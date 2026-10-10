# Zangarry POS — Mobile Mart Point of Sale

A self-contained retail POS web application: login, dashboard, products,
billing (new sale) with printable invoices, sales history, stock management,
reports, staff accounts and settings.

## Stack

- PHP 8.1+ (no frameworks, no Composer, no build step)
- SQLite via PDO (database file is created automatically on first run)
- Vanilla CSS + JavaScript (mobile-first)

## Run it

Drop this folder into any PHP host (e.g. KSWEB on Android, XAMPP) and open
it in a browser, e.g. `http://localhost:8080/zangarry-pos-web`.

On first load the app creates `data/pos.db` and seeds a demo catalogue.

## Default login

- Username: `admin`
- Password: `admin123`

Change the password after first login (Users page). A reminder banner is
shown while the default password is in use.
