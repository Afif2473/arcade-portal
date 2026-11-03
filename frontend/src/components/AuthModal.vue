<script setup>
import { ref } from 'vue';
import { useAuthStore } from '../stores/auth';
import apiClient from '../api/client'; // <--- ADD THIS IMPORT

const props = defineProps({
  isOpen: Boolean
});
const emit = defineEmits(['close']);

const authStore = useAuthStore();
const mode = ref('login'); // 'login', 'register', or 'forgot'

// Form fields
const loginField = ref('');
const password = ref('');
const username = ref('');
const email = ref('');
const role = ref('player'); // 'player' or 'developer'

const errorMessage = ref('');
const successMessage = ref(''); // <--- ADD THIS REF
const isLoading = ref(false);

async function handleSubmit() {
  errorMessage.value = '';
  isLoading.value = true;

  try {
    if (mode.value === 'login') {
      await authStore.login(loginField.value.trim(), password.value);
      emit('close');
    } else if (mode.value === 'register'){
      await authStore.register(username.value.trim(), email.value.trim(), password.value, role.value);
      emit('close');
    } else if (mode.value === 'forgot'){
      const res = await apiClient.post('/auth/forgot-password', {email: email.value.trim() });
      successMessage.value = res.data.message;
    }
  } catch (err) {
    if (err.response?.data?.errors) {
      errorMessage.value = Object.values(err.response.data.errors).flat().join(' ');
    } else {
      errorMessage.value = err.response?.data?.message || 'Authentication failed. Please try again.';
    }
  } finally {
    isLoading.value = false;
  }
}
</script>

<template>
  <div v-if="isOpen" class="modal-backdrop fade show"></div>
  <div v-if="isOpen" class="modal fade show d-block" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content bg-dark border border-secondary text-light shadow-lg">
        <div class="modal-header border-secondary">
          <div class="btn-group w-100" role="group">
            <button
              class="btn btn-sm"
              :class="mode === 'login' ? 'btn-primary' : 'btn-outline-secondary'"
              @click="mode = 'login'; errorMessage = ''"
            >
              Sign In
            </button>
            <button
              class="btn btn-sm"
              :class="mode === 'register' ? 'btn-primary' : 'btn-outline-secondary'"
              @click="mode = 'register'; errorMessage = ''"
            >
              Create Account
            </button>
          </div>
          <button type="button" class="btn-close btn-close-white ms-2" @click="$emit('close')"></button>
        </div>

        <div class="modal-body p-4">
          <div v-if="errorMessage" class="alert alert-danger py-2 small">
            {{ errorMessage }}
          </div>

          <form @submit.prevent="handleSubmit">
            <!-- Register Extra Fields -->
            <template v-if="mode === 'register'">
              <div class="mb-3">
                <label class="form-label small text-secondary">Username</label>
                <input
                  v-model="username"
                  type="text"
                  class="form-control bg-dark border-secondary text-light"
                  placeholder="e.g. PixelHero"
                  required
                />
              </div>

              <div class="mb-3">
                <label class="form-label small text-secondary">Email Address</label>
                <input
                  v-model="email"
                  type="email"
                  class="form-control bg-dark border-secondary text-light"
                  placeholder="you@example.com"
                  required
                />
              </div>

              <div class="mb-3">
                <label class="form-label small text-secondary">Account Type</label>
                <div class="d-flex gap-2">
                  <label class="btn btn-sm flex-fill" :class="role === 'player' ? 'btn-outline-primary active' : 'btn-outline-secondary'">
                    <input type="radio" v-model="role" value="player" class="d-none" />
                    🎮 Player
                  </label>
                  <label class="btn btn-sm flex-fill" :class="role === 'developer' ? 'btn-outline-primary active' : 'btn-outline-secondary'">
                    <input type="radio" v-model="role" value="developer" class="d-none" />
                    🛠️ Developer
                  </label>
                </div>
                <div class="form-text text-secondary x-small mt-1">
                  {{ role === 'developer' ? 'Developers can upload and manage games in Developer Studio.' : 'Players play games and compete on global leaderboards.' }}
                </div>
              </div>
            </template>

            <!-- Login Input -->
            <template v-if="mode === 'login'">
              <div class="mb-3">
                <label class="form-label small text-secondary">Username or Email</label>
                <input
                  v-model="loginField"
                  type="text"
                  class="form-control bg-dark border-secondary text-light"
                  placeholder="Username or email"
                  required
                />
              </div>
            </template>

            <!-- Password (Only for Login & Register) -->
            <template v-if="mode !== 'forgot'">
              <div class="mb-3">
                <label class="form-label small text-secondary">Password</label>
                <input
                  v-model="password"
                  type="password"
                  class="form-control bg-dark border-secondary text-light"
                  placeholder="••••••••"
                  required
                />
              </div>

              <!-- Forgot Password link (only in login mode) -->
              <div v-if="mode === 'login'" class="d-flex justify-content-end mb-3">
                <a href="#" class="text-info small text-decoration-none" @click.prevent="mode = 'forgot'; errorMessage = ''; successMessage = ''">
                  Forgot Password?
                </a>
              </div>

              <!-- Submit Button for Login / Register -->
              <button type="submit" class="btn btn-primary w-100 mt-2" :disabled="isLoading">
                <span v-if="isLoading" class="spinner-border spinner-border-sm me-1"></span>
                {{ mode === 'login' ? 'Sign In' : 'Create Account' }}
              </button>
            </template>

            <!-- Forgot Password Mode -->
            <div v-if="mode === 'forgot'">
              <p class="text-secondary small mb-3">
                Enter your registered email address and we will send you a password reset link.
              </p>
              <div v-if="successMessage" class="alert alert-success py-2 small">{{ successMessage }}</div>
              <div v-if="errorMessage" class="alert alert-danger py-2 small">{{ errorMessage }}</div>

              <div class="mb-3">
                <label class="form-label small text-secondary">Email Address</label>
                <input v-model="email" type="email" class="form-control bg-dark text-light border-secondary" placeholder="you@example.com" required />
              </div>
              <button type="submit" class="btn btn-primary w-100 mb-2" :disabled="isLoading">
                <span v-if="isLoading" class="spinner-border spinner-border-sm me-1"></span>
                Send Reset Link
              </button>
              <div class="text-center">
                <button type="button" class="btn btn-link btn-sm text-secondary" @click="mode = 'login'; errorMessage = ''; successMessage = ''">
                  &larr; Back to Sign In
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.x-small {
  font-size: 0.75rem;
}
</style>
