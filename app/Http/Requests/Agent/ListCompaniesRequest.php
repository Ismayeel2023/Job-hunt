<?php

namespace App\Http\Requests\Agent;

class ListCompaniesRequest extends AgentRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
