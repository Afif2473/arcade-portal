<script setup>
import Sidebar from './components/Sidebar.vue';
import TopHeader from './components/TopHeader.vue';
import { RouterView } from 'vue-router';
</script>

<template>
  <div class="app-layout">
    <!-- Left Navigation Sidebar -->
    <Sidebar />

    <!-- Main Application Column -->
    <div class="main-column">
      <!-- Sticky Top Header (Search & Actions) -->
      <TopHeader />

      <!-- Standardized Viewport Content Across All Pages -->
      <main class="main-content">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<style scoped>
.app-layout {
  display: flex;
  min-height: 100vh;
  background-color: var(--bg);
  color: var(--text);
}

.main-column {
  display: flex;
  flex-direction: column;
  flex-grow: 1;
  min-width: 0;
  overflow: hidden;
}

/* Standardized Content Container: 32px Top, 40px Sides, 64px Bottom */
.main-content {
  flex-grow: 1;
  padding: 2rem 2.5rem 4rem 2.5rem;
  overflow-y: auto;
}

@media (max-width: 768px) {
  .main-content {
    padding: 1.5rem 1rem 3rem 1rem;
  }
}
</style>