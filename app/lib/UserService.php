<?php
declare(strict_types=1);

final class UserService
{
    public static function paginate(array $filters, int $page, int $perPage = 10): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['q'])) {
            $where[] = '(u.first_name LIKE :q1 OR u.last_name LIKE :q2 OR u.email LIKE :q3 OR u.phone LIKE :q4)';
            $like = '%' . $filters['q'] . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
            $params[':q4'] = $like;
        }
        if (!empty($filters['role_id'])) {
            $where[] = 'u.role_id = :role_id';
            $params[':role_id'] = (int) $filters['role_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'u.account_status = :status';
            $params[':status'] = $filters['status'];
        }
        $sqlWhere = implode(' AND ', $where);

        $count = Database::connection()->prepare("SELECT COUNT(*) FROM users u WHERE $sqlWhere");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pager = pagination($total, $page, $perPage, $filters);

        $sql = "SELECT u.*, r.role_name
                FROM users u
                INNER JOIN roles r ON r.role_id = u.role_id
                WHERE $sqlWhere
                ORDER BY u.created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $pager['per_page'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pager['offset'], PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(), 'pager' => $pager];
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.role_name FROM users u INNER JOIN roles r ON r.role_id = u.role_id WHERE u.user_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data, array $actor): array
    {
        if (!Authorization::allows('users.manage', $actor)) {
            return ['ok' => false, 'errors' => ['form' => 'You are not allowed to create users.']];
        }
        $errors = self::validate($data, true);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }
        $role = RoleService::find((int) $data['role_id']);
        if (!$role || !Authorization::canAssignRole($actor, $role)) {
            return ['ok' => false, 'errors' => ['role_id' => 'You are not allowed to assign that role.']];
        }
        if (self::emailTaken($data['email'])) {
            return ['ok' => false, 'errors' => ['email' => 'That email is already in use.']];
        }

        Database::connection()->prepare(
            'INSERT INTO users (role_id, first_name, last_name, email, phone, password_hash, account_status, must_change_password)
             VALUES (:role_id, :first_name, :last_name, :email, :phone, :password_hash, :account_status, :must_change_password)'
        )->execute([
            ':role_id' => (int) $data['role_id'],
            ':first_name' => trim($data['first_name']),
            ':last_name' => trim($data['last_name']),
            ':email' => strtolower(trim($data['email'])),
            ':phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            ':password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            ':account_status' => $data['account_status'],
            ':must_change_password' => empty($data['must_change_password']) ? 0 : 1,
        ]);
        $id = (int) Database::connection()->lastInsertId();
        Audit::record((int) $actor['user_id'], 'user.create', 'user', (string) $id, 'Created user account.', 'success', null, [
            'email' => strtolower(trim($data['email'])),
            'role_id' => (int) $data['role_id'],
            'account_status' => $data['account_status'],
        ]);
        return ['ok' => true, 'id' => $id];
    }

    public static function update(int $id, array $data, array $actor): array
    {
        if (!Authorization::allows('users.manage', $actor)) {
            return ['ok' => false, 'errors' => ['form' => 'You are not allowed to update users.']];
        }
        $existing = self::find($id);
        if (!$existing) {
            return ['ok' => false, 'errors' => ['form' => 'User was not found.']];
        }
        $errors = self::validate($data, false);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }
        $role = RoleService::find((int) $data['role_id']);
        if (!$role || !Authorization::canAssignRole($actor, $role)) {
            return ['ok' => false, 'errors' => ['role_id' => 'You are not allowed to assign that role.']];
        }
        if ($existing['role_name'] === 'System Administrator' && (int) $data['role_id'] !== (int) $existing['role_id']) {
            if (self::activeAdminCount() <= 1) {
                return ['ok' => false, 'errors' => ['role_id' => 'Cannot change the role of the last active administrator.']];
            }
        }
        if ($existing['account_status'] === 'active' && $data['account_status'] !== 'active' && $existing['role_name'] === 'System Administrator') {
            if (self::activeAdminCount() <= 1) {
                return ['ok' => false, 'errors' => ['account_status' => 'Cannot deactivate the last active administrator.']];
            }
        }
        if ((int) $actor['user_id'] === $id && $data['account_status'] !== 'active') {
            return ['ok' => false, 'errors' => ['account_status' => 'You cannot deactivate your own account.']];
        }
        if (self::emailTaken($data['email'], $id)) {
            return ['ok' => false, 'errors' => ['email' => 'That email is already in use.']];
        }

        Database::connection()->prepare(
            'UPDATE users
             SET role_id = :role_id, first_name = :first_name, last_name = :last_name, email = :email,
                 phone = :phone, account_status = :account_status, must_change_password = :mcp
             WHERE user_id = :id'
        )->execute([
            ':role_id' => (int) $data['role_id'],
            ':first_name' => trim($data['first_name']),
            ':last_name' => trim($data['last_name']),
            ':email' => strtolower(trim($data['email'])),
            ':phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            ':account_status' => $data['account_status'],
            ':mcp' => empty($data['must_change_password']) ? 0 : 1,
            ':id' => $id,
        ]);

        Audit::record((int) $actor['user_id'], 'user.update', 'user', (string) $id, 'Updated user account.', 'success', [
            'role_id' => $existing['role_id'],
            'account_status' => $existing['account_status'],
            'email' => $existing['email'],
        ], [
            'role_id' => (int) $data['role_id'],
            'account_status' => $data['account_status'],
            'email' => strtolower(trim($data['email'])),
        ]);
        return ['ok' => true];
    }

    public static function setStatus(int $id, string $status, array $actor): array
    {
        $existing = self::find($id);
        if (!$existing) {
            return ['ok' => false, 'error' => 'User was not found.'];
        }
        if (!in_array($status, ['active', 'inactive', 'suspended', 'pending'], true)) {
            return ['ok' => false, 'error' => 'Invalid status.'];
        }
        if ((int) $actor['user_id'] === $id && $status !== 'active') {
            return ['ok' => false, 'error' => 'You cannot deactivate your own account.'];
        }
        if ($existing['role_name'] === 'System Administrator' && $status !== 'active' && self::activeAdminCount() <= 1) {
            return ['ok' => false, 'error' => 'Cannot deactivate the last active administrator.'];
        }
        Database::connection()->prepare('UPDATE users SET account_status = :s WHERE user_id = :id')
            ->execute([':s' => $status, ':id' => $id]);
        Audit::record((int) $actor['user_id'], $status === 'active' ? 'user.activate' : 'user.deactivate', 'user', (string) $id, 'Changed account status to ' . $status, 'success', ['account_status' => $existing['account_status']], ['account_status' => $status]);
        return ['ok' => true];
    }

    public static function adminResetPassword(int $id, string $password, array $actor): array
    {
        $existing = self::find($id);
        if (!$existing) {
            return ['ok' => false, 'error' => 'User was not found.'];
        }
        $error = Validator::password($password);
        if ($error) {
            return ['ok' => false, 'error' => $error];
        }
        Database::connection()->prepare('UPDATE users SET password_hash = :h, must_change_password = 1 WHERE user_id = :id')
            ->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $id]);
        Database::connection()->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = :id AND used_at IS NULL')
            ->execute([':id' => $id]);
        Audit::record((int) $actor['user_id'], 'user.password_reset', 'user', (string) $id, 'Administrator reset a user password.', 'success');
        return ['ok' => true];
    }

    public static function changeOwnPassword(array $user, string $current, string $new): array
    {
        $row = self::find((int) $user['user_id']);
        if (!$row || !password_verify($current, $row['password_hash'])) {
            return ['ok' => false, 'error' => 'Current password is incorrect.'];
        }
        $error = Validator::password($new);
        if ($error) {
            return ['ok' => false, 'error' => $error];
        }
        if (password_verify($new, $row['password_hash'])) {
            return ['ok' => false, 'error' => 'Choose a password that is different from the current one.'];
        }
        Database::connection()->prepare('UPDATE users SET password_hash = :h, must_change_password = 0 WHERE user_id = :id')
            ->execute([':h' => password_hash($new, PASSWORD_DEFAULT), ':id' => $user['user_id']]);
        Audit::record((int) $user['user_id'], 'user.password_change', 'user', (string) $user['user_id'], 'User changed their own password.', 'success');
        unset($_SESSION['_user_cache']);
        return ['ok' => true];
    }

    public static function activeAdminCount(): int
    {
        $sql = "SELECT COUNT(*)
                FROM users u
                INNER JOIN roles r ON r.role_id = u.role_id
                WHERE r.role_name = 'System Administrator' AND u.account_status = 'active'";
        return (int) Database::connection()->query($sql)->fetchColumn();
    }

    private static function emailTaken(string $email, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT user_id FROM users WHERE LOWER(email) = :email';
        $params = [':email' => strtolower(trim($email))];
        if ($ignoreId) {
            $sql .= ' AND user_id <> :id';
            $params[':id'] = $ignoreId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    private static function validate(array $data, bool $creating): array
    {
        $errors = [];
        $first = Validator::name($data['first_name'] ?? '', 'First name');
        if ($first) {
            $errors['first_name'] = $first;
        }
        $last = Validator::name($data['last_name'] ?? '', 'Last name');
        if ($last) {
            $errors['last_name'] = $last;
        }
        if (!Validator::email((string) ($data['email'] ?? ''))) {
            $errors['email'] = 'Enter a valid email address.';
        }
        $phone = Validator::phone($data['phone'] ?? null);
        if ($phone) {
            $errors['phone'] = $phone;
        }
        if (empty($data['role_id'])) {
            $errors['role_id'] = 'Select a role.';
        }
        $status = $data['account_status'] ?? '';
        if (!in_array($status, ['active', 'inactive', 'suspended', 'pending'], true)) {
            $errors['account_status'] = 'Select a valid account status.';
        }
        if ($creating) {
            $pwd = Validator