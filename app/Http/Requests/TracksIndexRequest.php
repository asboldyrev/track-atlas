<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Параметры списка треков (GET /api/tracks). Совпадают с фильтрами каталога.
 */
class TracksIndexRequest extends FormRequest
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
            'search' => ['sometimes', 'nullable', 'string', 'max:'.(int) config('api.limits.search_max_length')],
            'genres' => ['sometimes', 'array'],
            // id обязан принадлежать своему типу: жанр из moods[] (или наоборот)
            // раньше принимался и молча давал пустую выборку.
            'genres.*' => ['integer', Rule::exists('tags', 'id')->where('type', 'genre')],
            'moods' => ['sometimes', 'array'],
            'moods.*' => ['integer', Rule::exists('tags', 'id')->where('type', 'mood')],
            'untagged' => ['sometimes', Rule::in(['genre', 'mood', 'all'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
