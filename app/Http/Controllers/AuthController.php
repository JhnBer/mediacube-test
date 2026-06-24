<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = User::create($request->validated());
            $user->sendEmailVerificationNotification();

            auth()->login($user);

            return response()->json([
                'message' => 'Check your email for verification.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (!auth()->attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'These credentials do not match our records.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!auth()->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Your email is not verified.'
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'message' => 'You have successfully logged in.'
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        auth()->guard('web')->logout();

        session()->invalidate();

        return response()->json([
            'message' => 'You have successfully logged out.'
        ]);
    }
}
