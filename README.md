# Biomat Donor Services

A PHP and SQLite course-project prototype based on the Biomat USA assignment brief, the team proposal, and its suggested entity-relationship diagram. The site demonstrates donor registration and account management, appointment scheduling, donation-center information, personnel workflows, and operational reports.

## Requirements

- PHP 7.4 or newer
- The PHP `pdo_sqlite` extension

The database schema and three example donation centers are initialized automatically the first time the site or setup command connects to the database. Appointment, donation, and compensation timestamps use UTC.

## Run locally

From the project directory:

```sh
php -S 127.0.0.1:8000 router.php
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000). The default SQLite database is created in `data/biomat.sqlite`; it is excluded from version control and protected from direct requests by the development router and `.htaccess`.

To keep the database outside the project directory, set `BIOMAT_DB_PATH` to an absolute database-file path before starting PHP and before running the account setup command.

## Create the first administrator

Create the initial admin account from a terminal. The password is read from the environment, not committed to source:

```sh
BIOMAT_ADMIN_PASSWORD='choose-a-long-password' php bin/create-admin.php admin@example.test
```

Sign in using that email and password. From **Staff workspace**, an administrator can create personnel and donor login accounts, then manage donor profiles, appointments, donations, medical personnel, and compensation records. Personnel have access to operational records and reports but cannot manage login accounts.

Donors can register through the site, sign in, update their own profile, schedule or change upcoming appointments, review appointment history, and delete their own account after confirming their password.

## Data model

The SQLite schema includes login accounts, donors, donation centers, medical personnel, appointments, donations, and compensation records. Foreign keys enforce the relationships in the assignment ERD. Reports summarize weekly donation volume by blood type, donation activity by center and donor, and recorded compensation.

## Privacy and demo limitations

This is a classroom prototype—not a production donor, medical, scheduling, or payment system. Use fictional information only. Donor Social Security numbers are stored only as keyed HMAC digests and are never displayed; the local demo generates a 256-bit key in the protected, ignored `data/` directory on first registration. For deployments, provide a private `BIOMAT_SSN_HMAC_KEY` value of at least 32 characters and keep it outside source control. Payment-card or debit-card numbers are not collected or stored. Passwords are hashed, forms use CSRF tokens, database operations use prepared statements, output is HTML-escaped, and donor self-service operations are scoped to the signed-in donor.

The sample center addresses and phone numbers are illustrative. The application does not send appointment reminders, process payments, or determine donor eligibility.

## PHP style

PHP files use strict types, four-space indentation, PSR-12-style braces and naming, and explicit error handling. No Composer packages are required.
