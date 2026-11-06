<?php

namespace App\Http\Controllers;

use App\Enums\JobPlatform;
use App\Enums\PipelineStatus;
use App\Models\Job;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;

class JobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'platform' => ['nullable', Rule::enum(JobPlatform::class)],
            'status' => ['nullable', Rule::enum(PipelineStatus::class)],
            'band' => ['nullable', 'in:strong,good,fair,low,unscored'],
            'location' => ['nullable', 'string', 'max:255'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $jobs = Job::with(['company:id,name', 'applications:id,job_id'])
            ->when($filters['platform'] ?? null, fn (Builder $query, $platform) => $query->where('platform', $platform))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters['location'] ?? null, fn (Builder $query, $location) => $query->where('location', 'like', "%{$location}%"))
            ->when($filters['band'] ?? null, fn (Builder $query, $band) => $this->applyBand($query, $band))
            ->orderByRaw('match_score is null')
            ->orderBy('match_score', $filters['direction'] ?? 'desc')
            ->latest('found_at')
            ->select(['id', 'company_id', 'title', 'platform', 'url', 'location', 'experience_range', 'salary_text', 'match_score', 'status', 'found_at'])
            ->paginate(25)
            ->withQueryString();

        return response()->json([
            'jobs' => $jobs,
            'filters' => (object) $filters,
        ]);
    }

    private function applyBand(Builder $query, string $band): void
    {
        match ($band) {
            'strong' => $query->where('match_score', '>=', 80),
            'good' => $query->whereBetween('match_score', [60, 79]),
            'fair' => $query->whereBetween('match_score', [40, 59]),
            'low' => $query->where('match_score', '<', 40),
            'unscored' => $query->whereNull('match_score'),
        };
    }
}
