<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$actor = requirePermission('settings.manage');

$catalog = Settings::CATALOG;
$current = Settings::all();
$groups = [];
foreach ($catalog as $key => $meta) {
    $groups[$meta['group'] ?? 'General'][$key] = $meta;
}

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
        <div class="crumb">Administration</div>
        <h1 class="h3 mb-1">System settings</h1>
        <p class="text-secondary mb-0">Non-secret business configuration. Database credentials are never stored here.</p>
    </div>
</div>

<div class="card crms-card">
    <div class="card-body">
        <?php if (!empty($errors['form'])): ?><div class="alert alert-danger"><?= e($errors['form']) ?></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <?php foreach ($groups as $group => $items): ?>
                <section class="settings-section">
                    <h2><?= e($group) ?></h2>
                    <div class="row g-3">
                        <?php foreach ($items as $key => $meta): ?>
                            <?php
                            $row = $current[$key] ?? null;
                            $value = $row['setting_value'] ?? '';
                            $checked = in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
                            ?>
                            <div class="col-md-6">
                                <label class="form-label"><?= e($meta['label']) ?></label>
                                <?php if ($meta['type'] === 'boolean'): ?>
                                    <input type="hidden" name="settings[<?= e($key) ?>]" value="false">
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" name="settings[<?= e($key) ?>]" value="true" <?= $checked ? 'checked' : '' ?>>
                                        <label class="form-check-label"><?= e($meta['description']) ?></label>
                                    </div>
                                <?php elseif (!empty($meta['options'])): ?>
                                    <select class="form-select <?= isset($errors[$key]) ? 'is-invalid' : '' ?>" name="settings[<?= e($key) ?>]">
                                        <?php foreach ($meta['options'] as $opt => $label): ?>
                                            <option value="<?= e((string) $opt) ?>" <?= (string) $value === (string) $opt ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text"><?= e($meta['description']) ?></div>
                                <?php else: ?>
                                    <input class="form-control <?= isset($errors[$key]) ? 'is-invalid' : '' ?>" name="settings[<?= e($key) ?>]" value="<?= e((string) $value) ?>">
                                    <div class="form-text"><?= e($meta['description']) ?></div>
                                <?php endif; ?>
                                <?php if (isset($errors[$key])): ?><div class="invalid-feedback d-block"><?= e($errors[$key]) ?></div><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
            <button class="btn btn-primary" type="submit">Save settings</button>
        </form>
    </div>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
