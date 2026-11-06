<?php

namespace App\Http\Requests\Agent;

use Illuminate\Validation\Rule;

class UpdateJobRequest extends JobRequest
{
    public function rules(): array
    {
        return [
            'url' => ['sometimes', 'url:http,https', 'max:2048', Rule::unique('jobs', 'url')->ignore($this->route('job'))],
            'title' => ['sometimes', 'string', 'max:255'],
            'company_id' => ['sometimes', 'integer', 'exists:companies,id'],
            ...$this->attributeRules(),
        ];
    }
}
