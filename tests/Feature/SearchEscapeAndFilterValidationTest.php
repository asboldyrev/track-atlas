<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Дефекты, найденные ревизией логики/бэкенда:
 *  1) LIKE-поиск (короткие запросы и /api/tags) не экранировал подстановочные
 *     знаки `%` и `_`, поэтому запрос вроде «_» совпадал с любой строкой;
 *  2) `genres[]`/`moods[]` принимали id тега чужого типа и молча возвращали
 *     пустую выборку вместо 422;
 *  3) порядок каталога не имел тай-брейка — строки с одинаковыми artist/title
 *     могли дублироваться или пропадать между страницами;
 *  4) POST /tags отвечал 201 и на повторное создание существующей категории.
 */
class SearchEscapeAndFilterValidationTest extends TestCase
{
    use RefreshDatabase;

    private function makeTrack(string $title, string $artist = 'Artist'): Track
    {
        return Track::create([
            'path' => $title.'.mp3',
            'title' => $title,
            'artist' => $artist,
            'size' => 1,
            'file_mtime' => 1,
        ]);
    }

    public function test_short_search_term_treats_wildcards_as_literal_characters(): void
    {
        $this->makeTrack('Nightcall', 'Kavinsky');
        $this->makeTrack('100% Pure', 'Band');
        $this->makeTrack('a_b', 'Band');

        // Одним символом «_» ищем буквальный подчёркивающий знак, а не «любой символ».
        $this->getJson('/api/tracks?search='.urlencode('_'))
            ->assertOk()
            ->assertJsonPath('tracks.total', 1)
            ->assertJsonPath('tracks.data.0.title', 'a_b');

        // «%» — знак процента, а не «любая строка».
        $this->getJson('/api/tracks?search='.urlencode('%'))
            ->assertOk()
            ->assertJsonPath('tracks.total', 1)
            ->assertJsonPath('tracks.data.0.title', '100% Pure');

        // Обычный короткий поиск по-прежнему работает подстрокой.
        $this->getJson('/api/tracks?search='.urlencode('a_'))
            ->assertOk()
            ->assertJsonPath('tracks.total', 1)
            ->assertJsonPath('tracks.data.0.title', 'a_b');
    }

    public function test_tag_search_treats_wildcards_as_literal_characters(): void
    {
        Tag::create(['type' => 'genre', 'name' => 'Rock', 'slug' => 'rock']);
        Tag::create(['type' => 'mood', 'name' => 'Dark', 'slug' => 'dark']);
        Tag::create(['type' => 'genre', 'name' => 'r_k', 'slug' => 'r-k']);

        $this->getJson('/api/tags?search='.urlencode('_'))
            ->assertOk()
            ->assertJsonPath('tags.total', 1)
            ->assertJsonPath('tags.data.0.name', 'r_k');

        $this->getJson('/api/tags?search='.urlencode('%'))
            ->assertOk()
            ->assertJsonPath('tags.total', 0);

        $this->getJson('/api/tags?search=rock')
            ->assertOk()
            ->assertJsonPath('tags.total', 1);
    }

    public function test_genre_filter_rejects_a_mood_id_and_vice_versa(): void
    {
        $track = $this->makeTrack('Track');
        $genre = Tag::create(['type' => 'genre', 'name' => 'Rock', 'slug' => 'rock']);
        $mood = Tag::create(['type' => 'mood', 'name' => 'Dark', 'slug' => 'dark']);
        $track->tags()->attach([$genre->id, $mood->id]);

        // id настроения в genres[] — ошибка ввода, а не пустой результат.
        $this->getJson('/api/tracks?genres[]='.$mood->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('genres.0');

        $this->getJson('/api/tracks?moods[]='.$genre->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('moods.0');

        // Корректные id типов работают как раньше.
        $this->getJson('/api/tracks?genres[]='.$genre->id.'&moods[]='.$mood->id)
            ->assertOk()
            ->assertJsonPath('tracks.total', 1);

        // Несуществующий id по-прежнему отвергается.
        $this->getJson('/api/tracks?genres[]=999999')
            ->assertStatus(422)
            ->assertJsonValidationErrors('genres.0');
    }

    public function test_pagination_does_not_duplicate_or_skip_identical_rows(): void
    {
        for ($i = 0; $i < 35; $i++) {
            // Одинаковые artist/title — именно тот случай, где порядок без
            // тай-брейка не определён. Путь обязан оставаться уникальным.
            Track::create([
                'path' => 'same-'.$i.'.mp3',
                'title' => 'Same',
                'artist' => 'Band',
                'size' => 1,
                'file_mtime' => 1,
            ]);
        }

        $first = $this->getJson('/api/tracks')->assertOk()->json('tracks.data');
        $second = $this->getJson('/api/tracks?page=2')->assertOk()->json('tracks.data');

        $this->assertCount(30, $first);
        $this->assertCount(5, $second);

        $firstIds = array_column($first, 'id');
        $secondIds = array_column($second, 'id');

        $this->assertSame([], array_intersect($firstIds, $secondIds), 'Страницы не должны пересекаться');
        $this->assertCount(35, array_unique(array_merge($firstIds, $secondIds)));
    }

    public function test_store_tag_reports_created_then_existing(): void
    {
        $created = $this->postJson('/tags', ['type' => 'genre', 'name' => 'Rock']);
        $created->assertStatus(201);
        $id = $created->json('tag.id');

        // Повторное создание (в том числе через алиас) — это 200, а не новое 201.
        $this->postJson('/tags', ['type' => 'genre', 'name' => 'rock'])
            ->assertStatus(200)
            ->assertJsonPath('tag.id', $id);

        $this->assertDatabaseCount('tags', 1);
    }
}
