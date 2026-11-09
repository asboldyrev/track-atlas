<?php

namespace App\Http\Controllers;

use App\Http\Requests\BatchTagsRequest;
use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\TagTranslationUpdateRequest;
use App\Http\Requests\TrackTagsUpdateRequest;
use App\Models\Tag;
use App\Models\Track;
use App\Services\GenreNormalizer;
use App\Services\TagDecisionSynchronizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TrackController extends Controller
{
    /**
     * Blade-обёртка с точкой монтирования Vue: один и тот же шаблон для `/`,
     * `/categories` и `/tagging` (страницу выбирает клиент по pathname).
     */
    public function index()
    {
        return view('tracks.index');
    }

    public function update(TrackTagsUpdateRequest $request, Track $track, TagDecisionSynchronizer $decisions): JsonResponse
    {
        $tagIds = array_map('intval', $request->validated()['tags']);
        DB::transaction(fn () => $decisions->replace($track, $tagIds));

        return response()->json(['ok' => true]);
    }

    public function batchTags(BatchTagsRequest $request, TagDecisionSynchronizer $decisions): JsonResponse
    {
        $data = $request->validated();
        $trackIds = array_map('intval', $data['tracks']);
        $tagIds = array_map('intval', $data['tags']);

        // Один транзакционный блок на весь батч: частичное применение недопустимо.
        DB::transaction(function () use ($data, $trackIds, $tagIds, $decisions) {
            if ($data['mode'] === 'add') {
                $decisions->accept($trackIds, $tagIds);
            } else {
                $decisions->ignore($trackIds, $tagIds);
            }
        });

        return response()->json(['updated' => count($trackIds)]);
    }

    public function updateTranslation(TagTranslationUpdateRequest $request, Tag $tag): JsonResponse
    {
        $nameRu = trim((string) ($request->validated()['name_ru'] ?? ''));
        $tag->update(['name_ru' => $nameRu !== '' ? $nameRu : null]);

        return response()->json(['tag' => $tag->fresh()]);
    }

    public function storeTag(StoreTagRequest $request, GenreNormalizer $normalizer): JsonResponse
    {
        $data = $request->validated();
        $name = $data['type'] === 'genre' ? $normalizer->canonical($data['name']) : trim($data['name']);
        $slug = $data['type'] === 'genre' ? $normalizer->slug($name) : Str::slug($name);
        if ($slug === '') {
            return response()->json(['message' => 'Введите название категории.'], 422);
        }

        $tag = Tag::firstOrCreate(['type' => $data['type'], 'slug' => $slug], ['name' => $name]);

        // 201 — только для действительно созданной категории; повторное создание
        // возвращает существующую с 200, чтобы клиент отличал обе ситуации.
        return response()->json(['tag' => $tag], $tag->wasRecentlyCreated ? 201 : 200);
    }
}
