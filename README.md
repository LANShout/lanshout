LanShout — Local Development Quick Start

This guide helps you start the application locally using the included Docker infrastructure (Postgres, Redis, MailHog) while running Laravel and Vite on your host.


Prerequisites
- Docker and Docker Compose
- PHP 8.2+ with required extensions (pdo_pgsql, openssl, mbstring, tokenizer, xml, ctype, json)
- Composer
- Node.js 20+ and npm 10+

1) Start infrastructure
- docker compose up -d
  - Postgres: localhost:5432 (db=lanshout, user=lanshout, pass=lanshout)
  - Redis: localhost:6379
  - MailHog: SMTP on 1025, Web UI at http://localhost:8025

You can also use npm helpers:
- npm run infra:up
- npm run infra:down

2) Environment configuration
This repo already includes an .env configured for the Docker services:
- DB_CONNECTION=pgsql
- DB_HOST=localhost
- DB_PORT=5432
- DB_DATABASE=lanshout
- DB_USERNAME=lanshout
- DB_PASSWORD=lanshout
- SESSION_DRIVER=redis
- SESSION_STORE=redis
- REDIS_HOST=localhost
- MAIL_MAILER=smtp
- MAIL_HOST=localhost
- MAIL_PORT=1025

If you don’t have an .env yet (fresh clone), copy from the example and generate an app key:
- cp .env.example .env
- php artisan key:generate

3) Install dependencies
- composer install
- npm install

4) Prepare the database
Run migrations (and seeds):
- php artisan migrate --seed
The seed creates a test user: test@example.com with password password.

5) Run the application
Option A — one command (Laravel + Vite):
- npm start
  - Alias of npm run dev:app which runs php artisan serve and Vite together.

Option B — separate terminals:
- php artisan serve
- npm run dev

Then open http://localhost:8000 in your browser.

**Important Ports:**
- Laravel (main app): http://localhost:8000 ← **Use this URL**
- Vite dev server: http://localhost:5172 (automatically used by Laravel)
- Note: Vite port is 5172, NOT 5712

6) Log in and try the Chat
- Visit the home page → Log in (or Register) → Dashboard → Open Chat
- Post a message and see it appear in the list.

Email verification
- With MAIL_MAILER=smtp and MailHog running, check verification emails in http://localhost:8025.
- If a route requires verified email and you skipped verifying, you may see a 403 until verified.

Sessions using Redis
- The app uses Redis for sessions by default. Ensure the PHP Redis client is available locally.
  - .env defaults to REDIS_CLIENT=phpredis. If the extension is missing, either install it or switch to Predis:
    - Set REDIS_CLIENT=predis and run composer require predis/predis
- For more details, see doc/Sessions.md.

Troubleshooting
- 419 Page Expired / CSRF errors:
  - php artisan config:clear && php artisan cache:clear && php artisan route:clear
  - Ensure APP_KEY exists and Redis is running
  - Clear browser cookies for localhost
- DB connection errors:
  - Ensure docker compose up -d is running and ports 5432/6379 are free
  - Verify credentials in .env match docker-compose.yml
- Vite or PHP server port already in use:
  - php artisan serve --port=8001 and/or vite --port 5174
- Node or PHP version mismatch:
  - node -v should be 20+, php -v should be 8.2+

Handy scripts
- npm run infra:up — start Postgres/Redis/MailHog
- npm run infra:down — stop infra
- npm run dev:app — run php artisan serve and Vite together
- npm start — alias of dev:app
- composer run dev — alternative dev runner with queue and logs (see composer.json)

That’s it! If you hit any issues, file them with logs and your OS/PHP/Node versions.
