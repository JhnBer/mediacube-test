<?php

namespace App\Http\Requests\Post;

use App\DTO\Post\SearchPostData;
use App\Enums\PostStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SearchPostRequest extends FormRequest
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
            'q' => 'required|string|min:3',
            'status' => ['sometimes', 'string', Rule::enum(PostStatus::class)],
            'published_at.from' => 'sometimes|date',
            'published_at.to' => 'sometimes|date|after_or_equal:published_at.from',
        ];
    }

    public function getDto(): SearchPostData
    {
        return new SearchPostData(
            q: $this->validated('q'),
            status: $this->enum('status', PostStatus::class),
            from: $this->filled('published_at.from')
                ? Carbon::parse($this->validated('published_at.from'))->startOfDay()
                : null,
            to: $this->filled('published_at.to')
                ? Carbon::parse($this->validated('published_at.to'))->endOfDay()
                : null,
        );
    }
}
