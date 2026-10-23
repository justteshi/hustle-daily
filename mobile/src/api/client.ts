import { useSession } from '../state/session';
import { strings } from '../i18n';

export type ApiErrorBody = {
    error?: {
        code?: string;
        message?: string;
        details?: Record<string, string[]>;
        request_id?: string;
    };
};

export class ApiError extends Error {
    constructor(
        message: string,
        public status: number | null = null,
        public requestId?: string,
    ) {
        super(message);
    }
}

export function apiBaseUrl(): string {
    const value = process.env.EXPO_PUBLIC_API_BASE_URL?.replace(/\/$/, '');
    if (!value || !value.startsWith('https://') || value.includes('.example')) {
        throw new ApiError(strings.errors.config);
    }
    return value;
}

export async function apiRequest<T>(
    path: string,
    options: RequestInit = {},
    authenticated = true,
): Promise<T> {
    const token = useSession.getState().token;
    if (authenticated && !token) throw new ApiError(strings.errors.signedOut, 401);
    let response: Response;
    try {
        response = await fetch(`${apiBaseUrl()}${path}`, {
            ...options,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(authenticated ? { Authorization: `Bearer ${token}` } : {}),
                ...options.headers,
            },
        });
    } catch (error) {
        if (error instanceof ApiError) throw error;
        throw new ApiError(strings.errors.network);
    }
    const body = (await response.json().catch(() => ({}))) as ApiErrorBody & { data?: T };
    if (!response.ok) {
        throw new ApiError(
            body.error?.message ?? `${strings.errors.request} (${response.status})`,
            response.status,
            body.error?.request_id,
        );
    }
    return body.data as T;
}

export function mutationKey(): string {
    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;
}
