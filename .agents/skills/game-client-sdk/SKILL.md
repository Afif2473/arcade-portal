---
name: game-client-sdk
description: Trigger when building the PlatformSDK.ts client library, writing the 50-line Test Harness HTML/JS game, or documenting how game developers integrate with the portal.
---

# Game Client SDK & Test Harness Skill

## Design Philosophy
The `PlatformSDK` is a zero-dependency, single-file TypeScript/JavaScript adapter (`PlatformSDK.ts`) that game developers drop into their Vite project (or include via `<script type="module">`) to communicate with the parent Vue.js portal.

## Dual-Mode Execution (Iframe vs. Local Standalone Mock)
The SDK must automatically detect its runtime environment using `const isEmbedded = window.self !== window.top;`:
1. **Embedded Mode (`isEmbedded === true`)**:
   - Communicates with the parent Vue.js portal using `window.parent.postMessage()`.
   - Wraps asynchronous request/response cycles (`init()`, `submitScore(score, metadata)`, `saveGame(data)`, `loadGame()`) in clean JavaScript `Promise`s using unique `requestId` correlation IDs and timeout safeguards (e.g., 5000ms).
2. **Standalone Mock Mode (`isEmbedded === false`)**:
   - Triggered automatically when the junior developer runs `npm run dev` on `http://localhost:5173` outside the portal.
   - Returns a mock player profile (`{ id: 'dev-local', username: 'LocalDevPlayer' }`), persists saves/scores to `window.localStorage`, and logs clear diagnostics (`[PlatformSDK Mock] Score 1250 saved locally`).
   - Ensures the game developer never crashes or gets blocked when working offline or outside the portal.

## Message Envelope Standard
Every `postMessage` payload between the SDK and the Vue portal must follow a strict schema:

    export interface PlatformMessage<T = unknown> {
      source: 'GAME_PORTAL_SDK' | 'GAME_PORTAL_HOST';
      type: 'INIT' | 'SUBMIT_SCORE' | 'SAVE_STATE' | 'LOAD_STATE' | 'READY' | 'RESPONSE' | 'ERROR';
      requestId: string;
      payload?: T;
    }

## Game Bundle & Browser Input Rules
1. **Relative Asset Paths (`base: './'`)**: All games are served from a sub-prefix on CloudFront (`/games/{slug}/v{version}/index.html`). Always instruct game developers (and configure any starter templates) to set `base: './'` in `vite.config.ts` and use relative paths (`./assets/...`) so asset requests never resolve to the root CloudFront domain (`/`).
2. **Prevent Page Scroll on Game Keys**: Inside `PlatformSDK.init()`, automatically attach a `keydown` listener when running inside an iframe that prevents `Space`, `ArrowUp`, `ArrowDown`, `ArrowLeft`, and `ArrowRight` from scrolling the parent browser window (`e.preventDefault()`).
3. **Auto-Focus Canvas**: Ensure the SDK focuses the game window (`window.focus()`) upon initialization or first click so keyboard controls respond immediately without requiring multiple clicks.

## Built-in Test Harness Game Standards
When generating the standalone `test-harness/index.html` dummy game:
- Keep it to a single, self-contained HTML file with inline CSS and vanilla ES6 JS so it requires zero build step to upload to LocalStack or S3.
- Include interactive UI buttons to test:
  1. Handshake & User Profile display (`INIT`).
  2. Submitting a custom or random score (`SUBMIT_SCORE`).
  3. Saving and loading a JSON state object (`SAVE_STATE` / `LOAD_STATE`).
  4. Real-time event log console on screen showing every `postMessage` sent and received.