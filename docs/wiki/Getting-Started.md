# Getting started

## Docker (recommended)
```bash
sh bin/docker-init.sh                # random secrets in .env.docker
docker compose up -d --build --wait  # web, MySQL 8, Redis 7, ClamAV
docker compose exec -e AIT_ADMIN_PASSWORD='a-strong-password' web \
  php bin/create-admin.php --email=you@example.com --name="Your Name"
```
Open http://localhost:8080/AIT/. Admin sign-in also asks for `SUPERADMIN_SECRET_KEY` from `.env.docker`.

## XAMPP
1. Put the project in `htdocs/AIT`.
2. Import `database/schema.sql` in phpMyAdmin. Without this every portal shows the "We will be right back" page.
3. Copy `.env.example` to `.env`; set the database credentials and `SUPERADMIN_SECRET_KEY`.
4. `php bin/create-admin.php --email=you@example.com`
5. Set `UPLOAD_AV_REQUIRED=0` if you are not running ClamAV.
