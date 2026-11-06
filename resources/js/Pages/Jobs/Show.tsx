import ApplyPanel, { ApplyRun, Worker } from '@/Components/ApplyPanel';
import SalaryAdvice, { SalaryAdviceData } from '@/Components/SalaryAdvice';
import { Chip, CompanyMark, EmptyState, Panel, ScoreBadge, StatusPill } from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDate, PLATFORM_LABELS } from '@/lib/format';
import { PageProps } from '@/types';
import { Job, Status } from '@/types/models';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowUpRight } from 'lucide-react';

type Props = {
    job: Job;
    resumeVersions: { id: number; label: string; pdf_path: string | null; keywords_covered: string[] | null; created_at: string }[];
    application: { id: number; status: Status; applied_at: string | null } | null;
    salary: SalaryAdviceData;
    run: ApplyRun | null;
    worker: Worker;
    steps: Record<string, string>;
};

function Skills({ title, skills, tone }: { title: string; skills: string[] | null | undefined; tone?: 'yes' | 'no' }) {
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

export default function Show({ job, resumeVersions, application, salary, run, worker, steps }: PageProps<Props>) {
    const status = usePage<PageProps>().props.flash?.status;

    return (
        <AuthenticatedLayout>
            <Head title={`${job.title}, ${job.company.name}`} />

            <Link href={route('jobs.index')} className="mb-4 inline-flex items-center gap-1 text-xs font-medium text-muted hover:text-ink">
                <ArrowLeft className="h-3.5 w-3.5" /> Jobs
            </Link>

            <header className="mb-6 flex flex-wrap items-start gap-4">
                <CompanyMark name={job.company.name} />
                <div className="min-w-0 flex-1">
                    <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">{job.title}</h1>
                    <p className="mt-1.5 text-sm text-muted">
                        {[job.company.name, job.location, PLATFORM_LABELS[job.platform], job.experience_range].filter(Boolean).join(' · ')}
                        {' · '}
                        <a href={job.url} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-0.5 font-medium text-accent hover:underline">
                            Posting <ArrowUpRight className="h-3.5 w-3.5" />
                        </a>
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    <StatusPill status={job.status} />
                    <ScoreBadge score={job.match_score} />
                </div>
            </header>

            {status && (
                <p role="status" className="mb-4 rounded-lg bg-accent-soft px-3 py-2 text-sm">
                    {status}
                </p>
            )}

            <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
                <div className="min-w-0 space-y-4">
                    <ApplyPanel
                        jobId={job.id}
                        company={job.company.name}
                        run={run}
                        worker={worker}
                        steps={steps}
                        hasResume={resumeVersions.length > 0}
                        application={application}
                    />

                    <Panel title="Match score">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Skills title="Matched skills" skills={job.matched_skills} tone="yes" />
                            <Skills title="Gaps" skills={job.gaps} tone="no" />
                            <Skills title="Must have" skills={job.must_have_skills} />
                            <Skills title="Nice to have" skills={job.nice_to_have_skills} />
                        </div>
                    </Panel>

                    <Panel title="Job description">
                        <p className="max-w-prose whitespace-pre-wrap text-sm leading-6">{job.jd_text || 'No job description saved.'}</p>
                    </Panel>
                </div>

                <div className="space-y-4 lg:sticky lg:top-6">
                    <Panel title="Salary">
                        <SalaryAdvice salary={salary} />
                        <Link href={route('settings.index')} className="mt-2 inline-block text-xs font-medium text-accent hover:underline">
                            Your salary details
                        </Link>
                    </Panel>

                    <Panel title="Tailored resumes">
                        {resumeVersions.length === 0 ? (
                            <EmptyState title="None yet">
                                Run <code className="font-mono">/tailor {job.id}</code> in Claude Code.
                            </EmptyState>
                        ) : (
                            <ul className="divide-y divide-line">
                                {resumeVersions.map((version, index) => (
                                    <li key={version.id} className="py-2 first:pt-0 last:pb-0">
                                        <Link href={route('resumes.show', version.id)} className="text-sm font-medium hover:underline">
                                            {version.label}
                                        </Link>
                                        <p className="text-xs text-muted">
                                            Version {version.id}, {formatDate(version.created_at)}
                                            {index === 0 && ', used when applying'}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
