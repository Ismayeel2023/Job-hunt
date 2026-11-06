import api, { fetchCsrfCookie, onUnauthenticated } from '@/lib/api';
import { User } from '@/types';
import { createContext, ReactNode, useCallback, useContext, useEffect, useState } from 'react';

type AuthState = {
    user: User | null;
    checking: boolean;
    setUser: (user: User | null) => void;
    login: (email: string, password: string, remember: boolean) => Promise<void>;
    logout: () => Promise<void>;
};

const AuthContext = createContext<AuthState | null>(null);

/**
 * Who is signed in. The backend keeps the session in a cookie (Sanctum SPA authentication),
 * so this only asks the backend once on load and after logging in or out.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(null);
    const [checking, setChecking] = useState(true);

    useEffect(() => {
        onUnauthenticated(() => setUser(null));

        fetchCsrfCookie()
            .then(() => api.get<User>('/api/app/user'))
            .then((response) => setUser(response.data))
            .catch(() => setUser(null))
            .finally(() => setChecking(false));
    }, []);

    const login = useCallback(async (email: string, password: string, remember: boolean) => {
        await fetchCsrfCookie();
        const response = await api.post<User>('/api/app/login', { email, password, remember });
        setUser(response.data);
    }, []);

    const logout = useCallback(async () => {
        await api.post('/api/app/logout');
        setUser(null);
    }, []);

    return <AuthContext.Provider value={{ user, checking, setUser, login, logout }}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState {
    const auth = useContext(AuthContext);

    if (!auth) {
        throw new Error('useAuth must be used inside AuthProvider');
    }

    return auth;
}
