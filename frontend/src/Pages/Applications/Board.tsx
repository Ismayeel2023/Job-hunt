import { CompanyMark, EmptyState, PageHeader, ScoreBadge, StatusPill } from '@/Components/ui';
import api from '@/lib/api';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDate, formatDateTime, isPast, STATUS_LABELS } from '@/lib/format';
import { PageProps } from '@/types';
import { Status } from '@/types/models';
import {
    DndContext,
    DragEndEvent,
    DragOverlay,
    DragStartEvent,
    KeyboardSensor,
    PointerSensor,
    TouchSensor,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import { Head, Link, router , route } from '@/lib/router';
import { useEffect, useState } from 'react';

interface Card {
    id: number;
    title: string;
    company: string;
    match_score: number | null;
    status: Status;
    stage_reached: Status | null;
    applied_at: string | null;
    next_step: string | null;
    next_step_at: string | null;
}

const COLUMNS: Status[] = ['applied', 'online_test', 'interview', 'hr', 'offer', 'rejected'];

function CardBody({ card, dragging = false }: { card: Card; dragging?: boolean }) {
    return (
        <div
            className={`rounded-xl border bg-surface p-3 ${
                dragging ? 'rotate-1 border-accent shadow-xl' : 'border-line'
            }`}
        >
            <div className="flex items-start gap-2.5">
                <CompanyMark name={card.company} />
                <div className="min-w-0 flex-1">
                    <Link
                        href={route('applications.show', card.id)}
                        draggable={false}
                        className="block text-sm font-medium leading-snug hover:underline"
                    >
                        {card.title}
                    </Link>
                    <p className="mt-0.5 truncate text-xs text-muted">{card.company}</p>
                </div>
            </div>

            <dl className="mt-3 flex flex-wrap items-center justify-between gap-x-2 gap-y-1 text-xs">
                <div className="flex items-center gap-1.5">
                    <dt className="text-muted">Match score</dt>
                    <dd>
                        <ScoreBadge score={card.match_score} />
                    </dd>
                </div>
                <dd className="text-muted">{formatDate(card.applied_at)}</dd>
            </dl>

            <div className="mt-1.5 text-xs text-muted">
                Stage reached:{' '}
                <span className="font-medium text-ink">
                    {card.stage_reached ? STATUS_LABELS[card.stage_reached] : 'None'}
                </span>
            </div>

            {card.next_step && (
                <p className="mt-2 border-t border-line pt-2 text-xs">
                    {card.next_step}
                    {card.next_step_at && (
                        <span className={isPast(card.next_step_at) ? ' text-low-fg' : ' text-muted'}>
                            {' '}
                            ({formatDateTime(card.next_step_at)})
                        </span>
                    )}
                </p>
            )}
        </div>
    );
}

function DraggableCard({ card }: { card: Card }) {
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({ id: card.id });

    return (
        <li
            ref={setNodeRef}
            {...attributes}
            {...listeners}
            aria-roledescription={`Application card, ${STATUS_LABELS[card.status]}. Press space to pick up.`}
            className={`cursor-grab rounded-xl active:cursor-grabbing ${isDragging ? 'opacity-30' : ''}`}
        >
            <CardBody card={card} />
        </li>
    );
}

function Column({ status, cards }: { status: Status; cards: Card[] }) {
    const { setNodeRef, isOver } = useDroppable({ id: status });

    return (
        <section
            ref={setNodeRef}
            aria-label={STATUS_LABELS[status]}
            className={`flex w-[272px] shrink-0 snap-start flex-col rounded-2xl p-2 transition-colors min-[1400px]:w-auto ${
                isOver ? 'bg-accent-soft' : 'bg-sunken/70'
            }`}
        >
            <h2 className="flex items-center justify-between px-1.5 pb-2 pt-1 font-sans text-xs font-semibold">
                {STATUS_LABELS[status]}
                <span className="font-mono font-normal text-muted">{cards.length}</span>
            </h2>
            <ul className="flex min-h-24 flex-1 flex-col gap-2">
                {cards.map((card) => (
                    <DraggableCard key={card.id} card={card} />
                ))}
            </ul>
        </section>
    );
}

export default function Board({ applications }: PageProps<{ applications: Card[] }>) {
    const [cards, setCards] = useState(applications);
    const [activeId, setActiveId] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => setCards(applications), [applications]);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(TouchSensor, { activationConstraint: { delay: 220, tolerance: 8 } }),
        useSensor(KeyboardSensor),
    );

    const active = cards.find((card) => card.id === activeId) ?? null;
    const offBoard = cards.filter((card) => !COLUMNS.includes(card.status));

    const onDragStart = (event: DragStartEvent) => {
        setError(null);
        setActiveId(Number(event.active.id));
    };

    const onDragEnd = (event: DragEndEvent) => {
        setActiveId(null);

        const card = cards.find((item) => item.id === Number(event.active.id));
        const status = event.over?.id as Status | undefined;

        if (!card || !status || status === card.status) {
            return;
        }

        setCards((current) =>
            current.map((item) => (item.id === card.id ? { ...item, status } : item)),
        );

        // Saved straight through the API, so moving another card or leaving the page never drops it.
        api
            .patch(route('applications.status', card.id), { status })
            .then(() => router.reload({ only: ['applications'], async: true }))
            .catch(() => {
                setCards((current) =>
                    current.map((item) => (item.id === card.id ? { ...item, status: card.status } : item)),
                );
                setError(`Could not move ${card.title}, so it went back to ${STATUS_LABELS[card.status]}.`);
            });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Applications" />

            <PageHeader
                title="Applications"
                description="Drag a card to change its status. Each move is logged on the application's timeline."
            />

            {error && (
                <p role="alert" className="mb-3 rounded-lg bg-low-bg px-3 py-2 text-sm text-low-fg">
                    {error}
                </p>
            )}

            {cards.length === 0 ? (
                <EmptyState title="No applications yet">
                    Applications appear here once the agent records them through the API.
                </EmptyState>
            ) : (
                <DndContext
                    sensors={sensors}
                    onDragStart={onDragStart}
                    onDragEnd={onDragEnd}
                    onDragCancel={() => setActiveId(null)}
                >
                    <div className="-mx-4 flex snap-x gap-3 overflow-x-auto px-4 pb-3 sm:-mx-6 sm:px-6 lg:mx-0 lg:px-0 min-[1400px]:grid min-[1400px]:grid-cols-6 min-[1400px]:overflow-visible">
                        {COLUMNS.map((status) => (
                            <Column
                                key={status}
                                status={status}
                                cards={cards.filter((card) => card.status === status)}
                            />
                        ))}
                    </div>
                    <DragOverlay>
                        {active && <CardBody card={active} dragging />}
                    </DragOverlay>
                </DndContext>
            )}

            {offBoard.length > 0 && (
                <section className="mt-6">
                    <h2 className="mb-2 font-sans text-xs font-semibold text-muted">
                        Not on the board
                    </h2>
                    <ul className="divide-y divide-line rounded-2xl border border-line bg-surface">
                        {offBoard.map((card) => (
                            <li key={card.id}>
                                <Link
                                    href={route('applications.show', card.id)}
                                    className="flex items-center justify-between gap-3 px-4 py-2.5 text-sm transition hover:bg-sunken/50"
                                >
                                    <span className="min-w-0 truncate">
                                        {card.title}
                                        <span className="text-muted">, {card.company}</span>
                                    </span>
                                    <StatusPill status={card.status} />
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </AuthenticatedLayout>
    );
}
