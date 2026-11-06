<?php

namespace App\Http\Requests\Agent;

use App\Enums\PipelineStatus;
use Illuminate\Validation\Rule;

class StoreApplicationRequest extends AgentRequest
{
    protected array $dates = ['applied_at', 'next_step_at'];

    public function rules(): array
    {
        return [
            'job_id' => ['required', 'integer', 'exists:jobs,id'],
            'resume_version_id' => [
                'nullable',
                'integer',
                Rule::exists('resume_versions', 'id')->where(fn ($query) => $query->where(
                    fn ($query) => $query->where('job_id', $this->input('job_id'))->orWhere('is_base', true)
                )),
            ],
            'applied_at' => ['nullable', 'date'],
            'applied_via' => ['nullable', 'string', 'max:255'],
            'cover_note' => ['nullable', 'string'],
            'screening_answers' => ['nullable', 'array'],
            'status' => ['sometimes', Rule::enum(PipelineStatus::class)],
            'next_step' => ['nullable', 'string', 'max:255'],
            'next_step_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'resume_version_id.exists' => 'The resume version must be the base resume or belong to this job.',
        ];
    }
}
