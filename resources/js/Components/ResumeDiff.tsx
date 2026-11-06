import { diffLines, diffWordsWithSpace } from 'diff';
import { Fragment, useMemo, useState } from 'react';

interface Cell {
    number: number;
    text: string;
}

interface Row {
    left: Cell | null;
    right: Cell | null;
    changed: boolean;
}

const CONTEXT_LINES = 3;

function toLines(value: string): string[] {
    const lines = value.split('\n');

    if (lines[lines.length - 1] === '') {
        lines.pop();
    }

    return lines;
}

function buildRows(base: string, version: string): Row[] {
    const parts = diffLines(base.replace(/\r\n/g, '\n'), version.replace(/\r\n/g, '\n'));
    const rows: Row[] = [];
    let leftNumber = 1;
    let rightNumber = 1;

    for (let index = 0; index < parts.length; index++) {
        const part = parts[index];
        const lines = toLines(part.value);

        if (!part.added && !part.removed) {
            for (const text of lines) {
                rows.push({
                    left: { number: leftNumber++, text },
                    right: { number: rightNumber++, text },
                    changed: false,
                });
            }
            continue;
        }

        // A removal followed by an addition is a replacement, so the lines sit side by side.
        const next = parts[index + 1];
        const removed = part.removed ? lines : [];
        const added = part.added ? lines : next?.added ? toLines(next.value) : [];

        if (part.removed && next?.added) {
            index++;
        }

        for (let line = 0; line < Math.max(removed.length, added.length); line++) {
            rows.push({
                left: line < removed.length ? { number: leftNumber++, text: removed[line] } : null,
                right: line < added.length ? { number: rightNumber++, text: added[line] } : null,
                changed: true,
            });
        }
    }

    return rows;
}

function Words({ row, side }: { row: Row; side: 'left' | 'right' }) {
    const cell = side === 'left' ? row.left : row.right;

    if (!cell) {
        return null;
    }

    if (!row.changed || !row.left || !row.right) {
        return <>{cell.text || ' '}</>;
    }

    return (
        <>
            {diffWordsWithSpace(row.left.text, row.right.text).map((part, index) => {
                if ((side === 'left' && part.added) || (side === 'right' && part.removed)) {
                    return null;
                }

                if (part.added || part.removed) {
                    return (
                        <mark
                            key={index}
                            className={`rounded-sm text-inherit ${side === 'left' ? 'bg-low-fg/25' : 'bg-strong-fg/25'}`}
                        >
                            {part.value}
                        </mark>
                    );
                }

                return <Fragment key={index}>{part.value}</Fragment>;
            })}
        </>
    );
}

function DiffCell({ row, side }: { row: Row; side: 'left' | 'right' }) {
    const cell = side === 'left' ? row.left : row.right;

    if (!cell) {
        return <div className="col-span-3 bg-sunken/60" />;
    }

    const tone = !row.changed
        ? ''
        : side === 'left'
          ? 'bg-low-bg/60 text-low-fg'
          : 'bg-strong-bg/60 text-strong-fg';

    return (
        <>
            <div className={`select-none px-2 py-0.5 text-right text-muted ${tone}`}>{cell.number}</div>
            <div className={`select-none py-0.5 text-center ${tone}`}>
                {row.changed ? (side === 'left' ? '−' : '+') : ''}
            </div>
            <div className={`whitespace-pre-wrap break-words py-0.5 pr-3 ${tone}`}>
                <Words row={row} side={side} />
            </div>
        </>
    );
}

export default function ResumeDiff({
    base,
    version,
    baseLabel,
    versionLabel,
}: {
    base: string;
    version: string;
    baseLabel: string;
    versionLabel: string;
}) {
    const rows = useMemo(() => buildRows(base, version), [base, version]);
    const [expanded, setExpanded] = useState<Set<number>>(new Set());
    const changes = rows.filter((row) => row.changed).length;

    const items: ({ type: 'row'; row: Row } | { type: 'gap'; start: number; count: number })[] = [];
    let index = 0;

    while (index < rows.length) {
        if (rows[index].changed) {
            items.push({ type: 'row', row: rows[index++] });
            continue;
        }

        let end = index;
        while (end < rows.length && !rows[end].changed) {
            end++;
        }

        const lead = index === 0 ? 0 : CONTEXT_LINES;
        const trail = end === rows.length ? 0 : CONTEXT_LINES;

        if (end - index > lead + trail + 1 && !expanded.has(index)) {
            rows.slice(index, index + lead).forEach((row) => items.push({ type: 'row', row }));
            items.push({ type: 'gap', start: index, count: end - index - lead - trail });
            rows.slice(end - trail, end).forEach((row) => items.push({ type: 'row', row }));
        } else {
            rows.slice(index, end).forEach((row) => items.push({ type: 'row', row }));
        }

        index = end;
    }

    if (changes === 0) {
        return <p className="text-sm text-muted">The LaTeX is identical to the base resume.</p>;
    }

    return (
        <div className="overflow-x-auto rounded-xl border border-line">
            <div className="min-w-[720px] font-mono text-xs leading-5">
                <div className="grid grid-cols-2 border-b border-line bg-sunken font-sans text-xs font-medium">
                    <div className="px-3 py-2">{baseLabel}</div>
                    <div className="border-l border-line px-3 py-2">{versionLabel}</div>
                </div>
                {items.map((item, position) =>
                    item.type === 'gap' ? (
                        <button
                            key={`gap-${item.start}`}
                            type="button"
                            onClick={() => setExpanded((current) => new Set(current).add(item.start))}
                            className="block w-full border-y border-line bg-sunken/60 py-1 text-center font-sans text-xs text-muted transition hover:text-ink"
                        >
                            Show {item.count} unchanged {item.count === 1 ? 'line' : 'lines'}
                        </button>
                    ) : (
                        <div key={position} className="grid grid-cols-2">
                            <div className="grid grid-cols-[40px_16px_minmax(0,1fr)]">
                                <DiffCell row={item.row} side="left" />
                            </div>
                            <div className="grid grid-cols-[40px_16px_minmax(0,1fr)] border-l border-line">
                                <DiffCell row={item.row} side="right" />
                            </div>
                        </div>
                    ),
                )}
            </div>
        </div>
    );
}
