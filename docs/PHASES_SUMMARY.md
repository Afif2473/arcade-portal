# Web Game Platform — Technical Architecture & Phase Summaries

This is a **living document**: it recaps everything built so far and is updated at the end of every phase. Use it to revise the architecture, design decisions, and request lifecycle.

## Progress Tracker

| Phase | Scope | Status |
|---|---|---|
| 1 | MySQL schema & Laravel migrations | ✅ Done |
| 2 | Eloquent models, N+1 guard, seeders | ✅ Done |
| 3 | API layer: FormRequests, Actions, Controllers, Routes | ✅ Done |
| 4 | `PlatformSDK.ts` + Test Harness Game + Host Stub | ✅ Done |
| 5 | Vue 3 + Bootstrap 5 portal (Catalog, Player & Live Leaderboard) | ✅ Done |
| 6 | Developer Dashboard & Direct Game `.zip` Upload Ingestion | ✅ Done |
| 7 | LocalStack & AWS Infrastructure (S3, CloudFront OAC, EC2, OIDC) | 🚧 In progress |

---

## 1. High-Level Architecture Recap

The platform is designed around a **two-party separation of concerns**:
1. **The Game Client (Iframe Sandbox)**: Runs independently inside a sandboxed `<iframe>` served from CloudFront/S3. The game client *never* speaks directly to the backend database or holds private tokens.
2. **The Web Portal (Vue 3 + Laravel API)**: Hosts the catalog, leaderboards, user session, and acts as the **secure relay bridge**.

```
┌─────────────────────────────────┐
│     Game Client (Iframe)        │
│  - Zero backend dependencies    │
│  - PlatformSDK.ts helper        │
└───────────────┬─────────────────┘
                │ window.postMessage (Structured envelope)
                ▼
┌─────────────────────────────────┐
│     Parent Vue 3 Portal         │
│  - Validates origin & source    │
│  - Holds Laravel Sanctum Token  │
└───────────────┬─────────────────┘
                │ HTTPS REST API (/api/...)
                ▼
┌─────────────────────────────────┐
│     Laravel 11 Backend API      │
│  - Thin Controllers             │
│  - FormRequest Validation       │
│  - Dedicated Action Classes     │
│  - MySQL 8.0 (Indexed Tables)   │
└─────────────────────────────────┘
```

---

## 2. Phase 1: Database Schema & Migration Architecture

### Core Design Decisions

#### A. Historical `scores` vs. Aggregated `leaderboard_entries`
Instead of having a single table or repeatedly scanning millions of score rows with `GROUP BY user_id`, we split scores into two tables:
* **`scores`**: Records **every single game run**. Keeps track of timestamp, score value, level metadata, and an anti-cheat SHA-256 IP hash for auditing.
* **`leaderboard_entries`**: Stores **one row per user per game** (their all-time personal best). 
  * Features a strict `UNIQUE KEY (game_id, user_id)`.
  * Features a composite index: `KEY lb_rank_idx (game_id, best_score DESC, achieved_at ASC)`.
  * **Benefit**: Leaderboard queries scan *only* unique users sorted in memory/index without needing expensive grouping or subqueries.

#### B. Circular Foreign Key Resolution (`games` ⟷ `game_versions`)
* `games` references its `active_version_id` (pointing to `game_versions`).
* `game_versions` references its parent `game_id` (pointing to `games`).
* **Solution**: In migration `073129_create_games_table`, we created `games` with an unconstrained column `active_version_id`. Then in `080416_add_active_version_id_to_games_table`, we applied the foreign key constraint *after* `game_versions` had been created.

#### C. Database Tables Overview

| Table | Purpose | Key Indexes / Constraints |
|---|---|---|
| `users` | Players, developers, and admins | `username` (unique), `email` (unique) |
| `games` | Game catalog metadata, slug, aspect ratio, status | `slug` (unique), `(status, published_at)` |
| `game_versions` | S3 release bundles (`v1`, `v2`, etc.) | `UNIQUE(game_id, version)` |
| `scores` | Full chronological audit log of all runs | `(game_id, score DESC, created_at ASC)` |
| `leaderboard_entries`| Current personal bests and ranks | `UNIQUE(game_id, user_id)`, `(game_id, best_score DESC, achieved_at ASC)` |
| `game_saves` | Cloud save slots (0–9) with JSON data | `UNIQUE(game_id, user_id, slot)` |
| `achievements` | Achievement badges defined per game | `UNIQUE(game_id, key)` |
| `user_achievements` | Unlocked achievements per user | `PRIMARY(user_id, achievement_id)` |
| `user_game_favorites`| User favorited games | `PRIMARY(user_id, game_id)` |

---

## 3. Phase 2: Eloquent Models & Development Discipline

### Key Standards Implemented

1. **Strict Relationship Definitions**:
   * `User->hasMany(Game::class, 'developer_id')`
   * `Game->hasMany(GameVersion::class)`
   * `Game->belongsTo(GameVersion::class, 'active_version_id')`
   * `Game->hasMany(LeaderboardEntry::class)`
   * `GameSave` with JSON casting (`protected $casts = ['data' => 'array']`)
2. **Preventing N+1 Query Degradation**:
   In `app/Providers/AppServiceProvider.php`, we added:
   ```php
   Model::preventLazyLoading(! app()->isProduction());
   ```
   If a controller or resource accesses a relation without eager-loading it via `->with([...])`, Laravel immediately throws an exception during local testing. This guarantees zero N+1 query bottlenecks reach production.
3. **Database Seeding**:
   `database/seeders/DatabaseSeeder.php` creates:
   * A developer account: `testdev` (`dev@example.com` / `password123`)
   * A player account: `speedrunner99` (`player@example.com` / `password123`)
   * A published game: `test-harness` with active version `v1`

---

## 4. Phase 3: The API & Leaderboard Service Layer

### Clean Architecture Pattern Applied

```
HTTP Request ──▶ FormRequest (Validation) ──▶ Controller (HTTP layer only)
                                                      │
                                                      ▼
                                           Action Class (DB Transaction & Business Logic)
                                                      │
                                                      ▼
HTTP Response ◀── JsonResource (Formatting) ◀─────────┘
```

### Components Built

#### 1. Form Requests (`app/Http/Requests/`)
* **`SubmitScoreRequest`**: Enforces integer `score` and optional array `metadata`.
* **`SaveStateRequest`**: Validates save slot (between 0 and 9), payload `data`, and concurrency `revision`.

#### 2. Business Action: `SubmitGameScoreAction.php`
Encapsulates score submissions inside an atomic database transaction (`DB::transaction`):
1. **Inserts history**: Creates a row in `scores` with `ip_hash` for cheating detection.
2. **Locks personal best**: Uses `lockForUpdate()` on `leaderboard_entries` to prevent race conditions during rapid submissions.
3. **Ascending vs. Descending handling**: Checks `game.score_sort`:
   * High-score games: Checks if `score > best_score`.
   * Speedrun/time games: Checks if `score < best_score`.
4. **Calculates rank**: Counts how many entries are ahead using the composite index:
   ```sql
   SELECT COUNT(*) + 1 FROM leaderboard_entries 
   WHERE game_id = ? AND (best_score > ? OR (best_score = ? AND achieved_at < ?));
   ```

#### 3. API Controllers (`app/Http/Controllers/Api/`)
* **`GameCatalogController`**:
  * `index()`: Returns paginated list of published games with developer info.
  * `show($slug)`: Returns single game with active version.
* **`GameScoreController`**:
  * `submit()`: Enforces game status rules (blocks draft games; restricts sandbox games to owner/admin), invokes the action, returns updated rank.
  * `leaderboard()`: Fetches top 50 personal bests with eager-loaded user avatars.
* **`GameSaveController`**:
  * `show()`: Retrieves cloud save by slot (0–9).
  * `store()`: Implements **optimistic concurrency control** via `revision` field to prevent overwriting newer saves from another tab/device.

#### 4. API Endpoints Registered (`routes/api.php`)

| Method | Endpoint | Auth | Rate Limit | Description |
|---|---|---|---|---|
| `GET` | `/api/games` | Public | — | Browse published game catalog |
| `GET` | `/api/games/{slug}` | Public | — | Get game details & active version |
| `GET` | `/api/games/{slug}/leaderboard` | Public | — | View top 50 leaderboard |
| `POST` | `/api/games/{slug}/scores` | Sanctum | 10 req/min | Submit game score & compute rank |
| `GET` | `/api/games/{slug}/saves/{slot?}` | Sanctum | — | Load game state |
| `POST` | `/api/games/{slug}/saves` | Sanctum | 20 req/min | Save game state (optimistic lock) |
| `GET` | `/api/user` | Sanctum | — | Get authenticated player profile |

---

## 5. Phase 4: PlatformSDK, Test Harness & Host Stub (✅ Done)

### Goal
Prove the full **game ⇄ portal ⇄ API** loop works *before* any real game or the Vue portal exists.

### What Was Built & Verified:
1. **`PlatformSDK.ts`**: Single zero-dependency TypeScript library with dual execution mode (standalone offline mock via `localStorage` vs embedded iframe mode via `postMessage`).
2. **`test-harness/index.html`**: Interactive dummy game testing Handshake, Score Submissions, and Optimistic-concurrency Cloud Saves.
3. **`host-stub/index.html`**: Local host relaying messages to Laravel REST API with strict origin check.

### Real-World Analogy for Phase 4:
> Think of `PlatformSDK` as a **secure walkie-talkie protocol**.
> The game running inside an iframe is like an embassy inside a foreign country: it has strict boundaries and cannot walk directly into the host nation's government databases.
> Instead, the game says over the walkie-talkie (`window.postMessage`): *"Hey Host, player scored 5,000 points!"*
> The Host verifies the frequency and voice (`event.origin` and `event.source`), takes out its official diplomatic badge (Laravel Sanctum token), calls the backend government office (`POST /api/games/test-harness/scores`), and radios back: *"Confirmed, you are Rank #1."*

---

## 6. Phase 5: Vue 3 + Bootstrap 5 Frontend Portal (✅ Done)

### Goal
Replace the temporary throwaway `host-stub` with the production-ready **Vue 3 single-page web portal (SPA)**, styled with an immersive dark gaming theme (Bootstrap 5), dynamic aspect-ratio iframe player, real-time leaderboard widget, and user authentication state (Pinia).

### Technical Concepts & Analogies

#### 1. Vue 3 Composition API & `<script setup>`
* **Analogy**: In the older Vue 2 (Options API), building a component was like putting your tools into rigid pre-labeled drawers (`data`, `methods`, `computed`). In Vue 3 Composition API (`<script setup>`), you keep related tools together on your workbench: all logic for loading a game, its state, and its cleanup live side-by-side in one tidy bundle.

#### 2. Pinia (State Management)
* **Analogy**: Think of Pinia as the **ship's master logbook**. Instead of passing user login info and game catalog state up and down through dozens of parent and child components (prop drilling), any component anywhere on the ship can open the logbook to see: *"Who is currently logged in?"* and *"What is their current active score?"*.

#### 3. Reusable Composable (`useGameBridge`)
* **Analogy**: A **dedicated translator headset**. Instead of cluttering your visual UI templates with low-level browser event listeners, the composable plugs into the iframe, listens for radio chatter from `PlatformSDK`, checks origin security credentials, calls Pinia/Axios, and replies back to the iframe.

#### 4. Dynamic Aspect Ratio Viewport (`16:9`, `4:3`, `9:16`)
* **Analogy**: A **universal projector screen**. An arcade retro game might be boxy (`4:3`), a modern action game wide (`16:9`), or a mobile runner tall (`9:16`). The portal dynamically adjusts its viewport frame so the game never stretches or cuts off.

---

## 7. Phase 6: Developer Dashboard & Secure Game `.zip` Upload Ingestion (✅ Done)

### Goal
Empower creators to upload compiled web game archives (`dist.zip` / itch.io style). Laravel inspects, extracts, validates, and stores game assets under versioned prefixes (`games/{slug}/v{version}/`), making them instantly launchable in the portal.

### Technical Concepts & Analogies

#### 1. The "Airport Security Scanner" (Inspecting before Unzipping)
* **Analogy**: You never let an uninspected suitcase into the building and open it on the floor. Before calling `extractTo()`, Laravel inspects the manifest of the `.zip` archive inside memory. If there's an executable bomb (`.php`, `.sh`, `.exe`), or if the luggage is missing its passport (`index.html` at root), it is confiscated before any file touches your disk.

#### 2. Zip Slip Vulnerability (Path Traversal Protection)
* **Analogy**: An attacker writes a file inside a `.zip` named `../../../../etc/passwd` or `../../public/evil.php`. When unzipped, standard extractors climb out of the game folder and overwrite critical server files. We explicitly inspect entry paths and reject any path containing `..`.

#### 3. Versioned Immutable Storage (`games/{slug}/v{version}/`)
* **Analogy**: Every game release is an **immutable museum display case**. Version 1 remains untouched forever. When a developer uploads an update, it becomes Version 2 in a new display case. If Version 2 breaks, rolling back to Version 1 is instant without re-uploading assets.

---

## 8. Phase 7: LocalStack & AWS Cloud Infrastructure (✅ LocalStack Verified · Next: AWS OAC & OIDC)

### Goal
Take our storage and deployment architecture to production-grade cloud standards:
1. **LocalStack (Local S3 Emulation)**: Run a local mock AWS S3 container via Docker so Laravel uploads directly to an S3 bucket locally without paying a cent or needing an AWS credit card. *(Verified & Complete)*
2. **Web vs CLI Architecture Comparison**: Documented in depth in [docs/LOCALSTACK_WEB_VS_CLI.md](file:///home/afif/projects/web-platform/docs/LOCALSTACK_WEB_VS_CLI.md).
3. **CloudFront with Origin Access Control (OAC)**: The industry standard for game CDN delivery. The S3 bucket has `BlockPublicAccess` enabled (100% private), and only CloudFront can read files from it.
4. **GitHub Actions CI/CD via OIDC**: Game repos can push to S3 automatically without saving permanent, risky AWS secret keys in GitHub.

### Technical Concepts & Analogies

#### 1. LocalStack (The Flight Simulator)
* **Analogy**: You don't learn to fly by hopping straight into a multi-million-dollar Boeing 747 in a storm. LocalStack is a **flight simulator for AWS** running right inside your Docker Desktop. Laravel talks to `http://localhost:4566` using the standard AWS SDK as if it were real AWS!

#### 2. LocalStack Web Console vs CLI/Init Script
* **Analogy**: The Web Console is the **cockpit dials and gauges** (great for visual inspection), while the init script (`init-s3.sh`) is the **automated pre-flight checklist** (runs deterministically every single time). See [LOCALSTACK_WEB_VS_CLI.md](file:///home/afif/projects/web-platform/docs/LOCALSTACK_WEB_VS_CLI.md) for full breakdown.

#### 3. Origin Access Control (OAC) & Private S3 Buckets
* **Analogy**: The private S3 bucket is a **bank vault** behind locked doors. CloudFront is the **teller at the counter**. Players cannot walk into the vault directly; they can only ask the teller (CloudFront CDN), who validates requests and hands out the game files securely at lightning speed.

#### 4. OpenID Connect (OIDC) Federation for GitHub Actions
* **Analogy**: Instead of leaving a master key to your house hidden under the welcome mat (permanent AWS access keys that can get leaked or stolen), you give GitHub a **temporary visitor badge** that expires after 15 minutes and only lets it enter one specific game room (`s3://games-bucket/games/{slug}/v{version}/`).

---

## 9. Official Documentation & References

Here are the official documentation links for the core technologies and architectural patterns used across the platform:

### Phase 1 & 2 (Database Schema & Eloquent ORM)
* [Laravel 11 Database Migrations](https://laravel.com/docs/11.x/migrations): Defining tables, foreign keys, and composite indexes.
* [Laravel 11 Eloquent ORM & Relationships](https://laravel.com/docs/11.x/eloquent-relationships): Defining relationships (`hasMany`, `belongsTo`, `belongsToMany`).
* [Laravel Eloquent Strictness (`preventLazyLoading`)](https://laravel.com/docs/11.x/eloquent#preventing-lazy-loading): Guarding against N+1 performance bottlenecks in development.

### Phase 3 (Backend REST API & Security)
* [Laravel Sanctum](https://laravel.com/docs/11.x/sanctum): Token-based API and SPA authentication.
* [Laravel Eloquent API Resources](https://laravel.com/docs/11.x/eloquent-resources): JSON response transformation layers.
* [Laravel Routing & Rate Limiting (`throttle`)](https://laravel.com/docs/11.x/routing#rate-limiting): Defending score submissions and save states against abuse.
* [Laravel CORS Configuration](https://laravel.com/docs/11.x/routing#cors): Allowing browser SPA requests across local development ports.

### Phase 4 (SDK, Sandboxing & Cross-Origin Messaging)
* [MDN `Window.postMessage()`](https://developer.mozilla.org/en-US/docs/Web/API/Window/postMessage): Secure cross-origin communication between iframe and host.
* [MDN `<iframe>` Sandbox Attribute](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/iframe#sandbox): Restricting iframe capabilities (`allow-scripts`, `allow-same-origin`, `allow-pointer-lock`).
* [TypeScript Official Handbook](https://www.typescriptlang.org/docs/handbook/intro.html): Static typing, interfaces, and declaration emission (`tsc`).

### Phase 5 (Vue 3 Portal & State Architecture)
* [Vue 3 Composition API & `<script setup>`](https://vuejs.org/api/sfc-script-setup.html): Modern Single-File Component reactive syntax.
* [Vue Router 4](https://router.vuejs.org/guide/): Client-side routing for Single Page Applications.
* [Pinia](https://pinia.vuejs.org/core-concepts/): Official Vue state store management.
* [Bootstrap 5 Color Modes (Dark Theme)](https://getbootstrap.com/docs/5.3/customize/color-modes/): `data-bs-theme="dark"` styling and responsive grid.
* [Axios Interceptors](https://axios-http.com/docs/interceptors): Request/response middleware for Bearer token attachment and error interception.

### Phase 6 (Game Ingestion & Zip Security)
* [PHP ZipArchive](https://www.php.net/manual/en/class.ziparchive.php): Stream inspection and archive extraction.
* [Snyk Zip Slip Vulnerability Research](https://snyk.io/research/zip-slip-vulnerability/): Understanding directory traversal attacks via archives.
* [Laravel File Storage](https://laravel.com/docs/11.x/filesystem): Managing local, public, and S3-compatible disks.

### Phase 7 (Cloud Infrastructure & LocalStack)
* [LocalStack S3 Emulation](https://docs.localstack.cloud/user-guide/aws/s3/): Local emulation of AWS S3.
* [AWS CloudFront Origin Access Control (OAC)](https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/private-content-restricting-access-to-s3.html): Restricting S3 access strictly to CloudFront distributions.
* [GitHub Actions OIDC with AWS](https://docs.github.com/en/actions/deployment/security-hardening-your-deployments/configuring-openid-connect-in-amazon-web-services): Keyless AWS authentication for automated CI/CD pipelines.

---

## 10. Project Summary & Completed Vision

All 7 core milestones of the Web Game Distribution & Portal Platform are now documented, designed, and verified end-to-end!
