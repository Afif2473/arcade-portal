import { createRouter, createWebHistory } from 'vue-router';
import CatalogView from '../views/CatalogView.vue';
import GameView from '../views/GameView.vue';
import DeveloperDashboardView from '../views/DeveloperDashboardView.vue';

const routes = [
    {
        path: '/',
        name: 'catalog',
        component: CatalogView,
    },
    {
        path: '/games/:slug',
        name: 'game-player',
        component: GameView,
    },
    {
        path: '/developer',
        name: 'developer-dashboard',
        component: DeveloperDashboardView,
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
