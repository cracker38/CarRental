<?php
declare(strict_types=1);
$flashes = flash_take();
if (!$flashes) {
    return;
}
foreach ($flashes as $flash):
    $type = in_array($flash['type'] ?? '', ['success', 'danger', 'warning', 'info'], true) ? $flash['type'] : 'info';
    ?>
    <div class="alert alert-<?= e($type) ?> alert-dismissible fade show" role="alert">
        <?= e((string) ($flash['message'] ?? '')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endforeach; ?>
