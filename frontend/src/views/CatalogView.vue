<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import apiClient from '../api/client';

const route = useRoute();
const games = ref([]);
const loading = ref(true);
const fetchError = ref(null);

const categories = [
  { name: 'All Games', slug: 'all' },
  { name: 'Action', slug: 'action' },
  { name: 'Arcade', slug: 'arcade' },
  { name: 'Puzzle', slug: 'puzzle' },
  { name: 'Retro', slug: 'retro' },
  { name: 'Strategy', slug: 'strategy' },
];

const currentCategory = computed(() => {
  const slug = route.query.category;
  if (!slug || slug === 'all') {
    return { name: 'All Games', slug: 'all' };
  }
  const match = categories.find((c) => c.slug === slug);
  return match || { name: slug.charAt(0).toUpperCase() + slug.slice(1), slug };
});

const pageTitle = computed(() => {
  if (route.query.q) {
    return `Search: "${route.query.q}"`;
  }
  if (currentCategory.value.slug === 'all') {
    return 'All Games';
  }
  return `${currentCategory.value.name} Games`;
});

// Instant in-memory filtering: Category switching never triggers a loading state
const filteredGames = computed(() => {
  let list = games.value;

  const cat = route.query.category;
  if (cat && cat !== 'all') {
    list = list.filter((game) => {
      const matchCat = game.category && game.category.toLowerCase() === cat.toLowerCase();
      const matchDesc = game.short_description && game.short_description.toLowerCase().includes(cat.toLowerCase());
      const matchTitle = game.title && game.title.toLowerCase().includes(cat.toLowerCase());
      return matchCat || matchDesc || matchTitle;
    });
  }

  const query = route.query.q?.trim().toLowerCase();
  if (query) {
    list = list.filter((game) =>
      game.title.toLowerCase().includes(query) ||
      (game.short_description && game.short_description.toLowerCase().includes(query))
    );
  }

  return list;
});

async function loadGames() {
  // Only trigger loading on the first fetch
  if (games.value.length === 0) {
    loading.value = true;
  }
  fetchError.value = null;

  try {
    const res = await apiClient.get('/games');
    games.value = res.data.data || [];
  } catch (err) {
    console.error('Failed to load games:', err);
    fetchError.value = 'Unable to connect to game catalog server.';
  } finally {
    loading.value = false;
  }
}

onMounted(loadGames);
</script>

<template>
  <div class="catalog-view">
    <!-- Header -->
    <div class="catalog-header mb-4">
      <div class="d-inline-flex align-items-center gap-2 mb-2">
        <span class="badge bg-primary-subtle text-accent px-3 py-1 rounded-pill small fw-semibold">
          Category
        </span>
      </div>
      <h1 class="catalog-title mb-1">{{ pageTitle }}</h1>
      <p class="text-muted mb-0">
        {{ currentCategory.slug === 'all' 
          ? 'Browse all titles published across the platform.' 
          : `Explore all titles in the ${currentCategory.name} category.` }}
      </p>
    </div>

    <!-- 1. Error State (When API is unreachable) -->
    <div v-if="fetchError" class="card empty-card p-5 text-center">
      <div class="empty-icon-box text-danger mb-3">
        <i class="bi bi-exclamation-triangle"></i>
      </div>
      <h3 class="mb-2">Connection Error</h3>
      <p class="text-muted mb-4">{{ fetchError }}</p>
      <div>
        <button class="btn btn-secondary" @click="loadGames">
          <i class="bi bi-arrow-clockwise"></i> Retry
        </button>
      </div>
    </div>

    <!-- 2. Skeleton Loading Grid (Prevents layout jump & awkward text) -->
    <div v-else-if="loading" class="games-grid">
      <div v-for="n in 6" :key="n" class="skeleton-card"></div>
    </div>

    <!-- 3. Category Empty State -->
    <div v-else-if="filteredGames.length === 0" class="card empty-card p-5 text-center">
      <div class="empty-icon-box mb-3">
        <i class="bi bi-controller"></i>
      </div>
      <h3 class="mb-2">No {{ currentCategory.slug === 'all' ? '' : currentCategory.name }} games yet</h3>
      <p class="text-muted mb-4">Be the first developer to publish a title in this category.</p>
      <div>
        <RouterLink to="/developer" class="btn btn-primary">
          <i class="bi bi-cloud-arrow-up"></i> Upload Game
        </RouterLink>
      </div>
    </div>

    <!-- 4. Games Grid -->
    <div v-else class="games-grid">
      <div v-for="game in filteredGames" :key="game.id" class="card game-card">
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
  </div>
</template>

<style scoped>
.catalog-view {
  max-width: 1200px;
  margin: 0 auto;
}

.catalog-title {
  font-size: 2.25rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.empty-card {
  background-color: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
}

.empty-icon-box {
  width: 54px;
  height: 54px;
  margin: 0 auto;
  border-radius: 14px;
  background: rgba(2, 132, 199, 0.12);
  color: var(--accent);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.6rem;
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