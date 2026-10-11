<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function config(?string $key = null, mixed $default = null): mixed
{
    $config = $GLOBALS['crms_config'] ?? [];
    if ($key === null) {
        return $config;
    }
    $parts = explode('.', $key);
    $value = $config;
    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function base_url(string $path = ''): string
{
    $base = rtrim((string) ($GLOBALS['crms_base_url'] ?? ''), '/');
    $path = ltrim($path, '/');
    return $path === '' ? $base : $base . '/' . $path;
}

function redirect(string $path, int $status = 302): never
{
    if (!headers_sent()) {
        header('Location: ' . (preg_match('#^https?://#', $path) ? $path : base_url($path)), true, $status);
    }
    exit;
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function is_post(): bool
{
    return request_method() === 'POST';
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return substr($ip, 0, 45);
}

function user_agent(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
}

function flash_set(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flash_take(): array
{
    $items = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($items) ? $items : [];
}

function old(string $key, mixed $default = ''): mixed
{
    $old = $_SESSION['_old'] ?? [];
    return $old[$key] ?? $default;
}

function remember_old(array $data): void
{
    unset($data['password'], $data['password_confirm'], $data['csrf_token'], $data['current_password']);
    $_SESSION['_old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(Csrf::token()) . '">';
}

function current_user(): ?array
{
    return Auth::user();
}

function is_logged_in(): bool
{
    return Auth::check();
}

function requireLogin(): array
{
    return Auth::requireLogin();
}

function requirePermission(string $permission): array
{
    $user = requireLogin();
    if (!Authorization::allows($permission, $user)) {
        Audit::record(
            $user['user_id'] ?? null,
            'authorization.denied',
            'permission',
            $permission,
            'Access denied to protected resource.',
            'denied'
        );
        http_response_code(403);
        $pageTitle = 'Access denied';
        $currentPage = '';
        require BASE_PATH . '/includes/header.php';
        echo '<div class="card border-0 shadow-sm"><div class="card-body p-5 text-center">';
        echo '<h1 class="h4">Access denied</h1>';
        echo '<p class="text-secondary mb-4">You do not have permission to perform this action.</p>';
        echo '<a class="btn btn-primary" href="' . e(base_url('admin/dashboard.php')) . '">Return to dashboard</a>';
        echo '</div></div>';
        require BASE_PATH . '/includes/footer.php';
        exit;
    }
    return $user;
}

function hasPermission(string $permission, ?array $user = null): bool
{
    return Authorization::allows($permission, $user);
}

function pagination(int $total, int $page, int $perPage, array $query = []): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    return [
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'pages' => $pages,
        'offset' => ($page - 1) * $perPage,
        'query' => $query,
    ];
}

function page_url(array $pager, int $page): string
{
    $query = $pager['query'];
    $query['page'] = $page;
    return '?' . http_build_query($query);
}

function setting(string $key, mixed $default = null): mixed
{
    return Settings::get($key, $default);
}
