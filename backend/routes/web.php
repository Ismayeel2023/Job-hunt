<?php

use App\Http\Controllers\GmailController;
use Illuminate\Support\Facades\Route;

// The website itself is the React app in ../frontend. These send stray visits there.
Route::get('/', fn () => redirect()->away(config('app.frontend_url')));
Route::get('/login', fn () => redirect()->away(rtrim(config('app.frontend_url'), '/').'/login'))->name('login');

// Google sends the user back to the backend, so the Gmail sign-in lives here.
Route::middleware('auth')->group(function () {
    Route::get('/settings/gmail/connect', [GmailController::class, 'connect'])->name('gmail.connect');
    Route::get('/settings/gmail/callback', [GmailController::class, 'callback'])->name('gmail.callback');
});
