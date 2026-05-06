# About

This is a DBMS project for the collaboration of IPT and IM subjects. All contents are collected data from our research on ABED's current situation.

# Purpose of the System

To:
- Centralize all project data
- Allow real-time encoding and updating
- Provide automated reporting and dashboard visualization

# The system will include the following core features:

- Centralized database
- Data entry & editing
- Search and filter
- Dashboard (charts/graphs)
- Automated reports
- Data export (PDF/Excel)

# Fresh DB Setup

For a new/fresh database import, use:

- `database/db.sql`

This file is the full baseline schema and already includes the structural outcomes of current migration files, so you do not need to run migration scripts separately for first-time setup.

# Installation Manual

This guide covers:

- Local setup (XAMPP)
- Shared hosting deployment
- Database connection configuration
- Common troubleshooting

---

## 1) System Requirements

- PHP `8.0+` (recommended `8.1+`)
- MySQL / MariaDB
- PHP extension: `mysqli`
- Apache or Nginx with PHP enabled
- Web browser (latest Chrome/Edge/Firefox)

---

## 2) Project Structure (important folders)

- `config/` - database and app configuration
- `database/db.sql` - full fresh-install schema
- `database/migrations/` - incremental migration scripts (optional for existing DBs)
- `uploads/` - uploaded files (must be writable)
- `api/`, `handlers/`, `components/`, `assets/` - app logic/UI

---

## 3) Local Installation (XAMPP)

### Step 1: Put project in htdocs

Copy project folder to:

- `C:\xampp\htdocs\abed-hub`

### Step 2: Start services

In XAMPP Control Panel, start:

- Apache
- MySQL

### Step 3: Create database

Open phpMyAdmin:

- [http://localhost/phpmyadmin](http://localhost/phpmyadmin)

Create a database (example):

- `abed_idm_hub`

### Step 4: Import schema

Import:

- `database/db.sql`

This is enough for a fresh install.

### Step 5: Open app

Visit:

- [http://localhost/abed-hub](http://localhost/abed-hub)

---

## 4) Shared Hosting / Production Installation

### Step 1: Upload project files

Upload the project to:

- `public_html/` (if app runs at root), or
- `public_html/abed-hub/` (if app runs in subfolder)

### Step 2: Create database on hosting panel

In your host control panel:

- Create MySQL database
- Create DB user
- Assign user to DB with full privileges

### Step 3: Import schema

Import:

- `database/db.sql`

### Step 4: Configure DB connection

Use either environment variables OR deploy file.

#### Option A (recommended): Environment Variables

Set these in hosting panel if supported:

- `DB_HOST`
- `DB_USER`
- `DB_PASS`
- `DB_NAME`
- `DB_PORT` (usually `3306`)

#### Option B: `config/database.deploy.php`

If env vars are unavailable (common on free/shared hosting), create:

- `config/database.deploy.php`

Example:

```php
<?php
return [
    'host' => 'YOUR_DB_HOST',
    'user' => 'YOUR_DB_USER',
    'pass' => 'YOUR_DB_PASSWORD',
    'name' => 'YOUR_DB_NAME',
    'port' => 3306,
];
```

Notes:

- DB host is often a remote hostname (not `localhost`)
- Values are read by `config/database.php`

### Step 5: Ensure writable uploads folder

Make sure this exists and is writable:

- `uploads/`

Typical permission:

- `755` or `775` (depends on host policy)

### Step 6: Test deployment

Open your domain/subfolder URL and confirm:

- Login page loads
- No DB connection error
- Basic pages (Dashboard, Projects) render correctly

---

## 5) First Login / Access Flow

- New account registrations are pending by default.
- Super Admin approves/rejects accounts from Admin Dashboard.
- Approved users can log in as employee/admin based on role.
- PS: Since first accounts don't have admins to accept for them, please proceed to phpMyAdmin and select your user > change is_active to 1 > select role (admin or employee)

---

## 6) Upgrade Existing Installation

If you already have an older DB and do NOT want a fresh import:

- Back up DB first.
- Run only needed scripts from `database/migrations/`.
- For fresh installs, prefer `database/db.sql`.

---

## 7) Troubleshooting

### A) "Database unavailable"

Check:

- `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, `DB_PORT`
- Host-provided DB hostname (not always localhost)
- DB user privileges
- MySQL service status

### B) HTTP 500 errors

Check:

- PHP version (`8.0+`)
- `mysqli` extension enabled
- File permissions
- Web server/PHP error logs

### C) Upload issues

Check:

- `uploads/` exists
- folder is writable by PHP process
- file size and MIME constraints in `config/database.php`

### D) UI changes not appearing after deploy

Hard refresh:

- `Ctrl+F5` (or clear browser/CDN cache)

---

## 8) Security Notes

- Never commit real production credentials into git.
- Keep `config/database.deploy.php` server-specific with real secrets.
- Disable debug output in production (`APP_DEBUG` should not be `1`).

