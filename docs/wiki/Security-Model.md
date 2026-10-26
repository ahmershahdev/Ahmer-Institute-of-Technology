# Security model

- **Passwords**: Argon2id, rehashed on login; policy enforced on every change.
- **Sign-in**: lockout after 5 failures for 15 minutes, quick check (captcha) after 3, same timing for unknown accounts.
- **Requests**: CSRF token on every form plus a `Sec-Fetch-Site` check; rate limits per account and per IP (HTTP 429 with `Retry-After`).
- **Browser**: nonce-based CSP with no inline handlers, `X-Frame-Options: DENY`, `nosniff`, strict referrer and permissions policies.
- **Uploads**: allow-list, magic bytes, size limits, ClamAV, image re-encoding, PDF rebuild with Ghostscript, random names, storage outside the web root, downloads only after an ownership check and as sandboxed attachments.
- **Data**: prepared statements everywhere; row locks around approvals, uploads, appeals and assignments.

Report issues privately through the repository's Security tab.
