<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\UnauthorizedEmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(UnauthorizedEmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('home');
    }
}
