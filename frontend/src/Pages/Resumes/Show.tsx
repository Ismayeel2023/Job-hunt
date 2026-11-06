import ResumeChanges, { BaseResume } from '@/Components/ResumeChanges';
import { Chip, EmptyState, Panel, StatusPill } from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDate } from '@/lib/format';
import { PageProps } from '@/types';
import { ResumeVersion, Status } from '@/types/models';
import { Head, Link , route } from '@/lib/router';
import { ArrowLeft, ArrowUpRight, Download } from 'lucide-react';
import { useState } from 'react';

type Props = {
    version: ResumeVersion & {
        created_at: string;
        job: { id: number; title: string; url: string; company: { name: string } } | null;
        applications: { id: number; status: Status }[];
    };
    baseResume: BaseResume | null;
};

type Tab = 'changes' | 'pdf';

export default function Show({ version, baseResume }: PageProps<Props>) {
    const [tab, setTab] = useState<Tab>(version.is_base ? 'pdf' : 'changes');
    const tabs: [Tab, string][] = version.is_base ? [['pdf', 'PDF']] : [['changes', 'What changed'], ['pdf', 'PDF']];

    return (
        <AuthenticatedLayout>
            <Head title={version.label} />

            <Link
                href={route('resumes.index')}
                className="mb-4 inline-flex items-center gap-1 text-xs font-medium text-muted hover:text-ink"
            >
                <ArrowLeft className="h-3.5 w-3.5" /> Resumes
            </Link>

            <header className="mb-6">
                <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">{version.label}</h1>
                <p className="mt-1.5 text-sm text-muted">
                    {version.is_base ? 'Base resume' : version.job ? `${version.job.company.name}, ${version.job.title}` : 'Not tied to a job'}
                    {`, created ${formatDate(version.created_at)}`}
                    {version.job && (
                        <>
                            {' · '}
                            <a
                                href={version.job.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-0.5 font-medium text-accent hover:underline"
                            >
                                Posting <ArrowUpRight className="h-3.5 w-3.5" />
                            </a>
                        </>
                    )}
                </p>
                {version.applications.length > 0 && (
                    <div className="mt-2 flex flex-wrap gap-3 text-xs">
                        {version.applications.map((application) => (
                            <Link
                                key={application.id}
                                href={route('applications.show', application.id)}
                                className="inline-flex items-center gap-1.5 hover:underline"
                            >
                                Application <StatusPill status={application.status} />
                            </Link>
                        ))}
                    </div>
                )}
            </header>

            {(version.keywords_covered?.length ?? 0) > 0 && (
                <div className="mb-6">
                    <h2 className="mb-1.5 font-sans text-xs font-medium text-muted">Keywords covered</h2>
                    <div className="flex flex-wrap gap-1.5">
                        {version.keywords_covered!.map((keyword) => (
                            <Chip key={keyword}>{keyword}</Chip>
                        ))}
                    </div>
                </div>
            )}

            <div role="tablist" aria-label="Resume version" className="mb-4 flex gap-1 overflow-x-auto overflow-y-hidden border-b border-line">
                {tabs.map(([key, label]) => (
                    <button
                        key={key}
                        type="button"
                        role="tab"
                        aria-selected={tab === key}
                        onClick={() => setTab(key)}
                        className={`whitespace-nowrap border-b-2 px-3 py-2 text-sm transition ${
                            tab === key ? 'border-accent font-medium text-ink' : 'border-transparent text-muted hover:text-ink'
                        }`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            <div role="tabpanel">
                {tab === 'changes' && <ResumeChanges resume={version} base={baseResume} />}
                {tab === 'pdf' && (
                    <Panel
                        title="PDF"
                        action={
                            version.pdf_path && (
                                <div className="flex gap-2">
                                    <a href={route('resumes.pdf', version.id)} target="_blank" rel="noopener" className="btn-quiet whitespace-nowrap">
                                        <ArrowUpRight className="h-4 w-4" /> Open
                                    </a>
                                    <a href={route('resumes.pdf', { resumeVersion: version.id, download: 1 })} className="btn-quiet whitespace-nowrap">
                                        <Download className="h-4 w-4" /> Download
                                    </a>
                                </div>
                            )
                        }
                    >
                        {version.pdf_path ? (
                            <iframe
                                src={route('resumes.pdf', version.id)}
                                title={`PDF of ${version.label}`}
                                className="h-[75vh] w-full rounded-lg border border-line bg-white"
                            />
                        ) : (
                            <EmptyState title="No PDF stored">This version only has its LaTeX source.</EmptyState>
                        )}
                    </Panel>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
