import { Chip, EmptyState, PageHeader, StatusPill } from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDate } from '@/lib/format';
import { PageProps } from '@/types';
import { Status } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { Download, FileText } from 'lucide-react';

interface Version {
    id: number;
    label: string;
    is_base: boolean;
    has_pdf: boolean;
    keywords_covered: string[];
    created_at: string;
    job_title: string | null;
    company: string | null;
    applications: { id: number; status: Status; company: string }[];
}

export default function Index({ versions }: PageProps<{ versions: Version[] }>) {
    return (
        <AuthenticatedLayout>
            <Head title="Resumes" />

            <PageHeader
                title="Resumes"
                description={`${versions.length} ${versions.length === 1 ? 'version' : 'versions'}, with where each one went`}
            />

            {versions.length === 0 ? (
                <EmptyState title="No resumes yet" />
            ) : (
                <ul className="space-y-2">
                    {versions.map((version) => (
                        <li
                            key={version.id}
                            className={`grid gap-3 rounded-2xl border bg-surface p-4 md:grid-cols-[minmax(0,5fr)_minmax(0,5fr)_auto] md:items-center ${
                                version.is_base ? 'border-accent' : 'border-line'
                            }`}
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h2 className="font-sans text-sm font-semibold">
                                        <Link href={route('resumes.show', version.id)} className="hover:underline">
                                            {version.label}
                                        </Link>
                                    </h2>
                                    {version.is_base && (
                                        <span className="rounded-md bg-accent-soft px-1.5 py-0.5 text-[11px] font-medium">
                                            Base
                                        </span>
                                    )}
                                </div>
                                <p className="mt-0.5 text-xs text-muted">
                                    {version.company ? `${version.company}, ${version.job_title}` : 'Not tied to a job'}
                                    {`, created ${formatDate(version.created_at)}`}
                                </p>
                                {version.applications.length > 0 && (
                                    <div className="mt-1.5 flex flex-wrap gap-x-3 gap-y-1">
                                        {version.applications.map((application) => (
                                            <Link
                                                key={application.id}
                                                href={route('applications.show', application.id)}
                                                className="inline-flex items-center gap-1.5 text-xs hover:underline"
                                            >
                                                {application.company}
                                                <span className="text-muted">
                                                    <StatusPill status={application.status} />
                                                </span>
                                            </Link>
                                        ))}
                                    </div>
                                )}
                            </div>

                            <div className="flex flex-wrap gap-1.5">
                                {version.keywords_covered.length === 0 ? (
                                    <span className="text-xs text-muted">No keywords recorded</span>
                                ) : (
                                    version.keywords_covered.map((keyword) => <Chip key={keyword}>{keyword}</Chip>)
                                )}
                            </div>

                            {version.has_pdf ? (
                                <div className="flex gap-3 whitespace-nowrap text-xs font-medium">
                                    <a
                                        href={route('resumes.pdf', version.id)}
                                        target="_blank"
                                        rel="noopener"
                                        className="inline-flex items-center gap-1 text-accent hover:underline"
                                    >
                                        <FileText className="h-3.5 w-3.5" /> View
                                    </a>
                                    <a
                                        href={route('resumes.pdf', { resumeVersion: version.id, download: 1 })}
                                        className="inline-flex items-center gap-1 text-muted hover:text-ink"
                                    >
                                        <Download className="h-3.5 w-3.5" /> Download
                                    </a>
                                </div>
                            ) : (
                                <span className="text-xs text-muted">No PDF</span>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </AuthenticatedLayout>
    );
}
