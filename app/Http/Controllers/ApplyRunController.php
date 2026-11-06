<?php

namespace App\Http\Controllers;

use App\Enums\ApplyRunStatus;
use App\Models\ApplyRun;
use App\Models\Job;
use App\Support\CandidateDetails;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The user's side of an apply run: start it, answer questions, approve or cancel the submit.
 * Approval only ever comes from here (a logged-in session), never from the agent API.
 */
class ApplyRunController extends Controller
{
    public function store(Job $job): RedirectResponse
    {
        if ($job->applications()->exists()) {
            return back()->with('status', 'You have already applied to this job.');
        }

        if ($job->applyRuns()->active()->exists()) {
            return back()->with('status', 'Claude is already working on this job.');
        }

        if (! $job->resumeVersions()->exists()) {
            return back()->with('status', "Tailor a resume first: run /tailor {$job->id} in Claude Code.");
        }

        $job->applyRuns()->create(['status' => ApplyRunStatus::Requested, 'step' => 'check_rules']);

        return back();
    }

    public function answer(Request $request, ApplyRun $run): RedirectResponse
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

        return back();
    }

    public function confirm(ApplyRun $run): RedirectResponse
    {
        abort_unless($run->status === ApplyRunStatus::AwaitingConfirmation, 409, 'This run is not waiting for your OK.');

        $run->update(['confirmed_at' => now()]);

        return back();
    }

    public function cancel(ApplyRun $run): RedirectResponse
    {
        abort_unless($run->status->isActive(), 409, 'This run has already finished.');

        $run->update(['status' => ApplyRunStatus::Cancelled, 'cancelled_at' => now(), 'finished_at' => now()]);

        return back();
    }
}
