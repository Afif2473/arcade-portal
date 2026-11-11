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
  <div v-if="loading" class="empty-state">
    Loading...
  </div>

  <div v-else-if="!game" class="empty-state">
    Game not found.
  </div>

  <div v-else class="game-view">
    <!-- Header -->
    <div class="game-header">
      <div>
        <h1>{{ game.title }}</h1>
        <span class="muted">Version {{ game.active_version?.version || 1 }}</span>
      </div>
      <div class="header-actions">
        <button class="btn btn-secondary" @click="reloadGame" title="Reload">
          <i class="bi bi-arrow-clockwise"></i> Reload
        </button>
        <button class="btn btn-secondary" @click="toggleFullscreen" title="Fullscreen">
          <i class="bi bi-arrows-fullscreen"></i> Fullscreen
        </button>
      </div>
    </div>

    <!-- Viewport -->
    <div class="viewport-frame card">
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

    <!-- Recent Score Submission Notice -->
    <div v-if="recentScoreNotice" class="score-notice">
      <span>Score submitted: {{ recentScoreNotice.score }} (Rank #{{ recentScoreNotice.rank }})</span>
    </div>

    <!-- Details & Leaderboard -->
    <div class="game-info-grid">
      <!-- Description -->
      <div class="card p-16">
        <h2>About</h2>
        <p class="muted">
          {{ game.description || game.short_description || 'No description provided.' }}
        </p>
      </div>

      <!-- Leaderboard -->
      <div class="card p-16">
        <h2>Leaderboard</h2>

        <div v-if="leaderboard.length === 0" class="empty-state">
          No scores yet.
        </div>

        <table v-else class="table">
          <thead>
            <tr>
              <th>#</th>
              <th>Player</th>
              <th style="text-align: right;">Score</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(entry, index) in leaderboard" :key="entry.user.id">
              <td class="muted">{{ index + 1 }}</td>
              <td>{{ entry.user.username }}</td>
              <td style="text-align: right; font-weight: 600;">{{ entry.best_score.toLocaleString() }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.game-view {
  max-width: 960px;
}

.game-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: var(--sp-16);
}

.header-actions {
  display: flex;
  gap: var(--sp-8);
}

.viewport-frame {
  margin-bottom: var(--sp-24);
  background-color: #000 !important;
  overflow: hidden;
}

.score-notice {
  background-color: var(--surface);
  border: 1px solid var(--border);
  padding: var(--sp-8) var(--sp-16);
  margin-bottom: var(--sp-24);
  font-size: var(--fs-13);
  color: var(--text);
}

.game-info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--sp-16);
}

.p-16 {
  padding: var(--sp-16) !important;
}

@media (max-width: 768px) {
  .game-info-grid {
    grid-template-columns: 1fr;
  }
}
</style>
