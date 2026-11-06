<?php

namespace App\Http\Requests\Agent;

use App\Enums\PipelineStatus;
use Illuminate\Validation\Rule;

class ListApplicationsRequest extends AgentRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(PipelineStatus::class)],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
        ];
    }
}
