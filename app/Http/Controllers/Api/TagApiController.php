<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TagsIndexRequest;
use App\Models\Tag;
use App\Support\LikePattern;
use Illuminate\Http\JsonResponse;

class TagApiController extends Controller
{
    /**
     * Справочник тегов: пагинация плюс число треков на тег.
     * Русское название отдаётся колонкой name_ru (ранее — отдельный файл-словарь).
     */
    public function index(TagsIndexRequest $request): JsonResponse
    {
        $data = $request->validated();

        $query = Tag::query()
            // Считаем только видимые треки: отсутствующие файлы каталог не показывает.
            ->withCount(['tracks' => fn ($q) => $q->where('tracks.is_missing', false)])
            ->orderBy('type')
            ->orderBy('name');

        if ($type = ($data['type'] ?? null)) {
            $query->where('type', $type);
        }

        if ($search = trim((string) ($data['search'] ?? ''))) {
            // Подстановочные знаки LIKE экранируются: без этого «_» или «%»
            // совпадали бы с любым тегом.
            $pattern = LikePattern::contains($search);
            $query->where(fn ($q) => $q
                ->whereRaw(LikePattern::expression('name'), [$pattern])
                ->orWhereRaw(LikePattern::expression('name_ru'), [$pattern]));
        }

        return response()->json([
            'tags' => $query->paginate((int) config('api.limits.tags_per_page')),
        ]);
    }
}
