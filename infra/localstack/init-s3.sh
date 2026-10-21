#!/bin/bash
set -e
BUCKET=game-portal-assets

awslocal s3 mb s3://$BUCKET || true

awslocal s3api put-bucket-cors --bucket $BUCKET --cors-configuration '{
  "CORSRules": [{
    "AllowedOrigins": ["http://localhost:5173", "http://127.0.0.1:5173"],
    "AllowedMethods": ["GET", "HEAD"],
    "AllowedHeaders": ["*"],
    "MaxAgeSeconds": 3000
  }]
}'

# Seed v1 from the test-harness folder (matches DatabaseSeeder s3_prefix)
awslocal s3 sync /seed/test-harness s3://$BUCKET/games/test-harness/v1/ \
  --cache-control "no-cache, must-revalidate"

echo "✅ S3 bucket $BUCKET ready"

chmod +x infra/localstack/init-s3.sh
docker compose up -d localstack
docker logs -f localstack-game-portal   # wait for "✅ S3 bucket game-portal-assets ready", then Ctrl+C
