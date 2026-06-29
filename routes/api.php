<?php

use Illuminate\Support\Facades\Route;

Route::prefix('auth')->as('auth.')->middleware('throttle:10,1')->group(function () {
    Route::post('login', [\App\Http\Controllers\AuthController::class, 'login'])->name('login');
    Route::post('register', [\App\Http\Controllers\AuthController::class, 'register'])->name('register');
    Route::post('logout', [\App\Http\Controllers\AuthController::class, 'logout'])->name('logout')->middleware('auth:sanctum');
});

Route::get('email/verify/{id}/{hash}', \App\Http\Controllers\VerifyEmailController::class)
    ->middleware(['signed'])
    ->name('verification.verify');

Route::get('posts/search', [\App\Http\Controllers\PostController::class, 'search']);

Route::apiResources([
    'posts' => \App\Http\Controllers\PostController::class,
    'comments' => \App\Http\Controllers\CommentController::class,
], ['only' => ['index', 'show']]);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('posts', \App\Http\Controllers\PostController::class)->except(['index', 'show']);
    Route::apiResource('comments', \App\Http\Controllers\CommentController::class)->except(['index', 'show']);
});

Route::prefix('meta')->as('meta.')->group(function () {
    Route::get('roles', function () {
        return response()->json(\App\Enums\UserRole::cases());
    });
});
