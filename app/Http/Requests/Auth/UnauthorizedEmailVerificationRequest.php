<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UnauthorizedEmailVerificationRequest extends FormRequest
{
    protected User $user;

    public function authorize()
    {
        $this->user = User::findOrFail($this->route('id'));

        if (! hash_equals(sha1($this->user->getEmailForVerification()), (string) $this->route('hash'))) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            //
        ];
    }

    /**
     * Fulfill the email verification request.
     */
    public function fulfill(): void
    {
        if (! $this->user->hasVerifiedEmail()) {
            $this->user->markEmailAsVerified();

            event(new Verified($this->user));
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): Validator
    {
        return $validator;
    }
}
