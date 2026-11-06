<?php

namespace App\Http\Requests\Agent;

class ListEmailsRequest extends AgentRequest
{
    public function rules(): array
    {
        return [
            'processed' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
        ];
    }
}
