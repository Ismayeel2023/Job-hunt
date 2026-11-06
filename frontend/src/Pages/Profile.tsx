import { PageHeader, Panel } from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useAuth } from '@/lib/auth';
import { Head, useForm } from '@/lib/router';
import { useState } from 'react';

function Field({
    label,
    error,
    ...props
}: { label: string; error?: string } & React.InputHTMLAttributes<HTMLInputElement>) {
    return (
        <label className="block">
            <span className="mb-1 block text-xs text-muted">{label}</span>
            <input className="field" {...props} />
            {error && <span className="mt-1 block text-xs text-low-fg">{error}</span>}
        </label>
    );
}

function ProfileForm() {
    const { user, setUser } = useAuth();
    const [saved, setSaved] = useState(false);
    const form = useForm({ name: user?.name ?? '', email: user?.email ?? '' });

    return (
        <Panel title="Profile" hint="Your name and the email you log in with.">
            <form
                className="space-y-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    setSaved(false);
                    form.patch('/api/app/profile', {
                        onSuccess: (data) => {
                            setUser(data as typeof user);
                            setSaved(true);
                        },
                    });
                }}
            >
                <Field label="Name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} error={form.errors.name} required />
                <Field
                    label="Email"
                    type="email"
                    value={form.data.email}
                    onChange={(event) => form.setData('email', event.target.value)}
                    error={form.errors.email}
                    required
                />
                <div className="flex items-center gap-3">
                    <button type="submit" disabled={form.processing} className="btn-primary disabled:opacity-50">
                        Save
                    </button>
                    {saved && <span className="text-xs text-muted">Saved.</span>}
                </div>
            </form>
        </Panel>
    );
}

function PasswordForm() {
    const [saved, setSaved] = useState(false);
    const form = useForm({ current_password: '', password: '', password_confirmation: '' });

    return (
        <Panel title="Change password">
            <form
                className="space-y-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    setSaved(false);
                    form.put('/api/app/password', {
                        onSuccess: () => {
                            form.reset();
                            setSaved(true);
                        },
                    });
                }}
            >
                <Field
                    label="Current password"
                    type="password"
                    autoComplete="current-password"
                    value={form.data.current_password}
                    onChange={(event) => form.setData('current_password', event.target.value)}
                    error={form.errors.current_password}
                    required
                />
                <Field
                    label="New password"
                    type="password"
                    autoComplete="new-password"
                    value={form.data.password}
                    onChange={(event) => form.setData('password', event.target.value)}
                    error={form.errors.password}
                    required
                />
                <Field
                    label="Confirm new password"
                    type="password"
                    autoComplete="new-password"
                    value={form.data.password_confirmation}
                    onChange={(event) => form.setData('password_confirmation', event.target.value)}
                    required
                />
                <div className="flex items-center gap-3">
                    <button type="submit" disabled={form.processing} className="btn-primary disabled:opacity-50">
                        Change password
                    </button>
                    {saved && <span className="text-xs text-muted">Password changed.</span>}
                </div>
            </form>
        </Panel>
    );
}

export default function Profile() {
    return (
        <AuthenticatedLayout>
            <Head title="Profile" />
            <PageHeader title="Profile" />
            <div className="max-w-xl space-y-4">
                <ProfileForm />
                <PasswordForm />
            </div>
        </AuthenticatedLayout>
    );
}
