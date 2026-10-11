# Integration guide for CRMS teammates

Include the shared bootstrap at the top of every protected page. Do not copy login or permission logic into your module.

```php
<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php'; // adjust relative path

$user = requireLogin();
requirePermission('vehicles.manage'); // use your module's permission key
```

## Available helpers

| Helper | Purpose |
|---|---|
| `requireLogin()` | Redirects guests to `login.php` and returns the current user |
| `requirePermission('key')` | Requires login and the named permission |
| `hasPermission('key')` | Boolean check for menus and extra UI |
| `current_user()` | Current user array or `null` |
| `e($text)` | HTML-escape output |
| `csrf_field()` | Hidden CSRF input for POST forms |
| `Csrf::requireValid()` | Reject forged POST requests |
| `Audit::record(...)` | Write a safe audit event |
| `setting('business_name')` | Read a system setting |
| `base_url('vehicles/index.php')` | Build a URL inside the app |

## Permission keys already in `permissions`

Use these exact keys so administrators can grant them from **Roles**:

- Vehicles: `vehicles.view`, `vehicles.manage`
- Customers: `customers.view`, `customers.manage`
- Reservations: `reservations.view`, `reservations.manage`
- Rentals / agreements: `rentals.manage`, `agreements.manage`
- Finance: `invoices.view`, `invoices.manage`, `payments.manage`, `refunds.manage`, `deposits.manage`
- Maintenance / drivers: `maintenance.manage`, `drivers.manage`
- Reports: `reports.operational`, `reports.financial`
- Notifications: `notifications.manage`
- Administration: `users.manage`, `roles.manage`, `audit.view`, `settings.manage`

Place your module entry file at one of these paths if you want it to appear in the shared sidebar automatically:

- `vehicles/index.php`
- `customers/index.php`
- `reservations/index.php`
- `rentals/index.php`
- `payments/index.php`
- `maintenance/index.php`
- `reports/index.php`

The sidebar only shows a link when the file exists and the signed-in user has the matching permission.

## Layout

Protected pages can reuse the admin chrome:

```php
$pageTitle = 'Vehicles';
$currentPage = 'vehicles';
require BASE_PATH . '/includes/header.php';
// page content
require BASE_PATH . '/includes/footer.php';
```

## Rules

- Use PDO prepared statements through `Database::connection()`.
- Never store plaintext passwords. Use `password_hash()` / `password_verify()`.
- Do not log passwords, reset tokens, or session IDs.
- Do not change `users`, `roles`, `permissions`, `role_permissions`, `password_reset_tokens`, `audit_logs`, or `system_settings` without coordinating with the administration module owner.
- CSRF-protect every state-changing form with `csrf_field()` and `Csrf::requireValid()`.
