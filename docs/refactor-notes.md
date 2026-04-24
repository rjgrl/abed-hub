# ABED Hub Refactor Notes

## Implemented foundations

- Central dashboard entrypoint is now `dashboard.php` with role-based routing.
- Login supports explicit `Employee Login` and `Admin Login (Super Admin)`.
- New employee accounts are created as inactive and require Super Admin approval.
- Super Admin dashboard is available at `admin-dashboard.php`:
  - approve/reject pending accounts
  - manage recent employee uploads
- Browser print buttons in reports were replaced with mPDF export route:
  - `exports/reports-pdf.php`
- Shared PDF service added at `services/PDFService.php` with reusable function:
  - `generatePDF($data)`
- API standardization foundation added via `api/common.php`:
  - shared auth and role guards for API endpoints
  - consistent JSON success/error response helpers
- `api/batch.php` now enforces elevated roles and fixes invalid switch case label.
- Module dashboards are centralized:
  - `fspf-dashboard.php`, `idp-dashboard.php`, `afme-dashboard.php` now redirect to `dashboard.php?module=...`
  - `dashboard-enhanced.php` supports a `module` query filter for a single reusable layout

## mPDF dependency

Install once in project root:

```bash
composer require mpdf/mpdf
```

`exports/reports-pdf.php` will show a clear error message if mPDF is missing.

## Phase 5 normalization (last)

Use migration script:

- `database/migrations/2026_04_23_phase5_normalization.sql`

It introduces normalized financial entries while preserving backward compatibility.
