<?php
// Copy this file to db-config.php and fill in your values.
// db-config.php is gitignored, so database secrets never reach the repository.

return [
    // 'sqlite' = local file database (default, zero setup).
    // 'pgsql'  = PostgreSQL, e.g. a Neon database.
    'driver' => 'sqlite',

    // For 'pgsql': paste the connection string from the Neon dashboard, e.g.
    // postgres://user:password@ep-xxxx.ap-southeast-1.aws.neon.tech/neondb?sslmode=require
    // (The DATABASE_URL environment variable overrides this when set.)
    'url' => '',
];
