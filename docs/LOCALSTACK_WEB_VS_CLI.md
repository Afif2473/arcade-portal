# LocalStack Web Console vs. CLI / Init-Script: Configuration Guide & Comparison

This guide details how to configure and inspect **LocalStack S3** using the **LocalStack Web App (Console)** and compares it directly with the **Docker Init-Script / CLI** workflow we built in Phase 7.

---

## 1. Overview & Real-World Analogy

When emulating AWS services locally with LocalStack, you have two primary ways to create, configure, and inspect resources:

```
┌────────────────────────────────────────────────────────┐
│                   LocalStack Runtime                   │
│               Docker (localhost:4566)                  │
└──────────────▲──────────────────────────▲──────────────┘
               │                          │
       HTTP / REST APIs            Local Docker Exec
               │                          │
┌──────────────┴──────────────┐   ┌───────┴──────────────┐
│     LocalStack Web App      │   │    CLI & Init Script │
│  (https://app.localstack.   │   │ (init-s3.sh /        │
│          cloud)             │   │  awslocal commands)  │
└─────────────────────────────┘   └──────────────────────┘
```

### The Analogy: The Flight Cockpit vs. The Autopilot Script
* **LocalStack Web App (The Cockpit Dashboard)**: You sit in front of physical knobs, gauges, and switches. You can visually inspect fuel levels, flip a switch to open a valve, and see live telemetry. It is intuitive, visual, and great for ad-hoc diagnostics.
* **CLI & Init Script (The Pre-Flight Autopilot Program)**: You write a flight plan once in code. Every time the jet engine spins up, the computer runs the pre-flight routine automatically—pressurizing tanks, checking flap angles, and setting radio frequencies—without touching a single manual switch.

---

## 2. Configuring LocalStack via the Web Console

LocalStack provides a cloud-hosted web interface at [app.localstack.cloud](https://app.localstack.cloud) that connects directly to your local Docker container via your web browser (`http://localhost:4566`).

### Step-by-Step Walkthrough

#### Step 1: Ensure LocalStack is Running Locally
Before opening the web console, ensure your LocalStack container is running on your machine:
```bash
docker compose up -d localstack
```
Verify the port is responding:
```bash
curl http://localhost:4566/_localstack/health
```

#### Step 2: Open and Authenticate on LocalStack Web
1. Open your browser and navigate to **[https://app.localstack.cloud](https://app.localstack.cloud)**.
2. Sign in with your account (or create a free personal tier account).

#### Step 3: Connect to your Local Instance
1. In the left navigation sidebar, navigate to **Instances** or look at the status indicator in the top header.
2. Select **Local Instance** (`http://localhost:4566`).
3. If connected successfully, the status indicator turns **Green (Active / Running)**.
> *Note: Because your browser connects directly to `localhost:4566`, ensure your browser allows local network requests without adblockers blocking `localhost` requests.*

#### Step 4: Create the S3 Bucket (`game-portal-assets`)
1. In the sidebar under **Cloud Services**, click on **S3**.
2. Click the **Create Bucket** button.
3. Fill in the bucket details:
   * **Bucket Name**: `game-portal-assets`
   * **Region**: `us-east-1`
4. Click **Create Bucket**. The bucket will now appear in your bucket inventory list.

#### Step 5: Configure CORS for Web Portal Access
Because our Vue 3 portal runs on `http://localhost:5173`, the browser will block cross-origin game requests unless CORS is defined:
1. Click on the `game-portal-assets` bucket in the list.
2. Switch to the **Permissions** or **CORS Configuration** tab.
3. Paste the following JSON policy:
   ```json
   [
     {
       "AllowedOrigins": [
         "http://localhost:5173",
         "http://127.0.0.1:5173"
       ],
       "AllowedMethods": ["GET", "HEAD"],
       "AllowedHeaders": ["*"],
       "MaxAgeSeconds": 3000
     }
   ]
   ```
4. Click **Save Changes**.

#### Step 6: Upload or Inspect Game Assets
1. Switch to the **Objects** tab inside `game-portal-assets`.
2. To create the folder structure manually:
   * Click **Create Folder** and enter `games/test-harness/v1/`.
3. Click **Upload** to select `index.html` and `PlatformSDK.js`.
4. Under **Object Metadata / System Defined Headers**:
   * Add key: `Cache-Control`
   * Add value: `no-cache, must-revalidate` (for `index.html`)
5. Click **Upload Objects**.

---

## 3. Recap: The CLI & Init-Script Workflow We Implemented

In Phase 7, we configured LocalStack deterministically via code rather than manual clicking.

### 1. Docker Compose Hook Mapping (`docker-compose.yaml`)
```yaml
services:
  localstack:
    image: localstack/localstack:3
    container_name: localstack-game-portal
    ports:
      - "4566:4566"
    environment:
      - SERVICES=s3
      - AWS_DEFAULT_REGION=us-east-1
    volumes:
      - ./infra/localstack/init-s3.sh:/etc/localstack/init/ready.d/init-s3.sh
      - ./test-harness:/seed/test-harness:ro
```

### 2. The Idempotent Init Script (`infra/localstack/init-s3.sh`)
```bash
#!/bin/bash
set -e
BUCKET=game-portal-assets

# Create Bucket
awslocal s3 mb s3://$BUCKET || true

# Apply CORS
awslocal s3api put-bucket-cors --bucket $BUCKET --cors-configuration '{
  "CORSRules": [{
    "AllowedOrigins": ["http://localhost:5173", "http://127.0.0.1:5173"],
    "AllowedMethods": ["GET", "HEAD"],
    "AllowedHeaders": ["*"],
    "MaxAgeSeconds": 3000
  }]
}'

# Seed Test-Harness version 1
awslocal s3 sync /seed/test-harness s3://$BUCKET/games/test-harness/v1/ \
  --cache-control "no-cache, must-revalidate"
```

### 3. Inspection via Docker CLI
```bash
# List all files recursively
docker exec localstack-game-portal awslocal s3 ls s3://game-portal-assets/games/test-harness/ --recursive
```

---

## 4. Comprehensive Comparison Matrix

| Criteria | Web Console (`app.localstack.cloud`) | CLI & Init Script (`init-s3.sh`) |
|---|---|---|
| **Primary Interaction** | Graphical user interface (point-and-click in browser) | Shell scripts, Docker entrypoint hooks, and `awslocal` commands |
| **Setup Time** | Fast for single, exploratory tweaks (no script writing needed) | Requires writing shell scripts and configuring Docker volume binds |
| **Reproducibility** | ❌ **Low**: Steps must be manually repeated by every team member | ✅ **100% Deterministic**: Run `docker compose up -d` and everything is ready |
| **Ephemeral Container Lifecycle** | ⚠️ **Fragile**: If you destroy or recreate the container (`docker compose down`), all manual clicks are lost | ✅ **Resilient**: Every time container starts from scratch, `ready.d` recreates bucket, CORS, and seeded files |
| **CI / CD Pipeline Compatibility** | ❌ **Impossible**: GitHub Actions and headless CI cannot click browser buttons | ✅ **Native**: Runs seamlessly inside automated test suites and GitHub Actions runners |
| **Authentication & Connectivity** | Requires browser internet access to `app.localstack.cloud` and account login | 100% offline; runs purely locally inside Docker without internet or account |
| **Visual File & Metadata Inspection** | ✅ **Excellent**: Visually browse folders, preview images, read headers, and inspect ETags | Requires terminal commands (`awslocal s3 ls`, `curl -I`) |
| **Error Proneness** | Prone to human omission (e.g. forgetting a CORS rule or typo in header) | Zero manual error once script is committed to version control (`git`) |

---

## 5. Architectural Recommendation: The Hybrid Pattern

In professional game portal and cloud engineering teams, the industry-standard best practice is **The Hybrid Approach**:

1. **Source of Truth = Init Script & Code (`Infrastructure as Code`)**:
   * All bucket definitions, CORS rules, and baseline seed data belong in `infra/localstack/init-s3.sh` and `docker-compose.yaml`.
   * When a new developer clones the repo or CI runs tests, they type `docker compose up -d` and the entire cloud environment boots up in seconds without documentation friction.
2. **Observability & Sanity Checking = Web Console**:
   * Use [app.localstack.cloud](https://app.localstack.cloud) as an **inspection microscope**.
   * When debugging why an asset returns a 403 or why a game texture isn't loading, open the Web Console to verify ETags, Content-Types, and folder hierarchies visually.

---

## 6. Official Documentation Links

* [LocalStack Web App (Console) Documentation](https://docs.localstack.cloud/user-guide/tools/web-app/)
* [LocalStack Initialization Hooks (`ready.d`)](https://docs.localstack.cloud/references/init-hooks/)
* [LocalStack AWS S3 Emulation Reference](https://docs.localstack.cloud/user-guide/aws/s3/)
* [AWS CLI `awslocal` Wrapper](https://github.com/localstack/awscli-local)
