<?php

namespace App\Http\Requests\Agent;

class StoreCompanyRequest extends AgentRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'url:http,https', 'max:2048'],
            'logo_url' => ['nullable', 'url:http,https', 'max:2048'],
            'location' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
