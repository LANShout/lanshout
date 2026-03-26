# LanCore SSO Integration Guide

> How to integrate a lan-software application with LanCore for single sign-on and identity management.

This document describes the SSO flow, the APIs your application must call, what LanCore must provide, and the architectural patterns used in LanShout as a reference implementation.

---

## Overview

LanCore is the central identity provider for the lan-software ecosystem. Each application ("integration app") delegates authentication to LanCore via an SSO authorization-code flow similar to OAuth 2.0, but simplified:

1. **Browser redirect** → LanCore login/consent page
2. **Browser redirect back** → Your app with a single-use `code`
3. **Server-to-server exchange** → Your app sends the `code` to LanCore, receives user data
4. **Local session** → Your app creates/updates a "shadow user" and logs them in

```
Browser                Your App (Server)           LanCore
  │                         │                         │
  │─── GET / ──────────────▶│                         │
  │◀── 302 to LanCore SSO ─│                         │
  │                         │                         │
  │─── GET /sso/authorize ──────────────────────────▶│
  │    ?app=yourapp                                   │
  │    &redirect_uri=...                              │
  │                         │                         │
  │     (user logs in if needed)                      │
  │                         │                         │
  │◀── 302 back ─────────────────────────────────────│
  │    ?code=<64-char code>                           │
  │                         │                         │
  │─── GET /callback?code= ▶│                         │
  │                         │── POST /api/integration │
  │                         │   /sso/exchange ───────▶│
  │                         │   { code: "..." }       │
  │                         │                         │
  │                         │◀── 200 { data: user } ──│
  │                         │                         │
  │                         │ (create/update shadow)  │
  │                         │ (log user in)           │
  │                         │                         │
  │◀── 302 to dashboard ───│                         │
```

---

## Prerequisites on the LanCore Side

LanCore must register your application as an integration and provide:

| Item | Description |
|------|-------------|
| **App slug** | A unique identifier for your app (e.g. `lanshout`, `lanwiki`) |
| **Integration token** | A `Bearer` token (prefixed `lci_...`) used for server-to-server API calls |
| **Scopes granted** | Which data your app can access: `user:read`, `user:email`, `user:roles` |

### LanCore API Endpoints Your App Uses

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/sso/authorize` | `GET` | None (browser) | SSO login redirect. Query params: `app`, `redirect_uri` |
| `/api/integration/sso/exchange` | `POST` | Bearer token | Exchange a 64-char code for user data |
| `/api/integration/user/resolve` | `POST` | Bearer token | Look up a user by `user_id` or `email` |

> **Important:** LanCore must exclude `/api/integration/*` from CSRF middleware, since these are server-to-server calls with Bearer token auth.

---

## Environment Configuration

Your app needs these environment variables:

```dotenv
# Toggle integration on/off
LANCORE_ENABLED=true

# LanCore URL the browser is redirected to (user-facing)
LANCORE_BASE_URL=http://lancore.lan

# LanCore URL for server-to-server calls from inside Docker
# Falls back to LANCORE_BASE_URL if unset
LANCORE_INTERNAL_URL=http://host.docker.internal:80

# Integration bearer token (secret)
LANCORE_TOKEN=lci_your_token_here

# Your app's slug as registered in LanCore
LANCORE_APP_SLUG=yourapp

# Where LanCore redirects the browser after login
LANCORE_CALLBACK_URL=http://localhost:81/auth/lancore/callback

# Optional HTTP client tuning
LANCORE_TIMEOUT=5          # seconds
LANCORE_RETRIES=2          # retry count on connection failures
LANCORE_RETRY_DELAY=100    # ms between retries
```

### Docker Networking

When your app runs in Docker, `localhost` in `LANCORE_BASE_URL` points to the browser's host machine, not the Docker network. The server-to-server API calls need a different hostname:

- **`LANCORE_BASE_URL`** — Used for **browser redirects** (the SSO authorize URL). This must be reachable from the user's browser.
- **`LANCORE_INTERNAL_URL`** — Used for **server-to-server API calls** (code exchange, user resolve). This must be reachable from inside your container. Typically `http://host.docker.internal:80` or the Docker service name of LanCore.

If `LANCORE_INTERNAL_URL` is not set, the HTTP client falls back to `LANCORE_BASE_URL`.

---

## Database Schema

Add these columns to your `users` table:

```php
Schema::table('users', function (Blueprint $table) {
    $table->unsignedBigInteger('lancore_user_id')->nullable()->unique()->after('id');
    $table->string('display_name')->nullable()->after('name');
    $table->string('avatar_url')->nullable()->after('locale');
    $table->timestamp('lancore_synced_at')->nullable()->after('avatar_url');

    // Make nullable for LanCore shadow users who have no local password/email
    $table->string('password')->nullable()->change();
    $table->string('email')->nullable()->change();
});
```

| Column | Type | Purpose |
|--------|------|---------|
| `lancore_user_id` | `unsigned bigint, nullable, unique` | The user's ID in LanCore. `null` = local-only user |
| `display_name` | `string, nullable` | User-chosen display name (not overwritten on sync) |
| `avatar_url` | `string, nullable` | Avatar URL from LanCore |
| `lancore_synced_at` | `timestamp, nullable` | When user data was last synced from LanCore |

---

## Implementation Components

### 1. Configuration File (`config/lancore.php`)

A dedicated config file that reads all `LANCORE_*` env vars with sensible defaults. The `internal_url` key is the critical Docker fix.

### 2. LanCore HTTP Client (`LanCoreClient`)

A singleton service that handles all communication with LanCore:

```php
// Register as singleton in AppServiceProvider
$this->app->singleton(LanCoreClient::class);
```

Key behaviors:
- All API calls use `Authorization: Bearer {token}` headers
- `ssoAuthorizeUrl()` uses `base_url` (browser-facing)
- `exchangeCode()` and `resolveUser*()` use `internal_url` (server-to-server)
- Automatic retries on `ConnectionException` (configurable count/delay)
- Throws typed exceptions: `LanCoreDisabledException`, `LanCoreRequestException`, `InvalidLanCoreUserException`

### 3. LanCore User DTO (`LanCoreUser`)

A `readonly` class that maps the JSON response from LanCore to typed PHP properties:

```php
readonly class LanCoreUser
{
    public function __construct(
        public int $id,           // Always present (user:read scope)
        public string $username,  // Always present (user:read scope)
        public ?string $locale,   // user:read scope
        public ?string $avatar,   // user:read scope
        public ?string $createdAt,// user:read scope
        public ?string $email,    // Only with user:email scope
        public ?array $roles,     // Only with user:roles scope
    ) {}
}
```

Scope-dependent fields are nullable. Always check before using them.

### 4. User Sync Service (`UserSyncService`)

The "shadow user" pattern — creates or updates a local user from LanCore data:

- **First login:** Creates a new user with `lancore_user_id`, synced fields, no password, auto-verified email
- **Repeat login:** Updates `name`, `avatar_url`, `locale`, `email` from LanCore. Preserves the local `display_name` if already set
- **`lancore_synced_at`** is updated on every sync

```php
$user = $syncService->resolveFromUpstream($lanCoreUser);
// Returns existing or newly created User model
```

### 5. Auth Controller (`LanCoreAuthController`)

Three endpoints:

| Route | Method | Purpose |
|-------|--------|---------|
| `/auth/lancore/redirect` | `GET` | Redirects browser to LanCore SSO |
| `/auth/lancore/callback` | `GET` | Receives code, exchanges it, logs user in |
| `/auth/lancore/status` | `GET` | Returns `{ enabled: bool }` JSON |

The callback validates the code is exactly 64 characters, exchanges it server-to-server, creates/updates the shadow user, and calls `Auth::login($user, remember: true)`.

### 6. Auto-Redirect Behavior

When `LANCORE_ENABLED=true`:

- **Landing page (`/`)** — Unauthenticated users are automatically redirected to LanCore SSO
- **Login page (`/login`)** — Automatically redirects to LanCore SSO
- **Local login fallback** — Accessing `/login?local` shows the traditional login form (for admin fallback)
- **Authenticated users** — Always redirected to the dashboard, ignoring LanCore redirects

---

## LanCore API Response Format

### SSO Code Exchange (`POST /api/integration/sso/exchange`)

**Request:**
```json
{
    "code": "a1b2c3...64_character_hex_string"
}
```

**Response (200):**
```json
{
    "data": {
        "id": 42,
        "username": "johndoe",
        "email": "john@example.com",
        "locale": "en",
        "avatar_url": "https://lancore.lan/avatars/42.jpg",
        "created_at": "2025-01-15T10:30:00Z",
        "roles": ["user", "moderator"]
    }
}
```

**Error (400 — code expired/used):**
```json
{
    "error": "The authorization code has expired or was already used."
}
```

### User Resolve (`POST /api/integration/user/resolve`)

**Request (by ID):**
```json
{
    "user_id": 42
}
```

**Request (by email):**
```json
{
    "email": "john@example.com"
}
```

**Response format is identical to the SSO exchange.**

---

## Exception Handling

| Exception | When | HTTP Status |
|-----------|------|-------------|
| `LanCoreDisabledException` | `LANCORE_ENABLED=false` and a LanCore method is called | 503 |
| `LanCoreRequestException` (code 400) | SSO code expired/used, bad request | Redirect with user-friendly error |
| `LanCoreRequestException` (code 401/403) | Invalid integration token | Redirect with "try again later" |
| `LanCoreRequestException` (connection) | LanCore unreachable | Redirect with "try again later" |
| `InvalidLanCoreUserException` | LanCore returned a user with `id=0` or empty username | Redirect with "incomplete info" |

All errors redirect back to the home page with a flash `error` message. No raw exceptions are exposed to the user.

---

## User Model Changes

Add these to your User model:

```php
protected $fillable = [
    // ... existing fields
    'lancore_user_id',
    'display_name',
    'avatar_url',
    'lancore_synced_at',
];

protected function casts(): array
{
    return [
        // ... existing casts
        'lancore_synced_at' => 'datetime',
    ];
}

public function isLanCoreUser(): bool
{
    return $this->lancore_user_id !== null;
}
```

---

## Testing Notes

- **Fake the `internal_url`** in test `beforeEach` to avoid `.env` config bleed:
  ```php
  beforeEach(function () {
      config(['lancore.internal_url' => null]);
  });
  ```
- Use `Http::fake()` to mock LanCore API responses in feature tests
- Use `Event::fake()` when testing flows that trigger broadcastable events
- The test suite should cover: successful SSO, expired code, unreachable LanCore, invalid token, incomplete user data, disabled integration, auto-redirect behavior

---

## Checklist for New Applications

1. [ ] Register your app in LanCore (get slug, token, scopes)
2. [ ] Add `LANCORE_*` env vars
3. [ ] Create `config/lancore.php`
4. [ ] Add LanCore columns to users table migration
5. [ ] Create `LanCoreClient`, `LanCoreUser`, `UserSyncService`
6. [ ] Create exception classes
7. [ ] Register `LanCoreClient` as singleton
8. [ ] Create `LanCoreAuthController` with redirect/callback/status
9. [ ] Add routes: `/auth/lancore/{redirect,callback,status}`
10. [ ] Add auto-redirect logic to landing/login pages
11. [ ] Add `?local` fallback for admin login
12. [ ] Exclude `/api/integration/*` from CSRF on the **LanCore** side
13. [ ] Configure `LANCORE_INTERNAL_URL` for Docker environments
14. [ ] Write tests covering all SSO paths
