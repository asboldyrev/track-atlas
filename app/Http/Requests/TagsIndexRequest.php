<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Параметры справочника тегов (GET /api/tags).
 */
class TagsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'nullable', Rule::in(['genre', 'mood'])],
            'search' => ['sometimes', 'nullable', 'string', 'max:'.(int) config('api.limits.search_max_length')],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
