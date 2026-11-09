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
            // Режим группы: «или» (по умолчанию) или «и». Значение вне
            // белого списка — 422, как у остальных параметров.
            'genres_mode' => ['sometimes', 'string', Rule::in(['or', 'and'])],
            'moods_mode' => ['sometimes', 'string', Rule::in(['or', 'and'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            // Сортировка и размер страницы: только значения из белых списков
            // config/api.php — произвольная колонка в ORDER BY не попадёт.
            'sort' => ['sometimes', 'string', Rule::in(array_keys(config('api.limits.tracks_sort_columns')))],
            'dir' => ['sometimes', 'string', Rule::in(config('api.limits.tracks_sort_directions'))],
            'per_page' => ['sometimes', 'integer', Rule::in(config('api.limits.tracks_per_page_options'))],
        ];
    }
}
