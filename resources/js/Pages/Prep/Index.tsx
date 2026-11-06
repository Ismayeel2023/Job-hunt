import PracticeMode from '@/Components/PracticeMode';
import { Chip, EmptyState, PageHeader, Panel } from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ROUND_LABELS, titleCase } from '@/lib/format';
import { PageProps } from '@/types';
import { InterviewQuestion, InterviewRound, RoundType } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight, Play } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface PrepCompany {
    id: number;
    name: string;
    industry: string | null;
    location: string | null;
    rounds_count: number;
    questions_count: number;
    practiced_count: number;
}

type Props = {
    companies: PrepCompany[];
    company: PrepCompany | null;
    rounds: InterviewRound[];
    questions: InterviewQuestion[];
}

const ROUND_ORDER: RoundType[] = ['aptitude', 'coding', 'technical', 'group_discussion', 'managerial', 'hr'];

type QuestionChange = Partial<Pick<InterviewQuestion, 'practiced' | 'my_notes'>>;

/**
 * answer_based_on is free-form JSON, so show any strings it holds.
 */
function basedOn(value: unknown): string[] {
    if (typeof value === 'string') {
        return value ? [value] : [];
    }

    if (Array.isArray(value)) {
        return value.flatMap(basedOn);
    }

    if (value && typeof value === 'object') {
        return Object.values(value).flatMap(basedOn);
    }

    return [];
}

function QuestionCard({
    question,
    onSave,
}: {
    question: InterviewQuestion;
    onSave: (question: InterviewQuestion, change: QuestionChange) => Promise<void>;
}) {
    const [notes, setNotes] = useState(question.my_notes ?? '');
    const [notesState, setNotesState] = useState<'idle' | 'saved' | 'failed'>('idle');
    const sources = basedOn(question.answer_based_on);

    const saveNotes = () => {
        if (notes !== (question.my_notes ?? '')) {
            onSave(question, { my_notes: notes }).then(
                () => setNotesState('saved'),
                () => setNotesState('failed'),
            );
        }
    };

    return (
        <li className="rounded-xl border border-line bg-surface p-4">
            <div className="flex items-start gap-3">
                <input
                    id={`practiced-${question.id}`}
                    type="checkbox"
                    checked={question.practiced}
                    aria-label={`Mark practiced: ${question.question}`}
                    onChange={(event) => {
                        onSave(question, { practiced: event.target.checked }).catch(() => {});
                    }}
                    className="mt-0.5 h-[18px] w-[18px] shrink-0 rounded border-line bg-surface text-accent focus:ring-accent"
                />
                <div className="min-w-0 flex-1">
                    <p className={`text-sm font-medium leading-snug ${question.practiced ? 'text-muted' : ''}`}>
                        {question.question}
                    </p>
                    <div className="mt-1.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs text-muted">
                        {question.difficulty && <Chip>{titleCase(question.difficulty)}</Chip>}
                        {question.year_reported && <span>Reported {question.year_reported}</span>}
                        {question.source_url && (
                            <a
                                href={question.source_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-0.5 hover:text-ink hover:underline"
                            >
                                {question.source_name ?? 'Source'} <ArrowUpRight className="h-3 w-3" />
                            </a>
                        )}
                    </div>

                    <details className="group mt-3">
                        <summary className="cursor-pointer text-xs font-medium text-accent">
                            Suggested answer
                        </summary>
                        <p className="mt-2 max-w-prose whitespace-pre-wrap text-sm leading-6">
                            {question.suggested_answer || 'No suggested answer saved yet.'}
                        </p>
                        {sources.length > 0 && (
                            <p className="mt-2 text-xs text-muted">Based on: {sources.join(', ')}</p>
                        )}
                    </details>

                    <label className="mt-3 block">
                        <span className="mb-1 flex items-center justify-between text-xs text-muted">
                            My notes
                            {notesState === 'saved' && <span role="status">Saved</span>}
                            {notesState === 'failed' && (
                                <span role="alert" className="text-low-fg">
                                    Not saved. Edit the note to try again.
                                </span>
                            )}
                        </span>
                        <textarea
                            rows={2}
                            className="field"
                            value={notes}
                            onChange={(event) => {
                                setNotes(event.target.value);
                                setNotesState('idle');
                            }}
                            onBlur={saveNotes}
                        />
                    </label>
                </div>
            </div>
        </li>
    );
}

export default function Index({ companies, company, rounds, questions: loadedQuestions }: PageProps<Props>) {
    const [practicing, setPracticing] = useState(false);
    const [questions, setQuestions] = useState(loadedQuestions);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => setQuestions(loadedQuestions), [loadedQuestions]);

    // Saved with axios because any Inertia visit, including clicking a nav link, cancels one in flight.
    const save = (question: InterviewQuestion, change: QuestionChange) => {
        const apply = (values: QuestionChange) =>
            setQuestions((current) =>
                current.map((item) => (item.id === question.id ? { ...item, ...values } : item)),
            );
        const previous = Object.fromEntries(
            Object.keys(change).map((key) => [key, question[key as keyof QuestionChange]]),
        );

        setError(null);
        apply(change);

        return window.axios
            .patch(route('prep.questions.update', question.id), change)
            .then(() => undefined)
            .catch((failure) => {
                apply(previous);
                setError('That change could not be saved. Please try again.');
                throw failure;
            });
    };

    const groups = useMemo(() => {
        const order = [...ROUND_ORDER, null];

        return order
            .map((type) => {
                const inRound = questions.filter((question) => question.round_type === type);
                const topics = [...new Set(inRound.map((question) => question.topic ?? 'General'))];

                return {
                    type,
                    label: type ? ROUND_LABELS[type] : 'Any round',
                    count: inRound.length,
                    topics: topics.map((topic) => ({
                        topic,
                        questions: inRound.filter((question) => (question.topic ?? 'General') === topic),
                    })),
                };
            })
            .filter((group) => group.count > 0);
    }, [questions]);

    return (
        <AuthenticatedLayout>
            <Head title="Interview prep" />

            <PageHeader title="Interview prep" description="Rounds and questions reported for each company." />

            {error && (
                <p role="alert" className="mb-3 rounded-lg bg-low-bg px-3 py-2 text-sm text-low-fg">
                    {error}
                </p>
            )}

            {!company ? (
                <EmptyState title="Nothing to prepare yet">
                    Rounds and questions appear here once the agent adds them for a company.
                </EmptyState>
            ) : (
                <div className="grid items-start gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
                    <nav aria-label="Companies" className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:-mx-6 sm:px-6 lg:sticky lg:top-6 lg:mx-0 lg:flex-col lg:overflow-visible lg:px-0">
                        {companies.map((item) => (
                            <Link
                                key={item.id}
                                href={route('prep.index', { company: item.id })}
                                preserveScroll
                                aria-current={item.id === company.id ? 'page' : undefined}
                                className={`shrink-0 rounded-xl border px-3 py-2.5 text-sm transition ${
                                    item.id === company.id
                                        ? 'border-ink bg-surface font-medium'
                                        : 'border-transparent text-muted hover:bg-sunken hover:text-ink'
                                }`}
                            >
                                <span className="block whitespace-nowrap lg:whitespace-normal">{item.name}</span>
                                <span className="block whitespace-nowrap text-xs font-normal text-muted">
                                    {item.id === company.id ? questions.filter((question) => question.practiced).length : item.practiced_count} of {item.questions_count} practiced
                                </span>
                            </Link>
                        ))}
                    </nav>

                    <div className="min-w-0 space-y-6" key={company.id}>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="text-2xl font-semibold tracking-tight">{company.name}</h2>
                                <p className="text-xs text-muted">
                                    {[company.industry, company.location].filter(Boolean).join(', ')}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setPracticing(true)}
                                disabled={questions.length === 0}
                                className="btn-primary disabled:opacity-40"
                            >
                                <Play className="h-4 w-4" /> Practice mode
                            </button>
                        </div>

                        <Panel title="Rounds">
                            {rounds.length === 0 ? (
                                <p className="text-sm text-muted">No rounds recorded for this company.</p>
                            ) : (
                                <ol className="divide-y divide-line">
                                    {rounds.map((round, index) => (
                                        <li key={round.id} className="grid grid-cols-[28px_minmax(0,1fr)] gap-2 py-3 first:pt-0 last:pb-0">
                                            <span className="font-display text-lg font-semibold leading-tight text-muted">
                                                {index + 1}
                                            </span>
                                            <div>
                                                <div className="flex flex-wrap items-baseline gap-x-2.5 gap-y-1">
                                                    <h3 className="font-sans text-sm font-medium">{round.round_name}</h3>
                                                    <span className="text-xs text-muted">
                                                        {[ROUND_LABELS[round.round_type], round.typical_duration].filter(Boolean).join(', ')}
                                                    </span>
                                                    {round.source_url && (
                                                        <a
                                                            href={round.source_url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="inline-flex items-center gap-0.5 text-xs text-muted hover:text-ink hover:underline"
                                                        >
                                                            Source <ArrowUpRight className="h-3 w-3" />
                                                        </a>
                                                    )}
                                                </div>
                                                {round.description && (
                                                    <p className="mt-0.5 text-sm text-muted">{round.description}</p>
                                                )}
                                            </div>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </Panel>

                        {groups.length === 0 ? (
                            <EmptyState title="No questions for this company yet" />
                        ) : (
                            groups.map((group) => (
                                <section key={group.type ?? 'any'}>
                                    <h2 className="mb-3 flex items-baseline gap-2 text-xl font-semibold tracking-tight">
                                        {group.label}
                                        <span className="font-mono text-xs font-normal text-muted">{group.count}</span>
                                    </h2>
                                    <div className="space-y-4">
                                        {group.topics.map(({ topic, questions: topicQuestions }) => (
                                            <div key={topic}>
                                                <h3 className="mb-2 font-sans text-xs font-medium text-muted">{topic}</h3>
                                                <ul className="space-y-2">
                                                    {topicQuestions.map((question) => (
                                                        <QuestionCard key={question.id} question={question} onSave={save} />
                                                    ))}
                                                </ul>
                                            </div>
                                        ))}
                                    </div>
                                </section>
                            ))
                        )}
                    </div>
                </div>
            )}

            {practicing && company && (
                <PracticeMode
                    company={company.name}
                    questions={questions}
                    onSetPracticed={(question, practiced) => {
                        save(question, { practiced }).catch(() => {});
                    }}
                    onClose={() => setPracticing(false)}
                />
            )}
        </AuthenticatedLayout>
    );
}
