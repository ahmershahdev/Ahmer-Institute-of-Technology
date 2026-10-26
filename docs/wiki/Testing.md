# Testing

```bash
docker compose exec web php tests/php/run.php   # backend: uploads, ClamAV, passwords, limits, captcha, cache
cd tests/e2e && npm install && npx playwright install chromium
npx playwright test                              # 58 end-to-end tests
npm run docs:assets                              # regenerate README screenshots and diagrams
```

The E2E suite follows one admissions cycle with 14 personas (applicants, admins, sub-admins, a teacher, staff, and an attacker). CI runs both suites on every push.
