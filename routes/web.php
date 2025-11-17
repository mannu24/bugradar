<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return view('welcome');
});

// OAuth Authentication Routes (need sessions, so in web.php)
Route::get('/api/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/api/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::get('/api/auth/github', [AuthController::class, 'redirectToGithub']);
Route::get('/api/auth/github/callback', [AuthController::class, 'handleGithubCallback']);
