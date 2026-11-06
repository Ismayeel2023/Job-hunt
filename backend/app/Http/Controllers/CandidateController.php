<?php

namespace App\Http\Controllers;

use App\Support\CandidateDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CandidateController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        CandidateDetails::put($request->validate([
            'current_ctc_lpa' => ['nullable', 'numeric', 'between:0,500'],
            'expected_ctc_lpa' => ['nullable', 'numeric', 'between:0,500'],
            'notice_period' => ['nullable', 'string', 'max:100'],
            'relocate' => ['nullable', 'string', 'max:200'],
        ]));

        return response()->json([
            'message' => 'Your details are saved. Claude uses them when an application form asks.',
            'candidate' => CandidateDetails::get(),
        ]);
    }

    public function forgetAnswer(int $index): Response
    {
        CandidateDetails::forgetAnswer($index);

        return response()->noContent();
    }
}
