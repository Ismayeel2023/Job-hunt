<?php

namespace App\Http\Requests\Agent;

use App\Enums\RoundType;
use Illuminate\Validation\Rule;

class StoreInterviewRoundRequest extends AgentRequest
{
    public function rules(): array
    {
        return [
            'round_name' => ['required', 'string', 'max:255'],
            'round_type' => ['required', Rule::enum(RoundType::class)],
            'description' => ['nullable', 'string'],
            'typical_duration' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
        ];
    }
}
