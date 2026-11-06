<?php

namespace App\Http\Controllers;

use App\Support\CandidateDetails;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CandidateController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        CandidateDetails::put($request->validate([
            'current_ctc_lpa' => ['nullable', 'numeric', 'between:0,500'],
            'expected_ctc_lpa' => ['nullable', 'numeric', 'between:0,500'],
            'notice_period' => ['nullable', 'string', 'max:100'],
            'relocate' => ['nullable', 'string', 'max:200'],
        ]));

        return back()->with('status', 'Your details are saved. Claude uses them when an application form asks.');
    }

    public function forgetAnswer(int $index): RedirectResponse
    {
        CandidateDetails::forgetAnswer($index);

        return back();
    }
}
