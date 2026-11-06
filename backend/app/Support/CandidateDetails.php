<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Answers the user typed into Settings for application forms: salary, notice period and relocation,
 * plus answers given during earlier apply runs, kept for reuse.
 */
class CandidateDetails
{
    public const DEFAULTS = [
        'current_ctc_lpa' => null,
        'expected_ctc_lpa' => null,
        'notice_period' => null,
        'relocate' => null,
    ];

    public static function get(): array
    {
        return [...self::DEFAULTS, ...array_intersect_key(Setting::getValue('candidate', []), self::DEFAULTS)];
    }

    public static function put(array $details): void
    {
        foreach (['current_ctc_lpa', 'expected_ctc_lpa'] as $field) {
            if (isset($details[$field])) {
                $details[$field] = (float) $details[$field];
            }
        }

        Setting::putValue('candidate', array_intersect_key([...self::get(), ...$details], self::DEFAULTS));
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    public static function savedAnswers(): array
    {
        return Setting::getValue('saved_answers', []);
    }

    public static function rememberAnswer(string $question, string $answer): void
    {
        $answers = collect(self::savedAnswers())
            ->reject(fn (array $saved) => mb_strtolower(trim($saved['question'])) === mb_strtolower(trim($question)))
            ->push(['question' => $question, 'answer' => $answer])
            ->values()
            ->all();

        Setting::putValue('saved_answers', $answers);
    }

    public static function forgetAnswer(int $index): void
    {
        $answers = self::savedAnswers();
        unset($answers[$index]);
        Setting::putValue('saved_answers', array_values($answers));
    }
}
