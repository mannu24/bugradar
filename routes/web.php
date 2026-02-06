<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\DevAuthController;

Route::get('/', function () {
    return view('welcome');
});

// Development-only auth bypass (REMOVE IN PRODUCTION!)
if (config('app.env') === 'local') {
    Route::get('/api/auth/dev-login', [DevAuthController::class, 'devLogin']);
}

// OAuth Authentication Routes (need sessions, so in web.php)
Route::get('/api/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/api/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::get('/api/auth/github', [AuthController::class, 'redirectToGithub']);
Route::get('/api/auth/github/callback', [AuthController::class, 'handleGithubCallback']);

// Integration OAuth Routes (use web middleware for session-based auth)
Route::middleware('web')->group(function () {
    Route::get('/api/integrations/github/connect', [IntegrationController::class, 'connectGithub']);
    Route::get('/api/integrations/gitlab/connect', [IntegrationController::class, 'connectGitlab']);
    Route::get('/api/integrations/bitbucket/connect', [IntegrationController::class, 'connectBitbucket']);
});

// Integration OAuth Callbacks (need sessions)
Route::get('/api/integrations/github/callback', [IntegrationController::class, 'handleGithubCallback']);
Route::get('/api/integrations/gitlab/callback', [IntegrationController::class, 'handleGitlabCallback']);
Route::get('/api/integrations/bitbucket/callback', [IntegrationController::class, 'handleBitbucketCallback']);
