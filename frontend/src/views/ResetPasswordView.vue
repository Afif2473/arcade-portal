<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import apiClient from '../api/client';

const route = useRoute();
const router = useRouter();

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
    errorMessage.value = 'Invalid password reset link. Please request a new one.';
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
    errorMessage.value = err.response?.data?.message || 'Failed to reset password. The link may have expired.';
  } finally {
    isLoading.value = false;
  }
}
</script>

<template>
  <div class="row justify-content-center py-5">
    <div class="col-12 col-md-6 col-lg-5">
      <div class="card bg-dark border-secondary shadow-lg">
        <div class="card-body p-4">
          <h4 class="fw-bold text-light mb-1">Set New Password</h4>
          <p class="text-secondary small mb-4">Enter a strong new password for your account.</p>

          <div v-if="successMessage" class="alert alert-success py-3">
            <i class="bi bi-check-circle-fill me-2"></i>{{ successMessage }}
            <div class="mt-3">
              <RouterLink to="/" class="btn btn-sm btn-success">Return to Portal</RouterLink>
            </div>
          </div>

          <div v-else>
            <div v-if="errorMessage" class="alert alert-danger py-2 small">{{ errorMessage }}</div>

            <form @submit.prevent="handleReset">
              <div class="mb-3">
                <label class="form-label small text-secondary">Email Address</label>
                <input v-model="email" type="email" class="form-control bg-dark text-light border-secondary" readonly disabled />
              </div>

              <div class="mb-3">
                <label class="form-label small text-secondary">New Password</label>
                <input v-model="password" type="password" class="form-control bg-dark text-light border-secondary" placeholder="Min. 8 characters" minlength="8" required />
              </div>

              <div class="mb-4">
                <label class="form-label small text-secondary">Confirm New Password</label>
                <input v-model="passwordConfirmation" type="password" class="form-control bg-dark text-light border-secondary" placeholder="Re-enter password" minlength="8" required />
              </div>

              <button type="submit" class="btn btn-primary w-100" :disabled="isLoading || !token">
                <span v-if="isLoading" class="spinner-border spinner-border-sm me-1"></span>
                Update Password
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>