<?php

namespace App\Http\Requests\Agent;

class UpdateEmailRequest extends AgentRequest
{
    public function rules(): array
    {
        return [
            'matched_application_id' => ['sometimes', 'nullable', 'integer', 'exists:applications,id'],
            'classification' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processed' => ['sometimes', 'boolean'],
        ];
    }
}
