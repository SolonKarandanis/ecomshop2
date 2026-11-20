# Deployment

- This app does not use Laravel Cloud, Forge or Vapor. The backend runs on Hostinger shared hosting at `api.solonkarandanis.shop`; the Nuxt frontend is a separate Hostinger Node.js site deployed by Hostinger's own git webhook.
- The backend deploys from `.github/workflows/deploy.yml`: on a push to `main` that touches `backend/`, once the Pest suite passes against MariaDB 11.8, it SSHes in, resets to `origin/main`, runs `composer install --no-dev`, `migrate --force`, `db:seed --force`, and rebuilds the config, route and view caches.
- Production installs without dev dependencies, so runtime code must never reference dev packages (Boost, Pail, Pint, Larastan, Pest, Faker).
- Seeders run on every deploy, so they must be idempotent (`firstOrCreate`/`updateOrCreate`, never blind inserts).
- Migrations run unattended against MariaDB, which enforces ENUMs and JSON validity that local SQLite does not. Keep them safe to run without a prompt.
- The server's default CLI `php` is 8.4; every server-side PHP command must use `/opt/alt/php85/usr/bin/php`.
- Hostinger disables `symlink()`, `exec()` and `proc_open()` in PHP, so do not rely on them at runtime; the deploy creates the `public/storage` link with `ln -s` instead of `storage:link`.
- There is no `crontab`; `schedule:run` is configured as a Custom cron job in hPanel.
