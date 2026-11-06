<?php

namespace App\Models;

use App\Enums\JobPlatform;
use App\Enums\PipelineStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Job extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'title', 'platform', 'url', 'location', 'experience_range', 'salary_text', 'jd_text',
        'must_have_skills', 'nice_to_have_skills', 'match_score', 'matched_skills', 'gaps', 'found_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'platform' => JobPlatform::class,
            'status' => PipelineStatus::class,
            'must_have_skills' => 'array',
            'nice_to_have_skills' => 'array',
            'matched_skills' => 'array',
            'gaps' => 'array',
            'found_at' => 'datetime',
        ];
    }

    protected function matchScore(): Attribute
    {
        return Attribute::set(function ($value) {
            if ($value !== null && ($value < 0 || $value > 100)) {
                throw new InvalidArgumentException('match_score must be between 0 and 100.');
            }

            return $value;
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function resumeVersions(): HasMany
    {
        return $this->hasMany(ResumeVersion::class);
    }

    public function applyRuns(): HasMany
    {
        return $this->hasMany(ApplyRun::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
