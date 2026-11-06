<?php

namespace App\Http\Controllers\Agent;

use App\Enums\PipelineStatus;
use App\Enums\StatusEventSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\ListApplicationsRequest;
use App\Http\Requests\Agent\StoreApplicationRequest;
use App\Http\Requests\Agent\StoreApplicationStatusRequest;
use App\Models\Application;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ApplicationController extends Controller
{
    public function index(ListApplicationsRequest $request): JsonResponse
    {
        $status = $request->validated('status');

        $applications = Application::with(['job:id,company_id,title,url,platform,match_score', 'job.company:id,name,website', 'statusEvents:id,application_id,from_status,to_status,happened_at'])
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->latest('applied_at')
            ->paginate($request->validated('per_page') ?? 50)
            ->through(fn (Application $application) => [
                ...$application->toArray(),
                'stage_reached' => $application->stageReached(),
                'last_event_at' => $application->statusEvents->max('happened_at'),
            ]);

        return response()->json($applications);
    }

    public function store(StoreApplicationRequest $request): JsonResponse
    {
        $data = $request->payload();

        try {
            $application = DB::transaction(function () use ($data) {
                $application = Application::create([
                    ...$data,
                    'applied_at' => $data['applied_at'] ?? now(),
                    'status' => $data['status'] ?? PipelineStatus::Applied,
                ]);

                $application->statusEvents()->create([
                    'to_status' => $application->status,
                    'source' => StatusEventSource::Claude,
                    'happened_at' => $application->applied_at,
                ]);

                $application->job->update(['status' => $application->status]);

                return $application;
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'An application for this job already exists.',
                'application_id' => Application::where('job_id', $data['job_id'])->value('id'),
            ], 409);
        }

        return response()->json($application->refresh()->load('statusEvents'), 201);
    }

    public function updateStatus(StoreApplicationStatusRequest $request, Application $application): JsonResponse
    {
        $data = $request->payload();

        $event = $application->transitionTo(
            PipelineStatus::from($data['to_status']),
            StatusEventSource::tryFrom($data['source'] ?? '') ?? StatusEventSource::Claude,
            $data,
        );

        return response()->json($event->refresh()->load('application'), 201);
    }
}
