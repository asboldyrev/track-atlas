<?php

namespace App\Services;

use App\Models\Tag;
use App\Models\Track;

class FileGenreSynchronizer
{
    public function __construct(private GenreNormalizer $normalizer) {}

    /** Never overwrite manual associations or manually removed genre assignments. */
    public function sync(Track $track, array $genres): void
    {
        // This first implementation is intentionally additive: manual choices are preserved.
        foreach ($genres as $genre) {
            $name = $this->normalizer->canonical((string) $genre);
            $slug = $this->normalizer->slug($name);
            if ($slug === '' || mb_strlen($name) > 80) {
                continue;
            }

            $tag = Tag::firstOrCreate(
                ['type' => 'genre', 'slug' => $slug],
                ['name' => $name],
            );

            if (! $track->tags()->where('tags.id', $tag->id)->exists()) {
                $track->tags()->attach($tag->id, ['source' => 'file']);
            }
        }
    }
}
