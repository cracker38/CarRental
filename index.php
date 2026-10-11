<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

if (is_logged_in()) {
    redirect('admin/dashboard.php');
}
redirect('login.php');
