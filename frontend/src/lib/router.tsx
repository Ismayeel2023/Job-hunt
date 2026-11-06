import api, { errorMessage } from '@/lib/api';
import { AnchorHTMLAttributes, createContext, ReactNode, useCallback, useContext, useEffect, useState } from 'react';
import { Link as RouterLink, NavigateFunction } from 'react-router-dom';

/*
 * Navigation and data helpers for the pages: named routes, a Link that takes href,
 * page data loading, and a router for actions that reloads the page's data afterwards.
 */

type Params = number | string | Record<string, string | number | undefined> | undefined;

const idOf = (params: Params) => (typeof params === 'object' ? Object.values(params ?? {})[0] : params);

/** Page routes are frontend paths. Action routes are backend API paths. */
const ROUTES: Record<string, (params?: Params) => string> = {
    dashboard: () => '/',
    'jobs.index': () => '/jobs',
    'jobs.show': (p) => `/jobs/${idOf(p)}`,
    'jobs.apply': (p) => `/api/app/jobs/${idOf(p)}/apply`,
    'apply-runs.answer': (p) => `/api/app/apply-runs/${idOf(p)}/answers`,
    'apply-runs.confirm': (p) => `/api/app/apply-runs/${idOf(p)}/confirm`,
    'apply-runs.cancel': (p) => `/api/app/apply-runs/${idOf(p)}/cancel`,
    'applications.index': () => '/applications',
    'applications.show': (p) => `/applications/${idOf(p)}`,
    'applications.status': (p) => `/api/app/applications/${idOf(p)}/status`,
    'resumes.index': () => '/resumes',
    'resumes.show': (p) => `/resumes/${idOf(p)}`,
    'resumes.pdf': (p) =>
        typeof p === 'object' && p?.download
            ? `/api/app/resumes/${p.resumeVersion}/pdf?download=1`
            : `/api/app/resumes/${typeof p === 'object' ? p?.resumeVersion : p}/pdf`,
    'prep.index': (p) => (typeof p === 'object' && p?.company ? `/prep?company=${p.company}` : '/prep'),
    'prep.questions.update': (p) => `/api/app/prep/questions/${idOf(p)}`,
    'settings.index': () => '/settings',
    'candidate.update': () => '/api/app/settings/candidate',
    'candidate.answers.destroy': (p) => `/api/app/settings/answers/${idOf(p)}`,
    'gmail.sync': () => '/api/app/settings/gmail/sync',
    'gmail.destroy': (p) => `/api/app/settings/gmail/${idOf(p)}`,
    'profile.edit': () => '/profile',
};

export function route(name: string, params?: Params): string {
    const build = ROUTES[name];

    if (!build) {
        throw new Error(`Unknown route: ${name}`);
    }

    return build(params);
}

/* Page data and the actions that refresh it */

const reloaders = new Set<() => Promise<void>>();
let navigate: NavigateFunction | null = null;
let showFlash: ((message: string | null) => void) | null = null;

export function registerNavigate(fn: NavigateFunction): void {
    navigate = fn;
}

async function reloadAll(): Promise<void> {
    await Promise.all([...reloaders].map((reload) => reload()));
}

/**
 * Loads a page's data from the backend and reloads it after any action. Data from the last
 * load stays on screen while a new one is in flight, so filters and polling don't flicker.
 */
export function usePageData<T>(url: string) {
    const [data, setData] = useState<T | null>(null);
    const [error, setError] = useState<string | null>(null);

    const load = useCallback(async () => {
        try {
            const response = await api.get<T>(url);
            setData(response.data);
            setError(null);
        } catch (failure) {
            setError(errorMessage(failure));
        }
    }, [url]);

    useEffect(() => {
        load();
        reloaders.add(load);

        return () => {
            reloaders.delete(load);
        };
    }, [load]);

    return { data, error, reload: load };
}

/** Reloads the current page's data every `interval` milliseconds while mounted. */
export function usePoll(interval: number, _options?: unknown): void {
    useEffect(() => {
        const timer = window.setInterval(() => {
            if (!document.hidden) {
                reloadAll();
            }
        }, interval);

        return () => window.clearInterval(timer);
    }, [interval]);
}

type VisitOptions = {
    replace?: boolean;
    onStart?: () => void;
    onSuccess?: (data: unknown) => void;
    onError?: (errors: Record<string, string>) => void;
    onFinish?: () => void;
    [key: string]: unknown;
};

async function send(method: 'post' | 'patch' | 'put' | 'delete', url: string, data: unknown, options: VisitOptions = {}) {
    options.onStart?.();

    try {
        const response = await api.request({ method, url, data });
        const message = (response.data as { message?: string } | undefined)?.message;

        if (message) {
            showFlash?.(message);
        }

        await reloadAll();
        options.onSuccess?.(response.data);
    } catch (failure) {
        const errors = (failure as { response?: { data?: { errors?: Record<string, string[]> } } }).response?.data?.errors;

        if (errors) {
            options.onError?.(Object.fromEntries(Object.entries(errors).map(([key, messages]) => [key, messages[0]])));
        } else {
            showFlash?.(errorMessage(failure));
            options.onError?.({});
        }
    } finally {
        options.onFinish?.();
    }
}

export const router = {
    visit: (url: string) => navigate?.(url),
    get: (url: string, query: Record<string, unknown> = {}, options: VisitOptions = {}) => {
        const search = new URLSearchParams(
            Object.entries(query)
                .filter(([, value]) => value !== undefined && value !== null && value !== '')
                .map(([key, value]) => [key, String(value)]),
        ).toString();

        navigate?.(search ? `${url}?${search}` : url, { replace: options.replace });
    },
    post: (url: string, data: unknown = {}, options?: VisitOptions) => send('post', url, data, options),
    patch: (url: string, data: unknown = {}, options?: VisitOptions) => send('patch', url, data, options),
    put: (url: string, data: unknown = {}, options?: VisitOptions) => send('put', url, data, options),
    delete: (url: string, options?: VisitOptions) => send('delete', url, undefined, options),
    reload: (_options?: unknown) => reloadAll(),
};

/* Flash messages: the short confirmation an action returns, shown on the page */

const FlashContext = createContext<{ message: string | null; setMessage: (message: string | null) => void }>({
    message: null,
    setMessage: () => {},
});

export function FlashProvider({ children }: { children: ReactNode }) {
    const [message, setMessage] = useState<string | null>(null);

    useEffect(() => {
        showFlash = setMessage;

        return () => {
            showFlash = null;
        };
    }, []);

    return <FlashContext.Provider value={{ message, setMessage }}>{children}</FlashContext.Provider>;
}

export function useFlash() {
    return useContext(FlashContext);
}

/* Small pieces the pages use */

export function Head({ title }: { title?: string }) {
    useEffect(() => {
        document.title = title ? `${title} · JobHunt` : 'JobHunt';
    }, [title]);

    return null;
}

type LinkProps = AnchorHTMLAttributes<HTMLAnchorElement> & {
    href: string;
    preserveScroll?: boolean;
    preserveState?: boolean;
};

export function Link({ href, preserveScroll: _preserveScroll, preserveState: _preserveState, ...props }: LinkProps) {
    return <RouterLink to={href} {...props} />;
}

/** Form state with backend validation errors, for the few forms in the app. */
export function useForm<T extends Record<string, string>>(initial: T) {
    const [data, setDataState] = useState<T>(initial);
    const [errors, setErrors] = useState<Partial<Record<keyof T, string>>>({});
    const [processing, setProcessing] = useState(false);

    const submit = (method: 'post' | 'patch' | 'put', url: string, options: VisitOptions = {}) =>
        send(method, url, data, {
            ...options,
            onStart: () => {
                setProcessing(true);
                setErrors({});
                options.onStart?.();
            },
            onError: (found) => {
                setErrors(found as Partial<Record<keyof T, string>>);
                options.onError?.(found);
            },
            onFinish: () => {
                setProcessing(false);
                options.onFinish?.();
            },
        });

    return {
        data,
        errors,
        processing,
        setData: (key: keyof T, value: string) => setDataState((current) => ({ ...current, [key]: value })),
        reset: () => setDataState(initial),
        post: (url: string, options?: VisitOptions) => submit('post', url, options),
        patch: (url: string, options?: VisitOptions) => submit('patch', url, options),
        put: (url: string, options?: VisitOptions) => submit('put', url, options),
    };
}
