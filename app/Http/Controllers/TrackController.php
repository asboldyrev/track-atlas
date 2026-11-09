<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\Track;
use App\Services\GenreNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TrackController extends Controller
{
    public function index()
    {
        return view('tracks.index');
    }

    public function catalog(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'genres' => ['sometimes', 'array'],
            'genres.*' => ['integer', 'exists:tags,id'],
            'moods' => ['sometimes', 'array'],
            'moods.*' => ['integer', 'exists:tags,id'],
            'untagged' => ['sometimes', Rule::in(['genre', 'mood', 'all'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = Track::query()->where('is_missing', false)->with(['tags', 'metadataSuggestions' => fn ($query) => $query->where('provider', 'openrouter')]);

        if ($search = ($filters['search'] ?? null)) {
            $query->where(fn ($q) => $q->where('title', 'like', '%'.$search.'%')
                ->orWhere('artist', 'like', '%'.$search.'%'));
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
            'tracks' => $query->orderBy('artist')->orderBy('title')->paginate(30),
            'tags' => Tag::orderBy('name')->get()->groupBy('type'),
            'tag_translations' => config('tag_translations'),
        ]);
    }

    public function update(Request $request, Track $track): JsonResponse
    {
        $data = $request->validate([
            'tags' => ['present', 'array'],
            'tags.*' => ['integer', 'distinct', 'exists:tags,id'],
        ]);

        $track->tags()->sync(collect($data['tags'])
            ->mapWithKeys(fn ($id) => [$id => ['source' => 'manual']])->all());

        return response()->json(['ok' => true]);
    }

    public function batchTags(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tracks' => ['required', 'array', 'min:1', 'max:'.(int) config('api.limits.batch_tracks_max')],
            'tracks.*' => ['required', 'integer', 'distinct', 'exists:tracks,id'],
            'tags' => ['required', 'array', 'min:1'],
            'tags.*' => ['integer', 'distinct', 'exists:tags,id'],
            'mode' => ['required', Rule::in(['add', 'remove'])],
        ]);

        $tagIds = $data['tags'];
        $tracks = Track::whereIn('id', $data['tracks'])->get();
        foreach ($tracks as $track) {
            if ($data['mode'] === 'add') {
                $track->tags()->syncWithoutDetaching(
                    collect($tagIds)->mapWithKeys(fn ($id) => [$id => ['source' => 'manual']])->all()
                );
            } else {
                $track->tags()->detach($tagIds);
            }
        }

        return response()->json(['updated' => $tracks->count()]);
    }

    public function updateTranslation(Request $request, Tag $tag): JsonResponse
    {
        $data = $request->validate([
            'name_ru' => ['present', 'nullable', 'string', 'max:120'],
        ]);
        $tag->update(['name_ru' => trim($data['name_ru'] ?? '') ?: null]);

        return response()->json(['tag' => $tag->fresh()]);
    }

    public function storeTag(Request $request, GenreNormalizer $normalizer): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['genre', 'mood'])],
            'name' => ['required', 'string', 'max:80'],
        ]);
        $name = $data['type'] === 'genre' ? $normalizer->canonical($data['name']) : trim($data['name']);
        $slug = $data['type'] === 'genre' ? $normalizer->slug($name) : Str::slug($name);
        if ($slug === '') {
            return response()->json(['message' => 'Введите название категории.'], 422);
        }

        $tag = Tag::firstOrCreate(['type' => $data['type'], 'slug' => $slug], ['name' => $name]);

        return response()->json(['tag' => $tag], 201);
    }
}
