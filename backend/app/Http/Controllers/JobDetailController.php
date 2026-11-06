<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\ResumeVersion;
use App\Support\ApplyWorker;
use App\Support\CandidateDetails;
use App\Support\Salary;
use Illuminate\Http\JsonResponse;

class JobDetailController extends Controller
{
    public function __invoke(Job $job): JsonResponse
    {
        $job->load('company');
        $candidate = CandidateDetails::get();

        return response()->json([
            'job' => $job,
            'resumeVersions' => ResumeVersion::where('job_id', $job->id)->latest('id')->get(['id', 'label', 'pdf_path', 'keywords_covered', 'created_at']),
            'application' => $job->applications()->first(['id', 'job_id', 'status', 'applied_at']),
            'salary' => Salary::advice($job->salary_text, $candidate['current_ctc_lpa'], $candidate['expected_ctc_lpa']),
            'run' => $job->applyRuns()->latest('id')->first(),
            'worker' => ApplyWorker::status(),
            'steps' => \App\Models\ApplyRun::STEPS,
        ]);
    }
}
