# 🕹️ Arcade Portal — Web Game Distribution & Cloud Platform

[![Laravel 11](https://img.shields.io/badge/Laravel-11-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![Vue 3](https://img.shields.io/badge/Vue-3-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)](https://vuejs.org)
[![TypeScript](https://img.shields.io/badge/TypeScript-5-3178C6?style=for-the-badge&logo=typescript&logoColor=white)](https://www.typescriptlang.org)
[![Bootstrap 5](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)](https://getbootstrap.com)
[![AWS CloudFront & S3](https://img.shields.io/badge/AWS-S3%20%2B%20CloudFront%20OAC-FF9900?style=for-the-badge&logo=amazon-aws&logoColor=white)](https://aws.amazon.com)
[![GitHub Actions CI/CD](https://img.shields.io/badge/CI%2FCD-PR%20Gate%20%2B%20Auto%20Deploy-2088FF?style=for-the-badge&logo=github-actions&logoColor=white)](https://github.com/features/actions)
[![SSL / HTTPS](https://img.shields.io/badge/SSL-Let's%20Encrypt%20Auto--Renew-003A70?style=for-the-badge&logo=letsencrypt&logoColor=white)](https://letsencrypt.org)

A production-ready, cloud-native **Web Game Distribution and Portal Platform** (itch.io / Kongregate style) built with **Laravel 11**, **Vue 3**, a zero-dependency **TypeScript Client SDK**, **AWS CloudFront/S3** asset infrastructure, and automated **GitHub Actions CI/CD** deploying to AWS EC2.

🌐 **Live Production Portal:** [https://arcadeportal.duckdns.org](https://arcadeportal.duckdns.org)

---

## 🌟 Architecture Overview

The platform uses a strict **Two-Party Sandboxed Architecture**:

```
┌─────────────────────────────────┐
│     Game Client (Iframe)        │
│  - Sandboxed HTML5/Canvas/WebGL │
│  - Zero backend tokens / keys   │
│  - PlatformSDK.ts client        │
└───────────────┬─────────────────┘
                │ window.postMessage (Structured envelope)
                ▼
┌─────────────────────────────────┐
│     Parent Vue 3 Portal         │
│  - Origin & source verification │
│  - Holds Laravel Sanctum Token  │
│  - Iframe aspect-ratio viewport │
└───────────────┬─────────────────┘
                │ HTTPS REST API (/api/...)
                ▼
┌─────────────────────────────────┐
│     Laravel 11 Backend API      │
│  - FormRequest Validation       │
│  - Dedicated Action Classes     │
│  - MySQL 8.0 (Composite Indexes)│
└───────────────┬─────────────────┘
                │
                ├────────────────────────────────────────┐
                ▼                                        ▼
┌─────────────────────────────────┐    ┌─────────────────────────────────┐
│    Local Development Storage    │    │    Production Cloud Storage     │
│  - LocalStack S3 (port 4566)    │    │  - 100% Private AWS S3 Bucket   │
│  - Automatic init & CORS hooks  │    │  - CloudFront OAC Edge Delivery │
└─────────────────────────────────┘    └─────────────────────────────────┘
```

1. **Sandboxed Game Client**: Web games run inside an isolated `<iframe>` served from CloudFront CDN with strict capabilities (`allow-scripts`, `allow-pointer-lock`). Games **never** hold database credentials or access tokens.
2. **Parent Host Portal**: The Vue 3 Single Page Application validates origin security, manages user sessions via Pinia and Laravel Sanctum, and translates SDK requests into backend API calls.
3. **Authoritative Backend**: Laravel 11 services handle atomic score ranking calculations and cloud save slots with optimistic concurrency.
4. **Cloud-Native Storage**: Game releases are stored under versioned, immutable prefixes (`games/{slug}/v{version}/`) on AWS S3, delivered globally via CloudFront with **Origin Access Control (OAC)**.
5. **Production Infrastructure**: AWS EC2 instance running Ubuntu 24.04, Nginx reverse proxy, PHP 8.3 FPM, MySQL 8.0, and automated Let's Encrypt SSL.

---

## 🚀 Key Features

* **Real Authentication & Role-Based Access Control (RBAC)**:
  * Laravel Sanctum plain-text bearer tokens stored in browser `localStorage`.
  * Distinct roles: `player`, `developer`, and `admin`.
  * Client-side Vue Router navigation guards (`beforeEach`) + backend controller authorization (`403 Forbidden`).
  * Welcome email notifications upon account registration.
  * Secure password recovery flow with 60-minute single-use reset tokens and custom email templates.
* **Developer Studio & Secure `.zip` Ingestion**:
  * Self-service game registration with automatic slug generator and aspect ratio configuration.
  * In-memory `ZipArchive` validation with **Zip Slip (Path Traversal) Protection** against `../` malicious archives.
  * Root `index.html` enforcement and automatic version increments (`v1` → `v2` → `v3`...).
  * Release lifecycle state transitions: `draft` (private) → `sandbox` (testing) → `published` (storefront).
* **Zero-Dependency Game SDK (`PlatformSDK.ts`)**:
  * Dual-mode execution: runs standalone offline using mock `localStorage`, or embedded in the portal via `window.postMessage`.
  * Typed promises with timeout handling, request correlation IDs, and handshake protocol.
* **Real-time Leaderboards & Atomic Scoring**:
  * Dual-table schema: `scores` (immutable chronological run history with SHA-256 IP audit trails) and `leaderboard_entries` (deduplicated personal best per player per game).
  * Instant composite rank calculations via `SubmitGameScoreAction` with row-level locking (`lockForUpdate`) against race conditions.
* **Optimistic-Concurrency Cloud Saves**:
  * 10 cloud save slots (0–9) per game per player.
  * Versioned `revision` locks prevent overwriting state from concurrent tabs or devices (`409 Conflict`).
* **Automated Two-Tier CI/CD Pipeline (GitHub Actions)**:
  * **PR Quality Gate (`.github/workflows/pr-checks.yml`)**: Runs full PHPUnit test suite with in-memory SQLite and verifies Vue 3 production build on every pull request.
  * **Continuous Deployment (`.github/workflows/deploy-production.yml`)**: Automatically connects via SSH to the live EC2 host on merge to `main`, pulling latest commits, migrating database, rebuilding the frontend, and reloading Nginx without downtime.
* **Infrastructure as Code (Terraform)**:
  * Complete declarative blueprints for S3, CloudFront OAC, S3 bucket policies, and scoped IAM roles in `infra/terraform/`.

---

## 📁 Repository Structure

```text
├── backend/                  # Laravel 11 REST API
│   ├── app/Actions/          # Business actions (SubmitGameScoreAction, ProcessGameZipUploadAction)
│   ├── app/Http/Controllers/ # Thin API controllers (AuthController, GameCatalog, GameScore, GameSave, DeveloperGame)
│   ├── app/Notifications/    # Mail notifications (WelcomeNotification, ResetPasswordNotification)
│   ├── config/filesystems.php# S3 & LocalStack filesystem disk bindings
│   ├── database/migrations/  # MySQL 8 schema with composite performance indexes
│   └── tests/Feature/        # PHPUnit automated feature test suites
├── frontend/                 # Vue 3 SPA (Vite + Bootstrap 5 dark theme)
│   ├── src/api/client.js     # Axios client with Bearer token interceptor
│   ├── src/components/       # Navbar.vue, AuthModal.vue
│   ├── src/stores/           # Pinia stores (auth.js)
│   └── src/views/            # CatalogView, GameView, DeveloperDashboardView, ResetPasswordView
├── sdk/                      # TypeScript PlatformSDK client library
│   └── src/PlatformSDK.ts    # Zero-dependency postMessage wrapper & offline mock
├── test-harness/             # 50-line HTML5 test harness game
├── infra/                    # Infrastructure configurations
│   ├── deploy-live-ec2.sh    # Single-command automated EC2 deployment script
│   ├── localstack/           # LocalStack Docker init scripts & CORS setup
│   └── terraform/            # Declarative AWS IaC (S3, CloudFront OAC, IAM)
├── .github/workflows/        # Automated CI/CD pipelines
│   ├── pr-checks.yml         # PR Quality Gate (Tests + Frontend Build)
│   ├── deploy-production.yml # CD Production Deployment to AWS EC2
│   └── deploy-test-harness.yml# Game asset deployment & boundary tests
└── docs/                     # Architectural documentation & manuals
    ├── SYSTEM_ARCHITECTURE_AND_FLOWS.md # End-to-end request lifecycles & Mermaid sequence diagrams
    ├── PHASES_SUMMARY.md     # Phase-by-phase design decision log
    ├── AWS_DEPLOYMENT_GUIDE.md# Step-by-step AWS console & Terraform setup guide
    └── LOCALSTACK_WEB_VS_CLI.md# Comparison between Web Console & CLI emulation
```

---

## 🛠️ Quick Start (Local Development)

### 1. Prerequisites
* **Docker** & **Docker Compose**
* **PHP 8.2+** & **Composer**
* **Node.js 20+** & **npm**

### 2. Clone Repository
```bash
git clone https://github.com/Afif2473/arcade-portal.git
cd arcade-portal
```

### 3. Start Local Database & Storage
```bash
# Start MySQL 8.0 and LocalStack S3
docker compose up -d
```

### 4. Backend Setup (Laravel API)
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate

# Run migrations and seed default data
php artisan migrate --seed

# Start API server
php artisan serve
# Listening on http://127.0.0.1:8000
```

### 5. Frontend Setup (Vue 3 Portal)
```bash
cd ../frontend
npm install
npm run dev
# Portal running on http://localhost:5173
```

---

## 🧪 Running Automated Tests

Run the full backend test suite in memory:
```bash
cd backend
php artisan test
```

Test coverage includes:
* `Tests\Feature\AuthTest`: Registration, login, logout, password recovery tokens, and reset validation.
* `Tests\Feature\DeveloperGameTest`: Game registration, slug uniqueness, and developer-only role gating.
* `Tests\Feature\GameCatalogTest`: Public catalog visibility filtering (`published` vs `draft`/`sandbox`).
* `Tests\Feature\ScoreSubmissionTest`: Leaderboard ranking, audit trails, and anti-tamper validation.
* `Tests\Feature\GameSaveTest`: Multi-slot saves (0–9) and optimistic concurrency conflict detection (`409 Conflict`).

---

## 🎮 How to Integrate Your Game with `PlatformSDK`

Integrating a web game requires zero external dependencies. Include `PlatformSDK.js` in your game bundle:

```html
<script type="module">
  import { PlatformSDK } from './PlatformSDK.js';

  // 1. Initialize SDK
  const sdk = new PlatformSDK({
    allowedHostOrigins: ['http://localhost:5173', 'https://arcadeportal.duckdns.org'],
    debug: true
  });

  // 2. Perform Handshake
  const session = await sdk.init();
  console.log('Player:', session.user?.username ?? 'Guest');

  // 3. Submit High Scores
  const result = await sdk.submitScore(12500, { level: 4, character: 'warrior' });
  console.log(`Current Rank: #${result.rank}, New Personal Best: ${result.isNewBest}`);

  // 4. Cloud Save & Load
  await sdk.saveState(0, { health: 100, inventory: ['sword', 'shield'] }, session.revision);
  const save = await sdk.loadState(0);
</script>
```

---

## 📚 Technical Documentation

For in-depth explanations, architectural diagrams, and step-by-step guides, refer to `/docs`:

* 📐 **[System Architecture & Deep Request Flows](docs/SYSTEM_ARCHITECTURE_AND_FLOWS.md)**: End-to-end request lifecycles, Mermaid sequence diagrams, sandbox security protocols, and CI/CD topologies.
* 📖 **[Phase-by-Phase Architecture Summary](docs/PHASES_SUMMARY.md)**: Design decisions, database schema evolution, and technical deep dives across all 7 phases.
* ☁️ **[AWS Production Deployment Guide](docs/AWS_DEPLOYMENT_GUIDE.md)**: Private S3 bucket configuration, CloudFront OAC policies, keyless CI/CD, and cost guardrails.
* ⚖️ **[LocalStack Web Console vs CLI/IaC](docs/LOCALSTACK_WEB_VS_CLI.md)**: Architectural comparison between GUI-based and script-based cloud emulation.

---

## 📄 License
This project is open-source and available under the [MIT License](LICENSE).
