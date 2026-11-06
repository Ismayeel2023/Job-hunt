<?php

namespace App\Models;

use App\Enums\RoundType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'round_type', 'question', 'topic', 'difficulty', 'year_reported', 'source_url', 'source_name',
        'suggested_answer', 'answer_based_on', 'practiced', 'my_notes',
    ];

    protected function casts(): array
    {
        return [
            'round_type' => RoundType::class,
            'answer_based_on' => 'array',
            'practiced' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
