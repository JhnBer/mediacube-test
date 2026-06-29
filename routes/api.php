<?php

use App\Enums\UserRole;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->as('auth.')->middleware('throttle:10,1')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('register', [AuthController::class, 'register'])->name('register');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth:sanctum');
});

Route::get('email/verify/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed'])
    ->name('verification.verify');

Route::middleware('throttle:100,1')->group(function () {
    Route::get('posts/search', [PostController::class, 'search'])->name('posts.search');

    Route::apiResources([
        'posts' => PostController::class,
        'comments' => CommentController::class,
    ], ['only' => ['index', 'show']]);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('posts', PostController::class)->except(['index', 'show']);
    Route::apiResource('comments', CommentController::class)->except(['index', 'show']);

    Route::prefix('stats')->as('stats.')->group(function () {
        Route::get('posts', [StatsController::class, 'posts'])->name('posts');
        Route::get('comments', [StatsController::class, 'comments'])->name('comments');
        Route::get('users', [StatsController::class, 'users'])->name('users');
        Route::post('clear-cache', [StatsController::class, 'clearCache'])->name('clear-cache');
    });
});

Route::prefix('meta')->as('meta.')->group(function () {
    Route::get('roles', function () {
        return response()->json(UserRole::cases());
    });
});
