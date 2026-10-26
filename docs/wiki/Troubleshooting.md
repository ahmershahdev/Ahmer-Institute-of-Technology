# Troubleshooting

| Symptom | Fix |
| --- | --- |
| Portals show "We will be right back" (503) | The database is unreachable or `database/schema.sql` was never imported. Import it and check `.env`. |
| Uploads refused: "virus scanner is unavailable" | ClamAV is still downloading signatures (2-3 minutes after first start), or set `UPLOAD_AV_REQUIRED=0` without ClamAV. |
| "Too many attempts" | Rate limit; wait the minutes shown. |
| "Your session expired" on a form | Reload the page; forms expire with the session. |
| Account locked | Wait 15 minutes, or an admin can reset the password. |
| Styles look stale | Hard refresh; assets are versioned, so this only happens with edited files and an old cache. |
