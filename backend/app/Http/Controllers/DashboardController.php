<?php

namespace App\Http\Controllers;

use App\Enums\PipelineStatus;
use App\Models\Application;
use App\Models\Job;
use App\Support\ApplyCalendar;
use App\Support\CandidateDetails;
use App\Support\Salary;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $applications = Application::with('statusEvents:id,application_id,from_status,to_status')->get(['id', 'status', 'applied_at']);

        $jobCounts = Job::toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $responded = $applications->filter->hasResponse()->count();

        return response()->json([
            'totals' => collect(PipelineStatus::cases())
                ->mapWithKeys(fn (PipelineStatus $status) => [$status->value => (int) ($jobCounts[$status->value] ?? 0)]),
            'thisWeek' => [
                'count' => Application::whereBetween('applied_at', ApplyCalendar::week())->count(),
                'last_week' => Application::whereBetween('applied_at', ApplyCalendar::week(-1))->count(),
            ],
            'response' => [
                'rate' => $applications->isEmpty() ? null : (int) round($responded / $applications->count() * 100),
                'responded' => $responded,
                'total' => $applications->count(),
            ],
            'stages' => collect(PipelineStatus::stages())->map(fn (PipelineStatus $stage) => [
                'status' => $stage,
                'count' => $applications->filter(fn (Application $application) => $application->stageReached()?->stageRank() >= $stage->stageRank())->count(),
            ]),
            'nextSteps' => Application::with('job.company:id,name')
                ->whereNotNull('next_step_at')
                ->whereNotIn('status', [PipelineStatus::Rejected, PipelineStatus::Withdrawn, PipelineStatus::Ghosted])
                ->orderBy('next_step_at')
                ->limit(6)
                ->get()
                ->map(fn (Application $application) => [
                    'id' => $application->id,
                    'title' => $application->job->title,
                    'company' => $application->job->company->name,
                    'next_step' => $application->next_step,
                    'next_step_at' => $application->next_step_at,
                    'status' => $application->status,
                ]),
            'topJobs' => Job::with('company:id,name')
                ->doesntHave('applications')
                ->whereIn('status', [PipelineStatus::Found, PipelineStatus::Queued])
                ->whereNotNull('match_score')
                ->orderByDesc('match_score')
                ->limit(5)
                ->get(['id', 'company_id', 'title', 'url', 'location', 'platform', 'match_score', 'matched_skills', 'gaps', 'status']),
            'week' => ApplyCalendar::strip(),
            'salaries' => $this->salaries(),
        ]);
    }

    /**
     * What to ask for at each company you have applied to or queued, from the posted range and your CTC.
     */
    private function salaries(): array
    {
        $candidate = CandidateDetails::get();

        return Job::with('company:id,name')
            ->where(fn ($query) => $query->has('applications')->orWhere('status', PipelineStatus::Queued))
            ->whereNotIn('status', [PipelineStatus::Rejected, PipelineStatus::Withdrawn, PipelineStatus::Ghosted])
            ->orderByDesc('match_score')
            ->get(['id', 'company_id', 'title', 'salary_text', 'status'])
            ->map(fn (Job $job) => [
                'id' => $job->id,
                'title' => $job->title,
                'company' => $job->company->name,
                'status' => $job->status,
                'salary_text' => $job->salary_text,
                ...Salary::advice($job->salary_text, $candidate['current_ctc_lpa'], $candidate['expected_ctc_lpa']),
            ])
            ->all();
    }
}
