<?php

namespace App\Http\Controllers;

use App\Models\ResumeVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResumeController extends Controller
{
    public function index(): JsonResponse
    {
        $versions = ResumeVersion::with(['job.company:id,name', 'applications:id,resume_version_id,job_id,status', 'applications.job:id,company_id', 'applications.job.company:id,name'])
            ->orderByDesc('is_base')
            ->latest('created_at')
            ->latest('id')
            ->get(['id', 'job_id', 'label', 'pdf_path', 'keywords_covered', 'is_base', 'created_at'])
            ->map(fn (ResumeVersion $version) => [
                'id' => $version->id,
                'label' => $version->label,
                'is_base' => $version->is_base,
                'has_pdf' => $version->pdf_path !== null,
                'keywords_covered' => $version->keywords_covered ?? [],
                'created_at' => $version->created_at,
                'job_title' => $version->job?->title,
                'company' => $version->job?->company->name,
                'applications' => $version->applications->map(fn ($application) => [
                    'id' => $application->id,
                    'status' => $application->status,
                    'company' => $application->job->company->name,
                ]),
            ]);

        return response()->json(['versions' => $versions]);
    }

    public function show(ResumeVersion $resumeVersion): JsonResponse
    {
        $resumeVersion->load(['job:id,company_id,title,url', 'job.company:id,name', 'applications:id,resume_version_id,status']);

        return response()->json([
            'version' => $resumeVersion,
            'baseResume' => $resumeVersion->is_base ? null : ResumeVersion::where('is_base', true)->latest('id')->first(['id', 'label', 'tex_source']),
        ]);
    }

    public function pdf(Request $request, ResumeVersion $resumeVersion): StreamedResponse
    {
        abort_unless($resumeVersion->pdf_path && Storage::disk('resumes')->exists($resumeVersion->pdf_path), 404);

        $filename = Str::slug($resumeVersion->label).'.pdf';

        return Storage::disk('resumes')->response(
            $resumeVersion->pdf_path,
            $filename,
            ['Content-Type' => 'application/pdf'],
            $request->boolean('download') ? 'attachment' : 'inline',
        );
    }
}
