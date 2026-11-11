<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import apiClient from '../api/client';
import heroImg from '../assets/hero.png';

const featuredGames = ref([]);
const loading = ref(true);

onMounted(async () => {
  try {
    const res = await apiClient.get('/games');
    featuredGames.value = (res.data.data || []).slice(0, 6);
  } catch (err) {
    console.error('Failed to load games:', err);
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div class="home-container">
    <!-- Hero Section -->
    <div class="hero-wrapper">
      <div class="hero-grid">
        <div class="hero-content">
          <h1 class="hero-headline">
            <span>Publish Your Game.</span>
            <span>Reach Players.</span>
            <span class="text-accent">Stay in the Browser.</span>
          </h1>
          <p class="hero-subhead">
            Upload your HTML5 and WebGL games with instant web play, cloud save persistence, and built-in global leaderboard competition.
          </p>
          <div class="hero-ctas">
            <RouterLink to="/games" class="btn btn-primary btn-lg">
              <i class="bi bi-controller"></i> Play Games
            </RouterLink>
            <RouterLink to="/developer" class="btn btn-secondary btn-lg">
              <i class="bi bi-cloud-arrow-up"></i> Developer Portal
            </RouterLink>
          </div>
        </div>

        <div class="hero-media">
          <div class="hero-media-box">
            <img :src="heroImg" alt="Arcade Platform" class="hero-media-img" />
          </div>
        </div>
      </div>

      <!-- 4-Column Feature Highlights Bar -->
      <div class="highlights-card">
        <div class="highlights-grid">
          <div class="highlight-item">
            <div class="highlight-icon-box icon-box-purple">
              <i class="bi bi-controller"></i>
            </div>
            <div>
              <h4 class="highlight-title">HTML5 & WebGL</h4>
              <p class="highlight-desc">Instant browser play</p>
            </div>
          </div>

          <div class="highlight-item">
            <div class="highlight-icon-box icon-box-rose">
              <i class="bi bi-cloud-check"></i>
            </div>
            <div>
              <h4 class="highlight-title">Cloud Saves</h4>
              <p class="highlight-desc">Cross-device sync</p>
            </div>
          </div>

          <div class="highlight-item">
            <div class="highlight-icon-box icon-box-green">
              <i class="bi bi-trophy"></i>
            </div>
            <div>
              <h4 class="highlight-title">Leaderboards</h4>
              <p class="highlight-desc">Global personal bests</p>
            </div>
          </div>

          <div class="highlight-item">
            <div class="highlight-icon-box icon-box-blue">
              <i class="bi bi-shield-check"></i>
            </div>
            <div>
              <h4 class="highlight-title">Sandboxed Releases</h4>
              <p class="highlight-desc">Secure iframe runtime</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Featured Games Section -->
    <section class="games-section">
      <div class="section-header">
        <div>
          <h2>Featured Games</h2>
          <p class="text-muted mb-0">Curated community titles ready to play instantly</p>
        </div>
        <RouterLink to="/games" class="btn btn-secondary">
          View All Games <i class="bi bi-arrow-right"></i>
        </RouterLink>
      </div>

      <div v-if="loading" class="text-center py-5 text-muted">
        Loading games catalog...
      </div>

      <div v-else-if="featuredGames.length === 0" class="text-center py-5 text-muted">
        No games published yet.
      </div>

      <div v-else class="games-grid">
        <div v-for="game in featuredGames" :key="game.id" class="card game-card">
          <div class="game-card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <h3 class="mb-0">{{ game.title }}</h3>
              <span class="badge-ratio">{{ game.aspect_ratio || '16:9' }}</span>
            </div>
            <p class="game-desc">{{ game.short_description || 'Instant browser game.' }}</p>
            <div class="game-footer">
              <span class="text-muted small">By {{ game.developer?.username || 'Creator' }}</span>
              <RouterLink :to="`/games/${game.slug}`" class="btn btn-primary btn-sm">
                Play Now
              </RouterLink>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.home-container {
  max-width: 1200px;
  margin: 0 auto;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 2rem;
}

.games-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 1.5rem;
}

.game-card {
  transition: transform 0.15s ease, border-color 0.15s ease;
}

.game-card:hover {
  transform: translateY(-2px);
  border-color: var(--accent);
}

.game-card-body {
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  height: 100%;
}

.game-desc {
  flex-grow: 1;
  font-size: 0.9rem;
  line-height: 1.5;
  margin-bottom: 1.25rem;
}

.game-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 1rem;
  border-top: 1px solid var(--border-subtle);
}

.badge-ratio {
  font-size: 0.75rem;
  padding: 0.2rem 0.5rem;
  background: var(--surface-raised);
  border-radius: var(--radius-sm);
  color: var(--text-muted);
}
</style>