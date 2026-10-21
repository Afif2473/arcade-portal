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
      <RouterLink to="/" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back to Catalog
      </RouterLink>
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
        <div class="card border-secondary shadow-sm">
          <div class="card-body p-4">
            <div class="row align-items-center g-3">
              <!-- Game Meta -->
              <div class="col-12 col-md-5">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <h4 class="fw-bold text-light mb-0">{{ game.title }}</h4>
                  <span class="badge bg-dark border border-secondary text-secondary">/games/{{ game.slug }}</span>
                </div>
                <p class="text-secondary small mb-2">{{ game.short_description || 'No description' }}</p>

                <!-- Status Selector -->
                <div class="d-flex align-items-center gap-2">
                  <span class="small text-secondary">Status:</span>
                  <div class="btn-group btn-group-sm" role="group">
                    <button
                      type="button"
                      class="btn"
                      :class="game.status === 'draft' ? 'btn-secondary' : 'btn-outline-secondary'"
                      @click="updateStatus(game.slug, 'draft')"
                    >
                      Draft
                    </button>
                    <button
                      type="button"
                      class="btn"
                      :class="game.status === 'sandbox' ? 'btn-warning text-dark' : 'btn-outline-warning'"
                      @click="updateStatus(game.slug, 'sandbox')"
                    >
                      Sandbox
                    </button>
                    <button
                      type="button"
                      class="btn"
                      :class="game.status === 'published' ? 'btn-success' : 'btn-outline-success'"
                      @click="updateStatus(game.slug, 'published')"
                    >
                      Published
                    </button>
                  </div>
                </div>
              </div>

              <!-- Version Info -->
              <div class="col-12 col-md-3 border-start-md border-secondary">
                <div class="small text-secondary">Active Version:</div>
                <div class="h5 fw-bold text-light mb-1">
                  v{{ game.active_version?.version || 1 }}
                  <span class="badge bg-primary text-light small ms-1">{{ game.active_version?.source || 'seed' }}</span>
                </div>
                <div class="small text-secondary">
                  Total Releases: <strong>{{ game.versions?.length || 1 }}</strong>
                </div>
                <RouterLink :to="`/games/${game.slug}`" class="btn btn-sm btn-outline-primary mt-2">
                  <i class="bi bi-play-circle me-1"></i> Preview Active Game
                </RouterLink>
              </div>

              <!-- Upload New .zip Release -->
              <div class="col-12 col-md-4">
                <div class="p-3 bg-dark rounded border border-secondary">
                  <label class="form-label small fw-semibold text-light mb-2">
                    <i class="bi bi-cloud-arrow-up text-primary me-1"></i>
                    Upload New Version (.zip)
                  </label>
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

                  <!-- Feedback -->
                  <div v-if="uploadSuccess[game.slug]" class="small text-success">
                    <i class="bi bi-check-circle me-1"></i>{{ uploadSuccess[game.slug] }}
                  </div>
                  <div v-if="uploadError[game.slug]" class="small text-danger">
                    <i class="bi bi-exclamation-triangle me-1"></i>{{ uploadError[game.slug] }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
