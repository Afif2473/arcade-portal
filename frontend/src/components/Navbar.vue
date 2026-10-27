<script setup>
import { ref } from 'vue';
import { useAuthStore } from '../stores/auth';
import { RouterLink } from 'vue-router';
import AuthModal from './AuthModal.vue';

const authStore = useAuthStore();
const isAuthModalOpen = ref(false);
</script>

<template>
  <nav class="navbar navbar-expand-lg navbar-dark portal-nav sticky-top py-3">
    <div class="container">
      <RouterLink class="navbar-brand fw-bold text-primary d-flex align-items-center gap-2" to="/">
        <i class="bi bi-controller fs-4"></i>
        <span>ARCADE PORTAL</span>
      </RouterLink>

      <!-- Role-Guarded Studio Button (Only visible to Developers / Admins) -->
      <RouterLink
        v-if="authStore.isDeveloper"
        to="/developer"
        class="btn btn-sm btn-outline-info ms-2"
      >
        <i class="bi bi-code-slash me-1"></i> Studio
      </RouterLink>

      <div class="d-flex align-items-center gap-3 ms-auto">
        <!-- Logged In State -->
        <template v-if="authStore.isAuthenticated && authStore.user">
          <span class="badge bg-dark border border-secondary text-light px-3 py-2 d-flex align-items-center gap-2">
            <i class="bi bi-person-circle text-primary"></i>
            {{ authStore.user.username }}
            <span class="badge" :class="authStore.isDeveloper ? 'bg-info text-dark' : 'bg-secondary'">
              {{ authStore.user.role }}
            </span>
          </span>
          <button class="btn btn-sm btn-outline-danger" @click="authStore.logout" title="Sign Out">
            <i class="bi bi-box-arrow-right"></i>
          </button>
        </template>

        <!-- Logged Out State -->
        <template v-else>
          <button class="btn btn-sm btn-primary" @click="isAuthModalOpen = true">
            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
          </button>
        </template>
      </div>
    </div>
  </nav>

  <!-- Login / Register Modal -->
  <AuthModal :is-open="isAuthModalOpen" @close="isAuthModalOpen = false" />
</template>
