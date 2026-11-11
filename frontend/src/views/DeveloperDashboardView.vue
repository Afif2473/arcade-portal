<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import apiClient from '../api/client';
import { useAuthStore } from '../stores/auth';
import AuthModal from '../components/AuthModal.vue';
import heroImg from '../assets/hero.png';

const authStore = useAuthStore();
const games = ref([]);
const loading = ref(true);
const isAuthModalOpen = ref(false);

// SDK Tab Switcher
const activeSdkTab = ref('quickstart');
const copied = ref(false);

const sdkSnippets = {
  quickstart: `// 1. Drop PlatformSDK.js into your game project
import { PlatformSDK } from './PlatformSDK.js';

const sdk = new PlatformSDK();

// Handshake with portal (or initializes local mock if outside iframe)
const session = await sdk.init();
console.log('Ready! Playing as:', session.user ? session.user.username : 'Guest');`,

  scores: `// Submit score to global leaderboard at game over
const result = await sdk.submitScore(currentScore);

if (result.isNewBest) {
  showCelebrationBanner(\`New Personal Best! Global Rank #\${result.rank}\`);
}`,

  saves: `// 1. Save game state to the cloud (with revision conflict check)
await sdk.saveGame({
  stage: 5,
  coins: 450,
  inventory: ['flame-sword', 'potion']
});

// 2. Load saved state on startup
const save = await sdk.loadGame();
if (save.data) {
  restorePlayerProgress(save.data);
}`,

  bundle: `// vite.config.js - CRITICAL: Always use relative paths for bundles!
export default {
  base: './', // Ensures assets load from CloudFront subpath in iframe
  build: {
    outDir: 'dist',
  }
};
// Zip the contents of 'dist/' directly so index.html is at the zip root.`
};

function copyActiveSnippet() {
  navigator.clipboard.writeText(sdkSnippets[activeSdkTab.value]);
  copied.value = true;
  setTimeout(() => copied.value = false, 2000);
}

// Upload & Lifecycle
const uploadLoading = ref({});
const uploadError = ref({});
const uploadSuccess = ref({});
const selectedFile = ref({});

// Register Modal State
const isCreateModalOpen = ref(false);
const createLoading = ref(false);
const createError = ref('');
const newGame = ref({
  title: '',
  slug: '',
  short_description: '',
  aspect_ratio: '16:9',
});

function autoSlug() {
  newGame.value.slug = newGame.value.title
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '');
}

async function handleCreateGame() {
  createError.value = '';
  createLoading.value = true;
  try {
    await apiClient.post('/developer/games', newGame.value);
    isCreateModalOpen.value = false;
    newGame.value = { title: '', slug: '', short_description: '', aspect_ratio: '16:9' };
    await fetchMyGames();
  } catch (err) {
    createError.value = err.response?.data?.errors
      ? Object.values(err.response.data.errors).flat().join(' ')
      : err.response?.data?.message || 'Failed to create game.';
  } finally {
    createLoading.value = false;
  }
}

async function fetchMyGames() {
  if (!authStore.isAuthenticated || !authStore.isDeveloper) {
    loading.value = false;
    return;
  }
  try {
    const res = await apiClient.get('/developer/games');
    games.value = res.data.data || [];
  } catch (err) {
    console.error('Failed to load developer games:', err);
  } finally {
    loading.value = false;
  }
}

function handleFileChange(event, slug) {
  const file = event.target.files[0];
  if (file) {
    selectedFile.value[slug] = file;
    uploadError.value[slug] = null;
    uploadSuccess.value[slug] = null;
  }
}

async function uploadZip(slug) {
  const file = selectedFile.value[slug];
  if (!file) return;

  uploadLoading.value[slug] = true;
  uploadError.value[slug] = null;
  uploadSuccess.value[slug] = null;

  const formData = new FormData();
  formData.append('bundle', file);

  try {
    const res = await apiClient.post(`/developer/games/${slug}/upload`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    uploadSuccess.value[slug] = res.data.message || 'Bundle uploaded successfully.';
    selectedFile.value[slug] = null;
    await fetchMyGames();
  } catch (err) {
    uploadError.value[slug] =
      err.response?.data?.errors?.bundle?.[0] ||
      err.response?.data?.message ||
      'Upload failed. Ensure index.html is located at the zip root.';
  } finally {
    uploadLoading.value[slug] = false;
  }
}

async function updateStatus(slug, newStatus) {
  try {
    await apiClient.patch(`/developer/games/${slug}`, { status: newStatus });
    await fetchMyGames();
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to update status.');
  }
}

onMounted(fetchMyGames);
</script>

<template>
  <div class="dev-container">
    <!-- View A: Non-Developer / Landing State -->
    <div v-if="!authStore.isAuthenticated || !authStore.isDeveloper" class="hero-wrapper">
      <div class="hero-grid">
        <div class="hero-content">
          <div class="d-inline-flex align-items-center gap-2 mb-3">
            <span class="badge bg-primary-subtle text-accent px-3 py-1 rounded-pill fw-semibold">
              Developer Portal
            </span>
          </div>
          <h1 class="hero-headline">
            <span>Publish Your Game.</span>
            <span>Reach Players.</span>
            <span class="text-accent">Stay in the Browser.</span>
          </h1>
          <p class="hero-subhead">
            Deploy your HTML5 and WebGL games directly to our portal. Enjoy instant web play, cloud save state persistence, and competitive global leaderboards.
          </p>
          <div class="hero-ctas">
            <button class="btn btn-primary btn-lg" @click="isAuthModalOpen = true">
              <i class="bi bi-cloud-arrow-up"></i> Upload Your Game
            </button>
            <a href="#sdk-section" class="btn btn-secondary btn-lg">
              <i class="bi bi-code-slash"></i> Explore Platform SDK
            </a>
          </div>
        </div>

        <div class="hero-media">
          <div class="hero-media-box">
            <img :src="heroImg" alt="Developer Platform" class="hero-media-img" />
          </div>
        </div>
      </div>

      <!-- Feature Highlights -->
      <div class="highlights-card mb-5">
        <div class="highlights-grid">
          <div class="highlight-item">
            <div class="highlight-icon-box icon-box-purple">
              <i class="bi bi-file-earmark-zip"></i>
            </div>
            <div>
              <h4 class="highlight-title">Zip Ingestion</h4>
              <p class="highlight-desc">Index.html at bundle root</p>
            </div>
          </div>

          <div class="highlight-item">
            <div class="highlight-icon-box icon-box-rose">
              <i class="bi bi-database-check"></i>
            </div>
            <div>
              <h4 class="highlight-title">Cloud Save API</h4>
              <p class="highlight-desc">Concurrency-checked state</p>
            </div>
          </div>

          <div class="highlight-item">
            <div class="highlight-icon-box icon-box-green">
              <i class="bi bi-trophy"></i>
            </div>
            <div>
              <h4 class="highlight-title">Leaderboard API</h4>
              <p class="highlight-desc">Real-time rank tracking</p>
            </div>
          </div>

          <div class="highlight-item">
            <div class="highlight-icon-box icon-box-blue">
              <i class="bi bi-arrow-repeat"></i>
            </div>
            <div>
              <h4 class="highlight-title">Release Stages</h4>
              <p class="highlight-desc">Draft, Sandbox, Published</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- View B: Authenticated Developer Dashboard -->
    <div v-else class="dev-dashboard mb-5">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h1 class="mb-1">Developer Studio</h1>
          <p class="text-muted mb-0">Register titles, deploy version bundles, and promote release stages.</p>
        </div>
        <button class="btn btn-primary" @click="isCreateModalOpen = true">
          <i class="bi bi-plus-lg"></i> Register Game
        </button>
      </div>

      <div v-if="loading" class="text-center py-5 text-muted">
        Loading studio titles...
      </div>

      <div v-else-if="games.length === 0" class="card empty-card p-5 text-center">
        <div class="empty-icon-box mb-3">
          <i class="bi bi-joystick"></i>
        </div>
        <h3 class="mb-2">No games registered yet</h3>
        <p class="text-muted mb-4">Register your first title to start uploading version packages.</p>
        <div>
          <button class="btn btn-primary" @click="isCreateModalOpen = true">
            <i class="bi bi-plus-lg"></i> Register First Game
          </button>
        </div>
      </div>

      <!-- Games Management Cards -->
      <div v-else class="dev-games-grid">
        <div v-for="game in games" :key="game.id" class="card game-manage-card p-4">
          <div class="row g-4 align-items-start">
            <!-- Col 1: Metadata & Lifecycle -->
            <div class="col-lg-5">
              <div class="d-flex align-items-center gap-2 mb-1">
                <h3 class="mb-0">{{ game.title }}</h3>
                <span class="badge" :class="`badge-status-${game.status}`">{{ game.status }}</span>
              </div>
              <div class="text-muted small mb-2">/games/{{ game.slug }} • Ratio {{ game.aspect_ratio }}</div>
              <p class="text-muted small mb-3">{{ game.short_description || 'No description provided.' }}</p>

              <div class="d-flex align-items-center gap-2">
                <span class="text-muted small">Lifecycle:</span>
                <div class="status-segment">
                  <button
                    class="segment-btn"
                    :class="{ active: game.status === 'draft' }"
                    @click="updateStatus(game.slug, 'draft')"
                  >Draft</button>
                  <button
                    class="segment-btn"
                    :class="{ active: game.status === 'sandbox' }"
                    @click="updateStatus(game.slug, 'sandbox')"
                  >Sandbox</button>
                  <button
                    class="segment-btn"
                    :class="{ active: game.status === 'published' }"
                    :disabled="!game.active_version"
                    @click="updateStatus(game.slug, 'published')"
                  >Published</button>
                </div>
              </div>
            </div>

            <!-- Col 2: Active Build -->
            <div class="col-lg-3">
              <span class="text-muted small d-block mb-1">Active Build</span>
              <div v-if="game.active_version">
                <div class="fs-4 fw-bold text-accent mb-2">v{{ game.active_version.version }}</div>
                <RouterLink :to="`/games/${game.slug}`" class="btn btn-secondary btn-sm">
                  <i class="bi bi-play-circle"></i> Launch Sandbox
                </RouterLink>
              </div>
              <div v-else class="text-muted small py-2">No builds uploaded yet</div>
            </div>

            <!-- Col 3: Upload Bundle -->
            <div class="col-lg-4">
              <span class="text-muted small d-block mb-1">Upload Release (.zip)</span>
              <div class="d-flex gap-2 mb-1">
                <input
                  type="file"
                  accept=".zip"
                  class="form-control form-control-sm"
                  @change="(e) => handleFileChange(e, game.slug)"
                />
                <button
                  class="btn btn-secondary btn-sm flex-shrink-0"
                  :disabled="!selectedFile[game.slug] || uploadLoading[game.slug]"
                  @click="uploadZip(game.slug)"
                >
                  {{ uploadLoading[game.slug] ? 'Uploading...' : 'Upload' }}
                </button>
              </div>
              <div v-if="uploadSuccess[game.slug]" class="text-success small">
                <i class="bi bi-check-circle"></i> {{ uploadSuccess[game.slug] }}
              </div>
              <div v-if="uploadError[game.slug]" class="text-danger small">
                <i class="bi bi-exclamation-triangle"></i> {{ uploadError[game.slug] }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- =====================================================================
         SDK Promotion & Integration Showcase (Visible to all users)
         ===================================================================== -->
    <section id="sdk-section" class="card sdk-showcase-card p-4 p-md-5 mb-5">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
          <div class="d-inline-flex align-items-center gap-2 mb-2">
            <span class="badge bg-primary-subtle text-accent px-3 py-1 rounded-pill small fw-semibold">
              Official Client Library
            </span>
            <span class="badge bg-surface-raised text-muted px-2 py-1 rounded small">
              v1.0.0
            </span>
          </div>
          <h2 class="mb-1">PlatformSDK.js Integration</h2>
          <p class="text-muted mb-0">
            A zero-dependency client adapter. Works seamlessly in both iframe and local standalone dev environments.
          </p>
        </div>

        <div class="d-flex gap-2">
          <a
            href="/PlatformSDK.js"
            download="PlatformSDK.js"
            class="btn btn-primary"
            title="Download single-file SDK library"
          >
            <i class="bi bi-download"></i> Download PlatformSDK.js
          </a>
        </div>
      </div>

      <!-- Feature Pillars -->
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="sdk-pillar-box p-3">
            <div class="pillar-icon text-accent mb-2">
              <i class="bi bi-arrow-left-right"></i>
            </div>
            <h4 class="fs-6 fw-bold mb-1">Offline Mock Mode</h4>
            <p class="text-muted small mb-0">
              When running locally outside the portal iframe, the SDK automatically persists saves and scores to localStorage so your game never crashes.
            </p>
          </div>
        </div>

        <div class="col-md-4">
          <div class="sdk-pillar-box p-3">
            <div class="pillar-icon text-accent mb-2">
              <i class="bi bi-trophy"></i>
            </div>
            <h4 class="fs-6 fw-bold mb-1">1-Line Leaderboards</h4>
            <p class="text-muted small mb-0">
              Submit scores asynchronously. The portal computes player rank and personal best flags in real time.
            </p>
          </div>
        </div>

        <div class="col-md-4">
          <div class="sdk-pillar-box p-3">
            <div class="pillar-icon text-accent mb-2">
              <i class="bi bi-shield-check"></i>
            </div>
            <h4 class="fs-6 fw-bold mb-1">Iframe Protection</h4>
            <p class="text-muted small mb-0">
              Auto-focuses the canvas and intercepts Arrow/Space keys to prevent scrolling the parent page during gameplay.
            </p>
          </div>
        </div>
      </div>

      <!-- Code Tabs -->
      <div class="code-box-wrapper">
        <div class="code-box-header d-flex justify-content-between align-items-center">
          <div class="code-tabs">
            <button
              class="code-tab-btn"
              :class="{ active: activeSdkTab === 'quickstart' }"
              @click="activeSdkTab = 'quickstart'"
            >
              1. Quickstart
            </button>
            <button
              class="code-tab-btn"
              :class="{ active: activeSdkTab === 'scores' }"
              @click="activeSdkTab = 'scores'"
            >
              2. Leaderboards
            </button>
            <button
              class="code-tab-btn"
              :class="{ active: activeSdkTab === 'saves' }"
              @click="activeSdkTab = 'saves'"
            >
              3. Cloud Saves
            </button>
            <button
              class="code-tab-btn"
              :class="{ active: activeSdkTab === 'bundle' }"
              @click="activeSdkTab = 'bundle'"
            >
              4. Bundle Checklist
            </button>
          </div>

          <button class="btn btn-secondary btn-sm" @click="copyActiveSnippet">
            <i class="bi" :class="copied ? 'bi-check-lg text-success' : 'bi-clipboard'"></i>
            <span>{{ copied ? 'Copied!' : 'Copy Code' }}</span>
          </button>
        </div>

        <pre class="code-preview"><code>{{ sdkSnippets[activeSdkTab] }}</code></pre>
      </div>
    </section>

    <!-- Register Modal -->
    <div v-if="isCreateModalOpen" class="modal-backdrop fade show"></div>
    <div v-if="isCreateModalOpen" class="modal fade show d-block" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card">
          <div class="modal-header border-bottom border-subtle p-3 d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Register New Game</h4>
            <button type="button" class="btn-close btn-close-white" @click="isCreateModalOpen = false"></button>
          </div>
          <form @submit.prevent="handleCreateGame">
            <div class="modal-body p-3">
              <div v-if="createError" class="alert alert-danger py-2 small mb-3">{{ createError }}</div>

              <div class="mb-3">
                <label class="form-label small text-muted">Title</label>
                <input v-model="newGame.title" @input="autoSlug" type="text" class="form-control" placeholder="Game Title" required />
              </div>

              <div class="mb-3">
                <label class="form-label small text-muted">Slug</label>
                <input v-model="newGame.slug" type="text" class="form-control" placeholder="game-slug" required />
              </div>

              <div class="mb-3">
                <label class="form-label small text-muted">Short Description</label>
                <input v-model="newGame.short_description" type="text" class="form-control" placeholder="Brief tagline" />
              </div>

              <div class="mb-3">
                <label class="form-label small text-muted">Aspect Ratio</label>
                <select v-model="newGame.aspect_ratio" class="form-select">
                  <option value="16:9">16:9 (Landscape Standard)</option>
                  <option value="4:3">4:3 (Classic Retro)</option>
                  <option value="9:16">9:16 (Portrait Mobile)</option>
                </select>
              </div>
            </div>
            <div class="modal-footer border-top border-subtle p-3 d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-secondary" @click="isCreateModalOpen = false">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="createLoading">
                {{ createLoading ? 'Registering...' : 'Register Game' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <AuthModal :is-open="isAuthModalOpen" @close="isAuthModalOpen = false" />
  </div>
</template>

<style scoped>
.dev-container {
  max-width: 1200px;
  margin: 0 auto;
}

.dev-games-grid {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.game-manage-card {
  transition: border-color 0.15s ease;
}

.game-manage-card:hover {
  border-color: var(--accent);
}

/* Status Badges */
.badge-status-draft     { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
.badge-status-sandbox   { background: rgba(14, 165, 233, 0.15); color: #38bdf8; }
.badge-status-published { background: rgba(34, 197, 94, 0.15);  color: #4ade80; }

/* Status Segmented Buttons */
.status-segment {
  display: inline-flex;
  background-color: var(--bg);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 2px;
}

.segment-btn {
  background: none;
  border: none;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-muted);
  padding: 0.25rem 0.65rem;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.segment-btn:hover:not(:disabled) {
  color: var(--text);
}

.segment-btn.active {
  background-color: var(--accent);
  color: #ffffff;
}

.segment-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* SDK Showcase */
.sdk-showcase-card {
  border-radius: var(--radius-lg);
  background: linear-gradient(180deg, var(--surface) 0%, rgba(20, 22, 24, 0.95) 100%);
}

.sdk-pillar-box {
  background-color: var(--bg);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  height: 100%;
}

.pillar-icon {
  font-size: 1.4rem;
}

/* Code Preview Box */
.code-box-wrapper {
  background-color: var(--bg);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
}

.code-box-header {
  padding: 0.6rem 1rem;
  border-bottom: 1px solid var(--border);
  background-color: var(--surface-raised);
}

.code-tabs {
  display: flex;
  gap: 0.5rem;
}

.code-tab-btn {
  background: none;
  border: none;
  font-size: 0.85rem;
  font-weight: 500;
  color: var(--text-muted);
  padding: 0.4rem 0.75rem;
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: all 0.15s ease;
}

.code-tab-btn:hover {
  color: var(--text);
}

.code-tab-btn.active {
  background-color: var(--accent);
  color: #ffffff;
  font-weight: 600;
}

.code-preview {
  margin: 0;
  padding: 1.25rem 1.5rem;
  font-family: 'Fira Code', ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.875rem;
  line-height: 1.6;
  color: #38bdf8;
  overflow-x: auto;
}

.empty-card {
  border-radius: var(--radius-lg);
}

.empty-icon-box {
  width: 56px;
  height: 56px;
  margin: 0 auto;
  border-radius: 14px;
  background: rgba(2, 132, 199, 0.12);
  color: var(--accent);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.75rem;
}
</style>