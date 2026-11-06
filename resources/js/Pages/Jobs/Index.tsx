import { BandSwatch, CompanyMark, EmptyState, PageHeader, ScoreBadge, StatusPill } from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { BAND_LABELS, formatDate, PLATFORM_LABELS, ScoreBand, STATUS_LABELS } from '@/lib/format';
import { PageProps } from '@/types';
import { Job, Platform, Status } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpRight, ChevronRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type JobRow = Job & { applications: { id: number }[] };

interface Filters {
    platform?: string;
    status?: string;
    band?: string;
    location?: string;
    direction?: 'asc' | 'desc';
}

type Props = {
    jobs: {
        data: JobRow[];
        total: number;
        from: number | null;
        to: number | null;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: Filters;
}

function applyFilters(filters: Filters) {
    const query = Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value),
    );

    router.get(route('jobs.index'), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function FilterSelect({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value?: string;
    options: [string, string][];
    onChange: (value: string) => void;
}) {
    return (
        <label className="block min-w-0">
            <span className="mb-1 block text-xs text-muted">{label}</span>
            <select
                className="field pr-8"
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
            >
                <option value="">All</option>
                {options.map(([key, text]) => (
                    <option key={key} value={key}>
                        {text}
                    </option>
                ))}
            </select>
        </label>
    );
}

/**
 * Clicking anywhere on a job row opens its page, except on the row's own links.
 */
function openJob(event: React.MouseEvent, jobId: number) {
    if (!(event.target as HTMLElement).closest('a, button')) {
        router.visit(route('jobs.show', jobId));
    }
}

function JobLink({ job }: { job: JobRow }) {
    const application = job.applications[0];
    const canApply = !application && job.status === 'queued';

    return (
        <div className="flex items-center justify-end gap-3 whitespace-nowrap text-xs font-medium">
            <Link
                href={route('jobs.show', job.id)}
                className={`inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 ${
                    canApply ? 'bg-accent text-accent-ink hover:opacity-90' : 'bg-accent-soft text-ink hover:opacity-80'
                }`}
            >
                {canApply ? 'Open and apply' : 'Open'} <ChevronRight className="h-3.5 w-3.5" />
            </Link>
            {application && (
                <Link
                    href={route('applications.show', application.id)}
                    className="text-accent hover:underline"
                >
                    Application
                </Link>
            )}
            <a
                href={job.url}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-0.5 text-muted hover:text-ink"
            >
                Posting <ArrowUpRight className="h-3.5 w-3.5" />
            </a>
        </div>
    );
}

export default function Index({ jobs, filters }: PageProps<Props>) {
    const [location, setLocation] = useState(filters.location ?? '');
    const firstRender = useRef(true);
    const direction = filters.direction ?? 'desc';
    const hasFilters = Boolean(filters.platform || filters.status || filters.band || filters.location);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timer = setTimeout(() => applyFilters({ ...filters, location }), 300);

        return () => clearTimeout(timer);
        // Only a change to the text box should schedule a request.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [location]);

    const toggleSort = () =>
        applyFilters({ ...filters, direction: direction === 'desc' ? 'asc' : 'desc' });

    return (
        <AuthenticatedLayout>
            <Head title="Jobs" />

            <PageHeader title="Jobs" description={`${jobs.total} ${jobs.total === 1 ? 'job' : 'jobs'}`} />

            <div className="mb-3 grid grid-cols-2 items-end gap-3 lg:grid-cols-[repeat(4,minmax(0,1fr))_auto]">
                <FilterSelect
                    label="Platform"
                    value={filters.platform}
                    options={Object.entries(PLATFORM_LABELS) as [Platform, string][]}
                    onChange={(platform) => applyFilters({ ...filters, platform })}
                />
                <FilterSelect
                    label="Match score"
                    value={filters.band}
                    options={[
                        ...(Object.entries(BAND_LABELS) as [ScoreBand, string][]),
                        ['unscored', 'Not scored'],
                    ]}
                    onChange={(band) => applyFilters({ ...filters, band })}
                />
                <FilterSelect
                    label="Status"
                    value={filters.status}
                    options={Object.entries(STATUS_LABELS) as [Status, string][]}
                    onChange={(status) => applyFilters({ ...filters, status })}
                />
                <label className="block min-w-0">
                    <span className="mb-1 block text-xs text-muted">Location</span>
                    <input
                        type="search"
                        className="field"
                        placeholder="Any city"
                        value={location}
                        onChange={(event) => setLocation(event.target.value)}
                    />
                </label>
                <button
                    type="button"
                    disabled={!hasFilters}
                    onClick={() => {
                        setLocation('');
                        router.get(route('jobs.index'), filters.direction ? { direction: filters.direction } : {}, {
                            preserveState: true,
                            replace: true,
                        });
                    }}
                    className="btn-quiet col-span-2 disabled:opacity-40 lg:col-span-1"
                >
                    Clear
                </button>
            </div>

            <ul className="mb-4 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                {(Object.entries(BAND_LABELS) as [ScoreBand, string][]).map(([band, label]) => (
                    <li key={band} className="flex items-center gap-1.5">
                        <BandSwatch band={band} /> {label}
                    </li>
                ))}
            </ul>

            {jobs.data.length === 0 ? (
                <EmptyState title="No jobs here">
                    {hasFilters
                        ? 'Try removing a filter.'
                        : 'Jobs appear here once the agent adds them through the API.'}
                </EmptyState>
            ) : (
                <>
                    <div className="hidden overflow-hidden rounded-2xl border border-line bg-surface md:block">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-line text-left text-xs text-muted">
                                    <th className="px-4 py-3 font-medium">Role</th>
                                    <th className="px-3 py-3 font-medium">Platform</th>
                                    <th className="px-3 py-3 font-medium">Location</th>
                                    <th
                                        className="px-3 py-3 font-medium"
                                        aria-sort={direction === 'desc' ? 'descending' : 'ascending'}
                                    >
                                        <button
                                            type="button"
                                            onClick={toggleSort}
                                            className="inline-flex items-center gap-1 font-medium text-ink"
                                        >
                                            Match score
                                            {direction === 'desc' ? (
                                                <ArrowDown className="h-3.5 w-3.5" />
                                            ) : (
                                                <ArrowUp className="h-3.5 w-3.5" />
                                            )}
                                        </button>
                                    </th>
                                    <th className="px-3 py-3 font-medium">Status</th>
                                    <th className="px-3 py-3 font-medium">Found</th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {jobs.data.map((job) => (
                                    <tr key={job.id} onClick={(event) => openJob(event, job.id)} className="cursor-pointer transition hover:bg-sunken/50">
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <CompanyMark name={job.company.name} />
                                                <div className="min-w-0">
                                                    <Link href={route('jobs.show', job.id)} className="font-medium hover:underline">{job.title}</Link>
                                                    <div className="text-xs text-muted">
                                                        {[job.company.name, job.experience_range, job.salary_text]
                                                            .filter(Boolean)
                                                            .join(', ')}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-3 py-3 text-muted">{PLATFORM_LABELS[job.platform]}</td>
                                        <td className="px-3 py-3 text-muted">{job.location}</td>
                                        <td className="px-3 py-3">
                                            <ScoreBadge score={job.match_score} />
                                        </td>
                                        <td className="px-3 py-3">
                                            <StatusPill status={job.status} />
                                        </td>
                                        <td className="whitespace-nowrap px-3 py-3 text-xs text-muted">
                                            {formatDate(job.found_at)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <JobLink job={job} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="md:hidden">
                        <button
                            type="button"
                            onClick={toggleSort}
                            className="btn-quiet mb-3 w-full"
                        >
                            Match score, {direction === 'desc' ? 'highest first' : 'lowest first'}
                        </button>
                        <ul className="space-y-2">
                            {jobs.data.map((job) => (
                                <li key={job.id} onClick={(event) => openJob(event, job.id)} className="cursor-pointer rounded-2xl border border-line bg-surface p-3.5">
                                    <div className="flex items-start gap-3">
                                        <div className="min-w-0 flex-1">
                                            <Link href={route('jobs.show', job.id)} className="block text-sm font-medium hover:underline">{job.title}</Link>
                                            <div className="mt-0.5 text-xs text-muted">
                                                {[job.company.name, job.location, PLATFORM_LABELS[job.platform]]
                                                    .filter(Boolean)
                                                    .join(', ')}
                                            </div>
                                        </div>
                                        <ScoreBadge score={job.match_score} />
                                    </div>
                                    <div className="mt-3 flex items-center justify-between gap-3">
                                        <StatusPill status={job.status} />
                                        <JobLink job={job} />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>

                    {(jobs.prev_page_url || jobs.next_page_url) && (
                        <div className="mt-4 flex items-center justify-between text-sm">
                            <span className="text-xs text-muted">
                                {jobs.from}-{jobs.to} of {jobs.total}
                            </span>
                            <div className="flex gap-2">
                                {jobs.prev_page_url && (
                                    <Link href={jobs.prev_page_url} preserveState className="btn-quiet">
                                        Previous
                                    </Link>
                                )}
                                {jobs.next_page_url && (
                                    <Link href={jobs.next_page_url} preserveState className="btn-quiet">
                                        Next
                                    </Link>
                                )}
                            </div>
                        </div>
                    )}
                </>
            )}
        </AuthenticatedLayout>
    );
}
