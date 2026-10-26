# Changelog

## v2.0.0 (2026-10-26)

### Added
- Docker stack: Apache 2.4 + PHP 8.3 (OPcache, JIT, APCu, phpredis, GD, Ghostscript), MySQL 8, Redis 7, ClamAV. Image published to GitHub Packages.
- `bin/docker-init.sh` (random local secrets) and `bin/create-admin.php` (CLI super-admin bootstrap).
- Upload pipeline: allow-list, magic bytes, size limits, ClamAV, image re-encoding, PDF rebuilding, random names, storage outside the web root, access-checked downloads.
- Redis-backed sessions, rate limits, data cache, and a full-page cache for anonymous visitors.
- SEO: per-page titles and descriptions, canonical, Open Graph, Twitter cards, JSON-LD (organisation, website, breadcrumbs, FAQ, programs), dynamic sitemap and robots.
- New public content: admissions process, key dates, eligibility, merit formula, fees, program facts, faculties, campus services, FAQ, privacy and terms. Campus photos, icons, illustrations.
- Admin: subject assignment for teachers, document list in the review modal, timetable clash detection.
- Tests: 23 backend checks and a 58-test Playwright suite with 14 personas; both run in CI.

### Security
- Argon2id with rehash on login, password policy, constant-time lookups.
- Captcha after three failed sign-ins; honeypot and signed timing on public forms.
- Strict nonce CSP with `script-src-attr 'none'`; all inline handlers removed; CDN assets self-hosted.
- `Sec-Fetch-Site` check alongside CSRF tokens; `.git` and other dot-directories denied.
- Per-account and per-IP rate limits with `Retry-After`.

### Fixed
- Applicant login redirected to a 404 page and counted correct passwords as failed attempts.
- Account lockouts never held in Docker because MySQL and PHP used different time zones.
- Applications without an entry-test date failed under MySQL strict mode.
- Review modals inside `<tbody>` broke the applications and teachers tables.
- The approval message hid the new student's temporary password.
- Admitted students were never enrolled in subjects, and some received department codes that do not exist.
- Semester and appeal actions skipped permission checks; sub-admin expiry was ignored on management pages.
- Teachers could write attendance and marks for students outside their class.
- Stored XSS sink in the timetable teacher picker; race condition in teacher and staff codes.
- AJAX forms kept stale captcha questions, so a corrected registration could never pass.
- Duplicate security headers, gzip pre-empting Brotli, and false CSP reports from parsed responses.

## v1.0.0 (2026-10-25)

First complete release: public site, applicant workflow, enrolled-student, teacher, staff and admin portals.
