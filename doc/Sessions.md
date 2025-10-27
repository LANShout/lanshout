# Sessions in LanShout (Local Development)

This project initially used the `database` session driver for MVP stability. Since Docker provides Redis out of the box, you can confidently use Redis for sessions in local development (and production) as well.

## Supported drivers (Laravel)
- file: simple, no extra services; not ideal for containers / multiple instances
- cookie: stateless but limited and exposes data (encrypted)
- database: reliable, queryable; requires `sessions` table
- redis: fast, suitable for horizontal scaling; requires Redis service

## Current default (updated)
The `.env` is configured to use Redis sessions:

```
SESSION_DRIVER=redis
SESSION_STORE=redis
```

Redis is provided by `docker-compose.yml` (localhost:6379). `.env` already includes:

```
REDIS_CLIENT=phpredis
REDIS_HOST=localhost
REDIS_PORT=6379
```

## How to switch drivers
- To Redis (recommended with Docker):
  - `SESSION_DRIVER=redis`
  - `SESSION_STORE=redis` (uses the `redis` cache store from config/cache.php)
- To Database (previous setting):
  - `SESSION_DRIVER=database`
  - Ensure the `sessions` table exists: `php artisan migrate`

After changing drivers, clear caches:
```
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

Then log out and log back in.

## Troubleshooting
- Redis not running: `docker compose up -d redis` and ensure port 6379 is free.
- PHP Redis extension: `.env` uses `REDIS_CLIENT=phpredis`. Install the `phpredis` extension locally, or switch to Predis by setting `REDIS_CLIENT=predis` and adding `predis/predis` via Composer.
- 419 Page Expired (CSRF)/session issues:
  - Clear browser cookies for the app domain
  - Run `php artisan config:clear` and restart the PHP server
  - Verify `APP_KEY` exists and `APP_URL` matches your access URL
- Mail login/verification flows: with `MAIL_MAILER=smtp` and MailHog at `http://localhost:8025`, check verification links in MailHog.

## Notes
- The `sessions` migration remains in the repo; it is harmless when using Redis and useful if you switch back to `database` sessions.
- For production, prefer Redis or database over file; choose based on infra and scaling requirements.


## Migration note
- The `sessions` table migration has been made idempotent. If the table already exists (e.g., when switching drivers or after a partial rollback), running `php artisan migrate` will skip creating it again.
- If you need a clean slate, you can use `php artisan migrate:fresh --seed` (this drops all tables) or manually drop the `sessions` table in your database.
