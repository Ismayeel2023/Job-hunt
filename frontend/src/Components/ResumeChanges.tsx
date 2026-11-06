import ResumeDiff from '@/Components/ResumeDiff';
import { EmptyState, Panel } from '@/Components/ui';
import { ResumeVersion } from '@/types/models';

export interface BaseResume {
    id: number;
    label: string;
    tex_source: string;
}

function ChangeList({ title, items }: { title: string; items: string[] | null | undefined }) {
    if (!items?.length) {
        return null;
    }

    return (
        <div>
            <h3 className="mb-1.5 font-sans text-xs font-medium text-muted">{title}</h3>
            <ul className="list-disc space-y-1 pl-4 text-sm">
                {items.map((item) => (
                    <li key={item}>{item}</li>
                ))}
            </ul>
        </div>
    );
}

/**
 * The change summary as plain bullets, then a side-by-side LaTeX diff against the base resume.
 */
export default function ResumeChanges({ resume, base }: { resume: ResumeVersion; base: BaseResume | null }) {
    const summary = resume.changes_summary;

    return (
        <div className="space-y-4">
            {summary && (summary.added?.length || summary.removed?.length || summary.reordered?.length) ? (
                <Panel title="Summary of changes">
                    <div className="grid gap-4 md:grid-cols-3">
                        <ChangeList title="Added" items={summary.added} />
                        <ChangeList title="Removed" items={summary.removed} />
                        <ChangeList title="Reordered" items={summary.reordered} />
                    </div>
                </Panel>
            ) : null}

            <Panel title="LaTeX differences" hint="Removed text is marked − on the left, added text + on the right">
                {base ? (
                    <ResumeDiff
                        base={base.tex_source}
                        version={resume.tex_source}
                        baseLabel={base.label}
                        versionLabel={resume.label}
                    />
                ) : (
                    <EmptyState title="No base resume to compare with" />
                )}
            </Panel>
        </div>
    );
}
