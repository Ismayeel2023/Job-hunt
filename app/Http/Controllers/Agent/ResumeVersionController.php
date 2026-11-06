<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StoreResumeVersionRequest;
use App\Models\Job;
use App\Models\ResumeVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class ResumeVersionController extends Controller
{
    public function store(StoreResumeVersionRequest $request): JsonResponse
    {
        $data = $request->payload();

        $resumeVersion = ResumeVersion::create([
            ...Arr::except($data, 'pdf'),
            'label' => $data['label'] ?? 'Tailored for '.Job::find($data['job_id'])->title,
            'pdf_path' => $request->file('pdf')?->store('', 'resumes'),
        ]);

        return response()->json($resumeVersion->refresh()->makeHidden('tex_source'), 201);
    }
}
