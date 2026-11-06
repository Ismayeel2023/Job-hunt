import ResumeChanges from '@/Components/ResumeChanges';
import { Chip, EmptyState, Panel, ScoreBadge, StatusPill } from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDate, formatDateTime, PLATFORM_LABELS, STATUS_LABELS, titleCase } from '@/lib/format';
import { PageProps } from '@/types';
import { Job, ResumeVersion, Status, StatusEvent } from '@/types/models';
import { Head, Link, router , route } from '@/lib/router';
import { ArrowLeft, ArrowUpRight, Bot, Download, Hand, Mail, LucideIcon } from 'lucide-react';
import { useState } from 'react';

interface Application {
    id: number;
    status: Status;
    stage_reached: Status | null;
    applied_at: string | null;
    applied_via: string | null;
    cover_note: string | null;
    screening_answers: { question?: string; answer?: string }[] | null;
    next_step: string | null;
    next_step_at: string | null;
    job: Job;
    resume_version: ResumeVersion | null;
    status_events: StatusEvent[];
}

type Props = {
    application: Application;
    baseResume: { id: number; label: string; tex_source: string } | null;
}

type Tab = 'overview' | 'resume' | 'changes';

const TABS: [Tab, string][] = [
    ['overview', 'Job and match'],
    ['resume', 'Resume'],
    ['changes', 'What changed'],
];

const MOVE_OPTIONS: Status[] = ['applied', 'online_test', 'interview', 'hr', 'offer', 'rejected', 'withdrawn', 'ghosted'];

const SOURCES: Record<StatusEvent['source'], { label: string; icon: LucideIcon }> = {
    manual: { label: 'You', icon: Hand },
    gmail: { label: 'Gmail', icon: Mail },
    claude: { label: 'Claude', icon: Bot },
};

function Fact({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <dt className="text-xs text-muted">{label}</dt>
            <dd className="mt-0.5 text-sm font-medium">{children || 'Not listed'}</dd>
        </div>
    );
}

function SkillList({ title, skills, tone }: { title: string; skills: string[] | null | undefined; tone: 'yes' | 'no' }) {
    return (
        <div>
            <h3 className="mb-1.5 font-sans text-xs font-medium text-muted">{title}</h3>
            {skills?.length ? (
                <div className="flex flex-wrap gap-1.5">
                    {skills.map((skill) => (
                        <Chip key={skill} tone={tone}>
                            {skill}
                        </Chip>
                    ))}
                </div>
            ) : (
                <p className="text-sm text-muted">None recorded</p>
            )}
        </div>
    );
}

function Overview({ application }: { application: Application }) {
    const { job } = application;

    return (
        <div className="space-y-4">
            <Panel title="Match score">
                <div className="flex items-center gap-3">
                    <span className="scale-125 pl-1">
                        <ScoreBadge score={job.match_score} />
                    </span>
                    <span className="pl-1 text-sm text-muted">out of 100, based on the job description and your resume</span>
                </div>
                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                    <SkillList title="Matched skills" skills={job.matched_skills} tone="yes" />
                    <SkillList title="Gaps" skills={job.gaps} tone="no" />
                </div>
            </Panel>

            <Panel title="Job">
                <dl className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <Fact label="Platform">{PLATFORM_LABELS[job.platform]}</Fact>
                    <Fact label="Location">{job.location}</Fact>
                    <Fact label="Experience">{job.experience_range}</Fact>
                    <Fact label="Salary">{job.salary_text}</Fact>
                </dl>
                <div className="mt-4 grid gap-4 border-t border-line pt-4 sm:grid-cols-2">
                    <div>
                        <h3 className="mb-1.5 font-sans text-xs font-medium text-muted">Must have</h3>
                        <div className="flex flex-wrap gap-1.5">
                            {job.must_have_skills?.map((skill) => <Chip key={skill}>{skill}</Chip>)}
                        </div>
                    </div>
                    <div>
                        <h3 className="mb-1.5 font-sans text-xs font-medium text-muted">Nice to have</h3>
                        <div className="flex flex-wrap gap-1.5">
                            {job.nice_to_have_skills?.map((skill) => <Chip key={skill}>{skill}</Chip>)}
                        </div>
                    </div>
                </div>
            </Panel>

            <Panel title="Job description">
                <p className="max-w-prose whitespace-pre-wrap text-sm leading-6">
                    {job.jd_text || 'No job description saved.'}
                </p>
            </Panel>

            {(application.cover_note || application.screening_answers?.length) && (
                <Panel title="What you sent">
                    {application.cover_note && (
                        <p className="mb-4 max-w-prose whitespace-pre-wrap text-sm leading-6">{application.cover_note}</p>
                    )}
                    {application.screening_answers?.length ? (
                        <dl className="space-y-2">
                            {application.screening_answers.map((item, index) => (
                                <div key={index} className="text-sm">
                                    <dt className="text-xs text-muted">{item.question}</dt>
                                    <dd className="font-medium">{item.answer}</dd>
                                </div>
                            ))}
                        </dl>
                    ) : null}
                </Panel>
            )}
        </div>
    );
}

function ResumeTab({ resume }: { resume: ResumeVersion | null }) {
    if (!resume) {
        return (
            <EmptyState title="No resume recorded">
                This application does not say which resume version was used.
            </EmptyState>
        );
    }

    return (
        <Panel
            title={resume.label}
            action={
                resume.pdf_path && (
                    <div className="flex gap-2">
                        <a
                            href={route('resumes.pdf', resume.id)}
                            target="_blank"
                            rel="noopener"
                            className="btn-quiet whitespace-nowrap"
                        >
                            <ArrowUpRight className="h-4 w-4" /> Open
                        </a>
                        <a
                            href={route('resumes.pdf', { resumeVersion: resume.id, download: 1 })}
                            className="btn-quiet whitespace-nowrap"
                        >
                            <Download className="h-4 w-4" /> Download
                        </a>
                    </div>
                )
            }
        >
            {resume.pdf_path ? (
                <iframe
                    src={route('resumes.pdf', resume.id)}
                    title={`PDF of ${resume.label}`}
                    className="h-[75vh] w-full rounded-lg border border-line bg-white"
                />
            ) : (
                <EmptyState title="No PDF stored">This version only has its LaTeX source.</EmptyState>
            )}
            {(resume.keywords_covered?.length ?? 0) > 0 && (
                <div className="mt-4">
                    <h3 className="mb-1.5 font-sans text-xs font-medium text-muted">Keywords covered</h3>
                    <div className="flex flex-wrap gap-1.5">
                        {resume.keywords_covered!.map((keyword) => (
                            <Chip key={keyword}>{keyword}</Chip>
                        ))}
                    </div>
                </div>
            )}
        </Panel>
    );
}

function ChangesTab({ resume, base }: { resume: ResumeVersion | null; base: Props['baseResume'] }) {
    if (!resume) {
        return <EmptyState title="No resume recorded">There is nothing to compare.</EmptyState>;
    }

    if (resume.is_base) {
        return (
            <EmptyState title="Sent as the base resume">
                This application used the base resume without changes.
            </EmptyState>
        );
    }

    return <ResumeChanges resume={resume} base={base} />;
}

function Timeline({ events }: { events: StatusEvent[] }) {
    if (events.length === 0) {
        return <p className="text-sm text-muted">No status changes recorded yet.</p>;
    }

    return (
        <ol>
            {events.map((event, index) => {
                const source = SOURCES[event.source];

                return (
                    <li key={event.id} className="relative flex gap-3 pb-5 last:pb-0">
                        {index < events.length - 1 && (
                            <span aria-hidden className="absolute bottom-0 left-[13px] top-7 w-px bg-line" />
                        )}
                        <span
                            title={`Source: ${source.label}`}
                            className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-sunken text-muted"
                        >
                            <source.icon className="h-3.5 w-3.5" />
                        </span>
                        <div className="min-w-0 flex-1 pt-0.5">
                            <p className="text-sm font-medium">
                                {event.from_status ? `${STATUS_LABELS[event.from_status]} to ` : ''}
                                {STATUS_LABELS[event.to_status]}
                            </p>
                            <p className="text-xs text-muted">
                                {formatDateTime(event.happened_at)}, {source.label}
                            </p>
                            {event.source === 'gmail' && (event.email || event.evidence) ? (
                                <blockquote className="mt-2 rounded-lg border-l-2 border-accent bg-sunken/60 px-3 py-2 text-xs">
                                    {event.email ? (
                                        <>
                                            <p className="font-semibold">{event.email.subject}</p>
                                            <p className="mt-0.5 break-words text-muted">From {event.email.from}</p>
                                            <p className="mt-1.5 break-words leading-5">{event.email.snippet}</p>
                                        </>
                                    ) : (
                                        <p className="break-words leading-5">{event.evidence}</p>
                                    )}
                                </blockquote>
                            ) : (
                                event.evidence && <p className="mt-1 break-words text-xs">{event.evidence}</p>
                            )}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}

export default function Show({ application, baseResume }: PageProps<Props>) {
    const [tab, setTab] = useState<Tab>('overview');
    const { job } = application;

    const move = (status: Status) =>
        router.patch(route('applications.status', application.id), { status }, { preserveScroll: true, preserveState: true });

    return (
        <AuthenticatedLayout>
            <Head title={`${job.title}, ${job.company.name}`} />

            <Link
                href={route('applications.index')}
                className="mb-4 inline-flex items-center gap-1 text-xs font-medium text-muted hover:text-ink"
            >
                <ArrowLeft className="h-3.5 w-3.5" /> Applications
            </Link>

            <header className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0">
                    <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">{job.title}</h1>
                    <p className="mt-1.5 text-sm text-muted">
                        {job.company.name}
                        {application.applied_at && `, applied ${formatDate(application.applied_at)}`}
                        {application.applied_via && ` via ${application.applied_via}`}
                        {' · '}
                        <a
                            href={job.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-0.5 font-medium text-accent hover:underline"
                        >
                            Posting <ArrowUpRight className="h-3.5 w-3.5" />
                        </a>
                    </p>
                </div>

                <label className="block w-44">
                    <span className="mb-1 block text-xs text-muted">Status</span>
                    <select
                        className="field pr-8"
                        value={application.status}
                        onChange={(event) => move(event.target.value as Status)}
                    >
                        {MOVE_OPTIONS.map((status) => (
                            <option key={status} value={status}>
                                {STATUS_LABELS[status]}
                            </option>
                        ))}
                    </select>
                </label>
            </header>

            <dl className="mb-6 grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-line bg-line sm:grid-cols-4">
                <div className="bg-surface p-3.5">
                    <dt className="text-xs text-muted">Match score</dt>
                    <dd className="mt-1"><ScoreBadge score={job.match_score} /></dd>
                </div>
                <div className="bg-surface p-3.5">
                    <dt className="text-xs text-muted">Stage reached</dt>
                    <dd className="mt-1 text-sm font-medium">
                        {application.stage_reached ? STATUS_LABELS[application.stage_reached] : 'None'}
                    </dd>
                </div>
                <div className="bg-surface p-3.5">
                    <dt className="text-xs text-muted">Status now</dt>
                    <dd className="mt-1"><StatusPill status={application.status} /></dd>
                </div>
                <div className="bg-surface p-3.5">
                    <dt className="text-xs text-muted">Next step</dt>
                    <dd className="mt-1 text-sm font-medium">
                        {application.next_step ?? titleCase('none')}
                        {application.next_step_at && (
                            <span className="block text-xs font-normal text-muted">
                                {formatDateTime(application.next_step_at)}
                            </span>
                        )}
                    </dd>
                </div>
            </dl>

            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                <div className="min-w-0">
                    <div role="tablist" aria-label="Application details" className="mb-4 flex gap-1 overflow-x-auto overflow-y-hidden border-b border-line">
                        {TABS.map(([key, label]) => (
                            <button
                                key={key}
                                type="button"
                                role="tab"
                                aria-selected={tab === key}
                                onClick={() => setTab(key)}
                                className={`whitespace-nowrap border-b-2 px-3 py-2 text-sm transition ${
                                    tab === key
                                        ? 'border-accent font-medium text-ink'
                                        : 'border-transparent text-muted hover:text-ink'
                                }`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>

                    <div role="tabpanel">
                        {tab === 'overview' && <Overview application={application} />}
                        {tab === 'resume' && <ResumeTab resume={application.resume_version} />}
                        {tab === 'changes' && <ChangesTab resume={application.resume_version} base={baseResume} />}
                    </div>
                </div>

                <Panel title="Timeline" className="lg:sticky lg:top-6">
                    <Timeline events={application.status_events} />
                </Panel>
            </div>
        </AuthenticatedLayout>
    );
}
