# admission/

Reserved namespace for future admission-cycle management tooling (cycle configuration,
program/seat management, bulk review workflows) — separate from the student-facing
application flow, which now lives under `student/`:

- `student/registration.php` / `student/submit_application.php` — applicant signup + application submission
- `student/upload_challan.php` / `student/generate_challan.php` — admission fee challan flow
- `admin/dashboard.php` — application review, approval, and student-code issuance

New admission-cycle tooling should be built under this folder going forward, reusing
`backend/session.php`, `backend/security.php`, and `backend/pdo.php` the same way
`admin/`, `staff/`, and `teachers/` do.
