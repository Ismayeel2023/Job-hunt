<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResumeVersion extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'job_id', 'label', 'tex_source', 'pdf_path', 'keywords_covered', 'changes_summary', 'is_base',
    ];

    protected function casts(): array
    {
        return [
            'keywords_covered' => 'array',
            'changes_summary' => 'array',
            'is_base' => 'boolean',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
