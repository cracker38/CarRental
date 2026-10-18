CRMS - folder structure
  index.php register.php logout.php seed.php dashboard.php   (public entry points)
  includes/app.php   database, auth, layout, helpers
  customer/  dashboard, cars, rent, my (profile/reservations/invoices/payments)
  officer/   dashboard, reservations, agreements, rentals (handover/return/inspections), customers
  cashier/   dashboard, finance (invoices, payments, refunds, deposits)
  manager/   dashboard, reports
  admin/     dashboard, users, vehicles, drivers, maintenance, audit, settings
  shared/    agreement (view/pay/sign), receipt
  assets/style.css   uploads/ (must be writable)
SETUP (XAMPP): import carrental.sql; copy folder to htdocs/crms; edit DBC in includes/app.php;
open /crms/seed.php once then delete it; sign in at /crms/ (demo password Pass@12345).
