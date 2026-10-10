<?php
// Zangarry Mobile Mart POS — front controller / router.
require __DIR__ . '/config.php';
require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/auth.php';

$routes = [
    'login'     => ['file' => 'login.php',     'title' => 'Sign in',        'bare' => true, 'guest' => true],
    'logout'    => ['file' => 'logout.php',    'title' => 'Sign out',       'bare' => true],
    'dashboard' => ['file' => 'dashboard.php', 'title' => 'Dashboard'],
    'sale'      => ['file' => 'sale.php',      'title' => 'New Sale'],
    'sales'     => ['file' => 'sales.php',     'title' => 'Sales History'],
    'invoice'   => ['file' => 'invoice.php',   'title' => 'Invoice'],
    'products'  => ['file' => 'products.php',  'title' => 'Products'],
    'stock'     => ['file' => 'stock.php',     'title' => 'Stock'],
    'reports'   => ['file' => 'reports.php',   'title' => 'Reports'],
    'users'     => ['file' => 'users.php',     'title' => 'Users',    'admin' => true],
    'settings'  => ['file' => 'settings.php',  'title' => 'Settings', 'admin' => true],
];

$page = $_GET['page'] ?? 'dashboard';
if (!isset($routes[$page])) {
    http_response_code(404);
    $page = 'dashboard';
}
$route = $routes[$page];

if (!empty($route['admin'])) {
    require_admin();
} elseif (empty($route['guest']) && $page !== 'logout') {
    require_login();
}
if (!empty($route['guest']) && current_user() && $page === 'login') {
    redirect('index.php?page=dashboard');
}

// Run the view first (it handles POSTs and may redirect), capture its HTML,
// then render the shared layout around it.
$shopName  = setting('shop_name') ?: 'Zangarry Mobile Mart';
$shopTag   = setting('tagline') ?: 'Smart retail. Clear accounts.';
ob_start();
include __DIR__ . '/views/' . $route['file'];
$content = ob_get_clean();

$pageTitle = $route['title'];
$bare      = !empty($route['bare']);
include __DIR__ . '/views/layout.php';
