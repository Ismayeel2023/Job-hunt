<?php

namespace App\Http\Requests\Agent;

use App\Enums\JobPlatform;
use App\Enums\PipelineStatus;
use Illuminate\Validation\Rule;

abstract class JobRequest extends AgentRequest
{
    protected array $dates = ['found_at'];

    /**
     * Rules for the optional job fields shared by upsert and update.
     */
    protected function attributeRules(): array
    {
        return [
            'platform' => ['sometimes', Rule::enum(JobPlatform::class)],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'experience_range' => ['sometimes', 'nullable', 'string', 'max:255'],
            'salary_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'jd_text' => ['sometimes', 'nullable', 'string'],
            'must_have_skills' => ['sometimes', 'nullable', 'array'],
            'must_have_skills.*' => ['string'],
            'nice_to_have_skills' => ['sometimes', 'nullable', 'array'],
            'nice_to_have_skills.*' => ['string'],
            'match_score' => ['sometimes', 'nullable', 'integer', 'between:0,100'],
            'matched_skills' => ['sometimes', 'nullable', 'array'],
            'matched_skills.*' => ['string'],
            'gaps' => ['sometimes', 'nullable', 'array'],
            'gaps.*' => ['string'],
            'found_at' => ['sometimes', 'date'],
            'status' => ['sometimes', Rule::enum(PipelineStatus::class)],
        ];
    }
}
