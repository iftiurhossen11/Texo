<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PasswordRecoveryController;
use App\Http\Controllers\Api\SuggestedQuestionsController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1');
Route::post('/forgot-password', [PasswordRecoveryController::class, 'sendResetLink'])
    ->middleware('throttle:5,1');
Route::post('/reset-password', [PasswordRecoveryController::class, 'reset'])
    ->middleware('throttle:10,1');
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::get('/documents/{document}/view', [DocumentController::class, 'view']);
    Route::get('/documents/{document}', [DocumentController::class, 'show']);
    Route::get('/analytics', AnalyticsController::class);
    Route::get('/suggested-questions', SuggestedQuestionsController::class);
    Route::post('/documents', [DocumentController::class, 'store']);
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);

    Route::post('/chat/sessions', [ChatController::class, 'createSession']);
    Route::get('/chat/sessions/{session}', [ChatController::class, 'showSession']);
    Route::post('/chat/sessions/{session}/messages', [ChatController::class, 'storeMessage'])
        ->middleware('throttle:20,1');
});
