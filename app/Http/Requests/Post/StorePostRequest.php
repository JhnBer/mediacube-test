<?php

namespace App\Http\Requests\Post;

use App\Enums\Enumb\PostStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:255', 'unique:posts,title'],
            'body' => ['required', 'string'],
            'status' => ['sometimes', 'string', \Illuminate\Validation\Rule::enum(\App\Enums\Enumb\PostStatus::class)],
        ];
    }
}
