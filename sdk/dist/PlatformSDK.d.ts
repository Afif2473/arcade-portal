export type MessageType = 'READY' | 'INIT' | 'SUBMIT_SCORE' | 'SAVE_STATE' | 'LOAD_STATE' | 'RESPONSE' | 'ERROR';
export interface PlatformMessage<T = unknown> {
    source: 'GAME_PORTAL_SDK' | 'GAME_PORTAL_HOST';
    version: 1;
    type: MessageType;
    requestId: string;
    payload?: T;
}
export interface PlatformUser {
    id: number | string;
    username: string;
    avatarUrl: string | null;
}
export interface InitResult {
    user: PlatformUser | null;
    game: {
        slug: string;
        version: number;
    } | null;
    isGuest: boolean;
    isSandbox: boolean;
}
export interface ScoreResult {
    score: number;
    personalBest: number;
    rank: number;
    isNewBest: boolean;
}
export interface SaveResult {
    slot: number;
    revision: number;
    savedAt: string;
}
export interface LoadResult<T = unknown> {
    slot: number;
    data: T | null;
    revision: number;
    savedAt: string | null;
}
export type PlatformErrorCode = 'UNAUTHENTICATED' | 'FORBIDDEN' | 'VALIDATION_FAILED' | 'RATE_LIMITED' | 'CONFLICT' | 'TIMEOUT' | 'NETWORK_ERROR' | 'BAD_MESSAGE' | 'NOT_INITIALIZED';
export declare class PlatformError extends Error {
    readonly code: PlatformErrorCode;
    readonly status?: number | undefined;
    constructor(code: PlatformErrorCode, message: string, status?: number | undefined);
}
export interface PlatformSDKOptions {
    allowedHostOrigins: string[];
    timeoutMs?: number;
    debug?: boolean;
    onLog?: (direction: 'out' | 'in', message: PlatformMessage) => void;
}
export declare class PlatformSDK {
    readonly isEmbedded: boolean;
    private readonly allowedOrigins;
    private readonly timeoutMs;
    private readonly options;
    private readonly pending;
    private hostOrigin;
    private initResult;
    constructor(options: PlatformSDKOptions);
    init(): Promise<InitResult>;
    submitScore(score: number, metadata?: Record<string, unknown>): Promise<ScoreResult>;
    saveGame<T>(data: T, options?: {
        slot?: number;
        revision?: number;
    }): Promise<SaveResult>;
    loadGame<T = unknown>(slot?: number): Promise<LoadResult<T>>;
    destroy(): void;
    private envelope;
    private request;
    private post;
    private onMessage;
    private installInputGuards;
    private requireInit;
    private validateSlot;
    private log;
    private mockLog;
    private mockScore;
    private mockSave;
    private mockLoad;
}
