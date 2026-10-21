import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '../api/client';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    const token = ref(localStorage.getItem('game_portal_token') || null);
    const isAuthenticated = computed(() => !!token.value);

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

    function setToken(newToken) {
        token.value = newToken;
        if (newToken) {
            localStorage.setItem('game_portal_token', newToken);
            fetchUser();
        } else {
            logout();
        }
    }

    function logout() {
        token.value = null;
        user.value = null;
        localStorage.removeItem('game_portal_token');
    }

    return {
        user,
        token,
        isAuthenticated,
        fetchUser,
        setToken,
        logout,
    };
});
