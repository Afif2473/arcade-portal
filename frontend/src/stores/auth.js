import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '../api/client';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    const token = ref(localStorage.getItem('game_portal_token') || null);

    const isAuthenticated = computed(() => !!token.value);
    const isDeveloper = computed(() => user.value?.role === 'developer' || user.value?.role === 'admin');
    const isAdmin = computed(() => user.value?.role === 'admin');

    async function fetchUser() {
        if (!token.value) {
            user.value = null;
            return null;
        }
        try {
            const response = await apiClient.get('/user');
            user.value = response.data;
            return user.value;
        } catch (err) {
            logout();
            return null;
        }
    }

    async function login(loginIdentifier, password) {
        const response = await apiClient.post('/auth/login', {
            login: loginIdentifier,
            password: password
        });

        token.value = response.data.token;
        user.value = response.data.user;
        localStorage.setItem('game_portal_token', token.value);
        return user.value;
    }

    async function register(username, email, password, role = 'player') {
        const response = await apiClient.post('/auth/register', {
            username,
            email,
            password,
            role
        });

        token.value = response.data.token;
        user.value = response.data.user;
        localStorage.setItem('game_portal_token', token.value);
        return user.value;
    }

    async function logout() {
        try {
            if (token.value) {
                await apiClient.post('/auth/logout');
            }
        } catch (err) {
            // Ignore failure on server logout
        } finally {
            token.value = null;
            user.value = null;
            localStorage.removeItem('game_portal_token');
        }
    }

    return {
        user,
        token,
        isAuthenticated,
        isDeveloper,
        isAdmin,
        fetchUser,
        login,
        register,
        logout,
    };
});
