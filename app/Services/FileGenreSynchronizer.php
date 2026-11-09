<?php

namespace App\Services;

use App\Models\Tag;
use App\Models\Track;
use App\Models\TrackTagDecision;

class FileGenreSynchronizer
{
    public function __construct(private GenreNormalizer $normalizer) {}

    /**
     * Дополняет трек жанрами из файловых тегов, не трогая ручные решения:
     * уже стоящие метки сохраняются, а снятые вручную (ignored) не возвращаются.
     */
    public function sync(Track $track, array $genres): void
    {
        $ignored = TrackTagDecision::query()
            ->where('track_id', $track->id)
            ->where('decision', TrackTagDecision::IGNORED)
            ->pluck('tag_id')
            ->all();
        $ignored = array_flip($ignored);

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

            if (isset($ignored[$tag->id])) {
                continue;
            }

            if (! $track->tags()->where('tags.id', $tag->id)->exists()) {
                $track->tags()->attach($tag->id, ['source' => 'file']);
            }
        }
    }
}
