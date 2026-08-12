# UltimateCooperative HMS

A PHP hospital management system starter built for Laragon, shared hosting, VPS, and Docker. It uses PHP, MySQL/MariaDB, REST JSON APIs, JWT authentication for external clients, session authentication for the web dashboard, role-based access control, and a WordPress-style first-time installer.

## Features

- First-time installation wizard at `install/index.php`
- Super Admin, Hospital Admin, Doctor, Nurse, Receptionist, Pharmacist, Lab Technician, Accountant, and Patient roles
- Patient file-number lookup and registration
- Queue board, appointments, EMR, prescriptions, lab requests, pharmacy dispensing, billing, receipts, wards, staff profiles, settings, reports
- Modern responsive UI with light/dark mode
- Normalized MySQL schema with foreign keys, indexes, constraints, and invoice/payment triggers
- REST endpoints under `api/index.php`
- Docker and Laragon compatible

Optional demo accounts are available in `database/sample_data.sql`. Every demo account uses the password `Password123!`.

## Laragon Setup

1. Copy `UltimateCooperative` into Laragon `www`.
2. Start Apache and MySQL/MariaDB.
3. Open `http://localhost/UltimateCooperative`.
4. Follow the installer:
   - Check requirements
   - Enter DB host `127.0.0.1`, port `3306`, username `root`, password blank unless changed
   - Create tables and seed data
   - Create hospital profile and Super Admin account
5. Log in from `login.php`.

## Docker Setup

```bash
docker compose up --build
```

Open `http://localhost:8080`, then run the installer with:

- Host: `db`
- Port: `3306`
- Database: `ultimate_hms`
- Username: `hms`
- Password: `hms_secret`

## Structure

```text
UltimateCooperative/
  index.php
  config.php
  admin/
  api/
  includes/
  templates/
  css/
  js/
  database/
  uploads/
  docs/
  install/
  storage/
```

## Security Notes

- The installer writes secrets to `storage/config.php`.
- Use HTTPS online.
- Change default database credentials before production.
- Restrict public write access to `uploads`.
- Configure backups for the database and uploads.
- Two-factor fields are present in the schema; connect an OTP provider before enabling enforcement.
