<script setup>
import { ref } from 'vue';
import { useAuthStore } from '../stores/auth';
import apiClient from '../api/client';

const props = defineProps({
  isOpen: Boolean
});
const emit = defineEmits(['close']);

const authStore = useAuthStore();
const mode = ref('login'); // 'login', 'register', 'forgot'

// Form fields
const loginField = ref('');
const password = ref('');
const username = ref('');
const email = ref('');
const role = ref('player');

const errorMessage = ref('');
const successMessage = ref('');
const isLoading = ref(false);

async function handleSubmit() {
  errorMessage.value = '';
  successMessage.value = '';
  isLoading.value = true;

  try {
    if (mode.value === 'login') {
      await authStore.login(loginField.value.trim(), password.value);
      emit('close');
    } else if (mode.value === 'register') {
      await authStore.register(username.value.trim(), email.value.trim(), password.value, role.value);
      emit('close');
    } else if (mode.value === 'forgot') {
      const res = await apiClient.post('/auth/forgot-password', { email: email.value.trim() });
      successMessage.value = res.data.message;
    }
  } catch (err) {
    if (err.response?.data?.errors) {
      errorMessage.value = Object.values(err.response.data.errors).flat().join(' ');
    } else {
      errorMessage.value = err.response?.data?.message || 'Authentication failed.';
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
      <div class="modal-content">
        <!-- Header Tabs -->
        <div class="modal-header d-flex justify-content-between align-items-center">
          <div class="tab-group">
            <button
              class="tab-btn"
              :class="{ active: mode === 'login' }"
              @click="mode = 'login'; errorMessage = ''; successMessage = ''"
            >
              Sign In
            </button>
            <button
              class="tab-btn"
              :class="{ active: mode === 'register' }"
              @click="mode = 'register'; errorMessage = ''; successMessage = ''"
            >
              Register
            </button>
          </div>
          <button type="button" class="btn-close-minimal" @click="$emit('close')">&times;</button>
        </div>

        <div class="modal-body">
          <div v-if="errorMessage" class="muted mb-3" style="color: #ef4444 !important;">
            {{ errorMessage }}
          </div>
          <div v-if="successMessage" class="muted mb-3 text-link">
            {{ successMessage }}
          </div>

          <form @submit.prevent="handleSubmit">
            <!-- Register Fields -->
            <template v-if="mode === 'register'">
              <div class="mb-3">
                <label class="form-label">Username</label>
                <input
                  v-model="username"
                  type="text"
                  class="form-control"
                  placeholder="Username"
                  required
                />
              </div>

              <div class="mb-3">
                <label class="form-label">Email</label>
                <input
                  v-model="email"
                  type="email"
                  class="form-control"
                  placeholder="Email address"
                  required
                />
              </div>

              <div class="mb-3">
                <label class="form-label">Account Role</label>
                <div class="role-selector">
                  <button
                    type="button"
                    class="btn flex-grow-1"
                    :class="role === 'player' ? 'btn-primary' : 'btn-secondary'"
                    @click="role = 'player'"
                  >
                    Player
                  </button>
                  <button
                    type="button"
                    class="btn flex-grow-1"
                    :class="role === 'developer' ? 'btn-primary' : 'btn-secondary'"
                    @click="role = 'developer'"
                  >
                    Developer
                  </button>
                </div>
              </div>
            </template>

            <!-- Login Input -->
            <template v-if="mode === 'login'">
              <div class="mb-3">
                <label class="form-label">Username or Email</label>
                <input
                  v-model="loginField"
                  type="text"
                  class="form-control"
                  placeholder="Username or email"
                  required
                />
              </div>
            </template>

            <!-- Password for Login & Register -->
            <template v-if="mode !== 'forgot'">
              <div class="mb-3">
                <label class="form-label">Password</label>
                <input
                  v-model="password"
                  type="password"
                  class="form-control"
                  placeholder="Password"
                  required
                />
              </div>

              <div v-if="mode === 'login'" class="mb-3">
                <a href="#" class="text-link" @click.prevent="mode = 'forgot'; errorMessage = ''; successMessage = ''">
                  Forgot password?
                </a>
              </div>

              <button type="submit" class="btn btn-primary w-100" :disabled="isLoading">
                <span v-if="isLoading">Loading...</span>
                <span v-else>{{ mode === 'login' ? 'Sign In' : 'Register' }}</span>
              </button>
            </template>

            <!-- Forgot Password View -->
            <template v-if="mode === 'forgot'">
              <p class="muted mb-3">
                Enter your registered email to receive a password reset link.
              </p>

              <div class="mb-3">
                <label class="form-label">Email</label>
                <input
                  v-model="email"
                  type="email"
                  class="form-control"
                  placeholder="you@example.com"
                  required
                />
              </div>

              <button type="submit" class="btn btn-primary w-100 mb-2" :disabled="isLoading">
                <span v-if="isLoading">Sending...</span>
                <span v-else>Send Reset Link</span>
              </button>

              <div class="text-center">
                <a href="#" class="muted" @click.prevent="mode = 'login'; errorMessage = ''; successMessage = ''">
                  Back to Sign In
                </a>
              </div>
            </template>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tab-group {
  display: flex;
  gap: var(--sp-16);
}

.tab-btn {
  background: none;
  border: none;
  font-family: inherit;
  font-size: var(--fs-15);
  font-weight: 400;
  color: var(--muted);
  cursor: pointer;
  padding: 0;
  transition: color 0.15s ease;
}

.tab-btn:hover {
  color: var(--text);
}

.tab-btn.active {
  color: var(--accent);
  font-weight: 600;
}

.btn-close-minimal {
  background: none;
  border: none;
  color: var(--muted);
  font-size: 20px;
  cursor: pointer;
  line-height: 1;
}

.btn-close-minimal:hover {
  color: var(--text);
}

.role-selector {
  display: flex;
  gap: var(--sp-8);
}
</style>
