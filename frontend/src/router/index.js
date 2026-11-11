import { createRouter, createWebHistory } from 'vue-router';
import HomeView from '../views/HomeView.vue';
import CatalogView from '../views/CatalogView.vue';
import GameView from '../views/GameView.vue';
import DeveloperDashboardView from '../views/DeveloperDashboardView.vue';
import ResetPasswordView from '../views/ResetPasswordView.vue';
import { useAuthStore } from '../stores/auth';

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: [
        {
            path: '/',
            name: 'home',
            component: HomeView,
        },
        {
            path: '/games',
            name: 'games',
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