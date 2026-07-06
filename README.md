# GoDoc — Consultation Booking System

A simplified end-to-end consultation booking flow: patients browse a doctor's
available slots and book one. The focus of this submission is **correctness
under concurrency** (no double-booking) and a **well-defined booking state
machine**, which are the two things the brief calls out most strongly.

> Assessment scope chosen: **#1 Consultation Booking System.**

- **Backend:** Laravel 10 (PHP 8.1) REST API
- **Frontend:** React 19 + TypeScript (Vite)
- **Database:** MySQL 8+

**🔗 Live demo:** https://godoc-booking.vercel.app (frontend on Vercel) ·
API at https://api-production-8340.up.railway.app/api (Laravel + MySQL on
Railway). Demo data resets on each backend deploy.

---

## Table of contents

1. [Tech stack & why](#tech-stack--why)
2. [How to run it locally](#how-to-run-it-locally)
3. [Running the tests](#running-the-tests)
4. [The concurrency problem (the interesting part)](#the-concurrency-problem-the-interesting-part)
5. [Booking state machine](#booking-state-machine)
6. [Data model](#data-model)
7. [API reference](#api-reference)
8. [Assumptions & known limitations](#assumptions--known-limitations)
9. [What I deliberately left out](#what-i-deliberately-left-out)

---

## Tech stack & why

| Layer | Choice | Why | Trade-off considered |
|-------|--------|-----|----------------------|
| Backend | **Laravel 10** | Batteries-included: migrations, Eloquent, validation, resources, first-class transaction + row-locking helpers (`lockForUpdate()`), and a fast test harness. Lets me spend time on the booking logic, not plumbing. | A leaner framework (Slim, or Go/Node) would have less overhead, but I'd rebuild the ORM/validation/testing stack by hand. For a booking domain with real relational integrity, Laravel + a relational DB is the sweet spot. |
| Database | **MySQL** | The correctness guarantees here lean on relational features: transactions, `SELECT … FOR UPDATE` row locks, and a UNIQUE index as a hard backstop. MySQL is ubiquitous and the brief named it. | Postgres offers partial indexes (`WHERE status IN (...)`) which would express the "one active booking per slot" rule even more cleanly. I worked around MySQL's lack of partial indexes (see below) — a deliberate, documented trade-off. |
| Frontend | **React + TypeScript + Vite** | Types catch API-shape mistakes at compile time; Vite gives instant dev feedback. The UI is intentionally small — it exists to demonstrate the flow, not to be a design showcase. | Could have server-rendered with Blade and shipped one app. A separate SPA keeps a clean API boundary that would scale to mobile/other clients, which matches the "scalable framework" ask. |
| Tests | **PHPUnit + a parallel-curl race script** | PHPUnit covers logic and the DB backstop deterministically; a shell script drives *real* concurrent HTTP to prove the end-to-end race is handled (a single PHP process can't issue truly parallel requests). | — |

---

## How to run it locally

### Prerequisites

- PHP **8.1+** with `pdo_mysql`
- Composer
- Node **18+**
- MySQL **8+** running locally

<details>
<summary><strong>Starting from a completely clean machine? (step-by-step guide)</strong></summary>

Everything below goes into the Terminal app. On macOS:

**1. Install Homebrew (the package manager):**

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

Follow its prompts (press Enter, type your login password). If it tells you to
run two `echo ... >> ~/.zprofile` commands at the end, run them. Verify with
`brew -v`.

**2. Install PHP, Composer, Node and MySQL in one go:**

```bash
brew install php composer node mysql
```

Verify: `php -v`, `composer -V`, `node -v`, `mysql --version` each print a version.

**3. Start the MySQL server** (installing it doesn't start it):

```bash
brew services start mysql
```

Verify: `mysql -uroot -e "SELECT 1;"` prints a `1`. Homebrew's MySQL has no
root password by default, which matches the committed `backend/.env` exactly.

**4. Clone this repository:**

```bash
git clone https://github.com/reikhai/godoc-booking.git
cd godoc-booking
```

Then continue with step **1. Database** below. Two notes so you don't go
looking for missing steps:

- There is **no separate "install Laravel" step** — `composer install` reads
  `backend/composer.json` and downloads the Laravel framework into
  `backend/vendor/`. Likewise `npm install` is what installs React.
- There is **no `cp .env.example .env` / `php artisan key:generate` step** —
  the `.env` files (including `APP_KEY`) are committed per the assessment brief.

**Troubleshooting:**

| Symptom | Fix |
|---------|-----|
| `command not found: brew` | Homebrew step didn't finish — rerun step 1 including the `echo` commands it prints |
| `Access denied for user 'root'` | Your MySQL has a password — put it in `DB_PASSWORD=` in `backend/.env` |
| Frontend shows "Failed to fetch" | The backend isn't running — rerun `php artisan serve` and keep that terminal open |
| Port 8000 already in use | `php artisan serve --port=8001` and change the port in `frontend/.env` accordingly |

</details>

### 1. Database

```bash
mysql -uroot -e "CREATE DATABASE godoc_booking       CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -uroot -e "CREATE DATABASE godoc_booking_test  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

> The test suite uses a **separate** `godoc_booking_test` database so it never
> touches your dev data.

### 2. Backend (Laravel API)

```bash
cd backend
composer install
# .env is committed for this assessment (see note below). If your MySQL uses a
# password or a non-root user, edit DB_USERNAME / DB_PASSWORD in backend/.env.
php artisan migrate:fresh --seed      # creates tables + 3 doctors with slots
php artisan serve                     # http://localhost:8000
```

The seeder creates 3 doctors, each with 30-minute slots from 09:00–12:00 across
the next 5 weekdays (Asia/Singapore).

### 3. Frontend (React)

```bash
cd frontend
npm install
npm run dev                           # http://localhost:5173
```

`frontend/.env` points the app at `http://localhost:8000/api`. Open
`http://localhost:5173`, enter a name + email, pick a doctor, and book a slot.

> **Note on committed env files:** the brief explicitly asks to commit the env
> to the repo, so `backend/.env` and `frontend/.env` are checked in. In a real
> project these would be git-ignored and provided via a secrets manager.

---

## Running the tests

### Unit + feature suite (PHPUnit)

```bash
cd backend
php artisan test
```

Covers the state-machine rules, the full booking API happy path, validation,
invalid transitions (422), the "slot already taken" path (409), that cancelling
frees a slot for rebooking, and that the **database unique index rejects a
second active booking even when the service layer is bypassed**.

### End-to-end race test (real concurrency)

With the server running and the DB freshly seeded:

```bash
cd backend
php artisan migrate:fresh --seed
php artisan serve --port=8099 &        # in one shell
./scripts/race-test.sh 1 20            # fire 20 concurrent bookings at slot #1
```

Expected output — exactly one winner:

```
HTTP status distribution:
   1 201
  19 409
201 Created : 1  (expected 1)
409 Conflict: 19 (expected 19)
PASS: exactly one booking won the slot.
```

---

## The concurrency problem

> *"What happens when two requests try to book the same slot at the same time?"*

A booking is created inside a single database transaction with **two
independent layers of defence**. Either one alone prevents a double-booking;
together they mean correctness never rests on application code alone.

**Layer 1 — Pessimistic row lock (primary mechanism).**
`BookingService::book()` opens a transaction and immediately does
`SELECT … FOR UPDATE` on the slot row:

```php
$slot = Slot::whereKey($slotId)->lockForUpdate()->firstOrFail();
```

Concurrent bookers for the same slot **serialise on that row lock**. The first
request holds it until commit; the second blocks, then wakes up, sees the now-
existing active booking, and is rejected with `409`. The race becomes an ordered
queue. Bookings for *different* slots don't contend, so this doesn't serialise
the whole system.

**Layer 2 — UNIQUE index (defence in depth).**
The `bookings` table has an `active_slot_id` column that equals `slot_id` while
the booking is active (`pending`/`confirmed`) and is `NULL` once terminal
(`cancelled`/`completed`). A `UNIQUE` index on it enforces **at most one active
booking per slot at the storage-engine level**. Because MySQL doesn't index
NULLs uniquely, any number of cancelled/completed bookings for the same slot may
coexist — only the single active one is constrained. If a second active insert
ever slips past the lock (a bug, a different code path), the DB rejects it and
the service translates that into the same `409`.

```
Request A ──▶ BEGIN ──▶ SELECT slot FOR UPDATE 🔒 ──▶ no active booking ──▶ INSERT ──▶ COMMIT ✅ (201)
Request B ──▶ BEGIN ──▶ SELECT slot FOR UPDATE ⏳ (waits for A) … ──▶ sees A's booking ──▶ 409 ❌
```

**Why an app-maintained column instead of a generated one?** The cleanest
expression would be a MySQL *generated* column (`GENERATED ALWAYS AS (CASE …)`),
but InnoDB refuses to add a generated column that derives from a foreign-key
column (`slot_id`). So `active_slot_id` is kept in sync by a single centralised
`saving` hook on the `Booking` model. The DB-level guarantee is identical.
(On Postgres this would instead be a one-line partial unique index.)

**How it stays correct as load grows:** the guarantee lives in the database, not
in a single app instance. Running 10 API servers behind a load balancer changes
nothing — they all contend on the same row lock and the same unique index. No
distributed lock or coordination service is required.

### Second invariant: one booking per patient per day

A patient may hold at most **one active booking per calendar day** (across all
doctors). This rule is *also* concurrency-sensitive — the per-slot row lock
cannot serialise two requests from the same patient for two *different* slots
on the same day — so it uses the same two-layer pattern:

1. An app-level check inside the transaction (friendly `422` with a clear
   message), and
2. a composite `UNIQUE(patient_id, active_date)` index as the DB backstop,
   where `active_date` is the slot's date while the booking is active and
   `NULL` once cancelled/completed. Cancelling therefore frees the day the
   same way it frees the slot.

---

## Booking state machine

States and the **only** allowed transitions (enforced in one place —
`App\Enums\BookingStatus` — and applied via `Booking::transitionTo()`):

```
      ┌─────────┐   confirm   ┌───────────┐  complete  ┌───────────┐
      │ pending │ ──────────▶ │ confirmed │ ─────────▶ │ completed │ (terminal)
      └────┬────┘             └─────┬─────┘            └───────────┘
           │ cancel                 │ cancel
           ▼                        ▼
      ┌───────────┐  ◀──────────────┘
      │ cancelled │ (terminal)
      └───────────┘
```

- `pending` and `confirmed` are **active** → they hold the slot.
- `cancelled` and `completed` are **terminal** → they free the slot.
- Any illegal transition (e.g. completing a `pending` booking, or confirming a
  `cancelled` one) throws `InvalidBookingTransitionException` → HTTP `422`.
- Each API response includes `allowed_transitions`, so the UI only ever offers
  valid next actions (the React app is driven entirely by this field).

---

## Data model

```
doctors ──< slots ──< bookings >── patients

doctors   (id, name, specialty)
patients  (id, name, email UNIQUE)          -- email is the patient identity
slots     (id, doctor_id, start_at, end_at) -- UNIQUE(doctor_id, start_at)
bookings  (id, slot_id, patient_id, status,
           confirmed_at, cancelled_at, completed_at,
           active_slot_id,                   -- UNIQUE(active_slot_id)            ← one active booking per slot
           active_date)                      -- UNIQUE(patient_id, active_date)   ← one active booking per patient per day
```

**Availability is derived, not stored.** A slot is available iff it has no active
booking. There is a single source of truth (the `bookings` table), so a slot's
availability can never drift out of sync with its bookings. `Slot::scopeAvailable()`
expresses this, and the list endpoint eager-loads `activeBooking` to avoid N+1s.

---

## API reference

Base URL: `http://localhost:8000/api`

| Method | Path | Description |
|--------|------|-------------|
| `GET`  | `/health` | Liveness check. |
| `GET`  | `/doctors` | List doctors. |
| `GET`  | `/doctors/{doctor}/slots` | A doctor's **available, future** slots. `?all=1` includes booked/past ones with `available` flags. |
| `POST` | `/bookings` | Book a slot. Body: `{ "slot_id": 1, "patient": { "name": "...", "email": "..." } }`. Returns `201` with the booking, or `409` if the slot was just taken. |
| `GET`  | `/bookings?email=` | List a patient's bookings. |
| `GET`  | `/bookings/{booking}` | Fetch one booking. |
| `POST` | `/bookings/{booking}/confirm` | `pending → confirmed`. |
| `POST` | `/bookings/{booking}/cancel` | `pending/confirmed → cancelled`. |
| `POST` | `/bookings/{booking}/complete` | `confirmed → completed`. |

**Status codes:** `201` created · `200` ok · `404` unknown slot/booking ·
`409` slot unavailable (lost the race) · `422` validation error, illegal
state transition, or daily booking limit (`error: "daily_booking_limit"` —
the patient already has an active booking that day).

Example:

```bash
curl -X POST http://localhost:8000/api/bookings \
  -H "Content-Type: application/json" \
  -d '{"slot_id":1,"patient":{"name":"Jane Tan","email":"jane@example.com"}}'
```

---

## Assumptions & known limitations

**Assumptions**
- **No authentication.** A patient is identified purely by email (upserted on
  booking). Real system: auth + a proper patient/session model. Auth was out of
  scope for demonstrating concurrency + state, so I left it out intentionally.
- **A slot belongs to one doctor and hosts one consultation.** "One active
  booking per slot" is the core invariant.
- **One booking per patient per day, across all doctors.** Interpreted as a
  global daily limit; scoping it per-doctor would be a one-line change to the
  composite index (`patient_id, doctor_id, active_date`).
- **Slots are pre-created** (by the seeder). Doctor-facing slot management (create/
  block/recur) isn't built.
- **No payment step.** `pending → confirmed` stands in for whatever gate a real
  flow would have (payment, doctor approval).

**Known limitations**
- **`pending` bookings never expire.** A real system would auto-release a slot if
  a pending hold isn't confirmed within N minutes (a scheduled job flipping stale
  `pending` → `cancelled`). The state machine already supports it; the reaper job
  isn't built.
- **No pagination** on list endpoints — fine at seed scale, needed at real volume.
- **No rate limiting / idempotency keys** on `POST /bookings`. A double-clicking
  user could create two bookings for two *different* slots; an idempotency key
  would dedupe retries of the *same* request.
- **Timezone** is fixed to `Asia/Singapore` app-wide rather than per-doctor/clinic.

---

## What I deliberately left out

Per the brief ("be explicit about what you left out and why"), and favouring a
smaller well-reasoned solution over a rushed larger one:

- **Auth / RBAC** — orthogonal to the concurrency + state-machine focus.
- **The pending-hold expiry job** — designed for (state machine supports it),
  not implemented, to keep the moving parts reviewable.
- ~~Deployment~~ — done as the optional bonus: frontend on Vercel, API + MySQL
  on Railway (see the live-demo links at the top). `backend/railway.json`
  holds the deploy config; the start command runs `migrate:fresh --seed` so
  the demo resets to a clean seeded state on each deploy. The concurrency
  race test passes against the production deployment too
  (`./scripts/race-test.sh 1 20 https://api-production-8340.up.railway.app`).
- **Notifications, doctor UI, search/pagination** — real-product surface area
  beyond the core flow.

The parts I *did* build — the double-booking guarantee, the state machine, the
derived-availability model, and the tests that prove them — are the parts the
brief weighted most heavily, so that's where the depth went.
