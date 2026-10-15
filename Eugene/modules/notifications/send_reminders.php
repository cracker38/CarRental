<?php
// modules/notifications/send_reminders.php  (Member 5: Eugenie)
// Browser: used by the "Generate reminders" button (POST + CSRF + permission).
// Command line (Windows Task Scheduler): php send_reminders.php
require_once __DIR__ . '/helpers.php';

$isCli = (PHP_SAPI === 'cli');

if (!$isCli) {
    require_notifications_permission();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: list.php');
        exit;
    }
    csrf_check();
}

try {
    $made  = generateReminders($pdo);
    $total = array_sum($made);
    $text  = "Reminders generated: $total new "
           . "(booking {$made['booking_reminder']}, return {$made['return_reminder']}, "
           . "overdue {$made['overdue_alert']}, payment {$made['payment_notice']}).";
} catch (Throwable $ex) {
    $text = 'Could not generate reminders.';
    if ($isCli) {
        fwrite(STDERR, $ex->getMessage() . PHP_EOL);
    }
}

if ($isCli) {
    echo $text . PHP_EOL;
    exit;
}

$_SESSION['flash'] = $text;
header('Location: list.php');
exit;