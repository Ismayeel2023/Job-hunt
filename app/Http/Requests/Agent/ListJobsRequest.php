<?php

namespace App\Http\Requests\Agent;

use App\Enums\PipelineStatus;
use Illuminate\Validation\Rule;

class ListJobsRequest extends AgentRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(PipelineStatus::class)],
            'min_score' => ['nullable', 'integer', 'between:0,100'],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
        ];
    }
}
