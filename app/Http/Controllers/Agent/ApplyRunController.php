<?php

namespace App\Http\Controllers\Agent;

use App\Enums\ApplyRunStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\UpdateApplyRunRequest;
use App\Models\ApplyRun;
use Illuminate\Http\JsonResponse;

class ApplyRunController extends Controller
{
    public function show(ApplyRun $run): JsonResponse
    {
        return response()->json($run);
    }

    /**
     * Progress reports from the worker. A run the user cancelled, or one already finished, can't be changed,
     * and submitting is refused unless the user approved it on the job page.
     */
    public function update(UpdateApplyRunRequest $request, ApplyRun $run): JsonResponse
    {
        if (! $run->status->isActive()) {
            return response()->json(['message' => "This run is already {$run->status->value}.", 'status' => $run->status], 409);
        }

        $data = $request->validated();
        $status = isset($data['status']) ? ApplyRunStatus::from($data['status']) : null;

        if (in_array($status, [ApplyRunStatus::Submitting, ApplyRunStatus::Submitted], true) && ! $run->confirmed_at) {
            return response()->json(['message' => 'The user has not approved this submit on the job page.'], 422);
        }

        if ($status === ApplyRunStatus::AwaitingConfirmation && empty($data['summary']) && empty($run->summary)) {
            return response()->json(['message' => 'Send a summary of what will be submitted before asking for approval.', 'errors' => ['summary' => ['Required for awaiting_confirmation.']]], 422);
        }

        $run->addNote($data['step'] ?? null, $data['note'] ?? null);

        if (! empty($data['questions'])) {
            $run->ask($data['questions']);
            $status = ApplyRunStatus::NeedsAnswer;
        }

        if (array_key_exists('summary', $data)) {
            $run->summary = $data['summary'];
        }

        if (array_key_exists('error', $data)) {
            $run->error = $data['error'];
        }

        if (array_key_exists('application_id', $data)) {
            $run->application_id = $data['application_id'];
        }

        if ($status) {
            $run->status = $status;

            if (! $status->isActive()) {
                $run->finished_at = now();
            }
        }

        $run->save();

        return response()->json($run);
    }
}
