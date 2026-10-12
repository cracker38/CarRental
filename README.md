# Rental Operations module — PHP / MySQL

This is a focused add-on for the existing Car Rental Management System that uses the application's current `app/bootstrap.php`, `Database::connection()`, `requireLogin()`, `hasPermission()`, `Csrf`, `base_url()`, `e()`, `setting()`, and shared `includes/header.php` / `includes/footer.php` conventions.

It targets the schema in the supplied SQL file (`carrental`) and uses these existing tables: `reservations`, `rental_agreements`, `rentals`, `vehicle_inspections`, `inspection_images`, `invoices`, `invoice_items`, `payments`, `payment_refunds`, `deposit_transactions`, `maintenance_records`, `customers`, `vehicles`, `users`, `roles`, `permissions`, and `role_permissions`. It expects the `vw_invoice_balances` view from that schema.

## Included pages

- `admin/rental-operations.php` — styled operations dashboard, work queue, latest rentals, receivables.
- `admin/agreements.php` and `admin/agreement-view.php` — create agreement records from confirmed reservations, review/print terms, issue, record signed status, then open a rental.
- `admin/rentals.php` and `admin/rental-view.php` — create a rental from a signed agreement; record pickup/return mileage, fuel, condition and charges; create return inspection; flag vehicles needing service.
- `admin/inspection-form.php` — create follow-up inspections and upload JPG/PNG/WebP evidence images.
- `admin/invoices.php` and `admin/invoice-view.php` — create an itemized invoice from rental charges, add extra line items while still draft, issue invoices, review balances and payments, print invoice.
- `admin/payment-form.php`, `admin/payments.php`, `admin/payment-receipt.php` — record full or partial payments, prevent payment above the outstanding balance, generate printable receipts.
- `admin/refunds.php` — request refunds, reserve amounts across open requests, require approval before processing, prevent a requester from approving their own request, and recalculate invoice/payment balances after processing.
- `admin/deposits.php` — record receipts, deductions, refunds, returns and positive adjustments; validate the held balance and prevent deposit receipts above the agreed deposit.
- `app/RentalOperations.php` — transaction-based business rules.
- `assets/rental-operations.css` — responsive visual styling and print styles.
- `uploads/inspection-images/.htaccess` — denies server-side script execution in the evidence-image folder on Apache.
- `sql/install_permissions.sql` — adds the permission keys and grants them to the `Rental Operations` role and common administrator role names.

## Install into your existing project

1. **Back up your existing project and database first.** Do not replace your current `app/bootstrap.php`, login files, database configuration, header, footer, or existing dashboard.
2. Copy the module's `admin/*.php` files into your existing project's `admin/` folder.
3. Copy `app/RentalOperations.php` into your existing project's `app/` folder.
4. Copy `includes/rental-operations-common.php` and `includes/rental-operations-nav.php` into your existing project's `includes/` folder.
5. Copy `assets/rental-operations.css` into your existing project's `assets/` folder.
6. In phpMyAdmin, select the same `carrental` database and run `sql/install_permissions.sql`. Make sure the intended staff user has the `Rental Operations` role, and that any administrator role used by your app has the module permissions.
7. Sign in to your application and open `admin/rental-operations.php`. Optionally add a quick-action link to your existing `admin/dashboard.php` for staff with `rental.view` permission:

   ```php
   <?php if (hasPermission('rental.view')): ?>
       <a class="quick-link" href="<?= e(base_url('admin/rental-operations.php')) ?>"><i class="bi bi-car-front"></i><span>Rental operations</span></a>
   <?php endif; ?>
   ```

8. Copy the supplied `uploads/inspection-images/.htaccess` into your project and make sure `uploads/inspection-images/` is writable by PHP. The module creates the directory when the first image is uploaded. Configure upload limits and backup policy for your hosting environment.

The pages intentionally use your existing authentication and layout. They are not a standalone login application and will not work until copied into the project that already contains the referenced bootstrap and helper functions.

## Business logic and guardrails

- Only **confirmed** reservations can generate an agreement.
- Agreement workflow is `draft → issued → signed`. A rental can only be opened from a signed agreement and confirmed reservation.
- Pickup records actual pickup time, odometer, fuel and a pickup inspection. Pickup mileage cannot be below the vehicle's current recorded mileage.
- Return records actual return time, odometer, fuel, condition and agreed charges. Return mileage cannot be below pickup/current mileage. Poor condition, cleaning needs or damage notes flag the vehicle for maintenance follow-up and create a scheduled maintenance record.
- Rental total is calculated as `base + extra mileage + late return + damage + cleaning + other agreed charges − discount + tax`, never below zero.
- The supplied schema does not define a late-return grace period, mileage allowance, tax percentage, damage pricing, or cleaning price. The module therefore requires staff to enter agreed fees; it does not invent these rates.
- Invoice line items are generated from the rental's stored charge fields. Extra line items can be added only while the invoice is a draft. Issue the invoice before accepting payments.
- Important agreement, rental, invoice, payment, refund, inspection and deposit changes are written to `audit_logs`.
- Payment amount must be positive and cannot exceed the current outstanding balance. A transaction reference, if provided, must be unique according to the schema.
- A refund must be requested, approved, then processed. The requester cannot approve their own refund. Processed refunds affect net payment and invoice balances.
- Deposit receipts cannot exceed the agreed security deposit. Deductions/refunds/returns cannot exceed the deposit currently held. “Adjustment” is treated as a positive credit; use “deduction” for a decrease.
- Card, online, bank-transfer and mobile-money entries are **accounting records only**. No gateway integration or real transfer is performed. Verify provider transactions and refunds independently before recording them as completed/processed.
- Agreement and invoice print pages use browser print. They do not create cryptographically signed PDFs or verify physical/digital signatures.

## Permissions

The permission keys used by the module are:

- `rental.view`
- `agreements.manage`
- `rentals.manage`
- `inspections.manage`
- `invoices.manage`
- `payments.manage`
- `refunds.manage`
- `refunds.approve`
- `refunds.process`
- `deposits.manage`

The SQL grants these to a role named `Rental Operations` and common administrator role names if those roles exist. If your administrator role has a different name, grant it the same permission keys through your role management page or by extending the SQL selection.

## Important integration checks

The PHP files pass CLI syntax checks, but this add-on has not been run against your actual application runtime because the existing `app/bootstrap.php`, permission helper implementation, and shared layout files were not included with the SQL schema. Before using it with real customer/financial data, test in a copy of the database and confirm:

- `BASE_PATH` points to the application root and `base_url()` handles the new routes and uploaded relative image paths.
- `csrf_field()` emits the token field expected by `Csrf::requireValid()`.
- Your permission manager reads the keys inserted by `install_permissions.sql`.
- Your business has reviewed agreement terms, pricing rules, refund policy, deposit policy, tax rules and legal requirements.
- Your PHP upload settings and file permissions allow the intended inspection images.
