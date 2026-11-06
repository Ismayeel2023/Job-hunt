<?php

namespace App\Http\Requests\Agent;

use App\Enums\RoundType;
use Illuminate\Validation\Rule;

class StoreInterviewQuestionsRequest extends AgentRequest
{
    public function rules(): array
    {
        return [
            'questions' => ['required', 'array', 'min:1', 'max:500'],
            'questions.*.question' => ['required', 'string'],
            'questions.*.round_type' => ['nullable', Rule::enum(RoundType::class)],
            'questions.*.topic' => ['nullable', 'string', 'max:255'],
            'questions.*.difficulty' => ['nullable', 'string', 'max:255'],
            'questions.*.year_reported' => ['nullable', 'integer', 'between:1990,2100'],
            'questions.*.source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'questions.*.source_name' => ['nullable', 'string', 'max:255'],
            'questions.*.suggested_answer' => ['nullable', 'string'],
            'questions.*.answer_based_on' => ['nullable', 'array'],
        ];
    }
}
