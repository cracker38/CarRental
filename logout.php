<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

if (!is_logged_in()) {
    redirect('login.php');
}

if (!is_post()) {
    http_response_code(405);
    header('Allow: POST');
    exit('Sign out must be submitted from the profile menu.');
}

Csrf::requireValid();
logout_user();
redirect('login.php');
