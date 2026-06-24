<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\UnauthorizedEmailVerificationRequest;

class VerifyEmailController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(UnauthorizedEmailVerificationRequest $request)
    {
        $request->fulfill();

        return redirect()->route('home');
    }
}
