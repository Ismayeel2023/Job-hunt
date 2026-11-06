<?php

namespace App\Http\Controllers\Agent;

use App\Enums\PipelineStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Job;
use App\Support\ApplyCalendar;
use Illuminate\Http\JsonResponse;

class SummaryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        [$from, $to] = ApplyCalendar::week();

        $applications = Application::with('job.company')
            ->whereBetween('applied_at', [$from, $to])
            ->latest('applied_at')
            ->get();

        return response()->json([
            'jobs_by_status' => $this->countByStatus(Job::class),
            'applications_by_status' => $this->countByStatus(Application::class),
            'this_week' => [
                'from' => $from,
                'to' => $to,
                'count' => $applications->count(),
                'applications' => $applications->map(fn (Application $application) => [
                    'id' => $application->id,
                    'job_id' => $application->job_id,
                    'title' => $application->job->title,
                    'company' => $application->job->company->name,
                    'status' => $application->status,
                    'applied_at' => $application->applied_at,
                ]),
            ],
        ]);
    }

    /**
     * Counts per status, including statuses with no rows.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function countByStatus(string $model): array
    {
        $counts = $model::toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(PipelineStatus::cases())
            ->mapWithKeys(fn (PipelineStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }
}
