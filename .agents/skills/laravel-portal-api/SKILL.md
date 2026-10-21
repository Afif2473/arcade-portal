---
name: laravel-portal-api
description: Trigger when designing or modifying Laravel backend routes, controllers, migrations, Eloquent models, Sanctum authentication, zip ingestion, or leaderboard/save-state endpoints.
---

# Laravel Game Portal API Skill

## Architectural Rules
1. **Laravel 11/12 Structure**: Configure middleware, CORS, and exception handling cleanly in `bootstrap/app.php`.
2. **Thin Controllers & Action Pattern**: Controllers must only handle HTTP concerns (invoking a `FormRequest`, calling a dedicated Action/Service class, and returning an Eloquent `JsonResource`). Put business logic inside `app/Actions/` (e.g., `SubmitGameScoreAction`, `ProcessGameZipUploadAction`).
3. **Standardized JSON Envelopes**:
   - Success responses must use Laravel API Resources (`data`, `meta`).
   - Error responses must return consistent status codes (`422` for validation, `401` unauthenticated, `403` unauthorized, `429` rate-limited).

## Database & Leaderboard Performance
1. **Zero N+1 Queries**: Always eager-load relationships (`->with(['user:id,name,avatar_path'])`) when querying leaderboards or game catalogs. Enforce `Model::preventLazyLoading(! app()->isProduction())` in `AppServiceProvider`.
2. **Leaderboard Indexing**: Ensure the `scores` table includes a composite index on `(game_id, score DESC, created_at ASC)` for high-speed ranking queries.
3. **Personal Best vs. History**: When recording a score, use atomic transactions or `updateOrCreate` if tracking personal bests alongside historical runs, and return both the player's current score and their all-time best rank.

## Game Zip Upload & S3 Ingestion Security
When implementing direct `.zip` game bundle uploads:
1. Validate archive size and MIME type strictly in a `FormRequest`.
2. Inspect the `ZipArchive` stream **before** extraction:
   - Reject Zip Slip path traversal (`../` in filenames).
   - Verify `index.html` exists at the archive root.
   - Whitelist allowed static asset extensions (`.html`, `.js`, `.mjs`, `.css`, `.json`, `.wasm`, `.png`, `.jpg`, `.webp`, `.svg`, `.mp3`, `.ogg`, `.wav`, `.glb`, `.gltf`, `.bin`, `.woff2`). Reject executable/server scripts (`.php`, `.sh`, `.exe`).
3. Upload extracted files to S3 (`Storage::disk('s3')`) under versioned prefixes (`games/{slug}/v{version}/`) with explicit `Content-Type` headers.

## Anti-Cheat & Rate Limiting
1. Apply dedicated Laravel `RateLimiter` rules on score and save-state endpoints (e.g., max 10 score submissions per minute per user).
2. Validate that the target game's `status` is `published` (or `sandbox` if the authenticated user is the game creator/admin) before accepting scores or saves.