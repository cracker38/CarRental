<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

$config = require BASE_PATH . '/app/config/config.php';
$GLOBALS['crms_config'] = $config;

require BASE_PATH . '/app/lib/Database.php';
require BASE_PATH . '/app/lib/Csrf.php';
require BASE_PATH . '/app/lib/Audit.php';
require BASE_PATH . '/app/lib/Validator.php';
require BASE_PATH . '/app/lib/Settings.php';
require BASE_PATH . '/app/lib/Authorization.php';
require BASE_PATH . '/app/lib/Auth.php';
require BASE_PATH . '/app/lib/PasswordReset.php';
require BASE_PATH . '/app/lib/RoleService.php';
require BASE_PATH . '/app/lib/UserService.php';
require BASE_PATH . '/app/lib/helpers.php';

$docRoot = rtrim(str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$baseFs = str_replace('\\', '/', BASE_PATH);
$webPath = '/';
if ($docRoot !== '' && str_starts_with(strtolower($baseFs), strtolower($docRoot))) {
    $webPath = substr($baseFs, strlen($docRoot)) ?: '/';
} elseif (PHP_SAPI === 'cli') {
    $webPath = '/carrental/CarRental';
}
$webPath = '/' . trim(str_replace('\\', '/', $webPath), '/');
if ($webPath !== '/') {
    $webPath = rtrim($webPath, '/');
}
$GLOBALS['crms_cookie_path'] = $webPath === '/' ? '/' : $webPath . '/';

if (!empty($config['base_url'])) {
    $GLOBALS['crms_base_url'] = rtrim((string) $config['base_url'], '/');
} elseif (PHP_SAPI === 'cli') {
    $GLOBALS['crms_base_url'] = 'http://localhost' . ($webPath === '/' ? '' : $webPath);
} else {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $GLOBALS['crms_base_url'] = $scheme . '://' . $host . ($webPath === '/' ? '' : $webPath);
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('X-XSS-Protection: 1; mode=block');
}

try {
    Database::connection();
    Settings::ensureDefaults();
    $timezone = (string) Settings::get('timezone', 'Africa/Kigali');
    try {
        new DateTimeZone($timezone);
        date_default_timezone_set($timezone);
    } catch (Throwable $e) {
        date_default_timezone_set('Africa/Kigali');
    }
} catch (Throwable $e) {
    if (!empty($config['debug'])) {
        throw $e;
    }
    http_response_code(500);
    echo 'The application is temporarily unavailable.';
    exit;
}

Auth::start();
