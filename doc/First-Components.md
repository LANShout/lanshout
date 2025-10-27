# LanShout – First Components to Develop (MVP Slice)

This document outlines the minimal, high‑value components to build first so the team can get a working end‑to‑end slice in place. Each item includes scope, dependencies, and a Definition of Done (DoD).

## Build Order Overview
1. Authentication basics (register, login, email verify)
2. Message domain (model, migration, repository/service)
3. Chat posting/listing (HTTP endpoints + Vue UI)
4. Real‑time foundation (events + broadcasting baseline)
5. Basic moderation (delete message)
6. Overlay endpoint (tokenized, read‑only feed)
7. Roles/Permissions skeleton + seeds
8. Seed data for local development

---

## 1) Authentication Basics
- Scope
  - User registration, login, logout
  - Email verification flow
  - Password reset (can be stubbed first if mail not fully wired)
- Dependencies: Laravel Breeze/Fortify or built‑in scaffolding; sessions and DB tables (users)
- DoD
  - Database migrations for `users` with necessary fields exist
  - Feature tests for register/login/verify pass
  - Routes and controllers wired; basic Vue pages exist (or Inertia views)
  - Mailer set to `log` in `.env` for local flow

## 2) Message Domain
- Scope
  - `messages` table with fields: id, user_id, body, created_at, deleted_at (soft delete)
  - Eloquent model `Message`
  - Validation constraints (e.g., max length)
- Dependencies: Auth (user_id), database
- DoD
  - Migration + model created
  - Indexes on (created_at), (user_id)
  - Factory for `Message`
  - Unit test: create/validate message

## 3) Chat Posting & Listing
- Scope
  - POST /messages (auth required)
  - GET /messages (paginated, newest last)
  - Vue components: ChatWall.vue (list), ChatInput.vue (post)
  - Simple content moderation hook (stub service returning sanitized body)
- Dependencies: 1, 2
- DoD
  - Feature tests: posting requires auth; invalid body rejected; list returns recent messages
  - Vue UI renders list and can submit a message (no real‑time yet)

## 4) Real‑Time Foundation
- Scope
  - Broadcast `MessagePosted` event
  - Configure broadcasting driver (start with `log` or Redis; later move to websockets)
  - Client listens via Laravel Echo (or placeholder until WS ready)
- Dependencies: 3
- DoD
  - Event dispatched on successful POST
  - Client receives and prepends message in the wall during dev (even if via polling fallback initially)

## 5) Basic Moderation (Minimal)
- Scope
  - Moderators/Admins can soft‑delete a message
  - Audit entry (stub) recorded
- Dependencies: 7 (roles), 2
- DoD
  - DELETE /messages/{id} (policy‑protected)
  - Feature tests: normal users cannot delete; moderators/admins can
  - UI: delete button visible only to authorized users

## 6) Overlay/OBS Endpoint
- Scope
  - Public read‑only view of last N messages, token‑guarded via URL (e.g., /overlay/{token})
  - Basic styles (line count, colors) from config table or `.env` defaults initially
- Dependencies: 2, 3
- DoD
  - Route + controller render minimal HTML/Vue for OBS browser source
  - Token validation works; invalid token returns 404
  - Manual test in OBS succeeds

## 7) Roles/Permissions Skeleton + Seeds
- Scope
  - Seed roles: Super Admin, Admin, Moderator, User
  - Policies for Message: create, delete
- Dependencies: 1
- DoD
  - Seeds create roles and attach to first admin user
  - Policies enforced in controllers and reflected in UI

## 8) Seed Data for Local Development
- Scope
  - User factory + admin seeder
  - Message factory with sample rows
- Dependencies: 1, 2
- DoD
  - `php artisan migrate --seed` yields an admin user and a few messages
  - README snippet with test credentials (to be added)

---

## Non‑Functional Baselines for MVP
- Input validation: server‑side length limits; basic profanity filter stub
- Rate limiting: per‑IP/user for posting (Laravel default throttle ok initially)
- Logging: actions logged to `stack` channel

## Suggested Next After MVP
- Full websocket setup (Redis + Laravel Echo Server or Soketi)
- Content moderation filters expansion
- API tokens for integrations (post/list messages)
- Admin screens for user/role management and chat settings
