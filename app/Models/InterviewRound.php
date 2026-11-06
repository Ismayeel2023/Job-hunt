<?php

namespace App\Models;

use App\Enums\RoundType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewRound extends Model
{
    use HasFactory;

    protected $fillable = ['company_id', 'round_name', 'round_type', 'description', 'typical_duration', 'source_url'];

    protected function casts(): array
    {
        return [
            'round_type' => RoundType::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
