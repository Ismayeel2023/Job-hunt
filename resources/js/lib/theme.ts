import { useCallback, useEffect, useState } from 'react';

export type Theme = 'light' | 'dark' | 'system';

const KEY = 'jobhunt-theme';

function readTheme(): Theme {
    try {
        const stored = localStorage.getItem(KEY);

        return stored === 'light' || stored === 'dark' ? stored : 'system';
    } catch {
        return 'system';
    }
}

function applyTheme(theme: Theme): void {
    const dark =
        theme === 'dark' ||
        (theme === 'system' &&
            window.matchMedia('(prefers-color-scheme: dark)').matches);

    document.documentElement.classList.toggle('dark', dark);
}

export function useTheme(): [Theme, () => void] {
    const [theme, setTheme] = useState<Theme>(readTheme);

    useEffect(() => {
        applyTheme(theme);

        if (theme !== 'system') {
            return;
        }

        const query = window.matchMedia('(prefers-color-scheme: dark)');
        const listener = () => applyTheme('system');
        query.addEventListener('change', listener);

        return () => query.removeEventListener('change', listener);
    }, [theme]);

    const cycle = useCallback(() => {
        setTheme((current) => {
            const next: Theme =
                current === 'system'
                    ? 'light'
                    : current === 'light'
                      ? 'dark'
                      : 'system';

            try {
                if (next === 'system') {
                    localStorage.removeItem(KEY);
                } else {
                    localStorage.setItem(KEY, next);
                }
            } catch {
                // The choice still applies for this page view.
            }

            return next;
        });
    }, []);

    return [theme, cycle];
}
