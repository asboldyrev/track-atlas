<?php

namespace App\Queries;

use App\Models\Track;
use Illuminate\Database\Eloquent\Builder;

/**
 * Сборка выборки каталога: единственное место, где определены фильтры
 * списка треков (поиск, жанры, настроения, «без категорий») и серверная
 * сортировка. Контроллеры чтения не собирают запрос сами — они передают
 * сюда прошедшие валидацию параметры.
 */
class TrackCatalogQuery
{
    /**
     * @param  array<string, mixed>  $filters  результат FormRequest::validated()
     */
    public function build(array $filters): Builder
    {
        $query = Track::query()
            ->where('is_missing', false)
            // Предложения отдаём вместе со строкой списка, чтобы интерфейс
            // не делал отдельный запрос на каждый трек.
            ->with(['tags', 'metadataSuggestions' => fn ($q) => $q->where('provider', 'openrouter')]);

        if ($search = ($filters['search'] ?? null)) {
            $query->search($search);
        }

        // OR внутри группы (жанры между собой, настроения между собой),
        // AND между группами.
        foreach (['genres' => 'genre', 'moods' => 'mood'] as $key => $type) {
            $ids = $filters[$key] ?? [];
            if ($ids !== []) {
                $query->whereHas('tags', fn ($q) => $q->where('type', $type)->whereIn('tags.id', $ids));
            }
        }

        if ($untagged = ($filters['untagged'] ?? null)) {
            if ($untagged === 'all') {
                $query->doesntHave('tags');
            } else {
                $query->whereDoesntHave('tags', fn ($q) => $q->where('type', $untagged));
            }
        }

        // Тай-брейк по id: при одинаковых artist/title порядок строк иначе не
        // определён, и пагинация может повторить или пропустить запись.
        return $query->orderBy('artist')->orderBy('title')->orderBy('tracks.id');
    }

    /**
     * Готовая страница списка для JSON-ответа.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage)
    {
        return $this->build($filters)->paginate($perPage);
    }
}
