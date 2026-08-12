# REST API

Base URL: `/api/index.php?path=...`

## Authentication

`POST /api/index.php?path=auth/login`

```json
{
  "email": "admin@example.com",
  "password": "secret"
}
```

Response includes a JWT token. Send it as:

```http
Authorization: Bearer TOKEN
```

## Endpoints

| Method | Path | Permission | Purpose |
|---|---|---|---|
| POST | `auth/login` | Public | Login and return JWT |
| GET | `patients/lookup&q=PAT` | `patients.manage` | Search patient by file number, name, or phone |
| POST | `patients` | `patients.manage` | Register patient |
| GET | `dashboard/stats` | `dashboard.view` | Dashboard statistics |
| GET | `reports/summary` | `reports.manage` | Patient, finance, lab, and pharmacy summaries |

## Example Patient Create

```json
{
  "first_name": "Amina",
  "last_name": "Bello",
  "gender": "female",
  "phone": "08000000000",
  "blood_group": "O+",
  "allergies": "Penicillin"
}
```

