import { scoreBand, ScoreBand, STATUS_LABELS } from '@/lib/format';
import { Status } from '@/types/models';
import { PropsWithChildren, ReactNode } from 'react';

export function PageHeader({
    title,
    description,
    actions,
}: {
    title: string;
    description?: ReactNode;
    actions?: ReactNode;
}) {
    return (
        <header className="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div className="min-w-0">
                <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">
                    {title}
                </h1>
                {description && (
                    <p className="mt-1.5 max-w-2xl text-sm text-muted">
                        {description}
                    </p>
                )}
            </div>
            {actions && <div className="flex items-center gap-2">{actions}</div>}
        </header>
    );
}

export function Panel({
    title,
    hint,
    action,
    className = '',
    children,
}: PropsWithChildren<{
    title?: ReactNode;
    hint?: ReactNode;
    action?: ReactNode;
    className?: string;
}>) {
    return (
        <section
            className={`rounded-2xl border border-line bg-surface p-4 sm:p-5 ${className}`}
        >
            {(title || action) && (
                <div className="mb-3 flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h2 className="text-base font-semibold">{title}</h2>
                        {hint && (
                            <p className="mt-0.5 text-xs text-muted">{hint}</p>
                        )}
                    </div>
                    {action}
                </div>
            )}
            {children}
        </section>
    );
}

const BAND_CLASSES: Record<ScoreBand, string> = {
    strong: 'bg-strong-bg text-strong-fg',
    good: 'bg-good-bg text-good-fg',
    fair: 'bg-fair-bg text-fair-fg',
    low: 'bg-low-bg text-low-fg',
};

export function ScoreBadge({ score }: { score: number | null | undefined }) {
    if (score === null || score === undefined) {
        return (
            <span
                title="Not scored yet"
                className="inline-block min-w-8 rounded-md bg-sunken px-1.5 py-0.5 text-center font-mono text-xs text-muted"
            >
                n/a
            </span>
        );
    }

    return (
        <span
            title={`Match score: ${score} out of 100`}
            className={`inline-block min-w-8 rounded-md px-1.5 py-0.5 text-center font-mono text-xs font-semibold tabular-nums ${BAND_CLASSES[scoreBand(score)]}`}
        >
            {score}
        </span>
    );
}

export function BandSwatch({ band }: { band: ScoreBand }) {
    return (
        <span
            aria-hidden
            className={`inline-block h-2.5 w-2.5 rounded-sm ${BAND_CLASSES[band]}`}
        />
    );
}

const STATUS_DOTS: Record<Status, string> = {
    found: 'bg-muted/50',
    queued: 'bg-muted',
    applied: 'bg-accent/60',
    online_test: 'bg-accent/80',
    interview: 'bg-accent',
    hr: 'bg-accent',
    offer: 'bg-strong-fg',
    rejected: 'bg-low-fg',
    withdrawn: 'bg-muted/50',
    ghosted: 'bg-muted/50',
};

export function StatusPill({ status }: { status: Status }) {
    return (
        <span className="inline-flex items-center gap-1.5 whitespace-nowrap text-xs font-medium">
            <span
                aria-hidden
                className={`h-1.5 w-1.5 rounded-full ${STATUS_DOTS[status]}`}
            />
            {STATUS_LABELS[status]}
        </span>
    );
}

export function Chip({
    children,
    tone = 'plain',
}: PropsWithChildren<{ tone?: 'plain' | 'yes' | 'no' }>) {
    const tones = {
        plain: 'border-line text-muted',
        yes: 'border-transparent bg-strong-bg text-strong-fg',
        no: 'border-transparent bg-low-bg text-low-fg',
    };

    return (
        <span
            className={`inline-block rounded-md border px-1.5 py-0.5 text-xs ${tones[tone]}`}
        >
            {children}
        </span>
    );
}

export function CompanyMark({ name }: { name: string }) {
    const initials = name
        .split(/\s+/)
        .filter((word) => /^[a-z0-9]/i.test(word))
        .slice(0, 2)
        .map((word) => word[0].toUpperCase())
        .join('');

    return (
        <span
            aria-hidden
            className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sunken font-display text-xs font-semibold text-muted"
        >
            {initials}
        </span>
    );
}

export function EmptyState({
    title,
    children,
}: PropsWithChildren<{ title: string }>) {
    return (
        <div className="rounded-xl border border-dashed border-line px-4 py-8 text-center">
            <p className="text-sm font-medium">{title}</p>
            {children && (
                <p className="mx-auto mt-1 max-w-md text-xs text-muted">
                    {children}
                </p>
            )}
        </div>
    );
}
