<?php

namespace App\Http\Requests\Post;

use App\DTO\Post\IndexPostData;
use Illuminate\Foundation\Http\FormRequest;

class IndexPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', 'in:published_at,title,created_at'],
            'direction' => ['sometimes', 'string', 'in:asc,desc'],
        ];
    }

    public function getDto(): IndexPostData
    {
        return IndexPostData::from($this->validated());
    }
}
