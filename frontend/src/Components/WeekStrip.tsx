export interface WeekDay {
    date: string;
    weekday: string;
    day: number;
    is_today: boolean;
    is_apply_day: boolean;
    locked: { start: string; end: string } | null;
    applied: number;
}

export interface Week {
    days: WeekDay[];
    now_minutes: number;
    is_apply_day: boolean;
    is_locked: boolean;
}

const DAY_MINUTES = 24 * 60;

function minutes(time: string): number {
    const [hours, mins] = time.split(':').map(Number);

    return hours * 60 + mins;
}

function DayTrack({ day, nowMinutes }: { day: WeekDay; nowMinutes: number }) {
    return (
        <div
            className={`relative h-1.5 rounded-full ${day.is_apply_day ? 'bg-accent' : 'bg-sunken'}`}
        >
            {day.locked && (
                <div
                    className="hatch absolute inset-y-0 rounded-sm"
                    style={{
                        left: `${(minutes(day.locked.start) / DAY_MINUTES) * 100}%`,
                        width: `${((minutes(day.locked.end) - minutes(day.locked.start)) / DAY_MINUTES) * 100}%`,
                    }}
                />
            )}
            {day.is_today && (
                <div
                    className="absolute -inset-y-1 w-0.5 rounded-full bg-ink ring-2 ring-surface"
                    style={{ left: `${(nowMinutes / DAY_MINUTES) * 100}%` }}
                />
            )}
        </div>
    );
}

export default function WeekStrip({ week }: { week: Week }) {
    const applyDays = week.days.filter((day) => day.is_apply_day);
    const lockedDay = week.days.find((day) => day.locked);

    return (
        <div>
            <ol className="grid grid-cols-7 gap-1 sm:gap-2">
                {week.days.map((day) => (
                    <li
                        key={day.date}
                        aria-current={day.is_today ? 'date' : undefined}
                        className={`min-w-0 rounded-xl p-1.5 sm:p-3 ${
                            day.is_apply_day ? 'bg-accent-soft' : ''
                        } ${day.is_today ? 'ring-1 ring-inset ring-ink' : ''}`}
                    >
                        <div className="flex items-baseline justify-between gap-1">
                            <span
                                className={`text-[11px] sm:text-xs ${day.is_apply_day || day.is_today ? 'font-semibold' : 'text-muted'}`}
                            >
                                {day.weekday}
                            </span>
                            {day.is_today && (
                                <span className="hidden text-[11px] font-medium sm:inline">
                                    Today
                                </span>
                            )}
                        </div>
                        <div className="mt-0.5 font-display text-lg font-semibold leading-tight sm:text-2xl">
                            {day.day}
                        </div>
                        <div className="mt-2 sm:mt-3">
                            <DayTrack day={day} nowMinutes={week.now_minutes} />
                        </div>
                        <div className="mt-2 hidden min-h-8 text-[11px] leading-4 text-muted sm:block">
                            {day.is_apply_day && (
                                <div className="font-medium text-ink">Apply day</div>
                            )}
                            {day.locked && (
                                <div className="font-medium text-ink">
                                    Locked {day.locked.start}-{day.locked.end}
                                </div>
                            )}
                            {day.applied > 0 && <div>{day.applied} sent</div>}
                        </div>
                        <div className="mt-1.5 text-center font-mono text-[10px] text-muted sm:hidden">
                            {day.applied > 0 ? `${day.applied} sent` : ' '}
                        </div>
                    </li>
                ))}
            </ol>

            <ul className="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-xs text-muted">
                {applyDays.length > 0 && (
                    <li className="flex items-center gap-2">
                        <span aria-hidden className="h-1.5 w-5 rounded-full bg-accent" />
                        Apply days: {applyDays.map((day) => day.weekday).join(' and ')}
                    </li>
                )}
                {lockedDay?.locked && (
                    <li className="flex items-center gap-2">
                        <span aria-hidden className="hatch h-1.5 w-5 rounded-sm" />
                        Locked window: {lockedDay.weekday} {lockedDay.locked.start}-
                        {lockedDay.locked.end} IST
                    </li>
                )}
                <li className="flex items-center gap-2">
                    <span aria-hidden className="h-3 w-0.5 rounded-full bg-ink" />
                    Now, on a 24-hour track
                </li>
            </ul>
        </div>
    );
}
