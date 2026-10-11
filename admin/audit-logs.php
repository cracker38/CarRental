<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
requirePermission('audit.view');

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'result' => (string) ($_GET['result'] ?? ''),
    'action' => trim((string) ($_GET['action'] ?? '')),
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$where = ['1=1'];
$params = [];
if ($filters['q'] !== '') {
    $where[] = '(a.action LIKE :q1 OR a.entity_type LIKE :q2 OR a.description LIKE :q3 OR u.email LIKE :q4 OR u.first_name LIKE :q5 OR u.last_name LIKE :q6)';
    $like = '%' . $filters['q'] . '%';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
    $params[':q4'] = $like;
    $params[':q5'] = $like;
    $params[':q6'] = $like;
}
if (in_array($filters['result'], ['success', 'failure', 'denied'], true)) {
    $where[] = 'a.result = :result';
    $params[':result'] = $filters['result'];
}
if ($filters['action'] !== '') {
    $where[] = 'a.action = :action';
    $params[':action'] = $filters['action'];
}
$sqlWhere = implode(' AND ', $where);

$count = Database::connection()->prepare("SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.user_id = a.user_id WHERE $sqlWhere");
$count->execute($params);
$pager = pagination((int) $count->fetchColumn(), $page, $perPage, $filters);

$sql = "SELECT a.*, u.first_name, u.last_name, u.email
        FROM audit_logs a
        LEFT JOIN users u ON u.user_id = a.user_id
        WHERE $sqlWhere
        ORDER BY a.audit_log_id DESC
        LIMIT :limit OFFSET :offset";
$stmt = Database::connection()->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $pager['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pager['offset'], PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$pageTitle = 'Audit logs';
$currentPage = 'audit';
require BASE_PATH . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumb">Administration</div>
        <h1 class="h3 mb-1">Audit logs</h1>
        <p class="text-secondary mb-0">Append-only history. Passwords, tokens, and session identifiers are never stored here.</p>
    </div>
</div>

<div class="card crms-card mb-3">
    <div class="card-body">
        <form class="row g-2" method="get">
            <div class="col-md-4">
                <input class="form-control" name="q" value="<?= e($filters['q']) ?>" placeholder="Search action, user, or description">
            </div>
            <div class="col-md-3">
                <input class="form-control" name="action" value="<?= e($filters['action']) ?>" placeholder="Exact action, e.g. login.success">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="result" data-auto-submit>
                    <option value="">All results</option>
                    <?php foreach (['success', 'failure', 'denied'] as $result): ?>
                        <option value="<?= e($result) ?>" <?= $filters['result'] === $result ? 'selected' : '' ?>><?= e(ucfirst($result)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" type="submit">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card crms-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th>When</th>
                <th>Actor</th>
                <th>Action</th>
                <th>Entity</th>
                <th>Result</th>
                <th>IP</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="text-center text-secondary py-4">No audit records found.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e(format_datetime($row['created_at'])) ?></td>
                    <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'System') ?><div class="small text-secondary"><?= e((string) $row['email']) ?></div></td>
                    <td>
                        <code><?= e($row['action']) ?></code>
                        <?php if ($row['description']): ?><div class="small text-secondary"><?= e($row['description']) ?></div><?php endif; ?>
                    </td>
                    <td><?= e($row['entity_type']) ?><?= $row['entity_id'] ? ' #' . e($row['entity_id']) : '' ?></td>
                    <td><span class="badge badge-result-<?= e($row['result']) ?>"><?= e($row['result']) ?></span></td>
                    <td><?= e((string) $row['ip_address']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pager['pages'] > 1): ?>
        <div class="card-body d-flex justify-content-end gap-2">
            <?php if ($pager['page'] > 1): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(page_url($pager, $pager['page'] - 1)) ?>">Previous</a><?php endif; ?>
            <?php if ($pager['page'] < $pager['pages']): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(page_url($pager, $pager['page'] + 1)) ?>">Next</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
