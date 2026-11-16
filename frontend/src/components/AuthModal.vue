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
const passwordConfirmation = ref('');
const username = ref('');
const email = ref('');
const role = ref('player');

const errorMessage = ref('');
const successMessage = ref('');
const isLoading = ref(false);

function switchMode(newMode) {
  mode.value = newMode;
  errorMessage.value = '';
  successMessage.value = '';
  passwordConfirmation.value = '';
}

async function handleSubmit() {
  errorMessage.value = '';
  successMessage.value = '';
  isLoading.value = true;

  try {
    if (mode.value === 'login') {
      await authStore.login(loginField.value.trim(), password.value);
      emit('close');
    } else if (mode.value === 'register') {
      if (password.value !== passwordConfirmation.value) {
        errorMessage.value = 'Passwords do not match.';
        isLoading.value = false;
        return;
      }
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
  <div v-if="isOpen" class="modal-backdrop fade show" @click="$emit('close')"></div>
  <div v-if="isOpen" class="modal fade show d-block" tabindex="-1" @click.self="$emit('close')">
    <div class="modal-dialog modal-dialog-centered auth-dialog">
      <div class="modal-content auth-card">
        <!-- Header with Segmented Tabs & Close -->
        <div class="auth-header">
          <div class="auth-tabs" v-if="mode !== 'forgot'">
            <button
              type="button"
              class="auth-tab-btn"
              :class="{ active: mode === 'login' }"
              @click="switchMode('login')"
            >
              Sign In
            </button>
            <button
              type="button"
              class="auth-tab-btn"
              :class="{ active: mode === 'register' }"
              @click="switchMode('register')"
            >
              Register
            </button>
          </div>
          <h4 v-else class="mb-0 fs-5 fw-bold">Reset Password</h4>

          <button type="button" class="btn-close-modal" @click="$emit('close')" aria-label="Close">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>

        <div class="auth-body">
          <!-- Alerts -->
          <div v-if="errorMessage" class="auth-alert auth-alert-error">
            <i class="bi bi-exclamation-circle"></i>
            <span>{{ errorMessage }}</span>
          </div>
          <div v-if="successMessage" class="auth-alert auth-alert-success">
            <i class="bi bi-check-circle"></i>
            <span>{{ successMessage }}</span>
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
                  placeholder="Choose a username"
                  required
                />
              </div>

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

              <div class="mb-3">
                <label class="form-label">Account Role</label>
                <div class="role-selector">
                  <button
                    type="button"
                    class="role-btn"
                    :class="{ active: role === 'player' }"
                    @click="role = 'player'"
                  >
                    <i class="bi bi-controller"></i> Player
                  </button>
                  <button
                    type="button"
                    class="role-btn"
                    :class="{ active: role === 'developer' }"
                    @click="role = 'developer'"
                  >
                    <i class="bi bi-code-slash"></i> Developer
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
                  placeholder="Enter your username or email"
                  required
                />
              </div>
            </template>

            <!-- Password for Login & Register -->
            <template v-if="mode !== 'forgot'">
              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <label class="form-label mb-0">Password</label>
                  <a
                    v-if="mode === 'login'"
                    href="#"
                    class="forgot-link"
                    @click.prevent="switchMode('forgot')"
                  >
                    Forgot password?
                  </a>
                </div>
                <input
                  v-model="password"
                  type="password"
                  class="form-control"
                  placeholder="Enter your password"
                  required
                />
              </div>
              <div v-if="mode === 'register'" class="mb-3">
                <label class="form-label">Confirm Password</label>
                <input
                  v-model="passwordConfirmation"
                  type="password"
                  class="form-control"
                  placeholder="Confirm your password"
                  required
                />
              </div>

              <button type="submit" class="btn btn-primary w-100 mt-2 py-2" :disabled="isLoading">
                <span v-if="isLoading">Please wait...</span>
                <span v-else>{{ mode === 'login' ? 'Sign In' : 'Create Account' }}</span>
              </button>
            </template>

            <!-- Forgot Password View -->
            <template v-if="mode === 'forgot'">
              <p class="text-muted small mb-3">
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

              <button type="submit" class="btn btn-primary w-100 mb-3 py-2" :disabled="isLoading">
                <span v-if="isLoading">Sending...</span>
                <span v-else>Send Reset Link</span>
              </button>

              <div class="text-center">
                <a href="#" class="forgot-link" @click.prevent="switchMode('login')">
                  <i class="bi bi-arrow-left"></i> Back to Sign In
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
.auth-dialog {
  max-width: 440px;
}

.auth-card {
  background-color: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: 0 24px 48px rgba(0, 0, 0, 0.55);
  overflow: hidden;
}

.auth-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border);
}

/* Segmented Pill Tabs */
.auth-tabs {
  display: inline-flex;
  background-color: var(--bg);
  border: 1px solid var(--border);
  border-radius: var(--radius-pill);
  padding: 4px;
  gap: 6px;
}

.auth-tab-btn {
  background: transparent;
  border: none;
  outline: none;
  font-family: var(--font-sans);
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text-muted);
  padding: 0.45rem 1.15rem;
  border-radius: var(--radius-pill);
  cursor: pointer;
  transition: all 0.15s ease;
}

.auth-tab-btn:hover:not(.active) {
  color: var(--text);
}

.auth-tab-btn.active {
  background-color: var(--accent);
  color: #ffffff;
}

.btn-close-modal {
  background: transparent;
  border: none;
  outline: none;
  color: var(--text-muted);
  font-size: 1rem;
  width: 32px;
  height: 32px;
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-close-modal:hover {
  background-color: var(--surface-raised);
  color: var(--text);
}

.auth-body {
  padding: 1.5rem;
}

.form-label {
  font-size: 0.85rem;
  font-weight: 500;
  color: var(--text);
  margin-bottom: 0.4rem;
}

.forgot-link {
  font-size: 0.8rem;
  color: var(--accent);
  text-decoration: none;
  transition: opacity 0.15s ease;
}

.forgot-link:hover {
  opacity: 0.85;
  text-decoration: underline;
}

/* Role Selector */
.role-selector {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}

.role-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.65rem 1rem;
  background-color: var(--bg);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  color: var(--text-muted);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.role-btn:hover:not(.active) {
  border-color: var(--text-muted);
  color: var(--text);
}

.role-btn.active {
  background-color: var(--accent-light);
  border-color: var(--accent);
  color: #38bdf8;
}

/* Alerts */
.auth-alert {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.65rem 0.9rem;
  border-radius: var(--radius-sm);
  font-size: 0.85rem;
  margin-bottom: 1rem;
}

.auth-alert-error {
  background-color: rgba(239, 68, 68, 0.12);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #fca5a5;
}

.auth-alert-success {
  background-color: rgba(34, 197, 94, 0.12);
  border: 1px solid rgba(34, 197, 94, 0.3);
  color: #86efac;
}
</style>