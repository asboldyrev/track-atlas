<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Правка русского названия категории (PUT /tags/{tag}/translation).
 */
class TagTranslationUpdateRequest extends FormRequest
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
            'name_ru' => ['present', 'nullable', 'string', 'max:'.(int) config('api.limits.tag_name_ru_max_length')],
        ];
    }
}
