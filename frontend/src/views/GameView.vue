<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import apiClient from '../api/client';
import { useGameBridge } from '../composables/useGameBridge';

const route = useRoute();
const slug = route.params.slug;

const game = ref(null);
const leaderboard = ref([]);
const loading = ref(true);
const iframeRef = ref(null);
const recentScoreNotice = ref(null);

const gameLaunchUrl = computed(() =>
  game.value?.active_version?.launch_url
    ?? `${import.meta.env.VITE_GAME_CDN_ORIGIN}/index.html`
);

const aspectRatioClass = computed(() => {
  switch (game.value?.aspect_ratio) {
    case '4:3': return 'ratio-4x3';
    case '9:16': return 'ratio-9x16';
    default: return 'ratio-16x9';
  }
});

async function fetchGameData() {
  try {
    const res = await apiClient.get(`/games/${slug}`);
    game.value = res.data;
    await fetchLeaderboard();
  } catch (err) {
    console.error('Failed to load game details:', err);
  } finally {
    loading.value = false;
  }
}

async function fetchLeaderboard() {
  try {
    const res = await apiClient.get(`/games/${slug}/leaderboard`);
    leaderboard.value = res.data.data || [];
  } catch (err) {
    console.error('Failed to load leaderboard:', err);
  }
}

// Security bridge composable
useGameBridge({
  iframeRef,
  game: computed(() => game.value || { slug }),
  onScoreSubmitted: (result) => {
    recentScoreNotice.value = result;
    fetchLeaderboard();
  },
});

function toggleFullscreen() {
  if (iframeRef.value?.requestFullscreen) {
    iframeRef.value.requestFullscreen();
  }
}

function reloadGame() {
  if (iframeRef.value) {
    iframeRef.value.src = iframeRef.value.src;
  }
}

onMounted(() => {
  fetchGameData();
});
</script>

<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status"></div>
  </div>

  <div v-else-if="!game" class="alert alert-danger">
    Game not found.
  </div>

  <div v-else>
    <!-- Sandbox Notice -->
    <div v-if="game.status === 'sandbox'" class="alert alert-warning py-2 mb-3 d-flex align-items-center gap-2">
      <i class="bi bi-cone-striped"></i>
      <span><strong>Sandbox Mode:</strong> Only developers and admins can play and submit scores for this game.</span>
    </div>

    <!-- Title & Controls Header -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h2 class="fw-bold mb-0 text-light">{{ game.title }}</h2>
        <span class="text-secondary small">Version {{ game.active_version?.version || 1 }}</span>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" @click="reloadGame" title="Restart Game">
          <i class="bi bi-arrow-clockwise"></i> Reload
        </button>
        <button class="btn btn-outline-primary btn-sm" @click="toggleFullscreen" title="Fullscreen">
          <i class="bi bi-arrows-fullscreen"></i> Fullscreen
        </button>
      </div>
    </div>

    <!-- Game Viewport Frame -->
    <div class="card bg-black border-secondary shadow-lg overflow-hidden mb-4">
      <div class="ratio" :class="aspectRatioClass">
        <iframe
          ref="iframeRef"
          :src="gameLaunchUrl"
          sandbox="allow-scripts allow-same-origin allow-pointer-lock"
          allow="autoplay; fullscreen; gamepad"
          class="w-100 h-100 border-0"
        ></iframe>
      </div>
    </div>

    <!-- Live Submission Feedback Toast/Banner -->
    <div v-if="recentScoreNotice" class="alert alert-success py-2 d-flex align-items-center justify-content-between mb-4">
      <span>
        <i class="bi bi-trophy-fill text-warning me-2"></i>
        New run submitted! Score: <strong>{{ recentScoreNotice.score }}</strong>
        (Rank: #{{ recentScoreNotice.rank }})
      </span>
      <span v-if="recentScoreNotice.is_new_best" class="badge bg-warning text-dark">Personal Best!</span>
    </div>

    <!-- Details & Leaderboard Row -->
    <div class="row g-4">
      <div class="col-12 col-lg-7">
        <div class="card p-4 h-100 border-secondary">
          <h5 class="fw-bold text-light mb-3">About this Game</h5>
          <p class="text-secondary">{{ game.description || game.short_description || 'No description provided.' }}</p>
        </div>
      </div>

      <div class="col-12 col-lg-5">
        <div class="card p-4 h-100 border-secondary">
          <h5 class="fw-bold text-light mb-3 d-flex align-items-center justify-content-between">
            <span><i class="bi bi-trophy text-warning me-2"></i>Leaderboard</span>
            <span class="badge bg-dark border border-secondary text-secondary fs-6">{{ leaderboard.length }} entries</span>
          </h5>

          <div v-if="leaderboard.length === 0" class="text-center py-4 text-secondary small">
            No scores submitted yet. Be the first!
          </div>

          <div v-else class="table-responsive">
            <table class="table table-dark table-hover table-sm align-middle mb-0">
              <thead>
                <tr class="text-secondary small">
                  <th>#</th>
                  <th>Player</th>
                  <th class="text-end">Best Score</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(entry, index) in leaderboard" :key="entry.user.id">
                  <td>
                    <span v-if="index === 0" class="badge bg-warning text-dark">1</span>
                    <span v-else-if="index === 1" class="badge bg-secondary text-light">2</span>
                    <span v-else-if="index === 2" class="badge bg-bronze text-light" style="background-color: #cd7f32;">3</span>
                    <span v-else class="text-secondary small ms-2">{{ index + 1 }}</span>
                  </td>
                  <td class="fw-semibold">{{ entry.user.username }}</td>
                  <td class="text-end text-primary fw-bold">{{ entry.best_score.toLocaleString() }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
