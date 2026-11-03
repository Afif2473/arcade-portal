import { createRouter, createWebHistory } from 'vue-router';
import CatalogView from '../views/CatalogView.vue';
import GameView from '../views/GameView.vue';
import DeveloperDashboardView from '../views/DeveloperDashboardView.vue';
import { useAuthStore } from '../stores/auth';
import ResetPasswordView from '../views/ResetPasswordView.vue';

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: [
        {
            path: '/',
            name: 'catalog',
            component: CatalogView,
        },
        {
            path: '/games/:slug',
            name: 'game',
            component: GameView,
        },
        {
            path: '/developer',
            name: 'developer',
            component: DeveloperDashboardView,
            meta: { requiresDev: true },
        },
        {
            path: '/reset-password',
            name: 'reset-password',
            component: ResetPasswordView,
        },
    ],
});

router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore();

    // Ensure user profile is loaded if token exists
    if (authStore.token && !authStore.user) {
        await authStore.fetchUser();
    }

    if (to.meta.requiresDev && !authStore.isDeveloper) {
        alert('Access restricted: Developer account required to enter Developer Studio.');
        return next({ path: '/' });
    }

    next();
});

export default router;
