-- Run after importing the supplied carrental schema and after the application roles exist.
-- Creates the focused Rental Operations role if it does not exist, adds permission keys,
-- and grants the module permissions to that role plus common administrator role names.

INSERT IGNORE INTO roles (role_name, description, is_system_role)
VALUES ('Rental Operations', 'Rental agreements, handovers, inspections, invoices, payments, refunds and deposits', 1);

INSERT IGNORE INTO permissions (permission_key, description) VALUES
('rental.view', 'View the rental operations dashboard'),
('agreements.manage', 'Create and manage rental agreements'),
('rentals.manage', 'Open rentals and record vehicle pickup/return'),
('inspections.manage', 'Create vehicle inspections and upload inspection evidence'),
('invoices.manage', 'Generate, itemize, issue and view invoices'),
('payments.manage', 'Record payments and view receipts'),
('refunds.manage', 'Request and view payment refunds'),
('refunds.approve', 'Approve or reject refund requests'),
('refunds.process', 'Mark approved refunds as processed'),
('deposits.manage', 'Record and view security deposit transactions');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
CROSS JOIN permissions p
WHERE r.role_name IN ('Rental Operations', 'System Administrator', 'Administrator', 'Admin')
  AND p.permission_key IN (
    'rental.view', 'agreements.manage', 'rentals.manage', 'inspections.manage',
    'invoices.manage', 'payments.manage', 'refunds.manage', 'refunds.approve',
    'refunds.process', 'deposits.manage'
  );
