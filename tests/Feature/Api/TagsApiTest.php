<?php

namespace Tests\Feature\Api;

use App\Models\Tag;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TagsApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeTrack(string $title, bool $missing = false): Track
    {
        return Track::create([
            'path' => $title.'.mp3',
            'title' => $title,
            'artist' => 'Artist',
            'size' => 1,
            'file_mtime' => 1,
            'is_missing' => $missing,
        ]);
    }

    private function makeTag(string $type, string $name, ?string $nameRu = null): Tag
    {
        return Tag::create([
            'type' => $type,
            'name' => $name,
            'slug' => Str::slug($name),
            'name_ru' => $nameRu,
        ]);
    }

    /**
     * Размер страницы берётся из /api/meta: это и есть проверка, что лимит
     * публикуется, а не хардкодится на клиенте.
     */
    private function pageSize(): int
    {
        return (int) $this->getJson('/api/meta')->assertOk()->json('limits.tags_per_page');
    }

    public function test_tags_are_paginated_with_the_page_size_published_in_meta(): void
    {
        $pageSize = $this->pageSize();
        $this->assertGreaterThan(5, $pageSize);

        for ($i = 1; $i <= $pageSize + 5; $i++) {
            $this->makeTag('genre', sprintf('Genre %02d', $i), sprintf('Жанр %02d', $i));
        }

        $this->getJson('/api/tags?type=genre')
            ->assertOk()
            ->assertJsonPath('tags.total', $pageSize + 5)
            ->assertJsonPath('tags.current_page', 1)
            ->assertJsonCount($pageSize, 'tags.data');

        $this->getJson('/api/tags?type=genre&page=2')
            ->assertOk()
            ->assertJsonPath('tags.current_page', 2)
            ->assertJsonCount(5, 'tags.data');
    }

    public function test_tag_payload_carries_track_count_and_russian_name(): void
    {
        $pop = $this->makeTag('genre', 'Pop', 'Поп');
        $rock = $this->makeTag('genre', 'Rock');

        $this->makeTrack('A')->tags()->attach($pop->id);
        $this->makeTrack('B')->tags()->attach($pop->id);
        // Отсутствующий файл каталог не показывает — в счётчик он тоже не попадает.
        $this->makeTrack('Gone', true)->tags()->attach($pop->id);

        $response = $this->getJson('/api/tags?type=genre')->assertOk();
        $response->assertJsonPath('tags.total', 2);

        $tags = collect($response->json('tags.data'))->keyBy('slug');
        $this->assertSame(2, $tags['pop']['tracks_count']);
        $this->assertSame('Поп', $tags['pop']['name_ru']);
        $this->assertSame(0, $tags['rock']['tracks_count']);
        $this->assertNull($tags['rock']['name_ru']);
    }

    public function test_tags_are_filtered_by_type_and_search_over_both_names(): void
    {
        $this->makeTag('genre', 'Pop', 'Поп');
        $this->makeTag('mood', 'Dark', 'Мрачный');

        $this->getJson('/api/tags?type=mood')
            ->assertOk()
            ->assertJsonPath('tags.total', 1)
            ->assertJsonPath('tags.data.0.slug', 'dark');

        $this->getJson('/api/tags?search=Поп')
            ->assertOk()
            ->assertJsonPath('tags.total', 1)
            ->assertJsonPath('tags.data.0.slug', 'pop');

        $this->getJson('/api/tags?search=dark')
            ->assertOk()
            ->assertJsonPath('tags.total', 1)
            ->assertJsonPath('tags.data.0.slug', 'dark');

        $this->getJson('/api/tags?type=unknown')
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    /**
     * D3: кириллица в LIKE на SQLite регистронезависима только для ASCII, поэтому
     * поиск идёт по нормализованным колонкам name_search/name_ru_search.
     */
    public function test_tag_search_ignores_case_for_cyrillic_names(): void
    {
        $this->makeTag('mood', 'Alternative', 'Альт');
        $this->makeTag('genre', 'Pop', 'Поп');

        foreach (['альт', 'Альт', 'АЛЬТ'] as $needle) {
            $this->getJson('/api/tags?search='.urlencode($needle))
                ->assertOk()
                ->assertJsonPath('tags.total', 1)
                ->assertJsonPath('tags.data.0.slug', 'alternative');
        }
    }

    public function test_tag_search_ignores_case_for_ascii_names_and_is_a_substring_match(): void
    {
        $this->makeTag('genre', 'Synthpop', 'Синти-поп');

        foreach (['SYNTH', 'synth', 'YnThp'] as $needle) {
            $this->getJson('/api/tags?search='.urlencode($needle))
                ->assertOk()
                ->assertJsonPath('tags.total', 1)
                ->assertJsonPath('tags.data.0.slug', 'synthpop');
        }
    }

    public function test_tag_search_key_follows_a_later_russian_name_update(): void
    {
        $tag = $this->makeTag('genre', 'Ambient');

        $this->getJson('/api/tags?search='.urlencode('эмби'))
            ->assertOk()
            ->assertJsonPath('tags.total', 0);

        $tag->update(['name_ru' => 'Эмбиент']);

        $this->getJson('/api/tags?search='.urlencode('ЭМБИ'))
            ->assertOk()
            ->assertJsonPath('tags.total', 1)
            ->assertJsonPath('tags.data.0.slug', 'ambient');
    }

    public function test_api_does_not_expose_the_internal_search_keys(): void
    {
        $this->makeTag('genre', 'Pop', 'Поп');

        $response = $this->getJson('/api/tags?search='.urlencode('поп'))
            ->assertOk()
            ->assertJsonPath('tags.total', 1);

        $payload = $response->json('tags.data.0');

        // Свёрнутые ключи — служебные: публичный контракт строки тега не меняется.
        $this->assertArrayNotHasKey('name_search', $payload);
        $this->assertArrayNotHasKey('name_ru_search', $payload);
    }
}
