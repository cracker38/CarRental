<?php
// TEMPORARY local connection for Eugenie's testing.
// Replace with the shared connection from Elias (config/) once it is ready.

if (!isset($pdo)) {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=carrental;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

// TEMPORARY permission stub. Remove when Elias's access control is merged.
if (!function_exists('has_permission')) {
    function has_permission(string $key): bool {
        return true; // replace with real role/permission check
    }
}