<?php

namespace App\Http\Controllers;

use App\Enums\ApplyRunStatus;
use App\Models\ApplyRun;
use App\Models\Job;
use App\Support\CandidateDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The user's side of an apply run: start it, answer questions, approve or cancel the submit.
 * Approval only ever comes from here (a logged-in session), never from the agent API.
 */
class ApplyRunController extends Controller
{
    public function store(Job $job): JsonResponse
    {
        if ($job->applications()->exists()) {
            return response()->json(['message' => 'You have already applied to this job.'], 422);
        }

        if ($job->applyRuns()->active()->exists()) {
            return response()->json(['message' => 'Claude is already working on this job.'], 422);
        }

        if (! $job->resumeVersions()->exists()) {
            return response()->json(['message' => "Tailor a resume first: run /tailor {$job->id} in Claude Code."], 422);
        }

        $run = $job->applyRuns()->create(['status' => ApplyRunStatus::Requested, 'step' => 'check_rules']);

        return response()->json($run->fresh(), 201);
    }

    public function answer(Request $request, ApplyRun $run): JsonResponse
    {
        abort_unless($run->status === ApplyRunStatus::NeedsAnswer, 409, 'This run is not waiting for answers.');

        $answers = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'string', 'max:2000'],
            'remember' => ['boolean'],
        ]);

        $run->questions = collect($run->questions)->map(function (array $question) use ($answers, $request) {
            $answer = trim((string) ($answers['answers'][$question['id']] ?? ''));

            if ($answer !== '' && ! filled($question['answer'])) {
                $question['answer'] = $answer;

                if ($request->boolean('remember', true)) {
                    CandidateDetails::rememberAnswer($question['question'], $answer);
                }
            }

            return $question;
        })->all();

        if ($run->allAnswered()) {
            $run->status = ApplyRunStatus::Running;
        }

        $run->save();

        return response()->json($run);
    }

    public function confirm(ApplyRun $run): JsonResponse
    {
        abort_unless($run->status === ApplyRunStatus::AwaitingConfirmation, 409, 'This run is not waiting for your OK.');

        $run->update(['confirmed_at' => now()]);

        return response()->json($run);
    }

    public function cancel(ApplyRun $run): JsonResponse
    {
        abort_unless($run->status->isActive(), 409, 'This run has already finished.');

        $run->update(['status' => ApplyRunStatus::Cancelled, 'cancelled_at' => now(), 'finished_at' => now()]);

        return response()->json($run);
    }
}
