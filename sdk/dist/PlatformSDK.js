export class PlatformError extends Error {
    constructor(code, message, status) {
        super(message);
        this.code = code;
        this.status = status;
        this.name = 'PlatformError';
    }
}
const SCROLL_KEYS = new Set(['Space', 'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight']);
const TEXT_INPUT_TAGS = new Set(['INPUT', 'TEXTAREA', 'SELECT']);
const MOCK_PREFIX = 'platformsdk:mock:';
const MAX_SLOT = 9;
const MAX_SAVE_BYTES = 64 * 1024;
const MOCK_INIT = {
    user: { id: 'dev-local', username: 'LocalDevPlayer', avatarUrl: null },
    game: null,
    isGuest: false,
    isSandbox: true,
};
export class PlatformSDK {
    constructor(options) {
        this.pending = new Map();
        this.hostOrigin = null;
        this.initResult = null;
        this.onMessage = (event) => {
            if (event.source !== window.parent)
                return;
            if (!this.allowedOrigins.has(event.origin))
                return;
            const msg = event.data;
            if (!msg || msg.source !== 'GAME_PORTAL_HOST' || msg.version !== 1 || typeof msg.requestId !== 'string')
                return;
            const pending = this.pending.get(msg.requestId);
            if (!pending)
                return;
            this.hostOrigin ??= event.origin;
            window.clearTimeout(pending.timer);
            this.pending.delete(msg.requestId);
            this.log('in', msg);
            if (msg.type === 'RESPONSE') {
                pending.resolve(msg.payload);
            }
            else if (msg.type === 'ERROR') {
                const p = (msg.payload ?? {});
                pending.reject(new PlatformError(p.code ?? 'BAD_MESSAGE', p.message ?? 'Unknown host error.', p.status));
            }
            else {
                pending.reject(new PlatformError('BAD_MESSAGE', `Unexpected message type "${msg.type}".`));
            }
        };
        this.options = options;
        this.isEmbedded = window.self !== window.top;
        this.allowedOrigins = new Set(options.allowedHostOrigins);
        this.timeoutMs = options.timeoutMs ?? 5000;
        if (this.isEmbedded)
            window.addEventListener('message', this.onMessage);
    }
    // ---------- Public API ----------
    async init() {
        if (this.initResult)
            return this.initResult;
        this.installInputGuards();
        if (!this.isEmbedded) {
            this.mockLog('init → mock player "LocalDevPlayer"');
            this.initResult = MOCK_INIT;
            return this.initResult;
        }
        this.post(this.envelope('READY'));
        this.initResult = await this.request('INIT');
        return this.initResult;
    }
    async submitScore(score, metadata) {
        this.requireInit();
        if (!Number.isSafeInteger(score))
            throw new PlatformError('VALIDATION_FAILED', 'Score must be an integer.');
        if (!this.isEmbedded)
            return this.mockScore(score);
        return this.request('SUBMIT_SCORE', { score, metadata });
    }
    async saveGame(data, options = {}) {
        this.requireInit();
        const slot = this.validateSlot(options.slot ?? 0);
        const bytes = new TextEncoder().encode(JSON.stringify(data)).length;
        if (bytes > MAX_SAVE_BYTES)
            throw new PlatformError('VALIDATION_FAILED', `Save data is ${bytes} bytes (max ${MAX_SAVE_BYTES}).`);
        if (!this.isEmbedded)
            return this.mockSave(slot, data, options.revision);
        return this.request('SAVE_STATE', { slot, data, revision: options.revision });
    }
    async loadGame(slot = 0) {
        this.requireInit();
        this.validateSlot(slot);
        if (!this.isEmbedded)
            return this.mockLoad(slot);
        return this.request('LOAD_STATE', { slot });
    }
    destroy() {
        window.removeEventListener('message', this.onMessage);
        for (const [, p] of this.pending) {
            window.clearTimeout(p.timer);
            p.reject(new PlatformError('NETWORK_ERROR', 'SDK destroyed.'));
        }
        this.pending.clear();
    }
    // ---------- Messaging ----------
    envelope(type, payload) {
        return { source: 'GAME_PORTAL_SDK', version: 1, type, requestId: crypto.randomUUID(), payload };
    }
    request(type, payload) {
        const message = this.envelope(type, payload);
        return new Promise((resolve, reject) => {
            const timer = window.setTimeout(() => {
                this.pending.delete(message.requestId);
                reject(new PlatformError('TIMEOUT', `${type} timed out after ${this.timeoutMs}ms.`));
            }, this.timeoutMs);
            this.pending.set(message.requestId, { resolve: resolve, reject, timer });
            this.post(message);
        });
    }
    post(message) {
        // Never '*': until we know the host, target each allowed origin.
        // The browser silently drops messages whose target origin doesn't match.
        const targets = this.hostOrigin ? [this.hostOrigin] : [...this.allowedOrigins];
        for (const origin of targets)
            window.parent.postMessage(message, origin);
        this.log('out', message);
    }
    // ---------- Guards & helpers ----------
    installInputGuards() {
        if (this.isEmbedded) {
            window.addEventListener('keydown', (e) => {
                const target = e.target;
                if (target && (target.isContentEditable || TEXT_INPUT_TAGS.has(target.tagName)))
                    return;
                if (SCROLL_KEYS.has(e.code))
                    e.preventDefault();
            }, { passive: false });
        }
        window.focus();
        window.addEventListener('pointerdown', () => window.focus());
    }
    requireInit() {
        if (!this.initResult)
            throw new PlatformError('NOT_INITIALIZED', 'Call sdk.init() first.');
    }
    validateSlot(slot) {
        if (!Number.isInteger(slot) || slot < 0 || slot > MAX_SLOT) {
            throw new PlatformError('VALIDATION_FAILED', `Slot must be an integer 0–${MAX_SLOT}.`);
        }
        return slot;
    }
    log(direction, message) {
        this.options.onLog?.(direction, message);
        if (this.options.debug)
            console.debug(`[PlatformSDK] ${direction === 'out' ? '⇒' : '⇐'}`, message);
    }
    mockLog(text) {
        console.info(`[PlatformSDK Mock] ${text}`);
    }
    // ---------- Standalone mock mode ----------
    mockScore(score) {
        const key = `${MOCK_PREFIX}best`;
        const stored = localStorage.getItem(key);
        const previous = stored === null ? null : Number(stored);
        const isNewBest = previous === null || score > previous;
        const personalBest = isNewBest ? score : previous;
        localStorage.setItem(key, String(personalBest));
        this.mockLog(`Score ${score} saved locally (best ${personalBest})`);
        return { score, personalBest, rank: 1, isNewBest };
    }
    mockSave(slot, data, revision) {
        const key = `${MOCK_PREFIX}save:${slot}`;
        const existing = JSON.parse(localStorage.getItem(key) ?? 'null');
        if (existing && revision !== undefined && revision !== existing.revision) {
            throw new PlatformError('CONFLICT', 'Save state revision out of date.', 409);
        }
        const record = { data, revision: (existing?.revision ?? 0) + 1, savedAt: new Date().toISOString() };
        localStorage.setItem(key, JSON.stringify(record));
        this.mockLog(`Slot ${slot} saved locally (revision ${record.revision})`);
        return { slot, revision: record.revision, savedAt: record.savedAt };
    }
    mockLoad(slot) {
        const record = JSON.parse(localStorage.getItem(`${MOCK_PREFIX}save:${slot}`) ?? 'null');
        this.mockLog(`Slot ${slot} loaded locally (${record ? 'found' : 'empty'})`);
        return { slot, data: record?.data ?? null, revision: record?.revision ?? 0, savedAt: record?.savedAt ?? null };
    }
}
