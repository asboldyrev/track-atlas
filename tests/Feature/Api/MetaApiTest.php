<?php

namespace Tests\Feature\Api;

use App\Models\Tag;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MetaApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeTrack(string $title): Track
    {
        return Track::create([
            'path' => $title.'.mp3',
            'title' => $title,
            'artist' => 'Artist',
            'size' => 1,
            'file_mtime' => 1,
        ]);
    }

    public function test_meta_publishes_limits_and_locales(): void
    {
        $this->getJson('/api/meta')
            ->assertOk()
            ->assertJsonPath('name', 'track-atlas')
            ->assertJsonPath('locales.default', 'ru')
            ->assertJsonPath('locales.available', ['ru'])
            ->assertJsonPath('limits.batch_tracks_max', 200)
            ->assertJsonPath('limits.ai_tracks_max', 10)
            ->assertJsonPath('limits.tracks_per_page', 30)
            ->assertJsonPath('limits.tags_per_page', 50)
            ->assertJsonPath('limits.search_max_length', 200)
            ->assertJsonPath('limits.tag_name_max_length', 80)
            ->assertJsonPath('limits.tag_name_ru_max_length', 120)
            ->assertJsonPath('ai.model', config('openrouter.model'));
    }

    public function test_published_batch_limit_is_the_enforced_limit(): void
    {
        $limit = (int) $this->getJson('/api/meta')->json('limits.batch_tracks_max');
        $tag = Tag::create(['type' => 'mood', 'name' => 'Dark', 'slug' => Str::slug('Dark')]);

        $ids = [];
        for ($i = 1; $i <= $limit; $i++) {
            $ids[] = $this->makeTrack('Track '.$i)->id;
        }

        $this->postJson('/tracks/batch-tags', ['tracks' => $ids, 'tags' => [$tag->id], 'mode' => 'add'])
            ->assertOk()
            ->assertJsonPath('updated', $limit);

        $ids[] = $this->makeTrack('Extra')->id;

        $this->postJson('/tracks/batch-tags', ['tracks' => $ids, 'tags' => [$tag->id], 'mode' => 'add'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tracks');
    }

    public function test_published_ai_limit_is_the_enforced_limit(): void
    {
        $limit = (int) $this->getJson('/api/meta')->json('limits.ai_tracks_max');

        $ids = [];
        for ($i = 1; $i <= $limit + 1; $i++) {
            $ids[] = $this->makeTrack('Track '.$i)->id;
        }

        // Запрос отсекается валидацией до обращения к OpenRouter.
        $this->postJson('/ai/classify', ['tracks' => $ids])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tracks');
    }
}
