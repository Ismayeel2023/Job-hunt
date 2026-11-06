<?php

namespace App\Http\Requests\Agent;

class UpsertJobRequest extends JobRequest
{
    public function rules(): array
    {
        return [
            'url' => ['required', 'url:http,https', 'max:2048'],
            'title' => ['required', 'string', 'max:255'],
            'company_id' => ['required_without:company', 'prohibits:company', 'integer', 'exists:companies,id'],
            'company' => ['required_without:company_id', 'array'],
            'company.name' => ['required_with:company', 'string', 'max:255'],
            'company.website' => ['nullable', 'url:http,https', 'max:2048'],
            'company.logo_url' => ['nullable', 'url:http,https', 'max:2048'],
            'company.location' => ['nullable', 'string', 'max:255'],
            'company.industry' => ['nullable', 'string', 'max:255'],
            'company.notes' => ['nullable', 'string'],
            ...$this->attributeRules(),
        ];
    }
}
