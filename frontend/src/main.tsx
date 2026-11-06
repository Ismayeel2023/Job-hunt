import '@fontsource-variable/bricolage-grotesque';
import '@fontsource-variable/instrument-sans';
import '@fontsource-variable/jetbrains-mono';
import './app.css';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { AuthProvider, useAuth } from '@/lib/auth';
import { FlashProvider, registerNavigate, usePageData } from '@/lib/router';
import ApplicationBoard from '@/Pages/Applications/Board';
import ApplicationShow from '@/Pages/Applications/Show';
import Dashboard from '@/Pages/Dashboard';
import JobsIndex from '@/Pages/Jobs/Index';
import JobShow from '@/Pages/Jobs/Show';
import Login from '@/Pages/Login';
import PrepIndex from '@/Pages/Prep/Index';
import Profile from '@/Pages/Profile';
import ResumesIndex from '@/Pages/Resumes/Index';
import ResumeShow from '@/Pages/Resumes/Show';
import SettingsIndex from '@/Pages/Settings/Index';
import { ComponentType, ReactNode, StrictMode, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Navigate, Params, Route, Routes, useLocation, useNavigate, useParams } from 'react-router-dom';

function NavigationBridge() {
    const navigate = useNavigate();

    useEffect(() => registerNavigate(navigate), [navigate]);

    return null;
}

function Splash() {
    return <div className="flex min-h-screen items-center justify-center text-sm text-muted">Loading…</div>;
}

function RequireAuth({ children }: { children: ReactNode }) {
    const { user, checking } = useAuth();
    const location = useLocation();

    if (checking) {
        return <Splash />;
    }

    if (!user) {
        return <Navigate to={`/login?next=${encodeURIComponent(location.pathname + location.search)}`} replace />;
    }

    return children;
}

type ApiFor = (params: Readonly<Params>, search: string) => string;

/**
 * Loads a page's data from the backend API, then renders the page with it.
 */
function DataPage({ api, component: Component }: { api: ApiFor; component: ComponentType<any> }) {
    const params = useParams();
    const { search } = useLocation();
    const { data, error } = usePageData<Record<string, unknown>>(api(params, search));

    if (!data) {
        return (
            <AuthenticatedLayout>
                <p className={`text-sm ${error ? 'text-low-fg' : 'text-muted'}`}>{error ?? 'Loading…'}</p>
            </AuthenticatedLayout>
        );
    }

    return <Component {...data} />;
}

/** A different record (say /jobs/65 to /jobs/66) starts fresh, rather than reusing the last page's state. */
function Page(props: { api: ApiFor; component: ComponentType<any> }) {
    const { pathname } = useLocation();

    return (
        <RequireAuth>
            <DataPage key={pathname} {...props} />
        </RequireAuth>
    );
}

function App() {
    return (
        <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="/" element={<Page api={() => '/api/app/dashboard'} component={Dashboard} />} />
            <Route path="/dashboard" element={<Navigate to="/" replace />} />
            <Route path="/jobs" element={<Page api={(_, search) => `/api/app/jobs${search}`} component={JobsIndex} />} />
            <Route path="/jobs/:id" element={<Page api={({ id }) => `/api/app/jobs/${id}`} component={JobShow} />} />
            <Route path="/applications" element={<Page api={() => '/api/app/applications'} component={ApplicationBoard} />} />
            <Route path="/applications/:id" element={<Page api={({ id }) => `/api/app/applications/${id}`} component={ApplicationShow} />} />
            <Route path="/resumes" element={<Page api={() => '/api/app/resumes'} component={ResumesIndex} />} />
            <Route path="/resumes/:id" element={<Page api={({ id }) => `/api/app/resumes/${id}`} component={ResumeShow} />} />
            <Route path="/prep" element={<Page api={(_, search) => `/api/app/prep${search}`} component={PrepIndex} />} />
            <Route path="/settings" element={<Page api={() => '/api/app/settings'} component={SettingsIndex} />} />
            <Route
                path="/profile"
                element={
                    <RequireAuth>
                        <Profile />
                    </RequireAuth>
                }
            />
            <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
    );
}

createRoot(document.getElementById('app')!).render(
    <StrictMode>
        <BrowserRouter>
            <AuthProvider>
                <FlashProvider>
                    <NavigationBridge />
                    <App />
                </FlashProvider>
            </AuthProvider>
        </BrowserRouter>
    </StrictMode>,
);
