# Phase 7 · Step 2 — Real AWS: Private S3 + CloudFront OAC + GitHub OIDC

**Approach:** first set everything up by hand in the **AWS Console** (Part B) so you see each piece. Then **delete it and rebuild it with Terraform** (Part C), which becomes the source of truth. This is the same idea as the LocalStack comparison: Console to learn and inspect, code to keep and repeat.

> [!IMPORTANT]
> Work through the parts in order. Each part ends with a **✅ Check**. Don't move on until it passes.

```
Player's browser
      │  https://dxxxx.cloudfront.net/games/test-harness/v4/index.html
      ▼
┌──────────────────────┐   signed request (OAC, SigV4)   ┌──────────────────────────┐
│  CloudFront (CDN)    │ ──────────────────────────────▶ │  S3 bucket (100% private) │
│  edge caches, HTTPS  │                                 │  Block Public Access = ON │
└──────────────────────┘                                 └────────────▲─────────────┘
                                                                      │ PutObject
                                       ┌──────────────────────────────┼───────────────────┐
                                       │                              │                   │
                              Laravel (zip upload)        GitHub Actions (OIDC role)      │
                              IAM user (local dev)        writes only games/{slug}/*      │
```

---

## Part A — AWS account & safety setup (do this once)

**Analogy:** before you open a bank account for your company, set up the alarm system. An AWS account is a credit card connected to the internet, so we add guard rails *before* building anything.

### A1. Find out if you already have an account
1. Go to <https://console.aws.amazon.com/> → **Root user** → enter each email you might have used.
2. If AWS says the account doesn't exist, you don't have one with that email. If it asks for a password, click **Forgot password** to recover it.

### A2. Create an account (if needed)
1. <https://aws.amazon.com/free/> → **Create a Free Account**.
2. You'll need an email, a phone number, and a debit/credit card (used for identity checks; a small temporary hold is normal).
3. If you're asked to choose a plan, pick the **Free plan**. New accounts get credits with spending limits. Check the current terms on the Free Tier page, because AWS changes them from time to time.
4. Support plan: **Basic (free)**.

### A3. Lock down the root user (do not skip)
1. Top-right account menu → **Security credentials** → **Assign MFA device** (use an authenticator app).
2. **Never** create access keys for the root user.

### A4. Set up billing alarms
1. Search **Billing and Cost Management** → **Budgets** → **Create budget** → **Use a template** → **Zero spend budget** → enter your email → Create.
2. Create a second one: **Monthly cost budget** at **$5**.

**Analogy:** a smoke detector. The zero-spend budget emails you as soon as anything costs even one cent.

### A5. Create your daily admin identity (instead of root)
1. Search **IAM** → **Users** → **Create user** → name `afif-admin` → check **Provide user access to the AWS Management Console**.
2. Permissions → **Attach policies directly** → `AdministratorAccess` → Create.
3. Sign out of root and sign in as `afif-admin` using the account sign-in URL shown on the IAM dashboard. Turn on MFA for this user too.
4. In IAM → Users → `afif-admin` → **Security credentials** → **Create access key** → choose **Command Line Interface (CLI)** → save the key ID and secret. They're shown only once.

> [!NOTE]
> Professional teams use **IAM Identity Center (SSO)** with short-lived credentials instead of long-lived access keys. An admin IAM user with MFA is fine for learning. We'll get rid of long-lived keys for CI in Part B5.

### A6. Install the AWS CLI v2 in WSL

```bash
cd ~
curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o awscliv2.zip
sudo apt install -y unzip && unzip -q awscliv2.zip && sudo ./aws/install
aws --version            # aws-cli/2.x ...

aws configure            # paste the afif-admin key + secret
#   Default region name: ap-southeast-1
#   Default output format: json
```

> [!TIP]
> **Region:** `ap-southeast-1` (Singapore) is the region closest to your UTC+8 time zone, so uploads from Laravel are faster. CloudFront itself is global, so players everywhere still get edge caching.

### ✅ Check A
```bash
aws sts get-caller-identity
```
You should see your 12-digit `Account` and `"Arn": "arn:aws:iam::<id>:user/afif-admin"`. **Write down the account ID.**

---

## Part B — Console walkthrough (learning pass)

Throughout this part, replace `<ACCOUNT_ID>` with your 12-digit account ID.

### B1. Private S3 bucket (the vault)
S3 → **Create bucket**:

| Setting | Value | Why |
|---|---|---|
| Region | `ap-southeast-1` | Close to you |
| Bucket name | `game-portal-assets-<ACCOUNT_ID>` | Bucket names are global; adding the account ID makes it unique |
| Object Ownership | **ACLs disabled (Bucket owner enforced)** | Turns off old ACL permissions; only policies control access |
| Block *all* public access | ✅ **ON** | Nobody gets direct access to the vault |
| Default encryption | SSE-S3 | Free encryption at rest |

### B2. CloudFront distribution with OAC (the teller)
CloudFront → **Create distribution**. The wizard's layout changes now and then; these are the settings that matter:

| Setting | Value |
|---|---|
| Pricing plan (if asked) | **Free** / pay-as-you-go. Do **not** choose a paid flat-rate tier |
| Origin type / domain | Amazon S3 → choose your bucket (`game-portal-assets-<id>.s3.ap-southeast-1.amazonaws.com`). **Not** the "website endpoint" |
| Origin access | **Origin access control settings (recommended)** → *Create new OAC* → Signing: **Sign requests** |
| Viewer protocol policy | **Redirect HTTP to HTTPS** |
| Allowed HTTP methods | `GET, HEAD` |
| Cache policy | **CachingOptimized** (uses the `Cache-Control` headers Laravel sets) |
| Web Application Firewall (WAF) | **Do not enable security protections** (WAF costs money) |
| Default root object | leave empty |

After you click Create, CloudFront shows a yellow banner: **"The S3 bucket policy needs to be updated" → Copy policy**. Paste it into **S3 → your bucket → Permissions → Bucket policy**. Some newer wizards apply it automatically. Either way, it should look like this:

```json
{
  "Version": "2012-10-17",
  "Statement": [{
    "Sid": "AllowCloudFrontServicePrincipal",
    "Effect": "Allow",
    "Principal": { "Service": "cloudfront.amazonaws.com" },
    "Action": "s3:GetObject",
    "Resource": "arn:aws:s3:::game-portal-assets-<ACCOUNT_ID>/*",
    "Condition": {
      "StringEquals": {
        "AWS:SourceArn": "arn:aws:cloudfront::<ACCOUNT_ID>:distribution/<DISTRIBUTION_ID>"
      }
    }
  }]
}
```

**Analogy:** the policy says *"only the teller at **this specific bank branch** (`SourceArn`) may open the vault."* Even another CloudFront distribution, including one in someone else's account, can't read it.

Wait about 3–5 minutes for the distribution status to change from *Deploying* to a date.

### B3. Put the test harness in the bucket as v1
```bash
BUCKET=game-portal-assets-<ACCOUNT_ID>
aws s3 sync test-harness/ s3://$BUCKET/games/test-harness/v1/ \
  --exclude "index.html" --cache-control "public, max-age=31536000, immutable"
aws s3 cp test-harness/index.html s3://$BUCKET/games/test-harness/v1/index.html \
  --cache-control "no-cache, must-revalidate"
```

### ✅ Check B3 — the vault is private, the teller works
```bash
CDN=dxxxxxxxxxxxx.cloudfront.net    # "Distribution domain name" in the console

# 1. Through CloudFront → 200
curl -sI https://$CDN/games/test-harness/v1/index.html | head -1

# 2. Directly from S3 → 403 AccessDenied (proves Block Public Access works)
curl -sI https://$BUCKET.s3.ap-southeast-1.amazonaws.com/games/test-harness/v1/index.html | head -1
```

### B4. IAM user for Laravel (local dev only)
Laravel runs on your laptop for now, so it needs credentials. We give it **only** the permissions it needs.

IAM → Users → Create user `game-portal-backend-local` (**no** console access) → **Attach policies directly → Create policy → JSON**:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "ReadWriteGameObjects",
      "Effect": "Allow",
      "Action": ["s3:PutObject", "s3:GetObject", "s3:DeleteObject"],
      "Resource": "arn:aws:s3:::game-portal-assets-<ACCOUNT_ID>/games/*"
    },
    {
      "Sid": "ListGamePrefix",
      "Effect": "Allow",
      "Action": "s3:ListBucket",
      "Resource": "arn:aws:s3:::game-portal-assets-<ACCOUNT_ID>",
      "Condition": { "StringLike": { "s3:prefix": ["games/*"] } }
    }
  ]
}
```
Name the policy `game-portal-backend-s3` and attach it. Then go to the user → **Security credentials → Create access key → Application running outside AWS**.

> [!NOTE]
> When Laravel moves to EC2 later, this user gets deleted and replaced by an **EC2 instance role**, so there are no stored keys at all.

### B5. GitHub OIDC (the temporary visitor badge)

**B5.0 — Put the project on GitHub first** (it isn't a git repo yet):
```bash
cd ~/projects/web-platform
git init -b main
cat backend/.gitignore | grep -x ".env"     # must print .env (your secrets must not be committed)
printf "infra/terraform/.terraform/\n*.tfstate\n*.tfstate.*\n*.zip\n" >> .gitignore
git add . && git status                      # double-check: no backend/.env in the list!
git commit -m "Game portal monorepo"
# Create an EMPTY private repo named web-platform on github.com, then:
git remote add origin git@github.com:<GITHUB_USER>/web-platform.git
git push -u origin main
```

**B5.1 — Identity provider:** IAM → **Identity providers → Add provider** → OpenID Connect
- Provider URL: `https://token.actions.githubusercontent.com`
- Audience: `sts.amazonaws.com`

**B5.2 — Role:** IAM → Roles → **Create role → Web identity** → provider `token.actions.githubusercontent.com`, audience `sts.amazonaws.com`, GitHub organization `<GITHUB_USER>`, repository `web-platform`, branch `main`. Name it `game-portal-gh-deploy-test-harness`.

Then open the role → **Trust relationships** and make sure it matches this. The `sub` line is the most important security setting:
```json
"Condition": {
  "StringEquals": {
    "token.actions.githubusercontent.com:aud": "sts.amazonaws.com",
    "token.actions.githubusercontent.com:sub": "repo:<GITHUB_USER>/web-platform:ref:refs/heads/main"
  }
}
```

**B5.3 — Permissions (one game folder only):** Role → Add permissions → **Create inline policy → JSON**:
```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "WriteOwnGamePrefixOnly",
      "Effect": "Allow",
      "Action": ["s3:PutObject", "s3:DeleteObject"],
      "Resource": "arn:aws:s3:::game-portal-assets-<ACCOUNT_ID>/games/test-harness/*"
    },
    {
      "Sid": "ListOwnGamePrefixOnly",
      "Effect": "Allow",
      "Action": "s3:ListBucket",
      "Resource": "arn:aws:s3:::game-portal-assets-<ACCOUNT_ID>",
      "Condition": { "StringLike": { "s3:prefix": ["games/test-harness/*"] } }
    }
  ]
}
```

**B5.4 — Repository variables** (GitHub → repo → Settings → Secrets and variables → Actions → **Variables** tab). None of these are secret, which is the whole point of OIDC:

| Name | Value |
|---|---|
| `AWS_DEPLOY_ROLE_ARN` | `arn:aws:iam::<ACCOUNT_ID>:role/game-portal-gh-deploy-test-harness` |
| `GAME_BUCKET` | `game-portal-assets-<ACCOUNT_ID>` |
| `CDN_DOMAIN` | `dxxxxxxxxxxxx.cloudfront.net` |

**B5.5 — Workflow:** create `.github/workflows/deploy-test-harness.yml`:
```yaml
name: Deploy test-harness to CDN

on:
  push:
    branches: [main]
    paths: ['test-harness/**']
  workflow_dispatch:

permissions:
  id-token: write   # lets the job request an OIDC token (the visitor badge)
  contents: read

env:
  AWS_REGION: ap-southeast-1
  GAME_SLUG: test-harness

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Assume deploy role via OIDC (no stored keys)
        uses: aws-actions/configure-aws-credentials@v4
        with:
          role-to-assume: ${{ vars.AWS_DEPLOY_ROLE_ARN }}
          aws-region: ${{ env.AWS_REGION }}

      - name: Upload build to an immutable, commit-scoped prefix
        run: |
          PREFIX="games/${GAME_SLUG}/ci-${GITHUB_SHA::7}"
          aws s3 sync test-harness/ "s3://${{ vars.GAME_BUCKET }}/${PREFIX}/" \
            --exclude "index.html" --cache-control "public, max-age=31536000, immutable"
          aws s3 cp test-harness/index.html "s3://${{ vars.GAME_BUCKET }}/${PREFIX}/index.html" \
            --cache-control "no-cache, must-revalidate"
          echo "PREFIX=${PREFIX}" >> "$GITHUB_ENV"

      - name: Prove prefix isolation (writing to another game must FAIL)
        run: |
          if aws s3 cp test-harness/index.html "s3://${{ vars.GAME_BUCKET }}/games/someone-else/hack.html"; then
            echo "❌ Isolation broken: role can write outside its game folder"; exit 1
          fi
          echo "✅ AccessDenied outside games/${GAME_SLUG}/ as expected"

      - name: Smoke test through CloudFront
        run: curl -fsSI "https://${{ vars.CDN_DOMAIN }}/${PREFIX}/index.html"
```
Commit, push, then GitHub → **Actions** → *Deploy test-harness to CDN* → **Run workflow**.

> [!NOTE]
> CI builds land in `ci-<sha>/` and are **not yet registered** as a `game_versions` row. That needs a public Laravel API to call back to, which comes with the EC2 step. For now we're proving that keyless deploys and per-game isolation work.

### ✅ Check B5
- The workflow run is green.
- The isolation step logs `AccessDenied` followed by `✅`.
- `curl -sI https://$CDN/games/test-harness/ci-<sha>/index.html` → `200`.

### B6. Point the portal at real AWS
**`backend/.env`** — keep the LocalStack block commented out so you can switch back:
```dotenv
# --- LocalStack ---
# AWS_ACCESS_KEY_ID=test
# AWS_SECRET_ACCESS_KEY=test
# AWS_DEFAULT_REGION=us-east-1
# AWS_BUCKET=game-portal-assets
# AWS_ENDPOINT=http://localhost:4566
# AWS_USE_PATH_STYLE_ENDPOINT=true
# GAME_CDN_URL=http://localhost:4566/game-portal-assets

# --- Real AWS ---
AWS_ACCESS_KEY_ID=<game-portal-backend-local key>
AWS_SECRET_ACCESS_KEY=<secret>
AWS_DEFAULT_REGION=ap-southeast-1
AWS_BUCKET=game-portal-assets-<ACCOUNT_ID>
AWS_USE_PATH_STYLE_ENDPOINT=false
GAME_ASSETS_DISK=s3
GAME_CDN_URL=https://dxxxxxxxxxxxx.cloudfront.net
```
> [!WARNING]
> **Remove or comment out** `AWS_ENDPOINT` completely. Don't leave it as an empty `AWS_ENDPOINT=`, because the AWS SDK may treat an empty string as an invalid endpoint.

**`frontend/.env`**
```dotenv
VITE_GAME_CDN_ORIGIN=https://dxxxxxxxxxxxx.cloudfront.net
```
Then run `php artisan config:clear`, restart `php artisan serve`, and restart `npm run dev`.

### ✅ Check B6
1. The database's active version is **v3**, which only exists in LocalStack, so the player will show a CloudFront 403 for now. That's expected.
2. Studio → upload `harness-v3.zip` (the zipped `test-harness/` contents) → it becomes **v4**.
3. Open the game → Network tab: the iframe loads from `https://dxxxx.cloudfront.net/games/test-harness/v4/index.html`, the handshake succeeds, and scores submit.
4. `curl -sI https://$CDN/games/test-harness/v4/index.html` → the response includes `x-cache: Miss from cloudfront` the first time and `Hit from cloudfront` on later requests for assets (e.g. `PlatformSDK.js`).

---

## Part C — Terraform: the source of truth

**Analogy:** Part B was cooking the dish by hand while learning the steps. Terraform is writing down the **recipe**. From now on anyone (including CI or a future you) can recreate the exact same kitchen with `terraform apply`, and delete all of it with `terraform destroy`.

### C0. Tear down the Console resources first
Terraform needs to **own** these resources. Two leftovers would block it: the bucket name is taken, and an account can only have **one** GitHub OIDC provider. Delete them in this order:
1. CloudFront distribution → **Disable** → wait → **Delete**. Also delete the OAC under *Origin access*.
2. S3 bucket → **Empty** → **Delete**.
3. IAM → role `game-portal-gh-deploy-test-harness`, identity provider `token.actions.githubusercontent.com`, user `game-portal-backend-local` (and its policy `game-portal-backend-s3`) → delete.

> [!TIP]
> Advanced alternative: keep the resources and adopt them with Terraform `import` blocks. Delete-and-recreate is simpler and a good test that the code really reproduces everything.

### C1. Install Terraform in WSL
```bash
wget -O- https://apt.releases.hashicorp.com/gpg | sudo gpg --dearmor -o /usr/share/keyrings/hashicorp-archive-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/hashicorp-archive-keyring.gpg] https://apt.releases.hashicorp.com $(lsb_release -cs) main" \
  | sudo tee /etc/apt/sources.list.d/hashicorp.list
sudo apt update && sudo apt install -y terraform
terraform -version
```

### C2. Create the files in `infra/terraform/`

**`versions.tf`**
```hcl
terraform {
  required_version = ">= 1.6"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 6.0"
    }
  }
}

provider "aws" {
  region = var.aws_region
  default_tags {
    tags = {
      Project   = var.project
      ManagedBy = "terraform"
    }
  }
}

data "aws_caller_identity" "current" {}
```

**`variables.tf`**
```hcl
variable "aws_region" {
  type    = string
  default = "ap-southeast-1"
}

variable "project" {
  type    = string
  default = "game-portal"
}

variable "github_owner" {
  type        = string
  description = "GitHub username or org that owns the repo"
}

variable "github_repo" {
  type    = string
  default = "web-platform"
}

variable "game_slug" {
  type    = string
  default = "test-harness"
}
```

**`terraform.tfvars`**
```hcl
github_owner = "<GITHUB_USER>"
```

**`s3.tf`** — the vault
```hcl
resource "aws_s3_bucket" "games" {
  bucket = "${var.project}-assets-${data.aws_caller_identity.current.account_id}"
}

resource "aws_s3_bucket_public_access_block" "games" {
  bucket                  = aws_s3_bucket.games.id
  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

resource "aws_s3_bucket_ownership_controls" "games" {
  bucket = aws_s3_bucket.games.id
  rule {
    object_ownership = "BucketOwnerEnforced"
  }
}

resource "aws_s3_bucket_server_side_encryption_configuration" "games" {
  bucket = aws_s3_bucket.games.id
  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256"
    }
  }
}

# Only THIS CloudFront distribution may read objects
data "aws_iam_policy_document" "games_bucket" {
  statement {
    sid       = "AllowCloudFrontOAC"
    actions   = ["s3:GetObject"]
    resources = ["${aws_s3_bucket.games.arn}/*"]

    principals {
      type        = "Service"
      identifiers = ["cloudfront.amazonaws.com"]
    }

    condition {
      test     = "StringEquals"
      variable = "AWS:SourceArn"
      values   = [aws_cloudfront_distribution.games.arn]
    }
  }
}

resource "aws_s3_bucket_policy" "games" {
  bucket     = aws_s3_bucket.games.id
  policy     = data.aws_iam_policy_document.games_bucket.json
  depends_on = [aws_s3_bucket_public_access_block.games]
}
```

**`cloudfront.tf`** — the teller
```hcl
resource "aws_cloudfront_origin_access_control" "games" {
  name                              = "${var.project}-oac"
  origin_access_control_origin_type = "s3"
  signing_behavior                  = "always"
  signing_protocol                  = "sigv4"
}

data "aws_cloudfront_cache_policy" "optimized" {
  name = "Managed-CachingOptimized"
}

resource "aws_cloudfront_distribution" "games" {
  enabled      = true
  comment      = "${var.project} game asset CDN"
  price_class  = "PriceClass_200" # includes Asia edges, excludes the priciest regions
  http_version = "http2and3"

  origin {
    domain_name              = aws_s3_bucket.games.bucket_regional_domain_name
    origin_id                = "s3-games"
    origin_access_control_id = aws_cloudfront_origin_access_control.games.id
  }

  default_cache_behavior {
    target_origin_id       = "s3-games"
    viewer_protocol_policy = "redirect-to-https"
    allowed_methods        = ["GET", "HEAD"]
    cached_methods         = ["GET", "HEAD"]
    compress               = true
    cache_policy_id        = data.aws_cloudfront_cache_policy.optimized.id
  }

  restrictions {
    geo_restriction {
      restriction_type = "none"
    }
  }

  viewer_certificate {
    cloudfront_default_certificate = true
  }
}
```

**`iam_github_oidc.tf`** — the temporary visitor badge
```hcl
resource "aws_iam_openid_connect_provider" "github" {
  url            = "https://token.actions.githubusercontent.com"
  client_id_list = ["sts.amazonaws.com"]
}

data "aws_iam_policy_document" "github_trust" {
  statement {
    actions = ["sts:AssumeRoleWithWebIdentity"]

    principals {
      type        = "Federated"
      identifiers = [aws_iam_openid_connect_provider.github.arn]
    }

    condition {
      test     = "StringEquals"
      variable = "token.actions.githubusercontent.com:aud"
      values   = ["sts.amazonaws.com"]
    }

    # Only pushes to main of THIS repo can wear the badge
    condition {
      test     = "StringEquals"
      variable = "token.actions.githubusercontent.com:sub"
      values   = ["repo:${var.github_owner}/${var.github_repo}:ref:refs/heads/main"]
    }
  }
}

resource "aws_iam_role" "github_deploy" {
  name                 = "${var.project}-gh-deploy-${var.game_slug}"
  assume_role_policy   = data.aws_iam_policy_document.github_trust.json
  max_session_duration = 3600
}

data "aws_iam_policy_document" "github_deploy" {
  statement {
    sid       = "WriteOwnGamePrefixOnly"
    actions   = ["s3:PutObject", "s3:DeleteObject"]
    resources = ["${aws_s3_bucket.games.arn}/games/${var.game_slug}/*"]
  }

  statement {
    sid       = "ListOwnGamePrefixOnly"
    actions   = ["s3:ListBucket"]
    resources = [aws_s3_bucket.games.arn]

    condition {
      test     = "StringLike"
      variable = "s3:prefix"
      values   = ["games/${var.game_slug}/*"]
    }
  }
}

resource "aws_iam_role_policy" "github_deploy" {
  name   = "s3-own-game-prefix"
  role   = aws_iam_role.github_deploy.id
  policy = data.aws_iam_policy_document.github_deploy.json
}
```

**`iam_backend.tf`** — Laravel (local dev only)
```hcl
resource "aws_iam_user" "backend_local" {
  name = "${var.project}-backend-local"
}

data "aws_iam_policy_document" "backend_s3" {
  statement {
    sid       = "ReadWriteGameObjects"
    actions   = ["s3:PutObject", "s3:GetObject", "s3:DeleteObject"]
    resources = ["${aws_s3_bucket.games.arn}/games/*"]
  }

  statement {
    sid       = "ListGamePrefix"
    actions   = ["s3:ListBucket"]
    resources = [aws_s3_bucket.games.arn]

    condition {
      test     = "StringLike"
      variable = "s3:prefix"
      values   = ["games/*"]
    }
  }
}

resource "aws_iam_user_policy" "backend_s3" {
  name   = "game-portal-backend-s3"
  user   = aws_iam_user.backend_local.name
  policy = data.aws_iam_policy_document.backend_s3.json
}

# Access keys are deliberately NOT created here: they would be stored
# in plain text in terraform.tfstate. Create them with the CLI instead (C4).
```

**`outputs.tf`**
```hcl
output "bucket_name" {
  value = aws_s3_bucket.games.bucket
}

output "cloudfront_domain" {
  value = aws_cloudfront_distribution.games.domain_name
}

output "cloudfront_distribution_id" {
  value = aws_cloudfront_distribution.games.id
}

output "github_deploy_role_arn" {
  value = aws_iam_role.github_deploy.arn
}

output "backend_iam_user" {
  value = aws_iam_user.backend_local.name
}
```

### C3. Plan → Apply
```bash
cd infra/terraform
terraform init       # downloads the AWS provider
terraform fmt        # auto-formats the files
terraform validate   # catches syntax errors
terraform plan       # dry run: shows what will be created, no changes yet
terraform apply      # type "yes"; CloudFront takes ~3–5 minutes
terraform output
```
> [!CAUTION]
> `terraform.tfstate` is Terraform's memory of what it built. **Don't delete it, and don't commit it** (it's covered by the `.gitignore` lines from B5.0). Professional setups store it remotely (an S3 backend with locking). We'll keep it local for now.

### C4. Reconnect everything to the new resources
```bash
# New backend keys (old ones were deleted with the user)
aws iam create-access-key --user-name game-portal-backend-local

# Re-seed v1
BUCKET=$(terraform output -raw bucket_name)
CDN=$(terraform output -raw cloudfront_domain)
cd ../..
aws s3 sync test-harness/ s3://$BUCKET/games/test-harness/v1/ \
  --exclude "index.html" --cache-control "public, max-age=31536000, immutable"
aws s3 cp test-harness/index.html s3://$BUCKET/games/test-harness/v1/index.html \
  --cache-control "no-cache, must-revalidate"
```
- Update `backend/.env` with the new keys and `GAME_CDN_URL=https://$CDN`, and update `frontend/.env` with `VITE_GAME_CDN_ORIGIN=https://$CDN`. Then clear the config cache and restart both servers.
- Update the GitHub repo variables `AWS_DEPLOY_ROLE_ARN`, `GAME_BUCKET`, and `CDN_DOMAIN` with the values from `terraform output`.

### ✅ Check C (the final Phase 7 checks)
| # | Test | Expected |
|---|---|---|
| 1 | `terraform plan` (run again) | `No changes. Your infrastructure matches the configuration.` |
| 2 | `curl -sI https://$CDN/games/test-harness/v1/index.html \| head -1` | `HTTP/2 200` |
| 3 | `curl -sI https://$BUCKET.s3.ap-southeast-1.amazonaws.com/games/test-harness/v1/index.html \| head -1` | `HTTP/1.1 403` |
| 4 | Re-run the GitHub workflow | Green, isolation step prints `✅ AccessDenied` |
| 5 | Upload a zip in Studio → play it | iframe loads from CloudFront, handshake and score submission work |

---

## Cost guardrails (Free-Tier traps to avoid)
- ❌ Don't enable **WAF** or a paid CloudFront flat-rate plan.
- ❌ Don't run **CloudFront invalidations** in loops. Versioned prefixes (`v4/`, `ci-<sha>/`) mean you never need them.
- ❌ No NAT Gateways or Elastic IPs (relevant in the upcoming EC2 step).
- ✅ The zero-spend budget will email you about the first cent.
- ✅ To shut everything down: `aws s3 rm s3://$BUCKET --recursive && terraform destroy`.

---

## Official documentation
- [AWS Free Tier](https://aws.amazon.com/free/) · [AWS Budgets](https://docs.aws.amazon.com/cost-management/latest/userguide/budgets-managing-costs.html)
- [IAM root user best practices](https://docs.aws.amazon.com/IAM/latest/UserGuide/root-user-best-practices.html) · [Installing AWS CLI v2](https://docs.aws.amazon.com/cli/latest/userguide/getting-started-install.html)
- [S3 Block Public Access](https://docs.aws.amazon.com/AmazonS3/latest/userguide/access-control-block-public-access.html) · [S3 Object Ownership](https://docs.aws.amazon.com/AmazonS3/latest/userguide/about-object-ownership.html)
- [CloudFront Origin Access Control](https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/private-content-restricting-access-to-s3.html) · [CloudFront managed cache policies](https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/using-managed-cache-policies.html)
- [GitHub Actions OIDC in AWS](https://docs.github.com/en/actions/deployment/security-hardening-your-deployments/configuring-openid-connect-in-amazon-web-services) · [aws-actions/configure-aws-credentials](https://github.com/aws-actions/configure-aws-credentials)
- [Terraform: Get started with AWS](https://developer.hashicorp.com/terraform/tutorials/aws-get-started) · [Terraform AWS provider docs](https://registry.terraform.io/providers/hashicorp/aws/latest/docs) · [Terraform `import` blocks](https://developer.hashicorp.com/terraform/language/import)
