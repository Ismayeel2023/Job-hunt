<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

abstract class AgentRequest extends FormRequest
{
    /**
     * Fields converted to the app timezone by payload().
     *
     * @var list<string>
     */
    protected array $dates = [];

    /**
     * Access is decided by the AuthenticateAgent middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validated data with dates normalized. Eloquent stores a date's wall-clock
     * time and drops its offset, so "10:00+05:30" must become 04:30 UTC first.
     */
    public function payload(): array
    {
        $data = $this->validated();

        foreach ($this->dates as $field) {
            if (! empty($data[$field])) {
                $data[$field] = Carbon::parse($data[$field])->setTimezone(config('app.timezone'));
            }
        }

        return $data;
    }
}
