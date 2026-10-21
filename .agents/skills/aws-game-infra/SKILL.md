---
name: aws-game-infra
description: Trigger when writing AWS S3 bucket policies, CloudFront configurations, IAM roles, GitHub Actions CI/CD workflows, Nginx server blocks, or Docker/LocalStack setups.
---

# AWS Infrastructure & LocalStack Emulation Skill

## S3 & CloudFront Security (Zero Public Bucket Access)
1. **Strictly Private S3**: Always enforce `BlockPublicAcls: true`, `BlockPublicPolicy: true`, `IgnorePublicAcls: true`, and `RestrictPublicBuckets: true` on the `games-bucket`.
2. **Origin Access Control (OAC)**: Grant read access (`s3:GetObject`) **exclusively** to the CloudFront service principal (`cloudfront.amazonaws.com`) scoped to the specific CloudFront Distribution ARN via `AWS:SourceArn`.
3. **Cache Optimization**:
   - Immutable versioned paths (`/games/{slug}/v{version}/assets/*`) should use long cache TTLs (`Cache-Control: public, max-age=31536000, immutable`).
   - Set `Cache-Control: no-cache, must-revalidate` on `index.html` files so new game deployments or patches take effect immediately without expensive CloudFront invalidations.

## GitHub Actions CI/CD & IAM Least Privilege
1. **No Static AWS Keys**: Always configure GitHub Actions using **OpenID Connect (OIDC)** (`aws-actions/configure-aws-credentials@v4` with `role-to-assume`).
2. **Per-Game Prefix Isolation**: When generating an IAM Role policy for an external/junior developer's GitHub repository (`repo:org/game-repo:ref:refs/heads/main`), restrict `s3:PutObject` and `s3:DeleteObject` strictly to that game's prefix:
   - `arn:aws:s3:::games-bucket/games/specific-game-slug/*`
   - Never grant a game repository write access to the root bucket or other games' folders.

## Local Emulation (Docker & LocalStack)
1. Ensure every S3 operation works locally via **LocalStack** before deploying to AWS:
   - Configure Laravel's `filesystems.php` `s3` disk to respect `AWS_ENDPOINT` and `AWS_USE_PATH_STYLE_ENDPOINT=true` in local `.env`.
   - Provide `awslocal s3 mb s3://games-bucket` initialization scripts and CORS configurations for quick local setup.

## Cost Optimization (Free-Tier Guardrails)
1. Keep EC2 configurations lean (Ubuntu 24.04 LTS on `t3.micro` or `t4g.small`), tuning `php-fpm` worker counts (`pm.max_children`) and MySQL `innodb_buffer_pool_size` so the server never runs out of RAM or swaps to disk.
2. Avoid billable AWS traps (no NAT Gateways, no unattached Elastic IPs, no excessive CloudFront invalidations beyond the 1,000/month free tier).