# Security policy

## Reporting a vulnerability

Please report security issues privately through GitHub's **Report a vulnerability** button on the [Security tab](https://github.com/ahmershahdev/Ahmer-Institute-of-Technology/security/advisories/new). Do not open a public issue.

Include the affected URL or file, the steps to reproduce, and the impact you observed. You can expect an acknowledgement within three working days and a fix or mitigation plan within fourteen.

Only test against your own local copy (see the Docker quick start in the README). Do not test against the hosted demo with automated scanners.

## Supported versions

| Version | Supported |
| --- | --- |
| 2.x | Yes |
| 1.x | No; upgrade to 2.x |

## Controls in place

A summary is in the [README security section](README.md#security). In short:

- Argon2id password hashing, account lockout, captcha after repeated failures
- Per-session CSRF tokens with a `Sec-Fetch-Site` check
- Strict nonce-based Content Security Policy, no inline event handlers
- Redis-backed rate limiting per account and per IP
- Upload allow-list, magic-byte checks, ClamAV scanning, image re-encoding, PDF reconstruction, random names, storage outside the web root, access-checked downloads
- Row-level locks around every state change that two requests could race
- Prepared statements everywhere (MySQLi and PDO with native prepares)

## Operating it safely

- Keep `.env` / `.env.docker` out of version control and rotate `SUPERADMIN_SECRET_KEY` if it is ever exposed.
- Serve over HTTPS; HSTS is sent automatically when HTTPS is detected.
- Set `UPLOAD_ROOT` outside the web root and keep `UPLOAD_AV_REQUIRED=1`.
- Use a least-privilege database user and a password on Redis.
- Behind a reverse proxy, configure `mod_remoteip` so rate limits see the real client address.
