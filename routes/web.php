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

// Integration OAuth connect routes
// GitHub reuses the login OAuth app — its callback is /api/auth/github/callback
// above, which routes login vs integration internally via the signed state param.
Route::get('/api/integrations/github/connect', [IntegrationController::class, 'connectGithub']);
Route::get('/api/integrations/gitlab/connect', [IntegrationController::class, 'connectGitlab']);
Route::get('/api/integrations/bitbucket/connect', [IntegrationController::class, 'connectBitbucket']);

// Integration OAuth Callbacks for GitLab & Bitbucket (need sessions)
Route::get('/api/integrations/gitlab/callback', [IntegrationController::class, 'handleGitlabCallback']);
Route::get('/api/integrations/bitbucket/callback', [IntegrationController::class, 'handleBitbucketCallback']);
