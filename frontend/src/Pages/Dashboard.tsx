import { Chip, CompanyMark, EmptyState, Panel, PageHeader, ScoreBadge, StatusPill } from '@/Components/ui';
import SalaryAdvice, { SalaryAdviceData } from '@/Components/SalaryAdvice';
import WeekStrip, { Week } from '@/Components/WeekStrip';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDateTime, isPast, PLATFORM_LABELS, STATUS_LABELS } from '@/lib/format';
import { PageProps } from '@/types';
import { Platform, Status } from '@/types/models';
import { Head, Link , route } from '@/lib/router';
import { Ban, CalendarCheck } from 'lucide-react';

type Props = {
    totals: Record<Status, number>;
    thisWeek: { count: number; last_week: number };
    response: { rate: number | null; responded: number; total: number };
    stages: { status: Status; count: number }[];
    nextSteps: {
        id: number;
        title: string;
        company: string;
        next_step: string | null;
        next_step_at: string;
        status: Status;
    }[];
    topJobs: {
        id: number;
        title: string;
        url: string;
        location: string | null;
        platform: Platform;
        match_score: number;
        matched_skills: string[] | null;
        gaps: string[] | null;
        company: { name: string };
    }[];
    week: Week;
    salaries: ({ id: number; title: string; company: string; status: Status; salary_text: string | null } & SalaryAdviceData)[];
}

const TOTAL_GROUPS: { label: string; statuses: Status[] }[] = [
    { label: 'Not applied yet', statuses: ['found', 'queued'] },
    { label: 'In progress', statuses: ['applied', 'online_test', 'interview', 'hr', 'offer'] },
    { label: 'Closed', statuses: ['rejected', 'withdrawn', 'ghosted'] },
];

function Stat({
    label,
    value,
    children,
}: {
    label: string;
    value: string;
    children: React.ReactNode;
}) {
    return (
        <Panel>
            <p className="text-xs font-medium text-muted">{label}</p>
            <p className="mt-1 font-display text-5xl font-semibold tabular-nums tracking-tight">
                {value}
            </p>
            <p className="mt-2 text-xs text-muted">{children}</p>
        </Panel>
    );
}

function WeekState({ week }: { week: Week }) {
    if (week.is_locked) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-lg bg-low-bg px-2.5 py-1.5 text-xs font-medium text-low-fg">
                <Ban className="h-3.5 w-3.5" /> Locked window is on now
            </span>
        );
    }

    if (week.is_apply_day) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-lg bg-accent-soft px-2.5 py-1.5 text-xs font-medium">
                <CalendarCheck className="h-3.5 w-3.5" /> Today is an apply day
            </span>
        );
    }

    return null;
}

export default function Dashboard({
    totals,
    thisWeek,
    response,
    stages,
    nextSteps,
    topJobs,
    week,
    salaries,
}: PageProps<Props>) {
    const maxStage = Math.max(1, ...stages.map((stage) => stage.count));

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />

            <PageHeader
                title="This week"
                description="All times are in IST."
                actions={<WeekState week={week} />}
            />

            <Panel className="mb-4">
                <WeekStrip week={week} />
            </Panel>

            <div className="grid gap-4 lg:grid-cols-12">
                <div className="grid gap-4 sm:grid-cols-2 lg:col-span-5 lg:grid-cols-1 xl:grid-cols-2">
                    <Stat label="Applications this week" value={String(thisWeek.count)}>
                        {thisWeek.last_week} last week
                    </Stat>
                    <Stat
                        label="Response rate"
                        value={response.rate === null ? 'n/a' : `${response.rate}%`}
                    >
                        {response.responded} of {response.total} applications got any
                        reply, including rejections
                    </Stat>
                </div>

                <Panel
                    title="Stage reached"
                    hint="How many applications got at least this far"
                    className="lg:col-span-7"
                >
                    <ol className="space-y-2.5">
                        {stages.map((stage) => (
                            <li
                                key={stage.status}
                                className="grid grid-cols-[84px_minmax(0,1fr)_28px] items-center gap-3 text-sm"
                            >
                                <span className="text-muted">{STATUS_LABELS[stage.status]}</span>
                                <span
                                    className="h-2.5 overflow-hidden rounded-sm bg-sunken"
                                    title={`${STATUS_LABELS[stage.status]}: ${stage.count}`}
                                >
                                    <span
                                        className="block h-full rounded-sm bg-accent"
                                        style={{ width: `${(stage.count / maxStage) * 100}%` }}
                                    />
                                </span>
                                <span className="text-right font-mono text-xs font-semibold tabular-nums">
                                    {stage.count}
                                </span>
                            </li>
                        ))}
                    </ol>
                </Panel>

                <Panel title="All jobs by status" className="lg:col-span-12">
                    <div className="grid gap-5 sm:grid-cols-[2fr_5fr_3fr]">
                        {TOTAL_GROUPS.map((group) => (
                            <div key={group.label}>
                                <p className="mb-2 text-xs text-muted">{group.label}</p>
                                <dl
                                    className="grid gap-2"
                                    style={{
                                        gridTemplateColumns: `repeat(${group.statuses.length}, minmax(0, 1fr))`,
                                    }}
                                >
                                    {group.statuses.map((status) => (
                                        <div key={status} className="border-t border-line pt-2">
                                            <dd
                                                className={`font-display text-2xl font-semibold tabular-nums ${totals[status] === 0 ? 'text-muted' : ''}`}
                                            >
                                                {totals[status]}
                                            </dd>
                                            <dt className="mt-0.5 truncate text-xs text-muted">
                                                {STATUS_LABELS[status]}
                                            </dt>
                                        </div>
                                    ))}
                                </dl>
                            </div>
                        ))}
                    </div>
                </Panel>

                <Panel title="Next steps" className="lg:col-span-5">
                    {nextSteps.length === 0 ? (
                        <EmptyState title="Nothing scheduled">
                            Next steps on open applications will appear here.
                        </EmptyState>
                    ) : (
                        <ol className="divide-y divide-line">
                            {nextSteps.map((step) => (
                                <li key={step.id}>
                                    <Link
                                        href={route('applications.show', step.id)}
                                        className="block py-2.5 transition hover:opacity-70"
                                    >
                                        <div className="flex items-baseline justify-between gap-3">
                                            <span className="min-w-0 truncate text-sm font-medium">
                                                {step.next_step ?? 'Next step'}
                                            </span>
                                            <span
                                                className={`shrink-0 text-xs ${isPast(step.next_step_at) ? 'font-medium text-low-fg' : 'text-muted'}`}
                                            >
                                                {isPast(step.next_step_at) && 'Overdue, '}
                                                {formatDateTime(step.next_step_at)}
                                            </span>
                                        </div>
                                        <div className="mt-0.5 flex items-center justify-between gap-3 text-xs text-muted">
                                            <span className="truncate">
                                                {step.company}, {step.title}
                                            </span>
                                            <StatusPill status={step.status} />
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ol>
                    )}
                </Panel>

                <Panel
                    title="Top matches not applied to"
                    action={
                        <Link
                            href={route('jobs.index')}
                            className="whitespace-nowrap text-xs font-medium text-accent hover:underline"
                        >
                            All jobs
                        </Link>
                    }
                    className="lg:col-span-7"
                >
                    {topJobs.length === 0 ? (
                        <EmptyState title="No scored jobs waiting">
                            Jobs the agent scores will appear here until you apply.
                        </EmptyState>
                    ) : (
                        <ol className="divide-y divide-line">
                            {topJobs.map((job) => (
                                <li key={job.id} className="flex items-center gap-3 py-2.5">
                                    <CompanyMark name={job.company.name} />
                                    <div className="min-w-0 flex-1">
                                        <Link
                                            href={route('jobs.show', job.id)}
                                            className="block truncate text-sm font-medium hover:underline"
                                        >
                                            {job.title}
                                        </Link>
                                        <p className="truncate text-xs text-muted">
                                            {job.company.name}, {job.location ?? 'Location not listed'},{' '}
                                            {PLATFORM_LABELS[job.platform]}
                                        </p>
                                    </div>
                                    {(job.gaps?.length ?? 0) > 0 && (
                                        <span className="hidden xl:block">
                                            <Chip>
                                                {job.gaps!.length} {job.gaps!.length === 1 ? 'gap' : 'gaps'}
                                            </Chip>
                                        </span>
                                    )}
                                    <ScoreBadge score={job.match_score} />
                                </li>
                            ))}
                        </ol>
                    )}
                </Panel>

                <Panel
                    title="What to ask for"
                    hint="Suggested salary for each company you have queued or applied to, from the posted range and your figures in Settings"
                    action={
                        <Link href={route('settings.index')} className="whitespace-nowrap text-xs font-medium text-accent hover:underline">
                            Your salary details
                        </Link>
                    }
                    className="lg:col-span-12"
                >
                    {salaries.length === 0 ? (
                        <EmptyState title="Nothing queued or applied yet">
                            Suggestions appear here once you queue or apply to jobs.
                        </EmptyState>
                    ) : (
                        <ul className="divide-y divide-line">
                            {salaries.map((item) => (
                                <li key={item.id} className="grid gap-2 py-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,2fr)] sm:items-center">
                                    <div className="min-w-0">
                                        <Link href={route('jobs.show', item.id)} className="block truncate text-sm font-medium hover:underline">
                                            {item.company}
                                        </Link>
                                        <p className="truncate text-xs text-muted">{item.title}</p>
                                    </div>
                                    <SalaryAdvice salary={item} compact />
                                    <p className="text-xs text-muted">
                                        {item.suggested ? `Based on ${item.basis}.` : item.basis}
                                        {item.notes.map((note) => (
                                            <span key={note} className="block font-medium text-fair-fg">
                                                {note}
                                            </span>
                                        ))}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>
        </AuthenticatedLayout>
    );
}
