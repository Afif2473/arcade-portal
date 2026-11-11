<script setup>
import { ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();

const isGamesOpen = ref(true);

const categories = [
  { name: 'All Games', slug: 'all' },
  { name: 'Action', slug: 'action' },
  { name: 'Arcade', slug: 'arcade' },
  { name: 'Puzzle', slug: 'puzzle' },
  { name: 'Retro', slug: 'retro' },
  { name: 'Strategy', slug: 'strategy' },
];

function handleGamesClick() {
  isGamesOpen.value = !isGamesOpen.value;
  // If not currently on /games or viewing a specific category, navigate to /games
  if (route.path !== '/games' || route.query.category) {
    router.push('/games');
  }
}
</script>

<template>
  <aside class="sidebar">
    <!-- Brand -->
    <div class="sidebar-header">
      <RouterLink to="/" class="brand">
        <i class="bi bi-joystick text-accent"></i>
        <span>Arcade</span>
      </RouterLink>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
      <ul class="nav-list">
        <li>
          <RouterLink
            to="/"
            class="nav-link"
            :class="{ active: route.path === '/' }"
          >
            <i class="bi bi-house"></i>
            <span>Home</span>
          </RouterLink>
        </li>

        <!-- Combined Games button & accordion toggle -->
        <li>
          <div
            class="nav-link nav-games"
            :class="{ active: route.path.startsWith('/games') && (!route.query.category || route.query.category === 'all') }"
            @click="handleGamesClick"
          >
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-grid"></i>
              <span>Games</span>
            </div>
            <span class="toggle-indicator">{{ isGamesOpen ? '−' : '+' }}</span>
          </div>

          <ul v-show="isGamesOpen" class="category-sublist">
            <li v-for="cat in categories" :key="cat.slug">
              <RouterLink
                :to="{ path: '/games', query: cat.slug !== 'all' ? { category: cat.slug } : {} }"
                class="category-link"
                :class="{ active: route.query.category === cat.slug || (!route.query.category && cat.slug === 'all') }"
              >
                {{ cat.name }}
              </RouterLink>
            </li>
          </ul>
        </li>

        <li>
          <RouterLink
            to="/developer"
            class="nav-link"
            :class="{ active: route.path === '/developer' }"
          >
            <i class="bi bi-code-slash"></i>
            <span>Dev Mode</span>
          </RouterLink>
        </li>
      </ul>
    </nav>
  </aside>
</template>

<style scoped>
.sidebar {
  width: 240px;
  height: 100vh;
  position: sticky;
  top: 0;
  background-color: var(--surface);
  border-right: 1px solid var(--border);
  padding: 1.5rem 1rem;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
}

.sidebar-header {
  margin-bottom: 2rem;
  padding-left: 0.5rem;
}

.brand {
  font-size: 1.35rem;
  font-weight: 800;
  color: var(--text);
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 0.6rem;
  letter-spacing: -0.5px;
}

.sidebar-nav {
  overflow-y: auto;
}

.nav-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.65rem 0.85rem;
  border-radius: var(--radius-sm);
  color: var(--text-muted);
  text-decoration: none;
  font-weight: 500;
  font-size: 0.95rem;
  transition: all 0.15s ease;
}

.nav-link:hover {
  color: var(--text);
  background-color: var(--surface-raised);
}

.nav-link.active {
  color: #ffffff;
  background-color: var(--accent);
}

/* Merged Games Clickable Row */
.nav-games {
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: space-between;
  user-select: none;
}

.toggle-indicator {
  font-size: 1.15rem;
  line-height: 1;
  font-weight: 600;
  color: var(--text-muted);
  padding-right: 0.25rem;
}

.nav-games.active .toggle-indicator {
  color: #ffffff;
}

.category-sublist {
  list-style: none;
  padding-left: 2.25rem;
  margin: 0.25rem 0 0.5rem 0;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.category-link {
  font-size: 0.875rem;
  color: var(--text-muted);
  text-decoration: none;
  padding: 0.25rem 0;
  display: block;
  transition: color 0.15s ease;
}

.category-link:hover {
  color: var(--text);
}

.category-link.active {
  color: var(--accent);
  font-weight: 600;
}
</style>