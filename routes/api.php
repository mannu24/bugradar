<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\PullRequestController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReviewController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Integrations
    Route::get('/integrations', [IntegrationController::class, 'index']);
    Route::delete('/integrations/{integration}', [IntegrationController::class, 'disconnect']);
    Route::post('/integrations/{integration}/sync', [IntegrationController::class, 'sync']);

    // Pull Requests
    Route::get('/pull-requests', [PullRequestController::class, 'index']);
    Route::get('/pull-requests/reviewed', [PullRequestController::class, 'reviewed']);
    Route::get('/pull-requests/{pullRequest}', [PullRequestController::class, 'show']);

    // Issues
    Route::get('/issues', [IssueController::class, 'index']);
    Route::get('/issues/bugs', [IssueController::class, 'bugs']);
    Route::get('/issues/tasks', [IssueController::class, 'tasks']);
    Route::get('/issues/{issue}', [IssueController::class, 'show']);

    // Reviews
    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::get('/reviews/stats', [ReviewController::class, 'stats']);
    Route::get('/reviews/{review}', [ReviewController::class, 'show']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/recent', [DashboardController::class, 'recent']);
});

