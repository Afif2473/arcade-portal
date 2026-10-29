<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import apiClient from '../api/client';
import { useAuthStore } from '../stores/auth';

const authStore = useAuthStore();
const games = ref([]);
const loading = ref(true);
const uploadLoading = ref({});
const uploadError = ref({});
const uploadSuccess = ref({});
const selectedFile = ref({});

// New Game Modal state
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
  if (!authStore.isAuthenticated) {
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
    uploadSuccess.value[slug] = res.data.message || 'Version uploaded successfully!';
    selectedFile.value[slug] = null;
    await fetchMyGames();
  } catch (err) {
    uploadError.value[slug] =
      err.response?.data?.errors?.bundle?.[0] ||
      err.response?.data?.message ||
      'Upload failed. Ensure the zip contains index.html at root.';
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

onMounted(() => {
  fetchMyGames();
});
</script>

<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h2 class="fw-bold mb-0 text-light">Developer Studio</h2>
        <span class="text-secondary small">Manage, inspect, and deploy your game versions</span>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" @click="isCreateModalOpen = true">
          <i class="bi bi-plus-lg me-1"></i> Register New Game
        </button>
        <RouterLink to="/" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrow-left"></i> Back to Catalog
        </RouterLink>
      </div>

    </div>

    <!-- Unauthenticated Warning -->
    <div v-if="!authStore.isAuthenticated" class="alert alert-warning">
      <i class="bi bi-shield-lock me-2"></i>
      Please enter your developer token in the top-right navbar to access your studio.
    </div>

    <div v-else-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div v-else-if="games.length === 0" class="text-center py-5 text-secondary">
      <p>No games found for your developer account.</p>
    </div>

    <!-- Games List -->
    <div v-else class="row g-4">
      <div v-for="game in games" :key="game.id" class="col-12">
        <div class="card border-secondary shadow-sm bg-dark">
          <div class="card-body p-4">
            <div class="row align-items-center g-4">
              
              <!-- Column 1: Game Meta & Status -->
              <div class="col-12 col-lg-5">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <h4 class="fw-bold text-light mb-0">{{ game.title }}</h4>
                  <span class="badge bg-black border border-secondary text-info font-monospace small">
                    /games/{{ game.slug }}
                  </span>
                </div>
                <p class="text-secondary small mb-3">
                  {{ game.short_description || 'No description provided.' }}
                </p>

                <!-- Status Lifecycle Selector -->
                <div class="d-flex flex-column gap-1">
                  <span class="small text-secondary fw-semibold">Visibility Status:</span>
                  <div class="btn-group btn-group-sm" role="group">
                    <button
                      type="button"
                      class="btn"
                      :class="game.status === 'draft' ? 'btn-secondary text-white' : 'btn-outline-secondary'"
                      @click="updateStatus(game.slug, 'draft')"
                      title="Only you can see this game."
                    >
                      <i class="bi bi-lock me-1"></i> Draft
                    </button>
                    <button
                      type="button"
                      class="btn"
                      :class="game.status === 'sandbox' ? 'btn-warning text-dark fw-bold' : 'btn-outline-warning'"
                      @click="updateStatus(game.slug, 'sandbox')"
                      title="Hidden from store; open for developer testing."
                    >
                      <i class="bi bi-flask me-1"></i> Sandbox
                    </button>
                    <button
                      type="button"
                      class="btn"
                      :class="game.status === 'published' ? 'btn-success text-white' : 'btn-outline-success'"
                      :disabled="!game.active_version"
                      @click="updateStatus(game.slug, 'published')"
                      :title="!game.active_version ? 'Upload a build first before publishing' : 'Live on public catalog for all players!'"
                    >
                      <i class="bi bi-globe me-1"></i> Published
                    </button>
                  </div>
                  <span v-if="!game.active_version" class="text-warning extra-small mt-1" style="font-size: 0.75rem;">
                    * Upload a build to enable publishing to the store.
                  </span>
                </div>
              </div>

              <!-- Column 2: Version & Preview Info -->
              <div class="col-12 col-lg-3 border-start-lg border-secondary ps-lg-4">
                <div class="small text-secondary fw-semibold mb-1">Current Deployment:</div>
                
                <!-- Has Active Version -->
                <div v-if="game.active_version">
                  <div class="h5 fw-bold text-light mb-1">
                    v{{ game.active_version.version }}
                    <span class="badge bg-primary text-light small ms-1">{{ game.active_version.source }}</span>
                  </div>
                  <div class="small text-secondary mb-3">
                    Total Releases: <strong>{{ game.versions?.length || 1 }}</strong>
                  </div>
                  <RouterLink :to="`/games/${game.slug}`" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
                    <i class="bi bi-play-circle"></i> Play & Preview Game
                  </RouterLink>
                </div>

                <!-- No Version Yet -->
                <div v-else>
                  <span class="badge bg-warning text-dark mb-2">
                    <i class="bi bi-exclamation-circle me-1"></i> No Builds Uploaded
                  </span>
                  <p class="small text-secondary mb-0">
                    Upload your first version package to make this game playable.
                  </p>
                </div>
              </div>

              <!-- Column 3: Upload .zip Release -->
              <div class="col-12 col-lg-4">
                <div class="p-3 bg-black rounded border border-secondary">
                  <label class="form-label small fw-semibold text-light mb-1 d-flex align-items-center gap-1">
                    <i class="bi bi-cloud-arrow-up text-primary"></i>
                    Upload New Version (.zip)
                  </label>
                  <p class="text-secondary extra-small mb-2" style="font-size: 0.75rem;">
                    Must include <code>index.html</code> at the root of the archive.
                  </p>
                  
                  <div class="input-group input-group-sm mb-2">
                    <input
                      type="file"
                      accept=".zip"
                      class="form-control bg-dark text-light border-secondary"
                      @change="(e) => handleFileChange(e, game.slug)"
                    />
                    <button
                      class="btn btn-primary"
                      :disabled="!selectedFile[game.slug] || uploadLoading[game.slug]"
                      @click="uploadZip(game.slug)"
                    >
                      <span v-if="uploadLoading[game.slug]" class="spinner-border spinner-border-sm"></span>
                      <span v-else>Upload</span>
                    </button>
                  </div>

                  <!-- Feedback Notifications -->
                  <div v-if="uploadSuccess[game.slug]" class="small text-success mt-1">
                    <i class="bi bi-check-circle me-1"></i>{{ uploadSuccess[game.slug] }}
                  </div>
                  <div v-if="uploadError[game.slug]" class="small text-danger mt-1">
                    <i class="bi bi-exclamation-triangle me-1"></i>{{ uploadError[game.slug] }}
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- Register New Game Modal (Moved outside the if/else chain so it can open anytime) -->
    <div v-if="isCreateModalOpen" class="modal-backdrop fade show"></div>
    <div v-if="isCreateModalOpen" class="modal fade show d-block" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border border-secondary text-light">
          <div class="modal-header border-secondary">
            <h5 class="modal-title fw-bold">Register New Game</h5>
            <button type="button" class="btn-close btn-close-white" @click="isCreateModalOpen = false"></button>
          </div>
          <form @submit.prevent="handleCreateGame">
            <div class="modal-body">
              <div v-if="createError" class="alert alert-danger py-2 small">{{ createError }}</div>
              <div class="mb-3">
                <label class="form-label small text-secondary">Game Title</label>
                <input v-model="newGame.title" @input="autoSlug" type="text" class="form-control bg-dark text-light border-secondary" placeholder="e.g. Space Odyssey" required />
              </div>
              <div class="mb-3">
                <label class="form-label small text-secondary">URL Slug</label>
                <input v-model="newGame.slug" type="text" class="form-control bg-dark text-light border-secondary" placeholder="e.g. space-odyssey" required />
              </div>
              <div class="mb-3">
                <label class="form-label small text-secondary">Short Description</label>
                <input v-model="newGame.short_description" type="text" class="form-control bg-dark text-light border-secondary" placeholder="Brief tagline for your game" />
              </div>
              <div class="mb-3">
                <label class="form-label small text-secondary">Aspect Ratio</label>
                <select v-model="newGame.aspect_ratio" class="form-select bg-dark text-light border-secondary">
                  <option value="16:9">16:9 (Standard Widescreen)</option>
                  <option value="4:3">4:3 (Classic Arcade / Retro)</option>
                  <option value="9:16">9:16 (Portrait / Mobile)</option>
                </select>
              </div>
            </div>
            <div class="modal-footer border-secondary">
              <button type="button" class="btn btn-secondary btn-sm" @click="isCreateModalOpen = false">Cancel</button>
              <button type="submit" class="btn btn-primary btn-sm" :disabled="createLoading">
                <span v-if="createLoading" class="spinner-border spinner-border-sm me-1"></span>
                Create Game
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>
