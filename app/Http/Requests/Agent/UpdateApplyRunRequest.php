<?php

namespace App\Http\Requests\Agent;

use App\Models\ApplyRun;
use Illuminate\Validation\Rule;

class UpdateApplyRunRequest extends AgentRequest
{
    public function rules(): array
    {
        return [
            // Agents can report progress and finish a run, but never approve one: there is no confirmed field.
            'status' => ['sometimes', Rule::in(['running', 'awaiting_confirmation', 'submitting', 'submitted', 'failed', 'refused'])],
            'step' => ['sometimes', Rule::in(array_keys(ApplyRun::STEPS))],
            'note' => ['nullable', 'string', 'max:500'],
            'questions' => ['sometimes', 'array', 'max:20'],
            'questions.*' => ['string', 'max:500'],
            'summary' => ['sometimes', 'array', 'max:60'],
            'summary.*.label' => ['required', 'string', 'max:200'],
            'summary.*.value' => ['nullable', 'string', 'max:2000'],
            'error' => ['nullable', 'string', 'max:1000'],
            'application_id' => ['nullable', 'integer', 'exists:applications,id'],
        ];
    }
}
