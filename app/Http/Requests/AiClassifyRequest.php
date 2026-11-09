<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Запрос классификации пачки треков (POST /ai/classify).
 */
class AiClassifyRequest extends FormRequest
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
            'tracks' => ['required', 'array', 'min:1', 'max:'.(int) config('api.limits.ai_tracks_max')],
            'tracks.*' => ['required', 'integer', 'distinct', 'exists:tracks,id'],
            'retry_empty' => ['sometimes', 'boolean'],
        ];
    }
}
