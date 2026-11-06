import { Panel } from '@/Components/ui';
import { formatDateTime } from '@/lib/format';
import { Link, router, usePoll } from '@inertiajs/react';
import { Check, CircleAlert, Hand, PartyPopper, Send, X } from 'lucide-react';
import { useState } from 'react';

export type ApplyRunStatus =
    | 'requested'
    | 'running'
    | 'needs_answer'
    | 'awaiting_confirmation'
    | 'submitting'
    | 'submitted'
    | 'failed'
    | 'refused'
    | 'cancelled';

export interface ApplyRun {
    id: number;
    status: ApplyRunStatus;
    step: string | null;
    log: { step: string | null; message: string | null; at: string }[] | null;
    questions: { id: string; question: string; answer: string | null }[] | null;
    summary: { label: string; value: string | null }[] | null;
    error: string | null;
    application_id: number | null;
    confirmed_at: string | null;
    created_at: string;
}

export interface Worker {
    online: boolean;
    seen_at: string | null;
}

const ACTIVE: ApplyRunStatus[] = ['requested', 'running', 'needs_answer', 'awaiting_confirmation', 'submitting'];

function ThinkingDots() {
    return (
        <span className="inline-flex items-center gap-1" aria-hidden>
            {[0, 150, 300].map((delay) => (
                <span
                    key={delay}
                    className="h-1.5 w-1.5 rounded-full bg-accent motion-safe:animate-bounce"
                    style={{ animationDelay: `${delay}ms` }}
                />
            ))}
        </span>
    );
}

function Steps({ steps, run }: { steps: Record<string, string>; run: ApplyRun }) {
    const keys = Object.keys(steps);
    const done = run.status === 'submitted';
    const current = done ? keys.length : Math.max(0, keys.indexOf(run.step ?? keys[0]));
    const progress = done ? 100 : ((current + 0.5) / keys.length) * 100;
    const stalled = ['failed', 'refused', 'cancelled'].includes(run.status);

    return (
        <div>
            <div className="relative mb-5 h-1.5 overflow-hidden rounded-full bg-sunken">
                <div
                    className={`h-full rounded-full transition-all duration-700 ease-out ${stalled ? 'bg-low-fg' : done ? 'bg-strong-fg' : 'bg-accent'}`}
                    style={{ width: `${progress}%` }}
                />
                {!stalled && !done && <div className="shimmer absolute inset-0 motion-reduce:hidden" />}
            </div>

            <ol className="space-y-2.5">
                {keys.map((key, index) => {
                    const isDone = index < current || done;
                    const isCurrent = index === current && !done;

                    return (
                        <li key={key} className="flex items-center gap-3 text-sm">
                            <span className="relative flex h-6 w-6 shrink-0 items-center justify-center">
                                {isCurrent && !stalled && (
                                    <span className="absolute inset-0 rounded-full bg-accent/40 motion-safe:animate-ping" />
                                )}
                                <span
                                    className={`relative flex h-6 w-6 items-center justify-center rounded-full border transition-all duration-500 ${
                                        isDone
                                            ? 'scale-100 border-transparent bg-strong-fg text-surface'
                                            : isCurrent
                                              ? stalled
                                                  ? 'border-low-fg bg-low-bg text-low-fg'
                                                  : 'border-accent bg-accent text-accent-ink'
                                              : 'border-line bg-surface text-muted'
                                    }`}
                                >
                                    {isDone ? (
                                        <Check className="h-3.5 w-3.5" />
                                    ) : key === 'review' && isCurrent ? (
                                        <Hand className="h-3.5 w-3.5" />
                                    ) : (
                                        <span className="text-[10px] font-semibold">{index + 1}</span>
                                    )}
                                </span>
                            </span>
                            <span className={isCurrent ? 'font-medium' : isDone ? '' : 'text-muted'}>{steps[key]}</span>
                            {isCurrent && !stalled && run.status !== 'needs_answer' && run.status !== 'awaiting_confirmation' && <ThinkingDots />}
                        </li>
                    );
                })}
            </ol>
        </div>
    );
}

function Questions({ run }: { run: ApplyRun }) {
    const open = (run.questions ?? []).filter((question) => !question.answer);
    const [answers, setAnswers] = useState<Record<string, string>>({});
    const [remember, setRemember] = useState(true);

    return (
        <form
            className="rounded-xl border border-accent bg-accent-soft p-4"
            onSubmit={(event) => {
                event.preventDefault();
                router.post(route('apply-runs.answer', run.id), { answers, remember }, { preserveScroll: true });
            }}
        >
            <p className="flex items-center gap-2 text-sm font-semibold">
                <CircleAlert className="h-4 w-4" /> Claude needs your answer
            </p>
            <p className="mt-0.5 text-xs text-muted">The form asks something only you should answer.</p>
            <div className="mt-3 space-y-3">
                {open.map((question) => (
                    <label key={question.id} className="block">
                        <span className="mb-1 block text-sm font-medium">{question.question}</span>
                        <input
                            className="field"
                            required
                            value={answers[question.id] ?? ''}
                            onChange={(event) => setAnswers({ ...answers, [question.id]: event.target.value })}
                        />
                    </label>
                ))}
            </div>
            <label className="mt-3 flex items-center gap-2 text-xs">
                <input
                    type="checkbox"
                    checked={remember}
                    onChange={(event) => setRemember(event.target.checked)}
                    className="rounded border-line bg-surface text-accent focus:ring-accent"
                />
                Remember these answers for next time
            </label>
            <button type="submit" className="btn-primary mt-3">
                <Send className="h-4 w-4" /> Send answers
            </button>
        </form>
    );
}

function Review({ run, company }: { run: ApplyRun; company: string }) {
    if (run.confirmed_at) {
        return (
            <p className="flex items-center gap-2 rounded-xl bg-strong-bg px-4 py-3 text-sm font-medium text-strong-fg">
                <Check className="h-4 w-4" /> You approved it. Claude is submitting <ThinkingDots />
            </p>
        );
    }

    return (
        <div className="rounded-xl border-2 border-accent p-4">
            <p className="text-sm font-semibold">Ready to submit to {company}</p>
            <p className="mt-0.5 text-xs text-muted">Check what will be sent. Nothing is submitted until you click Submit.</p>
            <dl className="mt-3 divide-y divide-line rounded-lg border border-line bg-surface">
                {(run.summary ?? []).map((item, index) => (
                    <div key={index} className="grid gap-1 px-3 py-2 text-sm sm:grid-cols-[180px_minmax(0,1fr)]">
                        <dt className="text-muted">{item.label}</dt>
                        <dd className="whitespace-pre-wrap break-words font-medium">{item.value || '—'}</dd>
                    </div>
                ))}
            </dl>
            <div className="mt-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    onClick={() => router.post(route('apply-runs.confirm', run.id), {}, { preserveScroll: true })}
                    className="btn-primary"
                >
                    <Send className="h-4 w-4" /> Submit to {company}
                </button>
                <button
                    type="button"
                    onClick={() => router.post(route('apply-runs.cancel', run.id), {}, { preserveScroll: true })}
                    className="btn-quiet"
                >
                    <X className="h-4 w-4" /> Cancel
                </button>
            </div>
        </div>
    );
}

export default function ApplyPanel({
    jobId,
    company,
    run,
    worker,
    steps,
    hasResume,
    application,
}: {
    jobId: number;
    company: string;
    run: ApplyRun | null;
    worker: Worker;
    steps: Record<string, string>;
    hasResume: boolean;
    application: { id: number; applied_at: string | null } | null;
}) {
    usePoll(2000, { only: ['run', 'worker', 'application'] });

    const active = run !== null && ACTIVE.includes(run.status);
    const start = () => router.post(route('jobs.apply', jobId), {}, { preserveScroll: true });
    const latest = run?.log?.length ? run.log[run.log.length - 1] : null;

    const workerLine = (
        <p className="flex items-center gap-2 text-xs text-muted">
            <span className={`h-2 w-2 rounded-full ${worker.online ? 'bg-strong-fg motion-safe:animate-pulse' : 'bg-muted/50'}`} />
            {worker.online ? 'Claude worker is online' : 'Claude worker is offline. In Claude Code, run /apply-worker'}
        </p>
    );

    if (run?.status === 'submitted') {
        return (
            <Panel
                title={
                    <span className="flex items-center gap-2">
                        <PartyPopper className="h-4 w-4 text-strong-fg motion-safe:animate-bounce" /> Submitted to {company}
                    </span>
                }
            >
                <div className="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
                    <Steps steps={steps} run={run} />
                    <div className="space-y-2 text-sm">
                        <p>Every step is done.</p>
                        {latest?.message && <p className="text-xs text-muted">{latest.message}</p>}
                        {application && (
                            <Link href={route('applications.show', application.id)} className="inline-block font-medium text-accent hover:underline">
                                Open the application
                            </Link>
                        )}
                    </div>
                </div>
            </Panel>
        );
    }

    if (application && !active) {
        return (
            <Panel title="Applied">
                <p className="flex items-center gap-2 text-sm">
                    <PartyPopper className="h-4 w-4 text-strong-fg" />
                    Applied {formatDateTime(application.applied_at)}.{' '}
                    <Link href={route('applications.show', application.id)} className="font-medium text-accent hover:underline">
                        Open the application
                    </Link>
                </p>
            </Panel>
        );
    }

    if (!active) {
        return (
            <Panel title="Apply with Claude" action={workerLine}>
                {run && (
                    <div className="mb-4 rounded-lg bg-sunken px-3 py-2 text-sm">
                        <p className="font-medium">
                            Last attempt {run.status === 'cancelled' ? 'was cancelled' : run.status === 'refused' ? 'was not allowed' : 'stopped'}
                        </p>
                        {(run.error || latest?.message) && <p className="mt-0.5 text-xs text-muted">{run.error || latest?.message}</p>}
                    </div>
                )}
                {hasResume ? (
                    <>
                        <p className="text-sm text-muted">
                            Claude opens the posting in your browser, fills the form, uploads your tailored resume and stops for
                            your OK before submitting. You can watch every step here.
                        </p>
                        <button type="button" onClick={start} className="btn-primary mt-4">
                            <Send className="h-4 w-4" /> {run ? 'Try again' : 'Apply with Claude'}
                        </button>
                    </>
                ) : (
                    <p className="text-sm text-muted">
                        Tailor a resume first: run <code className="font-mono text-xs">/tailor {jobId}</code> in Claude Code.
                    </p>
                )}
            </Panel>
        );
    }

    return (
        <Panel
            title={
                <span className="flex items-center gap-2">
                    Applying to {company}
                    {run.status !== 'needs_answer' && run.status !== 'awaiting_confirmation' && <ThinkingDots />}
                </span>
            }
            action={workerLine}
        >
            <div className="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
                <Steps steps={steps} run={run} />

                <div className="min-w-0 space-y-4">
                    {run.status === 'requested' && (
                        <p className="text-sm text-muted">
                            {worker.online ? 'Waiting for Claude to pick this up…' : 'Waiting for the Claude worker. In Claude Code, run /apply-worker.'}
                        </p>
                    )}
                    {run.status === 'needs_answer' && <Questions key={run.questions?.length} run={run} />}
                    {run.status === 'awaiting_confirmation' && <Review run={run} company={company} />}

                    {run.log && run.log.length > 0 && (
                        <ol className="space-y-1.5 border-l border-line pl-3 text-xs" aria-live="polite">
                            {run.log
                                .filter((entry) => entry.message)
                                .slice(-6)
                                .map((entry, index, list) => (
                                    <li key={entry.at + index} className={index === list.length - 1 ? 'font-medium text-ink' : 'text-muted'}>
                                        {entry.message}
                                    </li>
                                ))}
                        </ol>
                    )}

                    <button
                        type="button"
                        onClick={() => router.post(route('apply-runs.cancel', run.id), {}, { preserveScroll: true })}
                        className="text-xs font-medium text-muted hover:text-low-fg"
                    >
                        Stop this application
                    </button>
                </div>
            </div>
        </Panel>
    );
}
