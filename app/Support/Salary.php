<?php

namespace App\Support;

/**
 * Turns a posting's salary text into a yearly range, and suggests what to ask for.
 * Suggestions use only the posted range and the user's own CTC from Settings, never outside salary data.
 */
class Salary
{
    /**
     * @return array{0: int, 1: int}|null Yearly rupees, or null when the text gives no clear figure.
     */
    public static function parse(?string $text): ?array
    {
        if (! $text) {
            return null;
        }

        $lower = strtolower($text);

        // "An hour" in Indian postings is almost always a mislabelled monthly figure, so it is too unclear to use.
        if (str_contains($lower, 'hour')) {
            return null;
        }

        preg_match_all('/\d+(?:\.\d+)?/', str_replace(',', '', $lower), $matches);
        $numbers = array_map('floatval', $matches[0]);

        if ($numbers === []) {
            return null;
        }

        $min = $numbers[0];
        $max = $numbers[1] ?? $numbers[0];

        if (preg_match('/\b(lpa|lakhs?|lacs?|lac)\b/', $lower) || ($max < 100 && ! preg_match('/month|year|annum/', $lower))) {
            $factor = 100000;
        } elseif (preg_match('/month|\bpm\b/', $lower)) {
            $factor = 12;
        } elseif (preg_match('/year|annum|\bpa\b/', $lower) || $min >= 100000) {
            $factor = 1;
        } else {
            return null;
        }

        return [(int) round(min($min, $max) * $factor), (int) round(max($min, $max) * $factor)];
    }

    public static function lpa(int|float $rupees): float
    {
        return round($rupees / 100000, 1);
    }

    /**
     * @return array{posted: ?array{0: float, 1: float}, suggested: ?array{0: float, 1: float}, basis: string, notes: list<string>}
     */
    public static function advice(?string $salaryText, ?float $currentLpa, ?float $expectedLpa): array
    {
        $range = self::parse($salaryText);
        $raiseLow = 1 + config('jobhunt.salary.raise_low_percent') / 100;
        $raiseHigh = 1 + config('jobhunt.salary.raise_high_percent') / 100;
        $percent = config('jobhunt.salary.raise_low_percent').'-'.config('jobhunt.salary.raise_high_percent').'%';
        $current = $currentLpa ? $currentLpa * 100000 : null;
        $expected = $expectedLpa ? $expectedLpa * 100000 : null;
        $notes = [];

        if ($range) {
            [$min, $max] = $range;

            if ($min === $max) {
                [$low, $high, $basis] = [$max, $max, 'the figure in the posting'];
            } elseif ($current && $current >= $max) {
                [$low, $high, $basis] = [$max, $max, 'the top of the posted range'];
                $notes[] = 'The posted range tops out at or below your current CTC.';
            } elseif ($current) {
                $low = min(max($current * $raiseLow, $min), $max);
                $high = min(max($current * $raiseHigh, $min), $max);
                $basis = "a {$percent} raise on your current CTC, kept inside the posted range";
            } elseif ($max >= $min * 2.5) {
                // A very wide band usually spans several experience levels, so its top is not a realistic ask.
                [$low, $high, $basis] = [$min, $min + ($max - $min) / 3, 'the lower third of a very wide posted range, which likely covers more experienced hires too'];
                $notes[] = 'Add your current CTC in Settings for a more realistic figure.';
            } else {
                [$low, $high, $basis] = [$min + ($max - $min) / 2, $max, 'the upper half of the posted range. Add your current CTC in Settings for a sharper figure'];
            }

            if ($expected && $expected > $max) {
                $notes[] = 'Your expected CTC is above this range.';
            } elseif ($expected && $expected < $min) {
                $notes[] = 'Your expected CTC is below this range, so you could ask for more.';
            }

            return [
                'posted' => [self::lpa($min), self::lpa($max)],
                'suggested' => [self::lpa($low), self::lpa($high)],
                'basis' => $basis,
                'notes' => $notes,
            ];
        }

        if ($expected) {
            return ['posted' => null, 'suggested' => [self::lpa($expected), self::lpa($expected)], 'basis' => 'your expected CTC from Settings, as the posting lists no salary', 'notes' => []];
        }

        if ($current) {
            return ['posted' => null, 'suggested' => [self::lpa($current * $raiseLow), self::lpa($current * $raiseHigh)], 'basis' => "a {$percent} raise on your current CTC, as the posting lists no salary", 'notes' => []];
        }

        return ['posted' => null, 'suggested' => null, 'basis' => 'The posting lists no salary. Add your current or expected CTC in Settings, or ask the recruiter for the band.', 'notes' => []];
    }
}
