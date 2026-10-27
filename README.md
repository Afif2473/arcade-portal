# 🕹️ Arcade Portal — Web Game Distribution & Cloud Platform

[![Laravel 11](https://img.shields.io/badge/Laravel-11-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![Vue 3](https://img.shields.io/badge/Vue-3-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)](https://vuejs.org)
[![TypeScript](https://img.shields.io/badge/TypeScript-5-3178C6?style=for-the-badge&logo=typescript&logoColor=white)](https://www.typescriptlang.org)
[![Bootstrap 5](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)](https://getbootstrap.com)
[![AWS CloudFront & S3](https://img.shields.io/badge/AWS-S3%20%2B%20CloudFront%20OAC-FF9900?style=for-the-badge&logo=amazon-aws&logoColor=white)](https://aws.amazon.com)
[![Terraform](https://img.shields.io/badge/Terraform-1.17-844FBA?style=for-the-badge&logo=terraform&logoColor=white)](https://www.terraform.io)
[![Docker & LocalStack](https://img.shields.io/badge/LocalStack-S3%20Emulation-0052CC?style=for-the-badge&logo=docker&logoColor=white)](https://localstack.cloud)

A full-stack, cloud-native **Web Game Distribution and Portal Platform** (itch.io / Kongregate style) built with **Laravel 11**, **Vue 3**, a zero-dependency **TypeScript Client SDK**, and **AWS CloudFront/S3** infrastructure codified in **Terraform**.

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
3. **Optimized Backend**: Laravel 11 services handle atomic score ranking calculations and cloud save slots with optimistic concurrency.
4. **Cloud-Native Storage**: Game releases are stored under versioned, immutable prefixes (`games/{slug}/v{version}/`) on AWS S3, delivered globally via CloudFront with **Origin Access Control (OAC)**.

---

## 🚀 Key Features

* **Zero-Dependency Game SDK (`PlatformSDK.ts`)**:
  * Dual-mode execution: runs standalone offline using mock `localStorage`, or embedded in the portal via `window.postMessage`.
  * Typed promises with timeout handling, request correlation IDs, and handshake protocol.
* **Real-time Leaderboards & Atomic Scoring**:
  * Dual-table schema: `scores` (immutable chronological run history) and `leaderboard_entries` (one row per player per game).
  * Instant composite rank calculations via `SubmitGameScoreAction` with row-level locking (`lockForUpdate`) against race conditions.
* **Optimistic-Concurrency Cloud Saves**:
  * 10 cloud save slots (0–9) per game per player.
  * Versioned `revision` locks prevent overwriting state from concurrent tabs or devices.
* **Developer Studio & Secure `.zip` Ingestion**:
  * In-memory `ZipArchive` inspection before extraction.
  * **Zip Slip (Path Traversal) Protection** against `../` malicious archives.
  * Strict file extension whitelisting and root `index.html` enforcement.
  * Version incrementing (`v1` → `v2` → `v3`...) with immutable directory partitioning.
* **Automated CI/CD Deployment with Least Privilege**:
  * GitHub Actions workflow deploying game builds to S3 on `git push`.
  * **Per-game prefix isolation**: CI credentials can write *only* to their assigned folder (`games/{slug}/*`), verified with automated security boundary tests.
* **Infrastructure as Code (Terraform)**:
  * Complete declarative blueprints for S3, CloudFront OAC, S3 bucket policies, and scoped IAM roles.

---

## 📁 Repository Structure

```text
├── backend/                  # Laravel 11 REST API
│   ├── app/Actions/          # Business actions (SubmitGameScoreAction, ProcessGameZipUploadAction)
│   ├── app/Http/Controllers/ # Thin API controllers (GameCatalog, GameScore, GameSave, DeveloperGame)
│   ├── config/filesystems.php# S3 & LocalStack filesystem disk bindings
│   └── database/migrations/  # MySQL 8 schema with composite performance indexes
├── frontend/                 # Vue 3 SPA (Vite + Bootstrap 5 dark theme)
│   ├── src/composables/      # useGameBridge.js (postMessage listener & API relay)
│   ├── src/stores/           # Pinia stores (auth.js, game.js)
│   └── src/views/            # CatalogView, GameView (iframe player), DeveloperDashboardView
├── sdk/                      # TypeScript PlatformSDK client library
│   └── src/PlatformSDK.ts    # Zero-dependency postMessage wrapper & offline mock
├── test-harness/             # 50-line HTML5 test harness game
├── infra/                    # Infrastructure configurations
│   ├── localstack/           # LocalStack Docker init scripts & CORS setup
│   └── terraform/            # Declarative AWS IaC (S3, CloudFront OAC, IAM)
└── docs/                     # Architectural documentation & phase summaries
    ├── PHASES_SUMMARY.md     # Living phase-by-phase design decision log
    ├── AWS_DEPLOYMENT_GUIDE.md# Step-by-step console, CLI, and Terraform deployment guide
    └── LOCALSTACK_WEB_VS_CLI.md# Deep-dive comparison between Web Console & CLI automation
```

---

## 🛠️ Quick Start (Local Development)

### 1. Prerequisites
* **Docker** & **Docker Compose**
* **PHP 8.2+** & **Composer**
* **Node.js 20+** & **npm**

### 2. Clone and Start Infrastructure Containers
```bash
git clone https://github.com/Afif2473/web-platform.git
cd web-platform

# Start MySQL and LocalStack S3
docker compose up -d
```

### 3. Backend Setup (Laravel API)
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate

# Run migrations and seed test data
php artisan migrate --seed

# Start the API server
php artisan serve
# Listening on http://127.0.0.1:8000
```

### 4. Frontend Setup (Vue 3 Portal)
```bash
cd ../frontend
npm install
npm run dev
# Portal running on http://localhost:5173
```

### 5. Test Accounts Seeded by Default

| Role | Username | Email | Password |
|---|---|---|---|
| **Developer** | `testdev` | `dev@example.com` | `password123` |
| **Player** | `speedrunner99` | `player@example.com` | `password123` |

---

## 🎮 How to Integrate Your Game with `PlatformSDK`

Integrating a web game requires zero external dependencies. Include `PlatformSDK.js` in your game bundle:

```html
<script type="module">
  import { PlatformSDK } from './PlatformSDK.js';

  // 1. Initialize SDK
  const sdk = new PlatformSDK({
    allowedHostOrigins: ['http://localhost:5173', 'https://your-portal.com'],
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

For in-depth explanations, architectural decisions, and step-by-step guides, refer to the documentation in `/docs`:

* 📖 **[Phase-by-Phase Architecture Summary](docs/PHASES_SUMMARY.md)**: Design decisions, analogies, schema diagrams, and technical deep dives across all 7 phases.
* ☁️ **[AWS Production Deployment Guide](docs/AWS_DEPLOYMENT_GUIDE.md)**: Private S3 bucket configuration, CloudFront OAC policies, keyless CI/CD, and cost guardrails.
* ⚖️ **[LocalStack Web Console vs CLI/IaC](docs/LOCALSTACK_WEB_VS_CLI.md)**: Architectural comparison between GUI-based and script-based cloud emulation.

---

## 📄 License
This project is open-source and available under the [MIT License](LICENSE).
