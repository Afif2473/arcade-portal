<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import apiClient from '../api/client';

const games = ref([]);
const loading = ref(true);

onMounted(async () => {
  try {
    const res = await apiClient.get('/games');
    games.value = res.data.data || [];
  } catch (err) {
    console.error('Failed to load games:', err);
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h2 class="fw-bold mb-0">Browse Games</h2>
      <span class="text-secondary">{{ games.length }} Available</span>
    </div>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div v-else-if="games.length === 0" class="text-center py-5 text-secondary">
      <p>No games published yet.</p>
    </div>

    <div v-else class="row g-4">
      <div v-for="game in games" :key="game.id" class="col-12 col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm border-secondary overflow-hidden">
          <div class="ratio ratio-16x9 bg-dark border-bottom border-secondary d-flex align-items-center justify-content-center">
            <i class="bi bi-joystick text-secondary fs-1"></i>
          </div>
          <div class="card-body d-flex flex-column">
            <h5 class="card-title fw-bold text-light">{{ game.title }}</h5>
            <p class="card-text text-secondary small flex-grow-1">
              {{ game.short_description || 'No description provided.' }}
            </p>
            <div class="d-flex align-items-center justify-content-between pt-3 border-top border-secondary">
              <span class="small text-secondary">By {{ game.developer?.username || 'Unknown' }}</span>
              <RouterLink :to="`/games/${game.slug}`" class="btn btn-sm btn-primary">
                Play Now <i class="bi bi-play-fill"></i>
              </RouterLink>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
