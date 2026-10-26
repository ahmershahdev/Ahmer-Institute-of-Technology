#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/.."
target=.env.docker
if [ -f "$target" ] && [ "${1:-}" != "--force" ]; then
  echo "$target already exists (use --force to regenerate)."; exit 0
fi
rand() { head -c 48 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c "${1:-32}"; }
db_pass=$(rand 32)
cat > "$target" <<ENV
MYSQL_ROOT_PASSWORD=$(rand 32)
MYSQL_DATABASE=ait
MYSQL_USER=ait_app
MYSQL_PASSWORD=$db_pass
DB_NAME=ait
DB_USER=ait_app
DB_PASS=$db_pass
APP_ENV=local
APP_URL=http://localhost:8080/AIT
SUPERADMIN_SECRET_KEY=$(rand 40)
UPLOAD_AV_REQUIRED=1
RECAPTCHA_V2_SITE_KEY=
RECAPTCHA_V2_SECRET_KEY=
RECAPTCHA_V3_SITE_KEY=
RECAPTCHA_V3_SECRET_KEY=
E2E_ADMIN_EMAIL=e2e.superadmin@ait.test
E2E_ADMIN_PASSWORD=Ait$(rand 20)9
ENV
chmod 600 "$target"
echo "Wrote $target"
