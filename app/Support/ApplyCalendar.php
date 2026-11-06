<?php

namespace App\Support;

use App\Models\Application;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class ApplyCalendar
{
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(config('jobhunt.timezone'));
    }

    /**
     * The Monday-to-Sunday week containing now, offset by whole weeks, as app-timezone bounds for querying.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function week(int $weeksFromNow = 0): array
    {
        $day = self::now()->addWeeks($weeksFromNow);

        return [
            $day->startOfWeek(Carbon::MONDAY)->setTimezone(config('app.timezone')),
            $day->endOfWeek(Carbon::SUNDAY)->setTimezone(config('app.timezone')),
        ];
    }

    /**
     * Everything the weekly strip needs: one entry per day, plus the state of the rules right now.
     */
    public static function strip(): array
    {
        $rules = config('jobhunt.apply_rules');
        $locked = $rules['locked_window'];
        $now = self::now();
        $time = $now->format('H:i');

        $applied = Application::whereBetween('applied_at', self::week())
            ->pluck('applied_at')
            ->countBy(fn ($date) => $date->setTimezone(config('jobhunt.timezone'))->toDateString());

        $days = collect(range(0, 6))->map(function (int $offset) use ($now, $rules, $locked, $applied) {
            $date = $now->startOfWeek(Carbon::MONDAY)->addDays($offset);
            $weekday = strtolower($date->englishDayOfWeek);

            return [
                'date' => $date->toDateString(),
                'weekday' => $date->format('D'),
                'day' => $date->day,
                'is_today' => $date->isSameDay($now),
                'is_apply_day' => in_array($weekday, $rules['apply_days'], true),
                'locked' => $weekday === $locked['day'] ? ['start' => $locked['start'], 'end' => $locked['end']] : null,
                'applied' => $applied->get($date->toDateString(), 0),
            ];
        });

        return [
            'days' => $days->all(),
            'now_minutes' => $now->hour * 60 + $now->minute,
            'is_apply_day' => in_array(strtolower($now->englishDayOfWeek), $rules['apply_days'], true),
            'is_locked' => strtolower($now->englishDayOfWeek) === $locked['day'] && $time >= $locked['start'] && $time < $locked['end'],
        ];
    }
}
