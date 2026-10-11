<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

Auth::logout(true);
Auth::start();
flash_set('success', 'You have been signed out.');
redirect('login.php');
