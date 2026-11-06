import GuestLayout from '@/Layouts/GuestLayout';
import { errorMessage } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { Head } from '@/lib/router';
import { FormEvent, useState } from 'react';
import { Navigate, useNavigate, useSearchParams } from 'react-router-dom';

export default function Login() {
    const { user, checking, login } = useAuth();
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [remember, setRemember] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    // Only go back to a page inside this app: "/jobs", but never "//site" or "/\site".
    const requested = searchParams.get('next') ?? '';
    const next = /^\/(?![/\\])/.test(requested) ? requested : '/';

    if (!checking && user) {
        return <Navigate to={next} replace />;
    }

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        setBusy(true);
        setError(null);

        try {
            await login(email, password, remember);
            navigate(next, { replace: true });
        } catch (failure) {
            const errors = (failure as { response?: { data?: { errors?: Record<string, string[]> } } }).response?.data?.errors;
            setError(errors?.email?.[0] ?? errorMessage(failure));
        } finally {
            setBusy(false);
        }
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            <form onSubmit={submit} className="space-y-4">
                <label className="block">
                    <span className="mb-1 block text-sm font-medium">Email</span>
                    <input
                        type="email"
                        className="field"
                        autoComplete="username"
                        autoFocus
                        required
                        value={email}
                        onChange={(event) => setEmail(event.target.value)}
                    />
                </label>

                <label className="block">
                    <span className="mb-1 block text-sm font-medium">Password</span>
                    <input
                        type="password"
                        className="field"
                        autoComplete="current-password"
                        required
                        value={password}
                        onChange={(event) => setPassword(event.target.value)}
                    />
                </label>

                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={remember}
                        onChange={(event) => setRemember(event.target.checked)}
                        className="rounded border-line bg-surface text-accent focus:ring-accent"
                    />
                    Remember me
                </label>

                {error && (
                    <p role="alert" className="text-sm text-low-fg">
                        {error}
                    </p>
                )}

                <button type="submit" disabled={busy} className="btn-primary w-full disabled:opacity-50">
                    {busy ? 'Logging in…' : 'Log in'}
                </button>

                <p className="text-xs text-muted">The login is JOBHUNT_USER_EMAIL and JOBHUNT_USER_PASSWORD in backend/.env.</p>
            </form>
        </GuestLayout>
    );
}
