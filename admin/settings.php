<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$actor = requirePermission('settings.manage');

$catalog = Settings::CATALOG;
$current = Settings::all();

if (is_post()) {
    Csrf::requireValid();
    $errors = [];
    $pdo = Database::connection();
    $pdo->beginTransaction();
    try {
        foreach ($catalog as $key => $meta) {
            $raw = $_POST['settings'][$key] ?? ($meta['type'] === 'boolean' ? 'false' : '');
            if ($key === 'timezone') {
                try {
                    new DateTimeZone((string) $raw);
                } catch (Throwable $e) {
                    $errors[$key] = 'Enter a valid timezone identifier such as Africa/Kigali.';
                    continue;
                }
            }
            if ($key === 'default_currency' && !preg_match('/^[A-Z]{3}$/', (string) $raw)) {
                $errors[$key] = 'Use a 3-letter currency code such as RWF.';
                continue;
            }
            if ($key === 'session_idle_timeout') {
                $minutes = (int) $raw;
                if ($minutes < 5 || $minutes > 480) {
                    $errors[$key] = 'Session timeout must be between 5 and 480 minutes.';
                    continue;
                }
            }
            if ($key === 'business_email' && $raw !== '' && !Validator::email((string) $raw)) {
                $errors[$key] = 'Enter a valid business email address.';
                continue;
            }
            Settings::update($key, $raw, (int) $actor['user_id']);
        }
        if ($errors) {
            $pdo->rollBack();
        } else {
            $pdo->commit();
            flash_set('success', 'Settings saved.');
            redirect('admin/settings.php');
        }
    } catch (Throwable $e) {
        $pdo->rollBack();
        $errors['form'] = 'Settings could not be saved.';
    }
}

$pageTitle = 'System settings';
$currentPage = 'settings';
require BASE_PATH . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1 class="h3 mb-1">System settings</h1>
        <p class="text-secondary mb-0">Non-secret business configuration. Database credentials are not stored here.</p>
    </div>
</div>

<div class="card stat-card">
    <div class="card-body">
        <?php if (!empty($errors['form'])): ?><div class="alert alert-danger"><?= e($errors['form']) ?></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="row g-3">
                <?php foreach ($catalog as $key => $meta): ?>
                    <?php
                    $row = $current[$key] ?? null;
                    $value = $row['setting_value'] ?? '';
                    if ($meta['type'] === 'boolean') {
                        $checked = in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
                    }
                    ?>
                    <div class="col-md-6">
                        <label class="form-label"><?= e($meta['label']) ?></label>
                        <?php if ($meta['type'] === 'boolean'): ?>
                            <input type="hidden" name="settings[<?= e($key) ?>]" value="false">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="settings[<?= e($key) ?>]" value="true" <?= $checked ? 'checked' : '' ?>>
                                <label class="form-check-label"><?= e($meta['description']) ?></label>
                            </div>
                        <?php else: ?>
                            <input class="form-control <?= isset($errors[$key]) ? 'is-invalid' : '' ?>" name="settings[<?= e($key) ?>]" value="<?= e((string) $value) ?>">
                            <div class="form-text"><?= e($meta['description']) ?></div>
                            <?php if (isset($errors[$key])): ?><div class="invalid-feedback d-block"><?= e($errors[$key]) ?></div><?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="btn btn-primary mt-4" type="submit">Save settings</button>
        </form>
    </div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
