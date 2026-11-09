<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Создание категории (POST /tags).
 */
class StoreTagRequest extends FormRequest
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
            'type' => ['required', Rule::in(['genre', 'mood'])],
            'name' => ['required', 'string', 'max:'.(int) config('api.limits.tag_name_max_length')],
        ];
    }
}
