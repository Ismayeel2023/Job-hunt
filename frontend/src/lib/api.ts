import axios, { AxiosError } from 'axios';

/**
 * Talks to the Laravel backend. In development Vite forwards /api and /sanctum to
 * http://127.0.0.1:8000, so requests are same-origin and the session cookie just works.
 */
const api = axios.create({
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    withCredentials: true,
    withXSRFToken: true,
});

let unauthenticatedHandler: (() => void) | null = null;

/** Called when the backend says the session has ended, so the app can show the login page. */
export function onUnauthenticated(handler: () => void): void {
    unauthenticatedHandler = handler;
}

/** Sanctum sets the XSRF-TOKEN cookie that every change request must echo back. */
export function fetchCsrfCookie() {
    return api.get('/sanctum/csrf-cookie');
}

api.interceptors.response.use(undefined, async (error: AxiosError) => {
    const status = error.response?.status;
    const config = error.config as (typeof error.config & { retried?: boolean }) | undefined;

    // An expired CSRF token: fetch a fresh one and try once more.
    if (status === 419 && config && !config.retried) {
        config.retried = true;
        await fetchCsrfCookie();

        return api.request(config);
    }

    if (status === 401 && !config?.url?.endsWith('/api/app/login')) {
        unauthenticatedHandler?.();
    }

    return Promise.reject(error);
});

/** The message a failed request should show: the backend's own message when it gave one. */
export function errorMessage(error: unknown): string {
    if (axios.isAxiosError(error)) {
        const data = error.response?.data as { message?: string } | undefined;

        if (data?.message) {
            return data.message;
        }

        if (!error.response) {
            return 'The backend is not reachable. Is it running? Start it with npm run dev.';
        }
    }

    return 'Something went wrong. Please try again.';
}

export default api;
