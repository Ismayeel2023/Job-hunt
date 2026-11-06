<?php

namespace App\Http\Requests\Agent;

class StoreResumeVersionRequest extends AgentRequest
{
    /**
     * Multipart forms carry these as JSON strings; decode them so they validate as arrays.
     */
    protected function prepareForValidation(): void
    {
        foreach (['keywords_covered', 'changes_summary'] as $field) {
            $decoded = is_string($this->input($field)) ? json_decode($this->input($field), true) : null;

            if (is_array($decoded)) {
                $this->merge([$field => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'job_id' => ['required', 'integer', 'exists:jobs,id'],
            'label' => ['nullable', 'string', 'max:255'],
            'tex_source' => ['required', 'string'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'keywords_covered' => ['nullable', 'array'],
            'keywords_covered.*' => ['string'],
            'changes_summary' => ['nullable', 'array:added,removed,reordered'],
            'changes_summary.added' => ['nullable', 'array'],
            'changes_summary.added.*' => ['string'],
            'changes_summary.removed' => ['nullable', 'array'],
            'changes_summary.removed.*' => ['string'],
            'changes_summary.reordered' => ['nullable', 'array'],
            'changes_summary.reordered.*' => ['string'],
        ];
    }
}
