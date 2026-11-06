<?php

use App\Http\Controllers\Agent\ApplicationController;
use App\Http\Controllers\Agent\ApplyRunController;
use App\Http\Controllers\Agent\CompanyController;
use App\Http\Controllers\Agent\CompanyInterviewController;
use App\Http\Controllers\Agent\EmailController;
use App\Http\Controllers\Agent\JobController;
use App\Http\Controllers\Agent\ResumeVersionController;
use App\Http\Controllers\Agent\SummaryController;
use App\Http\Middleware\AuthenticateAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

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
