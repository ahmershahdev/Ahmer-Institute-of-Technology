# admission/

Reserved namespace for future admission-cycle management tooling (cycle configuration,
program/seat management, bulk review workflows) — separate from the student-facing
application flow, which today lives at the project root:

- `registration.php` / `submit_application.php` — applicant signup + application submission
- `upload_challan.php` / `generate_challan.php` — admission fee challan flow
- `admin/dashboard.php` — application review, approval, and student-code issuance

Those files are not being moved here yet: they're deeply cross-linked through
`.htaccess` rewrite rules and relative hrefs across the whole site, so relocating them
is a dedicated migration with its own testing pass, not a drop-in move. New
admission-cycle tooling should be built under this folder going forward, reusing
`backend/session.php`, `backend/security.php`, and `backend/pdo.php` the same way
`admin/` and `teachers/` do.
