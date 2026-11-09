<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Полная замена категорий одного трека (PUT /tracks/{track}/tags).
 */
class TrackTagsUpdateRequest extends FormRequest
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
            'tags' => ['present', 'array'],
            'tags.*' => ['integer', 'distinct', 'exists:tags,id'],
        ];
    }
}
