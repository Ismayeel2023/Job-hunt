<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\ListJobsRequest;
use App\Http\Requests\Agent\UpdateJobRequest;
use App\Http\Requests\Agent\UpsertJobRequest;
use App\Models\Company;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class JobController extends Controller
{
    public function index(ListJobsRequest $request): JsonResponse
    {
        $status = $request->validated('status');
        $minScore = $request->validated('min_score');

        $jobs = Job::with('company')
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($minScore !== null, fn ($query) => $query->where('match_score', '>=', $minScore))
            ->orderByDesc('match_score')
            ->latest('found_at')
            ->paginate($request->validated('per_page') ?? 50);

        return response()->json($jobs);
    }

    /**
     * One job in full, with its resume versions (no LaTeX) and application. pdf_file is the absolute path, for uploading.
     */
    public function show(Job $job): JsonResponse
    {
        $job->load(['company', 'resumeVersions' => fn ($query) => $query->latest('id'), 'applications.statusEvents']);

        return response()->json([
            ...$job->toArray(),
            'resume_versions' => $job->resumeVersions->map(fn ($version) => [
                ...$version->makeHidden('tex_source')->toArray(),
                'pdf_file' => $version->pdf_path ? Storage::disk('resumes')->path($version->pdf_path) : null,
            ]),
            'application' => $job->applications->first(),
        ]);
    }

    public function upsert(UpsertJobRequest $request): JsonResponse
    {
        $data = $request->payload();

        $job = DB::transaction(function () use ($data) {
            if (isset($data['company'])) {
                $data['company_id'] = $this->findOrCreateCompany($data['company'])->id;
            }

            return Job::updateOrCreate(['url' => $data['url']], Arr::except($data, ['url', 'company']));
        });

        $status = $job->wasRecentlyCreated ? 201 : 200;

        return response()->json($job->refresh()->load('company'), $status);
    }

    public function update(UpdateJobRequest $request, Job $job): JsonResponse
    {
        $job->update($request->payload());

        return response()->json($job->refresh()->load('company'));
    }

    private function findOrCreateCompany(array $attributes): Company
    {
        return Company::whereRaw('lower(name) = ?', [mb_strtolower($attributes['name'])])->first()
            ?? Company::create($attributes);
    }
}
