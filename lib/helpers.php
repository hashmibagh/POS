<?php
// Small output/formatting helpers used across views.

/** Escape for HTML output. */
function e($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Format a number as PKR. */
function money($n): string {
    return 'Rs ' . number_format((float)$n, 0);
}

/** Redirect and stop. Must be called before any output. */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/** One-time flash message shown on the next page load. */
function flash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}

/** Pop all queued flash messages. */
function flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** Today's date in Y-m-d (Asia/Karachi). */
function today(): string {
    return date('Y-m-d');
}

/** Validate an integer-ish input; returns null when invalid. */
function as_int($v): ?int {
    if ($v === null || $v === '') return null;
    if (filter_var($v, FILTER_VALIDATE_INT) === false) return null;
    return (int)$v;
}

/** Validate a numeric (float) input; returns null when invalid. */
function as_float($v): ?float {
    if ($v === null || $v === '') return null;
    if (!is_numeric($v)) return null;
    return (float)$v;
}
