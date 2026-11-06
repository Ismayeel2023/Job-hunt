import { Chip } from '@/Components/ui';
import { ROUND_LABELS, titleCase } from '@/lib/format';
import { InterviewQuestion } from '@/types/models';
import { ArrowLeft, ArrowRight, Check, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export default function PracticeMode({
    company,
    questions,
    onSetPracticed,
    onClose,
}: {
    company: string;
    questions: InterviewQuestion[];
    onSetPracticed: (question: InterviewQuestion, practiced: boolean) => void;
    onClose: () => void;
}) {
    const [unpracticedOnly, setUnpracticedOnly] = useState(false);
    // The deck is fixed when it starts, so marking a card does not pull it out from under you.
    const [deck, setDeck] = useState<number[]>(() => questions.map((question) => question.id));
    const [index, setIndex] = useState(0);
    const [revealed, setRevealed] = useState(false);
    const panel = useRef<HTMLDivElement>(null);

    const question = questions.find((item) => item.id === deck[index]);
    const finished = index >= deck.length;

    const goTo = (next: number) => {
        setIndex(Math.max(0, Math.min(next, deck.length)));
        setRevealed(false);
    };

    const restart = (onlyUnpracticed: boolean) => {
        setUnpracticedOnly(onlyUnpracticed);
        setDeck(
            questions
                .filter((item) => !onlyUnpracticed || !item.practiced)
                .map((item) => item.id),
        );
        setIndex(0);
        setRevealed(false);
    };

    useEffect(() => {
        panel.current?.focus();
        const overflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = overflow;
        };
    }, []);

    const onKeyDown = (event: React.KeyboardEvent) => {
        const typing = event.target instanceof HTMLInputElement || event.target instanceof HTMLButtonElement;

        if (event.key === 'Escape') {
            onClose();
        } else if (event.key === 'ArrowRight') {
            goTo(index + 1);
        } else if (event.key === 'ArrowLeft') {
            goTo(index - 1);
        } else if (event.key === ' ' && !typing) {
            event.preventDefault();
            setRevealed(true);
        }
    };

    return (
        <div
            ref={panel}
            role="dialog"
            aria-modal="true"
            aria-label={`Practice mode for ${company}`}
            tabIndex={-1}
            onKeyDown={onKeyDown}
            className="fixed inset-0 z-50 flex flex-col overflow-y-auto bg-canvas outline-none"
        >
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3 sm:px-8">
                <div>
                    <p className="font-display text-lg font-semibold">{company}</p>
                    <p className="text-xs text-muted">
                        {finished ? 'Finished' : `Question ${index + 1} of ${deck.length}`}
                    </p>
                </div>
                <div className="flex items-center gap-4">
                    <label className="flex items-center gap-2 text-xs">
                        <input
                            type="checkbox"
                            checked={unpracticedOnly}
                            onChange={(event) => restart(event.target.checked)}
                            className="rounded border-line bg-surface text-accent focus:ring-accent"
                        />
                        Unpracticed only
                    </label>
                    <button type="button" onClick={onClose} aria-label="Close practice mode" className="btn-quiet p-2">
                        <X className="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div aria-hidden className="h-0.5 bg-sunken">
                <div
                    className="h-full bg-accent transition-all"
                    style={{ width: `${deck.length ? (Math.min(index, deck.length) / deck.length) * 100 : 0}%` }}
                />
            </div>

            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col justify-center px-4 py-8 sm:px-6">
                {deck.length === 0 ? (
                    <div className="text-center">
                        <p className="font-display text-2xl font-semibold">Nothing left to practise</p>
                        <button type="button" onClick={() => restart(false)} className="btn-quiet mt-4">
                            Practise every question
                        </button>
                    </div>
                ) : finished || !question ? (
                    <div className="text-center">
                        <p className="font-display text-3xl font-semibold">End of the deck</p>
                        <p className="mt-2 text-sm text-muted">
                            {questions.filter((item) => item.practiced).length} of {questions.length} questions marked practiced.
                        </p>
                        <div className="mt-5 flex justify-center gap-2">
                            <button type="button" onClick={() => restart(unpracticedOnly)} className="btn-primary">
                                Go again
                            </button>
                            <button type="button" onClick={onClose} className="btn-quiet">
                                Close
                            </button>
                        </div>
                    </div>
                ) : (
                    <>
                        <div className="flex flex-wrap gap-1.5">
                            {question.round_type && <Chip>{ROUND_LABELS[question.round_type]}</Chip>}
                            {question.topic && <Chip>{question.topic}</Chip>}
                            {question.difficulty && <Chip>{titleCase(question.difficulty)}</Chip>}
                        </div>

                        <h2 className="mt-4 text-2xl font-semibold leading-snug tracking-tight sm:text-3xl">
                            {question.question}
                        </h2>

                        <div className="mt-8 min-h-48">
                            {revealed ? (
                                <div className="rounded-2xl border border-line bg-surface p-5">
                                    <p className="mb-2 text-xs font-medium text-muted">Suggested answer</p>
                                    <p className="whitespace-pre-wrap text-sm leading-6">
                                        {question.suggested_answer || 'No suggested answer saved yet.'}
                                    </p>
                                </div>
                            ) : (
                                <button
                                    type="button"
                                    onClick={() => setRevealed(true)}
                                    className="flex h-32 w-full items-center justify-center rounded-2xl border border-dashed border-line text-sm font-medium transition hover:border-accent hover:text-accent"
                                >
                                    Answer it out loud, then show the answer
                                </button>
                            )}
                        </div>

                        <div className="mt-6 flex items-center justify-between gap-2">
                            <button
                                type="button"
                                onClick={() => goTo(index - 1)}
                                disabled={index === 0}
                                className="btn-quiet disabled:opacity-40"
                            >
                                <ArrowLeft className="h-4 w-4" /> Back
                            </button>
                            <button
                                type="button"
                                aria-pressed={question.practiced}
                                onClick={() => onSetPracticed(question, !question.practiced)}
                                className={question.practiced ? 'btn bg-strong-bg text-strong-fg' : 'btn-quiet'}
                            >
                                <Check className="h-4 w-4" />
                                {question.practiced ? 'Practiced' : 'Mark practiced'}
                            </button>
                            <button type="button" onClick={() => goTo(index + 1)} className="btn-primary">
                                Next <ArrowRight className="h-4 w-4" />
                            </button>
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}
