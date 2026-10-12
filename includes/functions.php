<?php
function start_session(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
}

// Escape output to prevent XSS
function e(?string $v): string {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

// TEMPORARY: replace with the authentication member's session variable
function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

// CSRF protection
function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}
function verify_csrf(): void {
    start_session();
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403);
        die('Invalid request.');
    }
}

// One-time messages
function flash(string $msg): void { start_session(); $_SESSION['flash'] = $msg; }
function get_flash(): ?string {
    start_session();
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

function valid_date(string $d): bool {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

// Empty string -> NULL for optional DB columns
function nullable(?string $v): ?string {
    $v = trim($v ?? '');
    return $v === '' ? null : $v;
}