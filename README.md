# Ahmer Institute of Technology (AIT)

Ahmer Institute of Technology (AIT) is a PHP and MySQL admissions website for a university-style institution. The project combines a public-facing website, student admissions workflow, and admin review dashboard into one application.

## Overview

This codebase is structured to support a complete university website with:

- Home page
- About page
- Admissions and apply-online workspace
- FAQ page
- Contact page
- Terms of service page
- Privacy policy page
- Student dashboard
- Admin review dashboard
- Super admin access controls

The current implementation focuses on the admissions portal, document upload workflow, application review process, and secured admin login. Public informational pages can be added alongside the existing PHP pages without changing the core workflow.

## Key Features

- Responsive student dashboard with sidebar navigation and tab-style panes
- Apply Online form with grouped sections for personal, academic, and document data
- File validation with server-side size limits
- Automatic image compression to WebP for supported uploads larger than 1 MB
- Hard cap of 5 MB per uploaded file
- CSRF protection on forms
- CSP nonce generation for inline styles and scripts
- Secure session bootstrapping
- Admin login with a secret-key gate for super admin access
- Application review workflow with approval, rejection, and review notes
- Slip generation restricted to approved applications only

## Project Structure

- `home.php` - public homepage with carousel, feature cards, and footer navigation
- `dashboard.php` - student dashboard and application workspace
- `log-in.php` - student login page
- `registration.php` - student registration page
- `submit_application.php` - creates or updates application records
- `upload_challan.php` - challan receipt upload flow
- `generate_slip.php` - slip generation for approved students
- `admin/` - admin and super admin area
- `backend/data.php` - database bootstrap
- `backend/env.php` - `.env` loader
- `backend/security.php` - CSRF, CSP, and upload security helpers
- `assets/css/` - stylesheets
- `assets/js/` - frontend scripts
- `assets/images/` - logos and sample document visuals
- `uploads/` - generated uploaded files

## Pages and Modules

### Public Website Pages

The project is intended to grow into a full university website. The expected public pages include:

- Home
- About AIT
- Admissions
- Programs / degree listings
- Contact
- FAQ
- Terms of service
- Privacy policy
- Announcements / notices
- News and events

### Student Portal

- Register and sign in
- Complete admission profile
- Upload required documents
- Upload paid challan receipt
- Review application status
- Download slip after approval

### Admin Portal

- Login with email and password
- Super admin access protected by a shared secret key from `.env`
- Review incoming applications
- Approve or reject with a note
- Manage sub-admin access
- Track application status changes

## Upload Rules

- Images larger than 1 MB are compressed automatically when possible
- Maximum upload size is 5 MB per file
- Supported upload types include JPG, JPEG, PNG, WebP, and PDF depending on the field
- Unsupported or oversized files are rejected by the server

## Security Notes

- CSRF tokens are generated per session
- CSP uses a per-request nonce
- Passwords must be stored using `password_hash()`
- Super admin access requires both the account password and `SUPERADMIN_SECRET_KEY`
- Database credentials should live in `.env`, not in source files

## Configuration

Copy `.env.example` to `.env` and set your local values:

```env
DB_HOST=localhost
DB_USER=your_database_user
DB_PASS=your_database_password
DB_NAME=your_database_name
SUPERADMIN_SECRET_KEY=your-super-admin-secret-key
```

## Local Setup

1. Place the project inside your web server root, for example `C:\xampp\htdocs\muet`.
2. Create the database and import the application tables.
3. Copy `.env.example` to `.env` and update the values.
4. Ensure PHP has the required extensions enabled, especially `mysqli`, `fileinfo`, `gd`, and `openssl`.
5. Start Apache and MySQL from XAMPP.
6. Open the site in your browser and sign in or register a student account.

## Admin Access

The admin login page checks the `admins` table for the email and hashed password. Super admin accounts also require the secret key from `.env`.

If you forget the access details, reset them instead of trying to recover plaintext secrets:

- update the super admin secret key in `.env`
- update the admin password with a new `password_hash()` value
- confirm the admin email directly in the `admins` table

## Suggested Next Additions

- Home page with admissions highlights and notices
- About page with history, mission, and leadership
- Programs pages for BS, MS, and diploma offerings
- Faculty directory
- Contact form with map and support channels
- FAQ page with admissions and document questions
- Terms and privacy pages
- News and events feed

## Brand

Official project name: Ahmer Institute of Technology (AIT)

## Notes

- The dashboard uses responsive tab navigation for each workspace pane.
- Required document uploads are shown with sample reference images to guide students.
- Existing file names and assets can be renamed later if you want a full public rebrand from the old MUET naming.
