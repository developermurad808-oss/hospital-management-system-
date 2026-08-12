# Architecture

UltimateCooperative HMS is organized as a modular PHP application:

- `includes/bootstrap.php` loads config, sessions, helper functions, and classes.
- `includes/Database.php` provides PDO access.
- `includes/Auth.php` handles sessions, RBAC, and audit logging.
- `includes/Jwt.php` signs API tokens.
- `admin/` contains role-protected web modules.
- `api/` exposes REST JSON endpoints for dashboards, mobile apps, and future plugins.
- `database/schema.sql` defines normalized clinical, pharmacy, laboratory, billing, ward, staff, security, and audit tables.
- `install/` performs first-time setup and writes `storage/config.php`.

## Workflow

1. Reception searches a file number or registers a new patient.
2. A visit is created and appears on the queue board.
3. Doctor records symptoms, diagnosis, treatment, lab requests, and prescriptions.
4. Laboratory sees requested tests and records results.
5. Pharmacy sees prescriptions and dispenses medicines.
6. Billing creates invoice items, receives payment, and prints a receipt.
7. Admin dashboards and reports summarize activity.

## Production Hardening

- Put the app behind HTTPS.
- Disable display errors and write PHP errors to a private log.
- Move `storage/` outside the web root where possible.
- Add scheduled backups.
- Integrate SMS/email providers for notifications.
- Integrate a video SDK for telemedicine.
- Add Redis or database-backed session storage for multi-server deployments.

