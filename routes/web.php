<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ApplyRunController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GmailController;
use App\Http\Controllers\InterviewPrepController;
use App\Http\Controllers\JobDetailController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ResumeController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
    Route::get('/jobs/{job}', JobDetailController::class)->name('jobs.show');
    Route::post('/jobs/{job}/apply', [ApplyRunController::class, 'store'])->name('jobs.apply');
    Route::post('/apply-runs/{run}/answers', [ApplyRunController::class, 'answer'])->name('apply-runs.answer');
    Route::post('/apply-runs/{run}/confirm', [ApplyRunController::class, 'confirm'])->name('apply-runs.confirm');
    Route::post('/apply-runs/{run}/cancel', [ApplyRunController::class, 'cancel'])->name('apply-runs.cancel');

    Route::get('/applications', [ApplicationController::class, 'index'])->name('applications.index');
    Route::get('/applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
    Route::patch('/applications/{application}/status', [ApplicationController::class, 'updateStatus'])->name('applications.status');

    Route::get('/resumes', [ResumeController::class, 'index'])->name('resumes.index');
    Route::get('/resumes/{resumeVersion}', [ResumeController::class, 'show'])->name('resumes.show');
    Route::get('/resumes/{resumeVersion}/pdf', [ResumeController::class, 'pdf'])->name('resumes.pdf');

    Route::get('/prep', [InterviewPrepController::class, 'index'])->name('prep.index');
    Route::patch('/prep/questions/{question}', [InterviewPrepController::class, 'updateQuestion'])->name('prep.questions.update');

    Route::get('/settings', SettingsController::class)->name('settings.index');
    Route::patch('/settings/candidate', [CandidateController::class, 'update'])->name('candidate.update');
    Route::delete('/settings/answers/{index}', [CandidateController::class, 'forgetAnswer'])->whereNumber('index')->name('candidate.answers.destroy');
    Route::get('/settings/gmail/connect', [GmailController::class, 'connect'])->name('gmail.connect');
    Route::get('/settings/gmail/callback', [GmailController::class, 'callback'])->name('gmail.callback');
    Route::post('/settings/gmail/sync', [GmailController::class, 'sync'])->name('gmail.sync');
    Route::delete('/settings/gmail/{gmailAccount}', [GmailController::class, 'destroy'])->name('gmail.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
