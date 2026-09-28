# Deploying BizOps on a fresh Ubuntu server

A complete, copy-pasteable runbook to bring the app up on an **empty Ubuntu
24.04 LTS** box, native (no Docker). It covers the multi-tenant specifics that a
generic Laravel guide misses: wildcard subdomains + wildcard TLS, a Postgres role
that can create databases at runtime, the central-pinned queue, the scheduler,
and the `app:validate-env` gate.

> Substitute `example.com` with your real domain and `deploy` with your login
> user throughout. Commands assume a non-root sudo user.

---

## 0. Before you start — DNS

The platform identifies tenants by subdomain, so DNS must resolve **the apex and
every subdomain** to the server:

| Record | Type | Value |
|--------|------|-------|
| `example.com` | A (+ AAAA) | server IP |
| `*.example.com` | A (+ AAAA) | server IP |

The apex serves the marketing/login shell, `dashboard.example.com` is the SaaS
admin console, and each tenant lives at `its-slug.example.com`. The wildcard is
mandatory — without it new tenants don't resolve.

---

## 1. System packages

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl git unzip acl

# PHP 8.3 (ppa:ondrej/php) + the extensions this app uses
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-pgsql php8.3-redis \
  php8.3-gd php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip \
  php8.3-bcmath php8.3-intl php8.3-opcache

# Nginx, PostgreSQL, Redis
sudo apt install -y nginx postgresql postgresql-contrib redis-server

# Node 20 (to build the Vue/Inertia front-end) + Composer
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

`php8.3-gd` is required (native image processing for job photos); `php8.3-pgsql`
for the database; `php8.3-redis` is optional since the app uses the pure-PHP
`predis` client, but harmless to install.

---

## 2. PostgreSQL — a role that can create databases

Each tenant gets **its own database, created at runtime** by the app, so the
connection role needs `CREATEDB`.

```bash
sudo -u postgres psql <<'SQL'
CREATE ROLE bizops WITH LOGIN PASSWORD 'STRONG_DB_PASSWORD' CREATEDB;
CREATE DATABASE bizops_central OWNER bizops;
SQL
```

> `CREATEDB` (not superuser) is enough and is the least-privilege option. If you
> use **managed Postgres** that forbids `CREATEDB`, you must pre-create tenant
> databases another way — out of scope here; a self-managed role is simplest.

Redis needs no config for a single box (localhost, no password) — it's already
running. For a public/managed Redis, set a password and update `.env`.

---

## 3. Get the code & build

```bash
sudo mkdir -p /var/www && sudo chown $USER:$USER /var/www
cd /var/www
git clone <YOUR_REPO_URL> bizops
cd bizops

composer install --no-dev --optimize-autoloader
npm ci && npm run build          # compiles public/build (no Node needed at runtime)
```

---

## 4. Environment

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

Set these for production (the rest of `.env.example` is fine):

```dotenv
APP_NAME=BizOps
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com
CENTRAL_DOMAIN=example.com
ADMIN_DOMAIN=dashboard.example.com
APP_LOCALE=tr

# Database (central) — the role from step 2
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=bizops_central
DB_USERNAME=bizops
DB_PASSWORD=STRONG_DB_PASSWORD

# Sessions & cache
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1

# Queue — MUST stay pinned to the central Postgres connection
QUEUE_CONNECTION=database
DB_QUEUE_CONNECTION=pgsql

# Mail — see §12. Until SMTP is set, mail is written to the log only.
MAIL_MAILER=log

# File storage — local disk with signed URLs (MVP). For S3, set the AWS_* keys
# and FILESYSTEM_DISK=s3.
FILESYSTEM_DISK=local
```

Then set permissions so `www-data` (php-fpm/nginx) can write runtime dirs:

```bash
sudo chown -R $USER:www-data /var/www/bizops
sudo find /var/www/bizops -type f -exec chmod 664 {} \;
sudo find /var/www/bizops -type d -exec chmod 775 {} \;
sudo chmod -R ug+rwx storage bootstrap/cache
php artisan storage:link
```

---

## 5. Migrate & bootstrap the platform

```bash
php artisan migrate --force            # central DB (users, sessions, jobs, tenants…)
```

Create the **first central super-admin** (any user on the central domain is a
SaaS admin). Do NOT run `db:seed` in production — that seeder creates a weak
demo admin and a demo tenant. Create a real one:

```bash
php artisan tinker
>>> App\Models\User::create([
...   'name' => 'Platform Admin',
...   'email' => 'you@example.com',
...   'password' => Illuminate\Support\Facades\Hash::make('A-STRONG-PASSWORD'),
...   'email_verified_at' => now(),
... ]);
```

Log in at `https://dashboard.example.com` and provision tenants from the admin
console (each tenant's database is created, migrated and seeded automatically).
When you later ship a schema change, run tenant migrations for everyone:

```bash
php artisan tenants:migrate --force            # or: tenants:migrate-batched for many tenants
```

---

## 6. Warm caches & validate

```bash
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache

php artisan app:validate-env    # hard gate: https URL, APP_DEBUG=false,
                                # persistent session/cache, central-pinned queue…
php artisan app:health-check    # DB + cache + storage round-trip
```

Fix anything `app:validate-env` reports before serving traffic.

---

## 7. Nginx — one server block for the apex **and** all subdomains

`/etc/nginx/sites-available/bizops`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name example.com *.example.com;
    root /var/www/bizops/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;

        # Inertia/Vite emit a large `Link:` modulepreload header (one entry per JS
        # chunk) that overflows nginx's default FastCGI buffers → 502 "upstream
        # sent too big header". These sizes give it ample room.
        fastcgi_buffer_size 32k;
        fastcgi_buffers 16 16k;
        fastcgi_busy_buffers_size 32k;
    }

    location ~ /\.(?!well-known).* { deny all; }

    client_max_body_size 20M;   # job photos / attachments
}
```

`server_name example.com *.example.com` is the key line — Laravel routes by host,
so one docroot serves the central app **and** every tenant subdomain.

> **Match the FPM socket to your installed PHP version.** `fastcgi_pass` above
> assumes PHP 8.3. If you installed a different minor (e.g. 8.4, the default on
> newer Ubuntu), the socket is `/run/php/php8.4-fpm.sock` — a mismatch gives a 502
> with `connect() to unix:/run/php/phpX.Y-fpm.sock failed (2: No such file or
> directory)` in `/var/log/nginx/error.log`. Confirm the real path with
> `ls /run/php/` and update the line (composer allows `^8.3`, so any 8.3+ is fine).

```bash
sudo ln -s /etc/nginx/sites-available/bizops /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

---

## 8. TLS — a **wildcard** certificate

A wildcard (`*.example.com`) cert requires a **DNS-01** challenge, so you need
your DNS provider's Certbot plugin (Cloudflare shown; Route53/others exist too):

```bash
sudo apt install -y certbot python3-certbot-nginx python3-certbot-dns-cloudflare

# Store your DNS API token (chmod 600), then:
sudo certbot certonly \
  --dns-cloudflare --dns-cloudflare-credentials /root/.secrets/cloudflare.ini \
  -d example.com -d '*.example.com' \
  --agree-tos -m you@example.com --no-eff-email
```

Then point the nginx block at the cert (add a `listen 443 ssl;` server, or let
`certbot --nginx` wire it) and redirect 80→443. Certbot auto-renews via its
systemd timer. If your DNS provider has no plugin, use `--manual --preferred-
challenges dns` and add the TXT record by hand (renewals then need manual steps).

---

## 9. Queue worker (systemd)

The worker processes the central `jobs` table and re-initializes the right tenant
per job. `/etc/systemd/system/bizops-worker.service`:

```ini
[Unit]
Description=BizOps queue worker
After=network.target postgresql.service redis-server.service

[Service]
User=www-data
Group=www-data
Restart=always
WorkingDirectory=/var/www/bizops
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
StartLimitInterval=0

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now bizops-worker
```

Scale by adding `bizops-worker@` instances or raising the count; each is safe
(jobs carry their tenant id).

---

## 10. Scheduler (cron)

One cron entry drives all scheduled jobs (due-soon reminders hourly, quotation
expiry daily 02:00, tenant backups daily 01:00 — each `onOneServer`):

```bash
sudo crontab -u www-data -e
```
add:
```cron
* * * * * cd /var/www/bizops && php artisan schedule:run >> /dev/null 2>&1
```

---

## 11. Verify

```bash
curl -fsS https://example.com/up        # framework health endpoint → 200
```

Then: load `https://dashboard.example.com`, sign in as the central admin,
provision a tenant, and confirm it responds at `https://<slug>.example.com`.

---

## 12. Mail (so notifications, invites & e-quote links can send)

The app ships with `MAIL_MAILER=log` — the notification engine fires but nothing
is delivered; invitations and quotation-accept links are still usable via the
**copyable links** in the UI. To send real email, point it at an SMTP provider:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS="no-reply@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```
then `php artisan config:cache`.

---

## 13. Updating a running deployment

```bash
cd /var/www/bizops
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan tenants:migrate --force        # (batched for many tenants)
php artisan config:cache && php artisan route:cache && php artisan event:cache && php artisan view:cache
php artisan queue:restart                  # workers pick up new code
php artisan app:validate-env && php artisan app:health-check
php artisan up
```

Roll-forward/rollback discipline (expand-and-contract migrations) is in
[`deployment.md`](deployment.md) and [`rollback.md`](rollback.md).

---

## 14. Backups

- **App-level, per tenant:** `php artisan tenants:backup` (encrypted logical JSON;
  runs nightly via the scheduler). Restore with `tenants:restore`. See
  [`backups.md`](backups.md).
- **Whole cluster:** `pg_dump`/managed snapshots of Postgres, plus object-storage
  versioning if you move files to S3. Keep the `APP_KEY` backed up separately —
  encrypted settings and backups are unreadable without it.

---

## 15. Hardening checklist

- `ufw allow OpenSSH && ufw allow 'Nginx Full' && ufw enable`
- SSH keys only, disable password login.
- Keep `APP_DEBUG=false` (enforced by `app:validate-env`).
- `SESSION_SECURE_COOKIE=true` (set above) — cookies only over HTTPS.
- OPcache in production: `opcache.validate_timestamps=0` (re-`config:cache` and
  reload php-fpm on each deploy so new code is picked up).
- Restrict the Postgres role to `CREATEDB` (not superuser), and Redis to
  localhost or a password.
```
