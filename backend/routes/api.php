<?php

use App\Http\Controllers\Agent\ApplicationController;
use App\Http\Controllers\Agent\ApplyRunController;
use App\Http\Controllers\Agent\CompanyController;
use App\Http\Controllers\Agent\CompanyInterviewController;
use App\Http\Controllers\Agent\EmailController;
use App\Http\Controllers\Agent\JobController;
use App\Http\Controllers\Agent\ResumeVersionController;
use App\Http\Controllers\Agent\SummaryController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ApplicationController as AppApplicationController;
use App\Http\Controllers\ApplyRunController as AppApplyRunController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GmailController;
use App\Http\Controllers\InterviewPrepController;
use App\Http\Controllers\JobController as AppJobController;
use App\Http\Controllers\JobDetailController;
use App\Http\Controllers\ResumeController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SettingsController;
use App\Http\Middleware\AuthenticateAgent;
use Illuminate\Support\Facades\Route;

// The React app (../frontend), signed in with a session cookie through Sanctum.
Route::prefix('app')->group(function () {
    Route::post('login', [SessionController::class, 'store'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('user', [SessionController::class, 'show']);
        Route::post('logout', [SessionController::class, 'destroy']);
        Route::patch('profile', [AccountController::class, 'update']);
        Route::put('password', [AccountController::class, 'password']);

        Route::get('dashboard', DashboardController::class);

        Route::get('jobs', [AppJobController::class, 'index']);
        Route::get('jobs/{job}', JobDetailController::class);
        Route::post('jobs/{job}/apply', [AppApplyRunController::class, 'store']);
        Route::post('apply-runs/{run}/answers', [AppApplyRunController::class, 'answer']);
        Route::post('apply-runs/{run}/confirm', [AppApplyRunController::class, 'confirm']);
        Route::post('apply-runs/{run}/cancel', [AppApplyRunController::class, 'cancel']);

        Route::get('applications', [AppApplicationController::class, 'index']);
        Route::get('applications/{application}', [AppApplicationController::class, 'show']);
        Route::patch('applications/{application}/status', [AppApplicationController::class, 'updateStatus']);

        Route::get('resumes', [ResumeController::class, 'index']);
        Route::get('resumes/{resumeVersion}', [ResumeController::class, 'show']);
        Route::get('resumes/{resumeVersion}/pdf', [ResumeController::class, 'pdf']);

        Route::get('prep', [InterviewPrepController::class, 'index']);
        Route::patch('prep/questions/{question}', [InterviewPrepController::class, 'updateQuestion']);

        Route::get('settings', SettingsController::class);
        Route::patch('settings/candidate', [CandidateController::class, 'update']);
        Route::delete('settings/answers/{index}', [CandidateController::class, 'forgetAnswer'])->whereNumber('index');
        Route::post('settings/gmail/sync', [GmailController::class, 'sync']);
        Route::delete('settings/gmail/{gmailAccount}', [GmailController::class, 'destroy']);
    });
});

Route::prefix('agent')->middleware(AuthenticateAgent::class)->group(function () {
    Route::get('jobs', [JobController::class, 'index']);
    Route::post('jobs', [JobController::class, 'upsert']);
    Route::get('jobs/{job}', [JobController::class, 'show']);
    Route::patch('jobs/{job}', [JobController::class, 'update']);

    Route::post('resume-versions', [ResumeVersionController::class, 'store']);

    Route::get('applications', [ApplicationController::class, 'index']);
    Route::post('applications', [ApplicationController::class, 'store']);
    Route::post('applications/{application}/status', [ApplicationController::class, 'updateStatus']);

    Route::get('companies', [CompanyController::class, 'index']);
    Route::post('companies', [CompanyController::class, 'store']);
    Route::post('companies/{company}/rounds', [CompanyInterviewController::class, 'storeRound']);
    Route::post('companies/{company}/questions', [CompanyInterviewController::class, 'storeQuestions']);

    Route::get('emails', [EmailController::class, 'index']);
    Route::patch('emails/{email}', [EmailController::class, 'update']);

    Route::get('summary', SummaryController::class);

    Route::get('apply-runs/{run}', [ApplyRunController::class, 'show']);
    Route::patch('apply-runs/{run}', [ApplyRunController::class, 'update']);
});
