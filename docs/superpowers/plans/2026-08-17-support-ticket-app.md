# Support Ticket Management App Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a Zendesk-inspired support ticket application with a Laravel 13 backend (Passport auth + REST API + Filament admin) and a React customer frontend, per the KMC Senior Full Stack Developer technical exam.

**Architecture:** Monorepo with `backend/` (Laravel 13 + Filament 5 + Passport + spatie/laravel-permission, SQLite for dev) and `frontend/` (Vite + React 19 + TypeScript + Tailwind, Zendesk-like dark sidebar layout). Single `users` table; customers authenticate via Passport personal access tokens; staff access the Filament panel at `/admin`. Tickets have statuses/priorities/categories as lookup tables.

**Tech Stack:** Laravel 13, Filament 5, Laravel Passport, spatie/laravel-permission, React 19, Vite, TypeScript, Tailwind CSS, PHPUnit, Vitest + React Testing Library.

## Global Constraints

- Laravel 13 only; PHP >= 8.2 (local has PHP 8.5)
- SQLite for local dev (already configured: `database/database.sqlite`)
- API auth: Laravel Passport (personal access tokens via `HasApiTokens`), Bearer tokens
- Customers may only see/comment on their own tickets (scope by `user_id`); staff manage all
- Admin users (Filament) get `admin` role; customers get `customer` role
- The React app talks ONLY to `/api/*`; the Filament panel is a separate area
- Ticket number format: `TK-XXXXXX-YYMMDD` (auto-generated on the model)
- Time-box: 2–3 hours total. Prefer working, tested, clean code over breadth

## API Contract (agreed upfront — backend and frontend agents build against this)

Base URL: `/api/v1`. All endpoints return JSON. Auth: `Authorization: Bearer <token>`.

| Method | Endpoint | Auth | Purpose |
|---|---|---|---|
| POST | `/api/v1/auth/register` | public | `{name, email, password, password_confirmation}` → `{user, token}` |
| POST | `/api/v1/auth/login` | public | `{email, password}` → `{user, token}` |
| POST | `/api/v1/auth/logout` | Bearer | revokes current token |
| GET | `/api/v1/user` | Bearer | current user + roles |
| GET | `/api/v1/tickets` | Bearer | own tickets; filters `?status=&priority=&category=&search=&sort=created_at|updated_at&order=asc|desc&per_page=` |
| POST | `/api/v1/tickets` | Bearer | `{subject, description, category_id?, priority_id?}` → ticket |
| GET | `/api/v1/tickets/{ticket}` | Bearer | own ticket + comments + relations |
| POST | `/api/v1/tickets/{ticket}/comments` | Bearer | `{content}` (is_internal forced false for customers) → comment |
| GET | `/api/v1/meta/categories` | Bearer | active categories |
| GET | `/api/v1/meta/priorities` | Bearer | active priorities |
| GET | `/api/v1/meta/statuses` | Bearer | active statuses (read-only for customers) |

Resource shapes:
- `ticket`: `{id, ticket_number, subject, description, status: {id,name,slug,color,is_closed}, priority: {id,name,slug,color,level}, category: {id,name,slug,color}, created_at, updated_at, comments_count}`
- `comment`: `{id, content, user: {id,name}, created_at}` (is_internal hidden from customers)
- `meta`: `[{id, name, slug, color}]`

---

### Task 1: Backend foundation — seeders, Passport config, factories, base tests

**Files:**
- Create: `backend/database/seeders/DatabaseSeeder.php` (update), `backend/database/seeders/TicketLookupSeeder.php`, `backend/database/factories/TicketFactory.php`, `backend/database/factories/TicketCommentFactory.php`, `backend/database/factories/TicketCategoryFactory.php`, `backend/database/factories/TicketPriorityFactory.php`, `backend/database/factories/TicketStatusFactory.php`
- Modify: `backend/config/auth.php` (Passport guard), `backend/app/Providers/AuthServiceProvider.php` (if needed), `backend/bootstrap/app.php` (if needed)
- Test: `backend/tests/TestCase.php`

- [ ] **Step 1:** Update `DatabaseSeeder` to seed: 3 statuses (open, pending, resolved/closed), 4 priorities (low/medium/high/urgent), 5 categories (billing, technical, account, feature request, other), 1 admin user (`admin@example.com` / `password`, `admin` role), 1 demo customer (`customer@example.com` / `password`, `customer` role), ~20 sample tickets with comments via factories
- [ ] **Step 2:** Create factories for all ticket models; make sure TicketFactory generates unique ticket numbers and picks statuses/priorities/categories from seeded lookup rows
- [ ] **Step 3:** Publish spatie permission migrations (`php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`), run `php artisan migrate:fresh --seed`
- [ ] **Step 4:** Run a smoke test: `php artisan tinker` to assert admin can `assignRole('admin')`, and `php artisan test` passes

### Task 2: Backend API — auth, tickets, comments, meta

**Files:**
- Create: `backend/app/Http/Controllers/Api/AuthController.php`, `backend/app/Http/Controllers/Api/TicketController.php`, `backend/app/Http/Controllers/Api/MetaController.php`, `backend/app/Http/Requests/Api/RegisterRequest.php`, `backend/app/Http/Requests/Api/LoginRequest.php`, `backend/app/Http/Requests/Api/StoreTicketRequest.php`, `backend/app/Http/Requests/Api/StoreCommentRequest.php`, `backend/app/Http/Resources/TicketResource.php`, `backend/app/Http/Resources/TicketCommentResource.php`, `backend/app/Http/Resources/UserResource.php`
- Modify: `backend/routes/api.php`, `backend/bootstrap/app.php` (throttle/auth middleware if needed)
- Test: `backend/tests/Feature/AuthTest.php`, `backend/tests/Feature/TicketApiTest.php`

- [ ] **Step 1:** AuthController: `register` (creates user + assigns `customer` role + returns token), `login` (email/password → token), `logout` (revoke current token), `user` (returns UserResource)
- [ ] **Step 2:** TicketController: `index` (scope to own tickets, apply filters/search/sort/pagination, eager-load relations), `store` (validated, auto status=open default, auto ticket_number), `show` (own ticket + comments), `addComment` (validated, is_internal always false)
- [ ] **Step 3:** MetaController: categories/priorities/statuses lists (active only)
- [ ] **Step 4:** Wire routes in `api.php` with `auth:api` middleware; write feature tests: register/login/logout happy + validation failures, ticket CRUD happy + 403 for another user's ticket, comment flow, meta endpoints
- [ ] **Step 5:** Run `php artisan test` — all green

### Task 3: Filament admin panel

**Files:**
- Create: `backend/app/Filament/Admin/Resources/TicketResource.php`, `backend/app/Filament/Admin/Resources/TicketResource/Pages/ListTickets.php`, `.../Pages/CreateTicket.php`, `.../Pages/EditTicket.php`, `backend/app/Filament/Admin/Resources/TicketCommentResource.php` (or manage comments inline), `backend/app/Filament/Admin/Resources/TicketCategoryResource.php`, `backend/app/Filament/Admin/Resources/TicketPriorityResource.php`, `backend/app/Filament/Admin/Resources/TicketStatusResource.php`, `backend/app/Filament/Admin/Resources/UserResource.php`, `backend/app/Filament/Admin/Widgets/StatsOverview.php`
- Modify: `backend/app/Providers/Filament/AdminPanelProvider.php` (id `admin`, path `/admin`, only admin-role users via `canAccessPanel`)

- [ ] **Step 1:** AdminPanelProvider: restrict panel access to `admin` role; set navigation brand "Support Desk"
- [ ] **Step 2:** TicketResource: table (ticket number, subject, customer, status badge with color, priority badge, category, created_at, comments count), filters (status, priority, category, assigned), actions (assign to staff, change status), relation manager for comments with internal/public toggle, view page with infolist
- [ ] **Step 3:** Lookup resources (category/priority/status) + UserResource (staff assignment, role management)
- [ ] **Step 4:** StatsOverview widget: open tickets, overdue, unassigned, resolved today
- [ ] **Step 5:** Manual smoke test via `php artisan serve` + `http://localhost:8000/admin` login as admin; add a ticket, change status, add internal comment

### Task 4: React customer frontend

**Files:**
- Create: `frontend/` Vite React TS project with Tailwind; `frontend/src/lib/api.ts` (fetch wrapper + token storage), `frontend/src/context/AuthContext.tsx`, `frontend/src/pages/Login.tsx`, `frontend/src/pages/Register.tsx`, `frontend/src/pages/Tickets.tsx` (list), `frontend/src/pages/TicketDetail.tsx`, `frontend/src/pages/CreateTicket.tsx`, `frontend/src/components/Layout.tsx` (Zendesk-style dark sidebar: logo, nav, user menu), `frontend/src/components/TicketCard.tsx`, `frontend/src/components/StatusBadge.tsx`, `frontend/src/components/CommentThread.tsx`, `frontend/src/components/PriorityBadge.tsx`
- Modify: `frontend/package.json`, `frontend/vite.config.ts` (proxy `/api` → `http://localhost:8000`)

- [ ] **Step 1:** Scaffold Vite + React + TS + Tailwind in `frontend/`; `vite.config.ts` proxy `/api`
- [ ] **Step 2:** `api.ts` — fetch wrapper attaching Bearer token from localStorage, JSON error handling, typed responses matching the contract above; `AuthContext` with login/register/logout/me
- [ ] **Step 3:** Auth pages (Login, Register) — Zendesk-style centered card, demo credentials hint
- [ ] **Step 4:** Tickets list — table/cards with status + priority badges, filters, search, create button; CreateTicket form (subject, description, category, priority)
- [ ] **Step 5:** TicketDetail — subject, badges, description, comment thread, add-comment box, back link
- [ ] **Step 6:** Layout with sidebar; wire react-router routes; run `npm run build` and type-check

### Task 5: Frontend tests + polish

**Files:**
- Create: `frontend/src/lib/api.test.ts`, `frontend/src/components/StatusBadge.test.tsx`, `frontend/src/pages/Login.test.tsx`
- Modify: `frontend/package.json` (vitest, testing-library, jsdom, msw or fetch mock)

- [ ] **Step 1:** Add vitest + @testing-library/react + jsdom; unit-test api.ts (token attach, error handling) and StatusBadge rendering
- [ ] **Step 2:** Component test for Login form validation flow (mock fetch)
- [ ] **Step 3:** Run `npm test` + `npm run build` — green

### Task 6: Docs — README, Technical Handover, root .gitignore

**Files:**
- Create: `README.md` (root), `docs/TECHNICAL_HANDOVER.md`, `backend/.env.example` (verify), `frontend/.env.example`
- Modify: root `.gitignore`

- [ ] **Step 1:** README: prerequisites, backend setup (composer install, migrate --seed, passport keys), frontend setup, how to run tests, demo credentials, repo structure
- [ ] **Step 2:** TECHNICAL_HANDOVER.md: technical decisions, approach, trade-offs, AI usage (tools, where AI assisted, validation, rejected approaches), time spent
- [ ] **Step 3:** Root .gitignore for vendor/, node_modules/, .env, storage, etc. Commit everything

---

## Parallelization Strategy (superpowers:dispatching-parallel-agents)

Dependency graph:
- Task 1 (seeders/factories) — **must run first** (everything depends on DB seed data)
- Task 2 (API) — depends on Task 1; defines contract (already fixed above)
- Task 3 (Filament) — depends on Task 1 models; independent of Task 2
- Task 4 (React) — depends on contract only (fixed above); **can start immediately**, independent of backend code
- Task 5 — after Task 4
- Task 6 — last, by coordinator

Execution order: dispatch Task 1 alone → then Tasks 2+3+4 in parallel → then Task 5 → Task 6. Coordinator (main agent) verifies integration between streams at the end (migrate --fresh --seed, run full test suites).
---

## Revision 1 (2026-08-17) — infrastructure upgrades (user request)

- **Database:** MySQL 8.4 (local: `kmc_tickets` db, root, no password) — replaces SQLite. `.env` updated; fulltext indexes now active. `.env.example` must use mysql + empty password + `kmc_tickets`.
- **Redis all the way:** `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis` (phpredis client). No database/session/queue fallbacks.
- **Octane + FrankenPHP:** `laravel/octane` installed, `OCTANE_SERVER=frankenphp`, `config/octane.php` present. Backend runs via `php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000`. FrankenPHP config: `frankenphp/Caddyfile`.
- **Deployment (easy for examiner):** `docker-compose.yml` with services: `mysql` (mysql:8.4), `redis` (redis:7-alpine), `backend` (Dockerfile based on `dunglas/frankenphp`, runs octane, auto `migrate --seed` on boot), `frontend` (node:22-alpine, builds Vite app, serves via `pm2` on port 5173, proxies `/api` to backend). Plus `ecosystem.config.cjs` (pm2) as a non-docker alternative for the frontend.
- **Subagent skills:** implementer agents must read these skill files first and follow them. The skill files live in the agent's local skills cache — resolve each by name (the cache path is machine-specific and differs per developer): `test-driven-development` (backend + frontend), `verification-before-completion` (all), `systematic-debugging` (when debugging), and for the frontend agent additionally `frontend-design` (if present).
