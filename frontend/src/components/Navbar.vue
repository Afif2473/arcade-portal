<script setup>
import { ref } from 'vue';
import { useAuthStore } from '../stores/auth';
import { RouterLink } from 'vue-router';

const authStore = useAuthStore();
const inputToken = ref('');

function applyToken() {
  if (inputToken.value.trim()) {
    authStore.setToken(inputToken.value.trim());
    inputToken.value = '';
  }
}
</script>

<template>
  <nav class="navbar navbar-expand-lg navbar-dark portal-nav sticky-top py-3">
    <div class="container">
      <RouterLink class="navbar-brand fw-bold text-primary d-flex align-items-center gap-2" to="/">
        <i class="bi bi-controller fs-4"></i>
        <span>ARCADE PORTAL</span>
      </RouterLink>

      <RouterLink to="/developer" class="btn btn-sm btn-outline-secondary ms-2">
        <i class="bi bi-code-slash me-1"></i> Studio
      </RouterLink>

      <div class="d-flex align-items-center gap-3">
        <template v-if="authStore.isAuthenticated && authStore.user">
          <span class="badge bg-dark border border-secondary text-light px-3 py-2 d-flex align-items-center gap-2">
            <i class="bi bi-person-circle text-primary"></i>
            {{ authStore.user.username }}
          </span>
          <button class="btn btn-sm btn-outline-danger" @click="authStore.logout">
            <i class="bi bi-box-arrow-right"></i>
          </button>
        </template>
        <template v-else>
          <div class="input-group input-group-sm" style="max-width: 320px;">
            <input
              v-model="inputToken"
              type="password"
              class="form-control bg-dark text-light border-secondary"
              placeholder="Sanctum Token..."
              @keyup.enter="applyToken"
            />
            <button class="btn btn-primary" @click="applyToken">
              Login
            </button>
          </div>
          <span class="badge bg-secondary text-dark">Guest</span>
        </template>
      </div>
    </div>
  </nav>
</template>
