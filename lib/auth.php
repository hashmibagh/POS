<?php
// Authentication, authorization and CSRF helpers.

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool {
    $u = current_user();
    return $u !== null && $u['role'] === 'admin';
}

function require_login(): void {
    if (!current_user()) {
        redirect('index.php?page=login');
    }
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        echo 'Forbidden: administrators only.';
        exit;
    }
}

/** Attempt login; returns true on success. Regenerates session id. */
function do_login(string $username, string $password): bool {
    $st = db()->prepare("SELECT * FROM users WHERE username = ? AND active = 1");
    $st->execute([trim($username)]);
    $u = $st->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'       => (int)$u['id'],
            'name'     => $u['name'],
            'username' => $u['username'],
            'role'     => $u['role'],
        ];
        return true;
    }
    return false;
}

function do_logout(): void {
    unset($_SESSION['user'], $_SESSION['cart']);
    session_regenerate_id(true);
}

/** Session-bound CSRF token. */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Die unless the POST carries a valid token. */
function verify_csrf(): void {
    $sent = $_POST['csrf'] ?? '';
    $have = $_SESSION['csrf'] ?? '';
    if ($sent === '' || $have === '' || !hash_equals($have, $sent)) {
        http_response_code(419);
        echo 'Invalid form token. Please go back and try again.';
        exit;
    }
}
