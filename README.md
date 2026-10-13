# Zangarry POS — Mobile Mart Point of Sale

A self-contained retail POS web application: login, dashboard, products,
billing (new sale) with printable invoices, sales history, stock management,
reports, staff accounts and settings.

## Stack

- PHP 8.1+ (no frameworks, no Composer, no build step)
- SQLite via PDO by default (database file is created automatically on first run),
  or PostgreSQL via PDO — e.g. a Neon database (see below)
- Vanilla CSS + JavaScript (mobile-first)

## Run it

Drop this folder into any PHP host (e.g. KSWEB on Android, XAMPP) and open
it in a browser, e.g. `http://localhost:8080/zangarry-pos-web`.

On first load the app creates `data/pos.db` and seeds a demo catalogue.

## Using a hosted Postgres database (Neon or Supabase)

1. Create a Postgres database:
   - **Neon**: create a project at [Neon](https://neon.tech) and copy its
     connection string from the dashboard (it looks like
     `postgres://user:password@ep-xxxx.aws.neon.tech/neondb?sslmode=require`).
     Use the non-pooled connection string.
   - **Supabase**: open your project → Project Settings → Database →
     Connection string → **Direct connection**, URI format. It looks like
     `postgresql://postgres.[ref]:[YOUR-PASSWORD]@db.[ref].supabase.co:5432/postgres`.
     Replace `[YOUR-PASSWORD]` with your database password (if you forgot it,
     reset it on the same page). If your password contains special characters
     such as `@`, `/` or `:`, percent-encode them (e.g. `@` → `%40`).
2. Copy `db-config.example.php` to `db-config.php` (this file is gitignored,
   so your password never lands in the repository).
3. In `db-config.php` set `'driver' => 'pgsql'` and paste the connection
   string into `'url'`.
4. Make sure the PHP `pdo_pgsql` extension is enabled on your host
   (KSWEB: PHP settings → enable `pdo_pgsql`, then restart the server).
5. Open the app — tables and the demo catalogue are created automatically
   on first load. (Alternatively, run `schema-pg.sql` once in the database's
   SQL editor.)

To switch back to the local file database, set `'driver' => 'sqlite'`
(or delete `db-config.php`).

## Host it online, free, no credit card (InfinityFree)

If you want free hosting without adding a payment card, use InfinityFree
(a free PHP host) with the app's built-in SQLite database — no Supabase
or Docker setup needed:

1. Sign up at [infinityfree.com](https://infinityfree.com) (free, no card)
   and create a hosting account with a free subdomain.
2. Open the **File Manager**, go into the `htdocs` folder.
3. Upload `zangarry-pos-web-deploy.zip` and extract it there, so that
   `index.php` sits directly inside `htdocs`.
4. Open your site address (e.g. `https://yoursite.infinityfreeapp.com`).
   The database file is created automatically on first load.
5. Log in with `admin` / `admin123` and change the password.

Notes: on this setup the shop data lives in `data/pos.db` on the host —
download a backup regularly from Settings → Download database backup.
If the site shows PHP errors, set the PHP version to 8.1 or higher in the
control panel.

## Host it online (Render, free)

Netlify can't run PHP (it's for static sites), so use a PHP host instead.
This repo ships a `Dockerfile` + `render.yaml` for Render's free tier:

1. Sign up at [render.com](https://render.com) and click **New → Web Service**.
2. Connect your GitHub account and pick the `POS` repository.
3. Render detects `render.yaml` — keep the free plan and click **Deploy**.
4. Once deployed, open the service → **Environment** → add `DATABASE_URL`
   with your Neon connection string (non-pooled). Redeploy if asked.

Your shop gets an `https://zangarry-pos.onrender.com` style address.
First open the app and log in with `admin` / `admin123`, then change the
password. Note: on the free plan the site sleeps after ~15 minutes idle,
so the first visit of the day takes about a minute to wake up.

## Default login

- Username: `admin`
- Password: `admin123`

Change the password after first login (Users page). A reminder banner is
shown while the default password is in use.
