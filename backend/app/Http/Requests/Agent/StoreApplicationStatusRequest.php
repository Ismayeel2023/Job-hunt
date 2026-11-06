<?php

namespace App\Http\Requests\Agent;

use App\Enums\PipelineStatus;
use App\Enums\StatusEventSource;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreApplicationStatusRequest extends AgentRequest
{
    protected array $dates = ['happened_at', 'next_step_at'];

    public function rules(): array
    {
        return [
            'to_status' => ['required', Rule::enum(PipelineStatus::class)],
            'source' => ['sometimes', Rule::enum(StatusEventSource::class)],
            'evidence' => ['nullable', 'string'],
            'email_id' => ['nullable', 'integer', 'exists:emails,id'],
            'happened_at' => ['nullable', 'date'],
            'next_step' => ['sometimes', 'nullable', 'string', 'max:255'],
            'next_step_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('to_status') === $this->route('application')->status->value) {
                    $validator->errors()->add('to_status', 'The application is already in this status.');
                }
            },
        ];
    }
}
