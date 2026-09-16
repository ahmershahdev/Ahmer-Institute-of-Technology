# Security Policy

## Scope

This policy covers the AIT admissions portal, student dashboard, administrator dashboard, authentication endpoints, uploads, and database integrations.

## Reporting

Do not publish credentials, student data, uploaded documents, or exploit details in public issues. Report a suspected vulnerability privately to the project maintainer through the configured support channel.

Include:

- affected route and environment
- reproducible steps
- impact assessment
- sanitized request/response examples
- suggested mitigation, when available

## Security controls

- CSRF tokens for state-changing forms
- Per-request CSP nonces for inline scripts and styles
- Secure headers and same-origin framing policy
- Session regeneration after successful admin login
- Active and expiry checks for admin accounts
- Secret-key gate for super-admin authentication and recovery
- Rate limiting for login, recovery, uploads, and admin actions
- Prepared statements through MySQLi/PDO
- Password hashing with PHP `password_hash()` and verification with `password_verify()`
- Row locks and transactions for approval and credential updates
- Upload MIME/type/size checks, image re-encoding, PDF magic-byte validation, and executable-file blocking
- Unique database constraints and request idempotency guards

## Operational requirements

- Keep `.env` outside version control and rotate secrets after exposure.
- Run behind HTTPS in production.
- Use a least-privilege database account.
- Back up the database and uploads securely.
- Review Apache, PHP, and MySQL logs without recording secrets.
- Keep PHP, Apache, MySQL, Bootstrap, and other dependencies patched.

## Limitations

Application-level validation cannot prove that a file is free of every possible malware family. Production deployments should add antivirus scanning such as ClamAV or a managed malware-scanning service before making uploaded documents available to staff.
