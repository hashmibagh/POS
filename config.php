<?php
// Zangarry Mobile Mart POS — application bootstrap and configuration.
declare(strict_types=1);

date_default_timezone_set('Asia/Karachi');

define('APP_ROOT', __DIR__);
define('DATA_DIR', APP_ROOT . '/data');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Hardened session cookie; session_start() runs on every request.
session_name('zmpos');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
