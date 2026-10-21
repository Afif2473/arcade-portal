---
name: vue-bootstrap-portal
description: Trigger when building or updating Vue 3 components, Pinia stores, Bootstrap 5 UI layouts, the game catalog storefront, leaderboard tables, or the iframe game player view.
---

# Vue 3 & Bootstrap 5 Game Portal Skill

## Component & State Architecture
1. **Vue 3 Standards**: Always use `<script setup>` syntax. Organize code into `views/`, `components/`, `stores/` (Pinia), and `composables/`.
2. **Separation of Concerns**: Keep HTTP calls inside Pinia actions or dedicated service modules (`src/api/`), never directly inside raw template event handlers.
3. **Iframe Bridge Composable**: Encapsulate all `window.postMessage` logic inside a reusable composable (`useGameIframeBridge.js`):
   - Always register `window.addEventListener('message', handler)` inside `onMounted` and remove it in `onUnmounted` to prevent memory leaks and duplicate score submissions.
   - **Strict Origin Validation**: In the message listener, verify `event.origin === expectedCloudFrontOrigin` (or LocalStack/local dev origin from `.env`) and `event.source === iframeRef.value?.contentWindow` before acting on any event.

## Secure `<iframe>` Viewport Guidelines
1. Render the game viewport with strict sandboxing:

       <iframe
         ref="gameIframe"
         :src="gameLaunchUrl"
         sandbox="allow-scripts allow-same-origin allow-pointer-lock"
         allow="autoplay; fullscreen; gamepad"
         class="w-100 h-100 border-0"
       ></iframe>

2. Provide a responsive viewport container with a clean control bar underneath (Fullscreen toggle, Restart Game button, Theater Mode toggle, and Sandbox status indicator).
3. **Configurable Viewport & Focus Management**:
   - Bind the viewport container's aspect ratio dynamically from the `games` table metadata (`aspect_ratio`: `'16:9'`, `'4:3'`, or `'9:16'`) rather than hardcoding every game to widescreen.
   - Add a click-to-focus overlay or call `iframeRef.value?.contentWindow?.focus()` when the game finishes loading so keyboard controls work immediately.

## UI/UX & Bootstrap 5 Styling Rules
1. **Intentional Gaming Portal Aesthetic**: Avoid generic bright-white default Bootstrap layouts. Use dark-mode styling (`data-bs-theme="dark"`), slate/obsidian surfaces (`#0f172a`, `#1e293b`), crisp borders, and high-contrast accent badges for ranks (`#1`, `#2`, `#3`).
2. **Lifecycle State Handling**:
   - If `game.status === 'draft'`, render a sleek "Coming Soon" hero banner with game metadata instead of initializing the `<iframe>`.
   - If `game.status === 'sandbox'`, display a visible warning banner (`Sandbox Testing Mode — Scores only visible to creator`).
3. **Live Leaderboard Feedback**: When the iframe emits a `SUBMIT_SCORE` event and the Laravel API confirms a new rank, automatically refresh the leaderboard component and highlight the current player's row without reloading the page or interrupting the iframe.