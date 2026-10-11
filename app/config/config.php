<?php
declare(strict_types=1);

$defaults = [
    'app_name' => 'CRMS',
    'debug' => true,
    'app_key' => 'change-this-to-a-long-random-string',
    'base_url' => null,
    'session_name' => 'CRMSSESSID',
    'session_idle_minutes' => 30,
    'password_reset_ttl_minutes' => 60,
    'login_max_attempts' => 5,
    'login_attempt_window_minutes' => 15,
    'reset_max_attempts' => 3,
    'reset_attempt_window_minutes' => 60,
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'carrental',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
];

$localFile = __DIR__ . '/local.php';
if (is_file($localFile)) {
    $local = require $localFile;
    if (is_array($local)) {
        $defaults = array_replace_recursive($defaults, $local);
    }
}

return $defaults;
