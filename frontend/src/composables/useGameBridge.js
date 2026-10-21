import { onMounted, onUnmounted, unref } from 'vue';
import apiClient from '../api/client';
import { useAuthStore } from '../stores/auth';

export function useGameBridge({ iframeRef, game, onScoreSubmitted }) {
    const authStore = useAuthStore();
    const cdnEnv = import.meta.env.VITE_GAME_CDN_ORIGIN || 'http://localhost:5500';

    // Normalize allowed origins (support both localhost and 127.0.0.1 in local dev)
    const allowedOrigins = new Set([
        cdnEnv,
        cdnEnv.replace('localhost', '127.0.0.1'),
        cdnEnv.replace('127.0.0.1', 'localhost'),
    ]);

    function getGame() {
        return unref(game) || {};
    }

    function reply(targetWindow, targetOrigin, requestId, type, payload) {
        if (!targetWindow) {
            console.warn('[Host Bridge] ⚠️ Cannot reply: contentWindow is null');
            return;
        }
        console.log(`%c[Host Bridge] ⇒ ${type}`, 'color: #38bdf8; font-weight: bold;', { requestId, payload, targetOrigin });
        targetWindow.postMessage(
            {
                source: 'GAME_PORTAL_HOST',
                version: 1,
                type,
                requestId,
                payload,
            },
            targetOrigin
        );
    }

    const handlers = {
        READY: async () => {
            console.log('[Host Bridge] 🤝 Game SDK reported READY.');
        },

        INIT: async () => {
            const activeGame = getGame();
            const isGuest = !authStore.isAuthenticated;
            const currentUser = authStore.user;

            const responsePayload = {
                user: currentUser
                    ? {
                        id: currentUser.id,
                        username: currentUser.username,
                        avatarUrl: currentUser.avatar_path,
                    }
                    : null,
                game: {
                    slug: activeGame.slug,
                    version: activeGame.active_version?.version || 1,
                },
                isGuest,
                isSandbox: activeGame.status === 'sandbox',
            };
            console.log('[Host Bridge] 👤 Handshake resolved for:', isGuest ? 'Guest' : currentUser?.username);
            return responsePayload;
        },

        SUBMIT_SCORE: async (payload) => {
            if (!authStore.isAuthenticated) {
                throw { code: 'UNAUTHENTICATED', message: 'Log in to submit scores.', status: 401 };
            }
            const activeGame = getGame();
            console.log('[Host Bridge] 📡 Relaying score to Laravel API:', payload);
            const response = await apiClient.post(`/games/${activeGame.slug}/scores`, payload);
            const resData = response.data;

            if (onScoreSubmitted) {
                onScoreSubmitted(resData);
            }

            return {
                score: resData.score,
                personalBest: resData.personal_best,
                rank: resData.rank,
                isNewBest: resData.is_new_best,
            };
        },

        SAVE_STATE: async (payload) => {
            if (!authStore.isAuthenticated) {
                throw { code: 'UNAUTHENTICATED', message: 'Log in to save cloud progress.', status: 401 };
            }
            const activeGame = getGame();
            console.log('[Host Bridge] 💾 Relaying save to Laravel API (slot ' + (payload?.slot ?? 0) + ')');
            const response = await apiClient.post(`/games/${activeGame.slug}/saves`, payload);
            return {
                slot: response.data.slot,
                revision: response.data.revision,
                savedAt: response.data.updated_at,
            };
        },

        LOAD_STATE: async (payload) => {
            if (!authStore.isAuthenticated) {
                throw { code: 'UNAUTHENTICATED', message: 'Log in to load cloud progress.', status: 401 };
            }
            const activeGame = getGame();
            const slot = payload?.slot ?? 0;
            console.log('[Host Bridge] 📂 Relaying load from Laravel API (slot ' + slot + ')');
            const response = await apiClient.get(`/games/${activeGame.slug}/saves/${slot}`);
            return {
                slot: response.data.slot,
                data: response.data.data,
                revision: response.data.revision,
                savedAt: response.data.updated_at,
            };
        },
    };

    async function handleMessage(event) {
        // 1. Strict Origin Verification
        if (!allowedOrigins.has(event.origin)) {
            // Ignore random noise or browser extension events silently
            return;
        }

        const msg = event.data;
        if (msg?.source !== 'GAME_PORTAL_SDK' || msg.version !== 1 || !(msg.type in handlers)) {
            return;
        }

        // 2. Window Source Verification
        const expectedWindow = iframeRef.value?.contentWindow;
        if (expectedWindow && event.source !== expectedWindow) {
            console.warn('[Host Bridge] 🛑 Rejected message: event.source did not match active iframe window.');
            return;
        }

        console.log(`%c[Host Bridge] ⇐ ${msg.type}`, 'color: #4ade80; font-weight: bold;', msg);

        const handler = handlers[msg.type];
        try {
            const result = await handler(msg.payload);
            if (msg.type !== 'READY') {
                reply(event.source, event.origin, msg.requestId, 'RESPONSE', result);
            }
        } catch (err) {
            const status = err.response?.status || err.status || 500;
            const codeMap = {
                401: 'UNAUTHENTICATED',
                403: 'FORBIDDEN',
                409: 'CONFLICT',
                422: 'VALIDATION_FAILED',
                429: 'RATE_LIMITED',
            };
            const errorCode = err.code || codeMap[status] || 'NETWORK_ERROR';
            const errorMessage = err.response?.data?.message || err.message || 'Host error';

            console.error(`[Host Bridge] ❌ ${errorCode} (${status}):`, errorMessage);
            reply(event.source, event.origin, msg.requestId, 'ERROR', {
                code: errorCode,
                message: errorMessage,
                status,
            });
        }
    }

    onMounted(() => {
        window.addEventListener('message', handleMessage);
    });

    onUnmounted(() => {
        window.removeEventListener('message', handleMessage);
    });
}
