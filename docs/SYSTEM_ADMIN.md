# CRMS System Administration Module

Owner: DUKUZUMUREMYI Elias — System Administration, Authentication, Security, and Integration.

## What already existed

The GitHub repository contained only `README.md` and `Database/carrental.sql`. The live MySQL database `carrental` already matched that schema. No PHP application files were present, so this module is the first application layer. Other members' tables were not altered.

## Schema mapping (no table changes)

| Requirement | Existing column / table |
|---|---|
| Login identifier | `users.email` (there is no username column) |
| Password storage | `users.password_hash` |
| Account activation | `users.account_status` (`active`, `inactive`, `suspended`, `pending`) |
| Forced password change | `users.must_change_password` |
| Last login | `users.last_login_at` |
| Roles | `roles.role_name`, `roles.is_system_role` |
| Permissions | `permissions.permission_key` |
| Assignments | `role_permissions` |
| Password reset | `password_reset_tokens.token_hash`, `expires_at`, `used_at` |
| Audit | `audit_logs` (user, action, entity, old/new JSON, IP, user agent, result) |
| Settings | `system_settings.setting_key`, `setting_value`, `value_type` |

Login is email-based because the schema has no username field.

Failed-login throttling uses `audit_logs` rather than a new table.

Permission names already in the database are the source of truth (`users.manage`, `roles.manage`, `audit.view`, `settings.manage`, plus the operational keys used by other modules). Helper aliases such as `users.view` and `audit_logs.view` map onto those existing keys so teammates can use either style.

## Architecture

```
app/bootstrap.php          Shared entry for every page and other modules
app/config/                Database and security configuration
app/lib/Auth.php           Login, logout, sessions, idle timeout
app/lib/Authorization.php  RBAC checks and privilege-escalation rules
app/lib/UserService.php    User administration
app/lib/RoleService.php    Role and permission administration
app/lib/PasswordReset.php  Hashed, single-use, expiring tokens
app/lib/Audit.php          Append-only audit writer with secret redaction
app/lib/Settings.php       Typed, allow-listed system settings
admin/                     Protected administration UI
login.php / logout.php / forgot-password.php / reset-password.php
```

Other modules should include `app/bootstrap.php` and call:

```php
requireLogin();
requirePermission('vehicles.manage');
```

See `docs/INTEGRATION.md`.

## Local setup

1. Import `Database/carrental.sql` if the `carrental` database is empty.
2. Confirm `app/config/config.php` (or a gitignored `app/config/local.php`) matches XAMPP MySQL.
3. Create the first administrator:

```bash
C:\xampp\php\php.exe scripts\seed_admin.php
```

4. Open `http://localhost/carrental/CarRental/`
5. Sign in with `admin@carrental.local` / `Admin@12345` and change the password.

Password reset in development writes a one-time link to `storage/password_resets.log`. That file is not web-accessible. No mail service is configured in this project.

## Tests

```bash
C:\xampp\php\php.exe tests\run_admin_tests.php
```

The runner covers valid/invalid login, hashing, inactive accounts, SQL injection payloads, XSS escaping, CSRF tokens, RBAC, privilege escalation, last-admin protection, password reset, settings validation, and audit recording.
