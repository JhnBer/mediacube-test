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

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('posts', \App\Http\Controllers\PostController::class);
    Route::apiResource('comments', \App\Http\Controllers\CommentController::class);
});
