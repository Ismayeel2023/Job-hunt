<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\ListCompaniesRequest;
use App\Http\Requests\Agent\StoreCompanyRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    public function index(ListCompaniesRequest $request): JsonResponse
    {
        $search = $request->validated('search');

        $companies = Company::query()
            ->with('interviewRounds:id,company_id,round_name,round_type')
            ->withCount(['jobs', 'applications', 'interviewQuestions'])
            ->when($search, fn ($query) => $query->whereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%']))
            ->orderBy('name')
            ->limit(50)
            ->get();

        return response()->json(['data' => $companies]);
    }

    /**
     * Finds a company by name, ignoring case, or creates it. An existing company's details are left alone.
     */
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $data = $request->payload();
        $company = Company::whereRaw('lower(name) = ?', [mb_strtolower($data['name'])])->first();

        if ($company) {
            return response()->json($company);
        }

        return response()->json(Company::create($data), 201);
    }
}
