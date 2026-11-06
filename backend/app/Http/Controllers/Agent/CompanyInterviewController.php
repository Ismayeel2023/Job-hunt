<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StoreInterviewQuestionsRequest;
use App\Http\Requests\Agent\StoreInterviewRoundRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CompanyInterviewController extends Controller
{
    /**
     * A round with the same name already saved for the company is returned instead of duplicated.
     */
    public function storeRound(StoreInterviewRoundRequest $request, Company $company): JsonResponse
    {
        $data = $request->payload();
        $existing = $company->interviewRounds()->whereRaw('lower(round_name) = ?', [mb_strtolower($data['round_name'])])->first();

        if ($existing) {
            return response()->json($existing);
        }

        return response()->json($company->interviewRounds()->create($data), 201);
    }

    /**
     * Questions already saved for the company, ignoring case and spacing, are skipped, so research can be re-run safely.
     */
    public function storeQuestions(StoreInterviewQuestionsRequest $request, Company $company): JsonResponse
    {
        $key = fn (string $question) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($question)));
        $seen = $company->interviewQuestions()->pluck('question')->map($key)->flip()->all();
        $new = [];
        $skipped = 0;

        foreach ($request->validated('questions') as $question) {
            if (isset($seen[$key($question['question'])])) {
                $skipped++;

                continue;
            }

            $seen[$key($question['question'])] = true;
            $new[] = $question;
        }

        $questions = DB::transaction(fn () => $company->interviewQuestions()->createMany($new));

        return response()->json(['count' => $questions->count(), 'skipped' => $skipped, 'questions' => $questions], 201);
    }
}
