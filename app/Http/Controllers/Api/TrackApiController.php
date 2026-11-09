<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrackApiController extends Controller
{
    /**
     * Список треков с теми же фильтрами, что у /catalog, но без справочника тегов
     * и переводов: за них отвечает /api/tags.
     *
     * Сборка выборки намеренно повторяет /catalog: вынос в App\Queries\TrackCatalogQuery
     * назначен отдельной задаче, поэтому дублирование здесь временное.
     */
    public function index(Request $request): JsonResponse
    {
        $limits = config('api.limits');

        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:'.(int) $limits['search_max_length']],
            'genres' => ['sometimes', 'array'],
            'genres.*' => ['integer', 'exists:tags,id'],
            'moods' => ['sometimes', 'array'],
            'moods.*' => ['integer', 'exists:tags,id'],
            'untagged' => ['sometimes', Rule::in(['genre', 'mood', 'all'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = Track::query()
            ->where('is_missing', false)
            ->with(['tags', 'metadataSuggestions' => fn ($q) => $q->where('provider', 'openrouter')]);

        if ($search = ($filters['search'] ?? null)) {
            $query->search($search);
        }

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

        return response()->json([
            'tracks' => $query->orderBy('artist')->orderBy('title')
                ->paginate((int) $limits['tracks_per_page']),
        ]);
    }
}
