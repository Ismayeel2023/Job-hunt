<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\InterviewQuestion;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InterviewPrepController extends Controller
{
    public function index(Request $request): Response
    {
        $companies = Company::query()
            ->withCount([
                'interviewRounds as rounds_count',
                'interviewQuestions as questions_count',
                'interviewQuestions as practiced_count' => fn ($query) => $query->where('practiced', true),
            ])
            ->where(fn ($query) => $query->has('interviewRounds')->orHas('interviewQuestions'))
            ->orderBy('name')
            ->get(['id', 'name', 'industry', 'location']);

        $selected = $companies->firstWhere('id', $request->integer('company')) ?? $companies->first();

        return Inertia::render('Prep/Index', [
            'companies' => $companies,
            'company' => $selected,
            'rounds' => $selected?->interviewRounds()->orderBy('id')->get() ?? [],
            'questions' => $selected?->interviewQuestions()->orderBy('id')->get() ?? [],
        ]);
    }

    public function updateQuestion(Request $request, InterviewQuestion $question): HttpResponse
    {
        $question->update($request->validate([
            'practiced' => ['sometimes', 'boolean'],
            'my_notes' => ['sometimes', 'nullable', 'string', 'max:20000'],
        ]));

        return response()->noContent();
    }
}
