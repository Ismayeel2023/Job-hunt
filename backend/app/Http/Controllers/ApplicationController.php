<?php

namespace App\Http\Controllers;

use App\Enums\PipelineStatus;
use App\Enums\StatusEventSource;
use App\Models\Application;
use App\Models\ResumeVersion;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;

class ApplicationController extends Controller
{
    public function index(): JsonResponse
    {
        $applications = Application::with(['job.company:id,name', 'statusEvents:id,application_id,from_status,to_status'])
            ->latest('applied_at')
            ->get()
            ->map(fn (Application $application) => [
                'id' => $application->id,
                'title' => $application->job->title,
                'company' => $application->job->company->name,
                'match_score' => $application->job->match_score,
                'status' => $application->status,
                'stage_reached' => $application->stageReached(),
                'applied_at' => $application->applied_at,
                'next_step' => $application->next_step,
                'next_step_at' => $application->next_step_at,
            ]);

        return response()->json(['applications' => $applications]);
    }

    public function show(Application $application): JsonResponse
    {
        $application->load(['job.company', 'resumeVersion', 'statusEvents.email']);

        return response()->json([
            'application' => [
                ...$application->toArray(),
                'stage_reached' => $application->stageReached(),
                'status_events' => $application->statusEvents->sortByDesc('happened_at')->values(),
            ],
            'baseResume' => ResumeVersion::where('is_base', true)->latest('id')->first(['id', 'label', 'tex_source']),
        ]);
    }

    public function updateStatus(Request $request, Application $application): HttpResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(PipelineStatus::class)->except([PipelineStatus::Found, PipelineStatus::Queued])],
        ]);

        $status = PipelineStatus::from($validated['status']);

        if ($status !== $application->status) {
            $application->transitionTo($status, StatusEventSource::Manual);
        }

        return response()->noContent();
    }
}
