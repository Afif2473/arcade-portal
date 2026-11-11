<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import apiClient from '../api/client';

const route = useRoute();

const token = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');

const isLoading = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

onMounted(() => {
  token.value = route.query.token || '';
  email.value = route.query.email || '';
  if (!token.value || !email.value) {
    errorMessage.value = 'Invalid or expired password reset link.';
  }
});

async function handleReset() {
  errorMessage.value = '';
  successMessage.value = '';
  isLoading.value = true;

  try {
    const res = await apiClient.post('/auth/reset-password', {
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    });
    successMessage.value = res.data.message;
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Failed to reset password.';
  } finally {
    isLoading.value = false;
  }
}
</script>

<template>
  <div class="reset-wrapper">
    <div class="reset-card card">
      <h2>Set New Password</h2>
      <p class="muted">Enter a new password for your account.</p>

      <div v-if="successMessage">
        <p class="muted">{{ successMessage }}</p>
        <RouterLink to="/" class="btn btn-primary w-100">Return to Portal</RouterLink>
      </div>

      <div v-else>
        <p v-if="errorMessage" class="muted mb-3" style="color: #ef4444 !important;">{{ errorMessage }}</p>

        <form @submit.prevent="handleReset">
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input v-model="email" type="email" class="form-control" readonly disabled />
          </div>

          <div class="mb-3">
            <label class="form-label">New Password</label>
            <input v-model="password" type="password" class="form-control" placeholder="Min. 8 characters" minlength="8" required />
          </div>

          <div class="mb-4">
            <label class="form-label">Confirm New Password</label>
            <input v-model="passwordConfirmation" type="password" class="form-control" placeholder="Re-enter password" minlength="8" required />
          </div>

          <button type="submit" class="btn btn-primary w-100" :disabled="isLoading || !token">
            <span v-if="isLoading">Updating...</span>
            <span v-else>Update Password</span>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.reset-wrapper {
  display: flex;
  justify-content: center;
  padding-top: var(--sp-40);
}

.reset-card {
  width: 100%;
  max-width: 400px;
  padding: var(--sp-24);
}
</style>
