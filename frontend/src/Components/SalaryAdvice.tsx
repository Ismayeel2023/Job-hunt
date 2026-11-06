export interface SalaryAdviceData {
    posted: [number, number] | null;
    suggested: [number, number] | null;
    basis: string;
    notes: string[];
}

export function lpaRange(range: [number, number]): string {
    return range[0] === range[1] ? `₹${range[0]} LPA` : `₹${range[0]}–${range[1]} LPA`;
}

/**
 * The salary to ask for, with the posted range and how the suggestion was worked out.
 */
export default function SalaryAdvice({ salary, compact = false }: { salary: SalaryAdviceData; compact?: boolean }) {
    if (compact) {
        return (
            <div className="min-w-0">
                <p className="text-sm font-semibold tabular-nums">
                    {salary.suggested ? lpaRange(salary.suggested) : <span className="font-normal text-muted">No suggestion</span>}
                </p>
                <p className="text-xs text-muted">
                    {salary.posted ? `Posted ${lpaRange(salary.posted)}` : 'Salary not listed'}
                </p>
            </div>
        );
    }

    return (
        <div>
            <dl className="grid grid-cols-2 gap-3">
                <div>
                    <dt className="text-xs text-muted">Suggested ask</dt>
                    <dd className="mt-0.5 font-display text-2xl font-semibold tabular-nums">
                        {salary.suggested ? lpaRange(salary.suggested) : '—'}
                    </dd>
                </div>
                <div>
                    <dt className="text-xs text-muted">Posted range</dt>
                    <dd className="mt-0.5 font-display text-2xl font-semibold tabular-nums text-muted">
                        {salary.posted ? lpaRange(salary.posted) : 'Not listed'}
                    </dd>
                </div>
            </dl>
            <p className="mt-3 text-xs text-muted">
                {salary.suggested ? `Based on ${salary.basis}.` : salary.basis}
            </p>
            {salary.notes.map((note) => (
                <p key={note} className="mt-1 text-xs font-medium text-fair-fg">
                    {note}
                </p>
            ))}
            <p className="mt-2 text-xs text-muted">A suggestion only, from the posting and your own figures in Settings.</p>
        </div>
    );
}
