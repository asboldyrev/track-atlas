<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Массовое добавление/удаление метки (POST /tracks/batch-tags).
 */
class BatchTagsRequest extends FormRequest
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
            'tracks' => ['required', 'array', 'min:1', 'max:'.(int) config('api.limits.batch_tracks_max')],
            'tracks.*' => ['required', 'integer', 'distinct', 'exists:tracks,id'],
            'tags' => ['required', 'array', 'min:1'],
            'tags.*' => ['integer', 'distinct', 'exists:tags,id'],
            'mode' => ['required', Rule::in(['add', 'remove'])],
        ];
    }
}
