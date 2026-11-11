<script setup>
import { ref } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import AuthModal from './AuthModal.vue';

const authStore = useAuthStore();
const router = useRouter();

const searchQuery = ref('');
const isAuthModalOpen = ref(false);

function handleSearch() {
  if (searchQuery.value.trim()) {
    router.push({ path: '/games', query: { q: searchQuery.value.trim() } });
  }
}
</script>

<template>
  <header class="top-header">
    <div class="header-left">
      <form @submit.prevent="handleSearch" class="search-form">
        <div class="search-box">
          <i class="bi bi-search search-icon"></i>
          <input
            v-model="searchQuery"
            type="text"
            class="search-input"
            placeholder="Search titles..."
          />
        </div>
      </form>
    </div>

    <div class="header-right">
      <RouterLink to="/developer" class="dev-portal-link">
        <i class="bi bi-code-square"></i> Developer Studio
      </RouterLink>

      <template v-if="!authStore.isAuthenticated">
        <button class="btn btn-primary" @click="isAuthModalOpen = true">
          Sign In
        </button>
      </template>

      <template v-else>
        <div class="user-pill">
          <span class="user-name">{{ authStore.user?.username }}</span>
          <span class="user-badge">{{ authStore.user?.role }}</span>
          <button class="btn-icon" @click="authStore.logout" title="Sign out">
            <i class="bi bi-box-arrow-right"></i>
          </button>
        </div>
      </template>
    </div>

    <AuthModal :is-open="isAuthModalOpen" @close="isAuthModalOpen = false" />
  </header>
</template>

<style scoped>
.top-header {
  height: 60px;
  background-color: var(--surface);
  border-bottom: 1px solid var(--border);
  padding: 0 2rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky;
  top: 0;
  z-index: 100;
}

.search-box {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 0.85rem;
  color: var(--text-muted);
  pointer-events: none;
}

.search-input {
  width: 260px;
  height: 38px;
  background-color: var(--bg);
  border: 1px solid var(--border);
  border-radius: var(--radius-pill);
  color: var(--text);
  padding: 0 1rem 0 2.25rem;
  font-size: 0.875rem;
  outline: none;
  transition: all 0.15s ease;
}

.search-input:focus {
  border-color: var(--accent);
  width: 300px;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 1.5rem;
}

.dev-portal-link {
  color: var(--text-muted);
  font-size: 0.9rem;
  font-weight: 500;
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  transition: color 0.15s ease;
}

.dev-portal-link:hover {
  color: var(--text);
}

.user-pill {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background-color: var(--bg);
  border: 1px solid var(--border);
  padding: 0.35rem 0.75rem 0.35rem 1rem;
  border-radius: var(--radius-pill);
}

.user-name {
  font-weight: 600;
  font-size: 0.875rem;
}

.user-badge {
  font-size: 0.7rem;
  background-color: var(--surface-raised);
  color: var(--text-muted);
  padding: 0.15rem 0.5rem;
  border-radius: var(--radius-pill);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.btn-icon {
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 0;
  font-size: 1rem;
  line-height: 1;
}

.btn-icon:hover {
  color: #ef4444;
}
</style>