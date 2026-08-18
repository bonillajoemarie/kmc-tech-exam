# Support Ticket Management App

A Zendesk-inspired support ticket application built as the KMC Senior Full Stack Developer technical exam.

**Stack:** Laravel 13 (Passport auth + REST API + Filament 5 admin) · React 19 + TypeScript + Vite (customer frontend) · MySQL 8.4 · Redis · Reverb (realtime).

## Features

- **Auth** — customer register/login/logout + "me" via Laravel Passport personal access tokens (`Authorization: Bearer <token>`); single `users` table with `admin` and `customer` roles (spatie/laravel-permission).
- **Ticket lifecycle** — customers create and track their own tickets (auto ticket numbers `TK-XXXXXX-YYMMDD`); statuses (`open`, `pending`, `resolved/closed`), priorities (`low`–`urgent`), categories (billing, technical, account, feature request, other) as lookup tables.
- **Comments + internal comments** — public comment threads on tickets; staff can add `is_internal` comments via Filament that are never exposed to customers or broadcast.
- **Realtime notifications** — Reverb private channels deliver `TicketCommentCreated` events to the ticket owner and staff; the frontend skips self-authored events and dedupes by `comment_id`.
- **File attachments** — comments accept file uploads (stored on the `public` disk, exposed via `attachmentUrls`).
- **Filament admin** — `/admin` panel (Support Desk brand, admin role only): ticket table with filters, comment relation manager with internal/public toggle, lookup resources, users/staff assignment, stats widgets + status chart.

## Architecture

```
┌──────────────────────────────┐              ┌──────────────────────────────┐
│        Frontend (:5173)      │              │          Backend (:8000)     │
│  React 19 + TS + Vite SPA    │              │  Laravel 13 · Octane ·       │
│  laravel-echo + pusher-js    │  HTTP /api/v1  │  FrankenPHP (one process)    │
│  (dev: Vite proxy → backend) │ ───────────► │  Passport auth · Filament    │
│  ├ /api, /broadcasting →8000 │              │  /admin · REST API           │
│  └ /apps (ws)        →8080   │              │  Reverb (co-located :8080)   │
└───────┬───────────────┬──────┘              └───────┬──────────────┬───────┘
        │               │      WSS private-*          │              │
        │  /broadcasting│◄─────────────────────────────┘              │
        │               │                                             │
        ▼               ▼                                             ▼
  ┌────────────┐  ┌────────────┐                              ┌────────────┐
  │   MySQL    │  │   Redis    │  cache · queue · session ·    │            │
  │ kmc_tickets│  │   (:6379)  │  broadcasting (reverb scaling)│            │
  └────────────┘  └────────────┘                              └────────────┘
```

The backend (Octane/FrankenPHP serving the API and Reverb) is designed to run in **one container**; the frontend is a static Vite build served with `pm2`. Containerized deployment is implemented — see [Docker deployment](#docker-deployment) below.

## Directory layout

```
kmc-tech-exam/
├── backend/          # Laravel 13 + Filament 5 + Passport + Reverb (Octane/FrankenPHP)
│   ├── app/          # Controllers, Models, Resources, Filament panel
│   ├── config/       # reverb.php, octane.php, auth.php (passport guard), permission.php
│   ├── database/     # migrations, factories, seeders
│   ├── routes/       # api.php (REST), channels.php (broadcast auth)
│   └── tests/        # Pest feature tests
├── frontend/         # React 19 + TS + Vite SPA (customer portal)
│   └── src/          # pages, components, context (auth/notifications), lib (api/echo)
├── docs/             # implementation plan, technical handover
└── .github/workflows/ci.yml
```

## Software requirements

The examiner can run this app with **either** a full local toolchain (Option A) or **Docker only** (Option B — no Composer, PHP, Node, MySQL, or Redis installs needed).

### Option A — local dev (needs the full toolchain)

| Requirement | Version | Notes |
|---|---|---|
| PHP | >= 8.3 (dev/tested on 8.5) | CLI + common extensions (mbstring, xml, curl, zip, gd, intl, bcmath, pdo_mysql, pcntl, redis) |
| Composer | 2.x | PHP dependency manager (`composer install`) |
| Node.js | >= 22 | React/Vite frontend |
| npm | >= 10 | Ships with Node |
| MySQL | 8.4 | Local database `kmc_tickets` (dev: `root`, empty password) — **not** SQLite |
| Redis | >= 7 | With the `phpredis` PHP extension — cache, queue, session, broadcast all use Redis |
| FrankenPHP | latest | Laravel Octane (`php artisan octane:start --server=frankenphp`). Needs the binary on your `PATH` — see Installing FrankenPHP below. **Not needed at all if you use Option B (Docker)** |

Missing PHP extensions will be reported by `composer check-platform-reqs` inside `backend/`.

### Installing FrankenPHP (Option A only)

Octane needs the `frankenphp` binary on your `PATH`. The binary **cannot** be placed at `backend/frankenphp/` — that path is a **directory** holding the committed Caddyfile, and Octane would try to overwrite it with the downloaded binary. Instead, download the release for *your* OS from https://github.com/php/frankenphp/releases/latest and put it in a directory that is already on your `PATH`, then verify with `frankenphp version`.

| OS | Asset to download |
|---|---|
| Linux x86_64, glibc (Debian/Ubuntu/Fedora) | `frankenphp-linux-x86_64-gnu` |
| Linux ARM64, glibc | `frankenphp-linux-aarch64-gnu` |
| Linux x86_64 / ARM64, musl (Alpine) | `frankenphp-linux-x86_64` / `frankenphp-linux-aarch64` (static) |
| macOS Apple Silicon | `frankenphp-mac-arm64` |
| macOS Intel | `frankenphp-mac-x86_64` |
| Windows | Not directly supported by Octane — use **WSL2** (then the Linux asset) or **Option B (Docker)** |

If your OS is not listed here, or you would simply rather install nothing on your machine, use **Option B (Docker)** below — it builds FrankenPHP into the backend container and needs no local binary, PHP, Composer, Node, MySQL, or Redis.

### Option B — Docker-only (no local toolchain)

Only **Docker Engine + Docker Compose v2** (any OS). The backend image builds PHP 8.5 + FrankenPHP (static musl) + Octane + Reverb; the frontend image builds with Node 22; MySQL 8.4 and Redis 7 run as containers. Nothing else is installed on the host. See [Docker deployment](#docker-deployment) and Option B in Quickstart.

## Quickstart

> All shell commands below are POSIX shell (macOS, Linux, WSL2). On native Windows, or if you do not want to install the local toolchain at all, jump straight to [Option B — Docker-only](#option-b--docker-only-no-local-toolchain).

### Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed          # creates kmc_tickets tables + lookups + demo users
php artisan passport:keys --force         # JWT keys for Passport
php artisan passport:client --personal --name="Support Desk"   # personal access client (required)
php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000
```

`passport:client --personal` is **required** — without it, API login fails with `Personal access client not found for 'users' user provider`. The FrankenPHP binary must be on your `PATH` before Octane will start — see [Installing FrankenPHP](#installing-frankenphp-option-a-only).

Locally, **Octane, Reverb and the queue worker are three separate processes.** After Octane is running, start Reverb in a second terminal and the queue worker in a third:

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
php artisan queue:work redis --sleep=1 --tries=3
```

The queue worker is **required for realtime** — `TicketCommentCreated` is a queued broadcast (`QUEUE_CONNECTION=redis`), so without it the events sit in Redis and notifications never arrive.

(In Docker they are co-located in a single container — see [Docker deployment](#docker-deployment).) Set `BROADCAST_CONNECTION=reverb` (as in `.env.example`) and start the frontend so the `/broadcasting` auth proxy works.

Demo accounts (seeded): `admin@example.com` / `password` (Filament `/admin`), `customer@example.com` / `password`, `jane@example.com` / `password` (API).

### Frontend

```bash
cd frontend
npm ci
cp .env.example .env
npm run dev            # http://localhost:5173, proxies /api + /broadcasting → backend
```

**Frontend `.env` is required — realtime silently dies without it.** Set `VITE_REVERB_APP_KEY` to exactly the backend's `REVERB_APP_KEY` (the same value already in `backend/.env`). If `frontend/.env` is missing, or the two keys differ, the WebSocket connects with a wrong/empty key and **notifications never arrive — no error anywhere**. In Docker this is handled automatically via the compose build args.

### Option B — Docker-only (no local toolchain)

```bash
docker compose --profile services up -d --build
```

- Frontend http://localhost:5173 · backend http://localhost:8000 · Reverb ws://localhost:8080.
- On first boot the backend **auto-migrates and auto-seeds** (lookup data, the demo accounts, and 25 sample tickets) — nothing else to set up.
- Same demo accounts as Option A: `admin@example.com` / `password` (Filament `/admin`), `customer@example.com` / `password`, `jane@example.com` / `password` (API).
- Compose v5 note: `--profile` must come before the subcommand.

See [Docker deployment](#docker-deployment) for ports, secrets, and volumes.

## Tests

Backend (Pest):

```bash
cd backend && php artisan test           # or ./vendor/bin/pest --parallel
```

Frontend (Vitest + ESLint + type-check/build):

```bash
cd frontend
npm test                                 # vitest run
npm run lint                             # eslint --fix
npm run lint:check                       # eslint . (CI)
npm run build                            # tsc -b && vite build (CI)
```

## Realtime channel contract

| Channel | Auth | Subscribers |
|---|---|---|
| `private-user.{id}` | logged-in user matches `{id}` | the ticket owner |
| `private-staff` | user has `admin` role | staff panel / staff frontend |

- Event: **`TicketCommentCreated`** — payload `{comment_id, ticket_id, ticket_number, author_id, user_name, content, created_at}`.
- Broadcast to the **ticket owner's** channel + the staff channel (see `app/Events/TicketCommentCreated.php`).
- **Internal comments (`is_internal`) are never broadcast** (`app/Models/TicketComment.php` boot hook).
- The frontend **skips self-authored events** (`author_id === user.id`) and **dedupes by `comment_id`** (`frontend/src/context/NotificationContext.tsx`).
- Notifications persist per user in `localStorage` (`supportdesk.notifications.{userId}`, capped at 50).
- **Only comment creation broadcasts.** Status changes, resolving, and assignment fire **no** event — a notification appears only when a non-internal comment is posted on a ticket you own.

> **No notifications?** Check in order: (1) `frontend/.env` exists with `VITE_REVERB_APP_KEY` == backend `REVERB_APP_KEY`; (2) `BROADCAST_CONNECTION=reverb` in `backend/.env`; (3) a **queue worker is running** (`php artisan queue:work redis` — broadcasts are queued, see [Backend](#backend)); (4) Reverb is up (`curl http://localhost:8080/up` → 200); (5) you are the ticket owner and not the comment's author (self-authored comments don't notify).

## CI (`.github/workflows/ci.yml`)

- **Backend job** (PHP 8.4 + MySQL 8.4 + Redis services): `composer install` → `key:generate` + `passport:keys` → `migrate:fresh --seed` (MySQL + Redis) → `./vendor/bin/pest --parallel` (SQLite `:memory:` env) → `pint --test` → `composer audit`.
- **Frontend job** (Node 22): `npm ci` → `npx eslint .` → `npm test -- --run` → `npm run build` (type-check + vite) → `npm audit --audit-level=high`.

## Docker deployment

Containerized deployment is implemented at the repo root.

- **`backend` container** — `php:8.5-fpm-alpine` with the static (musl) FrankenPHP binary. `backend/supervisord.conf` runs **three** processes in the same container: **Octane** (HTTP :8000), **Reverb** (websocket :8080), and the **queue worker** (`queue:work redis`, which drains the queued `TicketCommentCreated` broadcasts); `backend/start.sh` waits for MySQL, generates the app key + Passport keys, runs `migrate --force`, **seeds on first boot** (demo users + lookups + sample tickets), caches config/routes/views, then boots supervisord.
- **`frontend` container** — multi-stage `node:22-alpine`: `npm ci` → `npm run build` (with `VITE_REVERB_*` build args), then `pm2` serves the built SPA on :5173 via `frontend/server.mjs`, which reverse-proxies `/api` and `/broadcasting` (the Laravel auth route) to the backend on :8000. The websocket connects directly to `ws://:8080`. **pm2 is installed inside the image** (`npm i -g pm2` in the Dockerfile) — nothing is installed on the host, and the image runs it via `pm2-runtime` (`CMD ["pm2-runtime", "start", "ecosystem.config.cjs"]`).
- **MySQL 8.4 + Redis 7** are provided behind the compose `services` profile.

Start the full stack:

```bash
docker compose --profile services up -d --build
```

- Frontend http://localhost:5173 · backend http://localhost:8000 · Reverb ws://localhost:8080 · MySQL `kmc_tickets`.
- Override secrets with env vars: `APP_KEY`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `MYSQL_ROOT_PASSWORD`.
- Compose v5 note: the `--profile` flag must come **before** the subcommand (`docker compose --profile services up -d`), not after.
