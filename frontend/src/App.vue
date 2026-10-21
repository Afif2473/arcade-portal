<script setup>
import Navbar from './components/Navbar.vue';
import { RouterView } from 'vue-router';
</script>

<template>
  <div data-bs-theme="dark">
    <Navbar />
    <main class="container py-4">
      <RouterView />
    </main>
  </div>
</template>
