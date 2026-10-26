# Work summary: AIT v2.0.0

A short, plain record of everything changed in this round of work.
Details live in [CHANGELOG.md](CHANGELOG.md), the [README](README.md) and the [wiki](https://github.com/ahmershahdev/Ahmer-Institute-of-Technology/wiki).

## Result at a glance

| Item | Status |
| --- | --- |
| End-to-end tests (Playwright, 14 personas) | 58 / 58 passing, locally and in GitHub CI |
| Backend tests (uploads, ClamAV, passwords, limits, captcha, cache) | 23 / 23 passing |
| Release | v2.0.0 published; Docker image pushed to GitHub Packages |
| Wiki | Live: Home + 7 guides + sidebar |
| Pull requests | All 7 Dependabot PRs resolved (6 merged, 1 superseded); only `main` remains |
| Docker image | Now on PHP 8.5 with phpredis 6.3.0 and APCu 5.1.28, verified by the full CI suite |
| Dependabot security alert (Playwright) | Fixed by upgrading to 1.64.1 |

## 1. Infrastructure
- **Docker stack**: Apache + PHP 8.3 (OPcache with JIT, APCu, phpredis, GD, Ghostscript), MySQL 8, Redis 7, ClamAV.
- **Caching layers**: OPcache, Redis (sessions, rate limits, data), APCu, a Redis full-page cache for visitors, one-year immutable caching for versioned assets.
- **Compression**: Brotli first, gzip fallback.
- **Tools**: `bin/docker-init.sh` creates random local secrets; `bin/create-admin.php` creates an admin from the command line. No passwords or keys live in the code.

## 2. Security
- **Uploads**: allow-list, magic-byte check, size limits, ClamAV virus scan, images re-encoded, PDFs rebuilt (scripts, attachments, encryption rejected), random file names, storage outside the web root, downloads only after an ownership check, sent as sandboxed attachments.
- **Sign-in**: Argon2id hashing, password policy, lockout after 5 wrong passwords, quick check after 3, temporary passwords must be replaced.
- **Requests**: CSRF tokens plus a `Sec-Fetch-Site` check, rate limits per account and per IP (429 with Retry-After), honeypot and timing checks on public forms.
- **Browser**: strict nonce-based CSP, no inline event handlers, `X-Frame-Options: DENY` and the other security headers, CDN libraries self-hosted.
- **Exposure**: `.env`, `.git`, SQL, tooling and docs are never served.

## 3. SEO and content
- Every public page: title under 60 characters, description under 150, canonical URL, Open Graph and Twitter tags, one `h1`, clean heading order, image alt text, breadcrumbs.
- JSON-LD: CollegeOrUniversity, WebSite, WebPage, BreadcrumbList, FAQPage, and an ItemList of programs.
- Generated `sitemap.xml` and `robots.txt`; refreshed `llms.txt` and `llms-full.txt`.
- New content modelled on a real university site structure (written fresh, nothing copied): admissions steps, dates, eligibility, merit formula, fees, programs with credit hours and seats, faculties, campus services, FAQ, privacy and terms.
- Images: responsive WebP campus photos, a social-sharing image, icons, six original illustrations.

## 4. Bugs found and fixed
| Bug | Effect before the fix |
| --- | --- |
| Applicant login redirected to `/student/dashboard` | Every applicant landed on a 404 after signing in |
| Correct password counted as a failure for admitted students | Admitted students could lock themselves out |
| MySQL and PHP used different time zones | Account lockouts never actually held |
| Empty entry-test date sent as `''` | Every application without a test date failed |
| Review pop-ups nested inside `<tbody>` | Admin applications and teachers tables were broken |
| Temporary password overwritten in the approval message | Admin never saw the new student's password |
| No subject enrolment or teacher assignment | Teachers had empty classes; students had no timetable |
| Wrong department codes on approval | Some admitted students belonged to departments that do not exist |
| Missing permission checks, sub-admin expiry ignored | Sub-admins could act outside their role |
| Teachers could mark students outside their class | Attendance and marks could be written for anyone |
| Teacher names inserted as HTML in the timetable picker | Stored XSS risk in the admin panel |
| `.git` folder readable over the web | Source history exposed |
| AJAX forms kept old captcha questions | Corrected registrations could never pass |
| Generated temporary passwords sometimes had no lowercase letter | About 1 in 500 broke the password policy |
| XAMPP had no `ait` database | All portal pages failed ("login not opening") |

## 5. Pages and UI
- **Error pages**: one branded design for 404, 403, 429, 500 and a 503 "back shortly" page when the database is down. Light and dark mode.
- **Offline page**: shown by a service worker when the visitor loses connection (public pages only, so it never touches logged-in sessions).
- **Quick security check (captcha)**: now a themed card with a shield icon that fits every portal, light or dark. The hidden anti-bot field is never visible to people.
- **Admin**: list of each applicant's uploaded documents, subject assignment for teachers, timetable clash detection.
- **Student**: shows "verified" once a receipt is accepted; forced password change after admission.

## 6. Tests and CI
- `tests/php/run.php`: 23 backend checks, including a real EICAR test file caught by ClamAV.
- `tests/e2e`: 58 Playwright tests. Personas include applicants, a super admin, two sub-admins, a teacher, a head of department, a security guard and an attacker (IDOR, CSRF, XSS, SQL injection, brute force, file exposure).
- Coverage meaning: every page, every portal action and every route is exercised. Line-by-line code coverage is not measured.
- GitHub CI builds the Docker stack and runs both suites on every push; tagging `vX.Y.Z` publishes a release and the Docker image.

## 7. GitHub repository
- Description and 20 topics updated; wiki live; issues on; projects off.
- **Ruleset "Protect main"**: no deletion, no force-push, linear history, PRs required with all 3 CI checks passing and threads resolved (repo admin can bypass).
- **Ruleset "Protect release tags"**: `v*` tags cannot be deleted, moved or rewritten.
- Merging: squash only (PR title as commit), auto-merge and update-branch on, branches deleted after merge.
- Actions: default token is read-only; workflows request write access only where needed.
- Community files: CODEOWNERS, bug and feature issue forms, PR template, security contact link.
- Security: Dependabot alerts and automatic fixes, secret scanning with push protection, private vulnerability reporting.
- Dependabot config for GitHub Actions, npm and Docker.

## 8. Left for you
- **Local admin on XAMPP**: `php bin/create-admin.php --email=you@example.com`
- **Production**: when `ait.ahmershah.dev` goes live, set `APP_URL` and the `CONTACT_*` values in its environment.
