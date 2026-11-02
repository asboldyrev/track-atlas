<?php

namespace App\Http\Controllers;

use App\Models\Track;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TrackController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'genres' => ['sometimes', 'array'],
            'genres.*' => ['integer', 'exists:tags,id'],
            'moods' => ['sometimes', 'array'],
            'moods.*' => ['integer', 'exists:tags,id'],
        ]);

        $query = Track::query()->where('is_missing', false)->with('tags');
        if ($search = ($filters['search'] ?? null)) {
            $escaped = addcslashes($search, '%_\\');
            $query->where(fn ($q) => $q->where('title', 'like', "%$escaped%")
                ->orWhere('artist', 'like', "%$escaped%"));
        }
        foreach (['genres' => 'genre', 'moods' => 'mood'] as $key => $type) {
            $ids = $filters[$key] ?? [];
            if ($ids) {
                $query->whereHas('tags', fn ($q) => $q->where('type', $type)->whereIn('tags.id', $ids));
            }
        }

        return view('tracks.index', [
            'tracks' => $query->orderBy('artist')->orderBy('title')->paginate(30)->withQueryString(),
            'tags' => Tag::orderBy('name')->get()->groupBy('type'),
            'filters' => $filters,
        ]);
    }

    public function update(Request $request, Track $track)
    {
        $data = $request->validate([
            'tags' => ['present', 'array'],
            'tags.*' => ['integer', 'distinct', 'exists:tags,id'],
        ]);
        // Explicit manual edit is authoritative.
        $track->tags()->sync(collect($data['tags'])->mapWithKeys(fn ($id) => [$id => ['source' => 'manual']])->all());
        return back()->with('status', 'Categories saved.');
    }

    public function storeTag(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['genre', 'mood'])],
            'name' => ['required', 'string', 'max:80'],
        ]);
        $name = trim($data['name']);
        $slug = Str::slug($name);
        if (! $slug) {
            return back()->withErrors(['name' => 'Please use a tag name with letters or numbers.']);
        }
        Tag::firstOrCreate(['type' => $data['type'], 'slug' => $slug], ['name' => $name]);
        return back()->with('status', 'Category saved.');
    }
}
