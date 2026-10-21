export type MessageType = 'READY' | 'INIT' | 'SUBMIT_SCORE' | 'SAVE_STATE' | 'LOAD_STATE' | 'RESPONSE' | 'ERROR';

export interface PlatformMessage<T = unknown> {
    source: 'GAME_PORTAL_SDK' | 'GAME_PORTAL_HOST';
    version: 1;
    type: MessageType;
    requestId: string;
    payload?: T;
}

export interface PlatformUser { id: number | string; username: string; avatarUrl: string | null; }
export interface InitResult { user: PlatformUser | null; game: { slug: string; version: number } | null; isGuest: boolean; isSandbox: boolean; }
export interface ScoreResult { score: number; personalBest: number; rank: number; isNewBest: boolean; }
export interface SaveResult { slot: number; revision: number; savedAt: string; }
export interface LoadResult<T = unknown> { slot: number; data: T | null; revision: number; savedAt: string | null; }

export type PlatformErrorCode =
    | 'UNAUTHENTICATED' | 'FORBIDDEN' | 'VALIDATION_FAILED' | 'RATE_LIMITED'
    | 'CONFLICT' | 'TIMEOUT' | 'NETWORK_ERROR' | 'BAD_MESSAGE' | 'NOT_INITIALIZED';

export class PlatformError extends Error {
    constructor(public readonly code: PlatformErrorCode, message: string, public readonly status?: number) {
        super(message);
        this.name = 'PlatformError';
    }
}

export interface PlatformSDKOptions {
    allowedHostOrigins: string[];
    timeoutMs?: number;
    debug?: boolean;
    onLog?: (direction: 'out' | 'in', message: PlatformMessage) => void;
}

interface Pending { resolve: (value: unknown) => void; reject: (error: PlatformError) => void; timer: number; }

const SCROLL_KEYS = new Set(['Space', 'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight']);
const TEXT_INPUT_TAGS = new Set(['INPUT', 'TEXTAREA', 'SELECT']);
const MOCK_PREFIX = 'platformsdk:mock:';
const MAX_SLOT = 9;
const MAX_SAVE_BYTES = 64 * 1024;

const MOCK_INIT: InitResult = {
    user: { id: 'dev-local', username: 'LocalDevPlayer', avatarUrl: null },
    game: null,
    isGuest: false,
    isSandbox: true,
};

export class PlatformSDK {
    readonly isEmbedded: boolean;
    private readonly allowedOrigins: Set<string>;
    private readonly timeoutMs: number;
    private readonly options: PlatformSDKOptions;
    private readonly pending = new Map<string, Pending>();
    private hostOrigin: string | null = null;
    private initResult: InitResult | null = null;

    constructor(options: PlatformSDKOptions) {
        this.options = options;
        this.isEmbedded = window.self !== window.top;
        this.allowedOrigins = new Set(options.allowedHostOrigins);
        this.timeoutMs = options.timeoutMs ?? 5000;
        if (this.isEmbedded) window.addEventListener('message', this.onMessage);
    }

    // ---------- Public API ----------

    async init(): Promise<InitResult> {
        if (this.initResult) return this.initResult;
        this.installInputGuards();

        if (!this.isEmbedded) {
            this.mockLog('init → mock player "LocalDevPlayer"');
            this.initResult = MOCK_INIT;
            return this.initResult;
        }

        this.post(this.envelope('READY'));
        this.initResult = await this.request<InitResult>('INIT');
        return this.initResult;
    }

    async submitScore(score: number, metadata?: Record<string, unknown>): Promise<ScoreResult> {
        this.requireInit();
        if (!Number.isSafeInteger(score)) throw new PlatformError('VALIDATION_FAILED', 'Score must be an integer.');
        if (!this.isEmbedded) return this.mockScore(score);
        return this.request<ScoreResult>('SUBMIT_SCORE', { score, metadata });
    }

    async saveGame<T>(data: T, options: { slot?: number; revision?: number } = {}): Promise<SaveResult> {
        this.requireInit();
        const slot = this.validateSlot(options.slot ?? 0);
        const bytes = new TextEncoder().encode(JSON.stringify(data)).length;
        if (bytes > MAX_SAVE_BYTES) throw new PlatformError('VALIDATION_FAILED', `Save data is ${bytes} bytes (max ${MAX_SAVE_BYTES}).`);
        if (!this.isEmbedded) return this.mockSave(slot, data, options.revision);
        return this.request<SaveResult>('SAVE_STATE', { slot, data, revision: options.revision });
    }

    async loadGame<T = unknown>(slot = 0): Promise<LoadResult<T>> {
        this.requireInit();
        this.validateSlot(slot);
        if (!this.isEmbedded) return this.mockLoad<T>(slot);
        return this.request<LoadResult<T>>('LOAD_STATE', { slot });
    }

    destroy(): void {
        window.removeEventListener('message', this.onMessage);
        for (const [, p] of this.pending) {
            window.clearTimeout(p.timer);
            p.reject(new PlatformError('NETWORK_ERROR', 'SDK destroyed.'));
        }
        this.pending.clear();
    }

    // ---------- Messaging ----------

    private envelope(type: MessageType, payload?: unknown): PlatformMessage {
        return { source: 'GAME_PORTAL_SDK', version: 1, type, requestId: crypto.randomUUID(), payload };
    }

    private request<T>(type: MessageType, payload?: unknown): Promise<T> {
        const message = this.envelope(type, payload);
        return new Promise<T>((resolve, reject) => {
            const timer = window.setTimeout(() => {
                this.pending.delete(message.requestId);
                reject(new PlatformError('TIMEOUT', `${type} timed out after ${this.timeoutMs}ms.`));
            }, this.timeoutMs);
            this.pending.set(message.requestId, { resolve: resolve as (v: unknown) => void, reject, timer });
            this.post(message);
        });
    }

    private post(message: PlatformMessage): void {
        // Never '*': until we know the host, target each allowed origin.
        // The browser silently drops messages whose target origin doesn't match.
        const targets = this.hostOrigin ? [this.hostOrigin] : [...this.allowedOrigins];
        for (const origin of targets) window.parent.postMessage(message, origin);
        this.log('out', message);
    }

    private onMessage = (event: MessageEvent): void => {
        if (event.source !== window.parent) return;
        if (!this.allowedOrigins.has(event.origin)) return;

        const msg = event.data as Partial<PlatformMessage> | null;
        if (!msg || msg.source !== 'GAME_PORTAL_HOST' || msg.version !== 1 || typeof msg.requestId !== 'string') return;

        const pending = this.pending.get(msg.requestId);
        if (!pending) return;

        this.hostOrigin ??= event.origin;
        window.clearTimeout(pending.timer);
        this.pending.delete(msg.requestId);
        this.log('in', msg as PlatformMessage);

        if (msg.type === 'RESPONSE') {
            pending.resolve(msg.payload);
        } else if (msg.type === 'ERROR') {
            const p = (msg.payload ?? {}) as { code?: PlatformErrorCode; message?: string; status?: number };
            pending.reject(new PlatformError(p.code ?? 'BAD_MESSAGE', p.message ?? 'Unknown host error.', p.status));
        } else {
            pending.reject(new PlatformError('BAD_MESSAGE', `Unexpected message type "${msg.type}".`));
        }
    };

    // ---------- Guards & helpers ----------

    private installInputGuards(): void {
        if (this.isEmbedded) {
            window.addEventListener('keydown', (e) => {
                const target = e.target as HTMLElement | null;
                if (target && (target.isContentEditable || TEXT_INPUT_TAGS.has(target.tagName))) return;
                if (SCROLL_KEYS.has(e.code)) e.preventDefault();
            }, { passive: false });
        }
        window.focus();
        window.addEventListener('pointerdown', () => window.focus());
    }

    private requireInit(): void {
        if (!this.initResult) throw new PlatformError('NOT_INITIALIZED', 'Call sdk.init() first.');
    }

    private validateSlot(slot: number): number {
        if (!Number.isInteger(slot) || slot < 0 || slot > MAX_SLOT) {
            throw new PlatformError('VALIDATION_FAILED', `Slot must be an integer 0–${MAX_SLOT}.`);
        }
        return slot;
    }

    private log(direction: 'out' | 'in', message: PlatformMessage): void {
        this.options.onLog?.(direction, message);
        if (this.options.debug) console.debug(`[PlatformSDK] ${direction === 'out' ? '⇒' : '⇐'}`, message);
    }

    private mockLog(text: string): void {
        console.info(`[PlatformSDK Mock] ${text}`);
    }

    // ---------- Standalone mock mode ----------

    private mockScore(score: number): ScoreResult {
        const key = `${MOCK_PREFIX}best`;
        const stored = localStorage.getItem(key);
        const previous = stored === null ? null : Number(stored);
        const isNewBest = previous === null || score > previous;
        const personalBest = isNewBest ? score : (previous as number);
        localStorage.setItem(key, String(personalBest));
        this.mockLog(`Score ${score} saved locally (best ${personalBest})`);
        return { score, personalBest, rank: 1, isNewBest };
    }

    private mockSave<T>(slot: number, data: T, revision?: number): SaveResult {
        const key = `${MOCK_PREFIX}save:${slot}`;
        const existing = JSON.parse(localStorage.getItem(key) ?? 'null') as { revision: number } | null;
        if (existing && revision !== undefined && revision !== existing.revision) {
            throw new PlatformError('CONFLICT', 'Save state revision out of date.', 409);
        }
        const record = { data, revision: (existing?.revision ?? 0) + 1, savedAt: new Date().toISOString() };
        localStorage.setItem(key, JSON.stringify(record));
        this.mockLog(`Slot ${slot} saved locally (revision ${record.revision})`);
        return { slot, revision: record.revision, savedAt: record.savedAt };
    }

    private mockLoad<T>(slot: number): LoadResult<T> {
        const record = JSON.parse(localStorage.getItem(`${MOCK_PREFIX}save:${slot}`) ?? 'null') as
            { data: T; revision: number; savedAt: string } | null;
        this.mockLog(`Slot ${slot} loaded locally (${record ? 'found' : 'empty'})`);
        return { slot, data: record?.data ?? null, revision: record?.revision ?? 0, savedAt: record?.savedAt ?? null };
    }
}
