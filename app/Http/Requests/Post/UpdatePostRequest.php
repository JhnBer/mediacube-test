<?php

namespace App\Http\Requests\Post;

use App\Enums\Enum\PostStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'min:2', 'max:255', Rule::unique('posts')->ignore($this->route('post')->id)],
            'body' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string', Rule::enum(PostStatus::class)],
        ];
    }
}
