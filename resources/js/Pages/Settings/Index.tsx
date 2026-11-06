import { EmptyState, PageHeader, Panel } from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDateTime, titleCase } from '@/lib/format';
import { PageProps } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Lock, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { useState } from 'react';

type Candidate = {
    current_ctc_lpa: number | null;
    expected_ctc_lpa: number | null;
    notice_period: string | null;
    relocate: string | null;
};

type Props = {
    candidate: Candidate;
    savedAnswers: { question: string; answer: string }[];
    gmail: {
        configured: boolean;
        redirect_uri: string;
        accounts: {
            id: number;
            email: string;
            needs_reconnect: boolean;
            last_synced_at: string | null;
            last_error: string | null;
            emails: number;
            unprocessed: number;
        }[];
        other_accounts: {
            account: string;
            emails: number;
            unprocessed: number;
            last_received_at: string | null;
        }[];
    };
    agentToken: { configured: boolean; masked: string | null; endpoint: string };
    rules: {
        apply_days: string[];
        locked_window: { day: string; start: string; end: string };
        timezone: string;
    };
};

function Rule({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="grid gap-1 py-3 first:pt-0 last:pb-0 sm:grid-cols-[160px_minmax(0,1fr)]">
            <dt className="text-sm text-muted">{label}</dt>
            <dd className="text-sm font-medium">{children}</dd>
        </div>
    );
}

function CandidatePanel({ candidate, savedAnswers }: { candidate: Candidate; savedAnswers: Props['savedAnswers'] }) {
    const form = useForm({
        current_ctc_lpa: candidate.current_ctc_lpa?.toString() ?? '',
        expected_ctc_lpa: candidate.expected_ctc_lpa?.toString() ?? '',
        notice_period: candidate.notice_period ?? '',
        relocate: candidate.relocate ?? '',
    });

    const field = (name: keyof typeof form.data, label: string, props: React.InputHTMLAttributes<HTMLInputElement> = {}) => (
        <label className="block">
            <span className="mb-1 block text-xs text-muted">{label}</span>
            <input
                className="field"
                value={form.data[name]}
                onChange={(event) => form.setData(name, event.target.value)}
                {...props}
            />
            {form.errors[name] && <span className="mt-1 block text-xs text-low-fg">{form.errors[name]}</span>}
        </label>
    );

    return (
        <Panel
            title="Your details for applications"
            hint="Claude uses these when a form asks, and the salary suggestions start from your current CTC. They stay on this computer."
        >
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.patch(route('candidate.update'), { preserveScroll: true });
                }}
            >
                <div className="grid gap-3 sm:grid-cols-2">
                    {field('current_ctc_lpa', 'Current CTC (LPA)', { type: 'number', step: '0.1', min: '0', placeholder: 'For example 3.6' })}
                    {field('expected_ctc_lpa', 'Expected CTC (LPA)', { type: 'number', step: '0.1', min: '0', placeholder: 'For example 5' })}
                    {field('notice_period', 'Notice period', { placeholder: 'For example 30 days' })}
                    {field('relocate', 'Willing to relocate?', { placeholder: 'For example Yes, within South India' })}
                </div>
                <button type="submit" disabled={form.processing} className="btn-primary mt-4 disabled:opacity-50">
                    Save details
                </button>
            </form>

            {savedAnswers.length > 0 && (
                <div className="mt-5 border-t border-line pt-4">
                    <p className="mb-2 text-xs font-medium text-muted">Answers you gave on earlier applications, reused when the same question comes up</p>
                    <ul className="divide-y divide-line">
                        {savedAnswers.map((saved, index) => (
                            <li key={saved.question} className="flex items-start justify-between gap-3 py-2 text-sm">
                                <div className="min-w-0">
                                    <p className="text-muted">{saved.question}</p>
                                    <p className="font-medium">{saved.answer}</p>
                                </div>
                                <button
                                    type="button"
                                    aria-label={`Forget the answer to ${saved.question}`}
                                    onClick={() => router.delete(route('candidate.answers.destroy', index), { preserveScroll: true })}
                                    className="rounded-lg p-1.5 text-muted hover:bg-sunken hover:text-low-fg"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </Panel>
    );
}

function GmailPanel({ gmail }: { gmail: Props['gmail'] }) {
    const [syncing, setSyncing] = useState(false);

    const syncNow = () =>
        router.post(route('gmail.sync'), {}, {
            preserveScroll: true,
            onStart: () => setSyncing(true),
            onFinish: () => setSyncing(false),
        });

    const disconnect = (id: number, email: string) => {
        if (window.confirm(`Disconnect ${email}? Email already saved from it is kept.`)) {
            router.delete(route('gmail.destroy', id), { preserveScroll: true });
        }
    };

    return (
        <Panel
            title="Gmail"
            hint="Read-only access. Only job-related email from the last 30 days is fetched, and only its sender, subject, snippet and date are saved."
        >
            {!gmail.configured ? (
                <div className="rounded-xl border border-dashed border-line p-4 text-sm">
                    <p className="font-medium">Gmail is not set up yet</p>
                    <p className="mt-1 text-muted">
                        Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to .env. Follow docs/GMAIL_SETUP.md, and use this
                        redirect URI:
                    </p>
                    <code className="mt-2 block break-all rounded-lg bg-sunken px-2.5 py-1.5 font-mono text-xs">
                        {gmail.redirect_uri}
                    </code>
                </div>
            ) : (
                <>
                    {gmail.accounts.length === 0 ? (
                        <EmptyState title="No Gmail accounts connected">
                            Connect an account to pull in replies from recruiters and job platforms.
                        </EmptyState>
                    ) : (
                        <ul className="divide-y divide-line">
                            {gmail.accounts.map((account) => (
                                <li key={account.id} className="flex flex-wrap items-start justify-between gap-x-4 gap-y-2 py-3 first:pt-0">
                                    <div className="min-w-0">
                                        <p className="break-all text-sm font-medium">{account.email}</p>
                                        <p className="mt-0.5 text-xs text-muted">
                                            {account.emails} {account.emails === 1 ? 'email' : 'emails'} saved, {account.unprocessed} unprocessed
                                            {account.last_synced_at
                                                ? `, last synced ${formatDateTime(account.last_synced_at)}`
                                                : ', not synced yet'}
                                        </p>
                                        {account.needs_reconnect ? (
                                            <p role="alert" className="mt-1 text-xs font-medium text-low-fg">
                                                Needs reconnecting. Google stopped accepting this connection.
                                            </p>
                                        ) : (
                                            account.last_error && (
                                                <p role="alert" className="mt-1 text-xs text-low-fg">
                                                    Last sync failed: {account.last_error}
                                                </p>
                                            )
                                        )}
                                    </div>
                                    <div className="flex gap-3 text-xs font-medium">
                                        {account.needs_reconnect && (
                                            <a href={route('gmail.connect')} className="text-accent hover:underline">
                                                Reconnect
                                            </a>
                                        )}
                                        <button
                                            type="button"
                                            onClick={() => disconnect(account.id, account.email)}
                                            className="text-muted hover:text-ink"
                                        >
                                            Disconnect
                                        </button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}

                    <div className="mt-4 flex flex-wrap gap-2">
                        <a href={route('gmail.connect')} className="btn-quiet">
                            <Plus className="h-4 w-4" /> Connect a Gmail account
                        </a>
                        <button
                            type="button"
                            onClick={syncNow}
                            disabled={syncing || gmail.accounts.length === 0}
                            className="btn-primary disabled:opacity-40"
                        >
                            <RefreshCw className={`h-4 w-4 ${syncing ? 'animate-spin' : ''}`} />
                            {syncing ? 'Syncing…' : 'Sync now'}
                        </button>
                    </div>
                    <p className="mt-2 text-xs text-muted">
                        Also syncs every hour while the app is running with npm run start.
                    </p>
                </>
            )}

            {gmail.other_accounts.length > 0 && (
                <div className="mt-5 border-t border-line pt-4">
                    <p className="mb-2 text-xs font-medium text-muted">Saved email from accounts that are not connected</p>
                    <ul className="space-y-1">
                        {gmail.other_accounts.map((account) => (
                            <li key={account.account} className="flex flex-wrap justify-between gap-x-4 text-xs">
                                <span className="break-all">{account.account}</span>
                                <span className="text-muted">
                                    {account.emails} {account.emails === 1 ? 'email' : 'emails'}, {account.unprocessed} unprocessed
                                    {account.last_received_at && `, latest ${formatDateTime(account.last_received_at)}`}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </Panel>
    );
}

export default function Index({ candidate, savedAnswers, gmail, agentToken, rules }: PageProps<Props>) {
    const status = usePage<PageProps>().props.flash?.status;

    return (
        <AuthenticatedLayout>
            <Head title="Settings" />

            <PageHeader title="Settings" />

            <div className="max-w-3xl space-y-4">
                {status && (
                    <p role="status" className="rounded-lg bg-accent-soft px-3 py-2 text-sm">
                        {status}
                    </p>
                )}

                <CandidatePanel candidate={candidate} savedAnswers={savedAnswers} />

                <GmailPanel gmail={gmail} />

                <Panel title="Agent API" hint="Claude Code uses this token to write into JobHunt. Change it in .env.">
                    <dl className="divide-y divide-line">
                        <Rule label="Token">
                            {agentToken.configured ? (
                                <code className="break-all font-mono text-xs">{agentToken.masked}</code>
                            ) : (
                                <span className="text-low-fg">Not set. Add AGENT_TOKEN to .env.</span>
                            )}
                        </Rule>
                        <Rule label="Endpoint">
                            <code className="break-all font-mono text-xs">{agentToken.endpoint}</code>
                        </Rule>
                    </dl>
                </Panel>

                <Panel
                    title="Apply-day rules"
                    hint="Read-only. Change them in config/jobhunt.php."
                    action={<Lock aria-label="Read-only" className="h-4 w-4 text-muted" />}
                >
                    <dl className="divide-y divide-line">
                        <Rule label="Apply days">{rules.apply_days.map(titleCase).join(' and ')}</Rule>
                        <Rule label="Locked window">
                            {titleCase(rules.locked_window.day)} {rules.locked_window.start}-{rules.locked_window.end} IST
                        </Rule>
                        <Rule label="Timezone">{rules.timezone}</Rule>
                    </dl>
                </Panel>
            </div>
        </AuthenticatedLayout>
    );
}
