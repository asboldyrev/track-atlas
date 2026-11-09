<?php

namespace Tests\Feature\Api;

use App\Models\Tag;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TracksApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeTrack(string $title, string $artist = 'Artist', bool $missing = false): Track
    {
        return Track::create([
            'path' => $title.'.mp3',
            'title' => $title,
            'artist' => $artist,
            'size' => 1,
            'file_mtime' => 1,
            'is_missing' => $missing,
        ]);
    }

    private function makeTag(string $type, string $name): Tag
    {
        return Tag::create(['type' => $type, 'name' => $name, 'slug' => Str::slug($name)]);
    }

    public function test_tracks_are_paginated_with_the_page_size_published_in_meta(): void
    {
        $pageSize = (int) $this->getJson('/api/meta')->assertOk()->json('limits.tracks_per_page');
        $this->assertGreaterThan(5, $pageSize);

        $mood = $this->makeTag('mood', 'Dark');
        for ($i = 1; $i <= $pageSize + 5; $i++) {
            $this->makeTrack(sprintf('Track %02d', $i))->tags()->attach($mood->id);
        }

        $this->getJson('/api/tracks?moods[]='.$mood->id)
            ->assertOk()
            ->assertJsonPath('tracks.total', $pageSize + 5)
            ->assertJsonPath('tracks.current_page', 1)
            ->assertJsonCount($pageSize, 'tracks.data');

        $this->getJson('/api/tracks?moods[]='.$mood->id.'&page=2')
            ->assertOk()
            ->assertJsonPath('tracks.current_page', 2)
            ->assertJsonCount(5, 'tracks.data');
    }

    public function test_tracks_response_carries_its_tags_but_not_the_dictionary(): void
    {
        $genre = $this->makeTag('genre', 'Rock');
        $this->makeTrack('Nightcall')->tags()->attach($genre->id);

        $this->getJson('/api/tracks')
            ->assertOk()
            ->assertJsonPath('tracks.total', 1)
            ->assertJsonPath('tracks.data.0.title', 'Nightcall')
            ->assertJsonPath('tracks.data.0.tags.0.name', 'Rock')
            // Справочник и переводы отдаёт /api/tags, а не список треков.
            ->assertJsonMissingPath('tags')
            ->assertJsonMissingPath('tag_translations');
    }

    public function test_filters_behave_like_the_catalog(): void
    {
        $mood = $this->makeTag('mood', 'Dark');
        $genre = $this->makeTag('genre', 'Rock');

        $this->makeTrack('Nightcall', 'Kavinsky')->tags()->attach($mood->id);
        $this->makeTrack('Untagged', 'Nobody');
        $this->makeTrack('Gone', 'Nobody', true);

        $this->getJson('/api/tracks?search=kavinsky')->assertOk()->assertJsonPath('tracks.total', 1);
        $this->getJson('/api/tracks?genres[]='.$genre->id)->assertOk()->assertJsonPath('tracks.total', 0);
        $this->getJson('/api/tracks?moods[]='.$mood->id)->assertOk()->assertJsonPath('tracks.total', 1);

        $this->getJson('/api/tracks?untagged=all')
            ->assertOk()
            ->assertJsonPath('tracks.total', 1)
            ->assertJsonPath('tracks.data.0.title', 'Untagged');

        $this->getJson('/api/tracks?untagged=genre')->assertOk()->assertJsonPath('tracks.total', 2);

        $this->getJson('/api/tracks?untagged=unknown')
            ->assertStatus(422)
            ->assertJsonValidationErrors('untagged');

        $this->getJson('/api/tracks?genres[]=999')->assertStatus(422)->assertJsonValidationErrors('genres.0');
    }

    /**
     * Массовое наполнение: 1435 треков — размер реальной библиотеки, на которой
     * воспроизводился дефект «первые 900».
     */
    private function seedTracks(int $count): void
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'path' => sprintf('track-%04d.mp3', $i),
                'title' => sprintf('Track %04d', $i),
                // Повторяющиеся artist/album нужны для проверки тай-брейка.
                'artist' => sprintf('Artist %03d', intdiv($i - 1, 10)),
                'album' => sprintf('Album %02d', intdiv($i - 1, 100)),
                'size' => 1,
                'file_mtime' => 1,
                'is_missing' => false,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('tracks')->insert($chunk);
        }
    }

    public function test_per_page_from_the_whitelist_changes_last_page(): void
    {
        $this->seedTracks(1435);

        $response = $this->getJson('/api/tracks?per_page=100&sort=title&dir=desc');

        $response->assertOk()
            ->assertJsonPath('tracks.total', 1435)
            ->assertJsonPath('tracks.per_page', 100)
            ->assertJsonPath('tracks.last_page', 15)
            ->assertJsonCount(100, 'tracks.data');

        // desc по title: первая строка — самый большой номер.
        $response->assertJsonPath('tracks.data.0.title', 'Track 1435');
    }

    public function test_all_pages_of_album_sort_cover_every_track_exactly_once(): void
    {
        $this->seedTracks(1435);

        $ids = [];
        $lastPage = 1;

        for ($page = 1; $page <= $lastPage; $page++) {
            $response = $this->getJson("/api/tracks?sort=album&dir=asc&per_page=100&page={$page}")->assertOk();
            $lastPage = (int) $response->json('tracks.last_page');

            foreach ($response->json('tracks.data') as $track) {
                $ids[] = $track['id'];
            }
        }

        $this->assertSame(15, $lastPage);
        $this->assertCount(1435, $ids);
        $this->assertSame(1435, count(array_unique($ids)), 'Пагинация повторила или пропустила строки');

        $expected = DB::table('tracks')->orderBy('id')->pluck('id')->all();
        sort($expected);
        $actual = $ids;
        sort($actual);
        $this->assertSame($expected, $actual);
    }

    public function test_default_sort_is_byte_for_byte_the_previous_order(): void
    {
        $this->seedTracks(40);

        $default = $this->getJson('/api/tracks?per_page=100')->assertOk()->json('tracks.data');
        $explicit = $this->getJson('/api/tracks?per_page=100&sort=artist&dir=asc')->assertOk()->json('tracks.data');

        $this->assertSame(array_column($default, 'id'), array_column($explicit, 'id'));

        // artist asc, title asc, tracks.id asc.
        $expectedIds = DB::table('tracks')
            ->orderBy('artist')->orderBy('title')->orderBy('id')
            ->pluck('id')->all();
        $this->assertSame($expectedIds, array_column($default, 'id'));
    }

    public function test_tie_break_keeps_pagination_stable_for_equal_album_and_artist(): void
    {
        // Одинаковые album и artist: порядок обязан решаться по title и id.
        // 12 строк при per_page=10 — так тай-брейк проверяется на границе страниц.
        $titles = ['Charlie', 'Alpha', 'Echo', 'Bravo', 'Delta', 'Foxtrot',
            'Alpha', 'Golf', 'Hotel', 'India', 'Juliet', 'Kilo'];
        foreach ($titles as $index => $title) {
            Track::create([
                'path' => 'same-'.$index.'.mp3',
                'title' => $title,
                'artist' => 'Same',
                'album' => 'One',
                'size' => 1,
                'file_mtime' => 1,
            ]);
        }

        $firstPage = array_column(
            $this->getJson('/api/tracks?sort=album&dir=asc&per_page=10')->assertOk()->json('tracks.data'),
            'title',
        );
        $secondPage = array_column(
            $this->getJson('/api/tracks?sort=album&dir=asc&per_page=10&page=2')->assertOk()->json('tracks.data'),
            'title',
        );

        $this->assertSame(
            ['Alpha', 'Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo', 'Foxtrot', 'Golf', 'Hotel', 'India'],
            $firstPage,
        );
        $this->assertSame(['Juliet', 'Kilo'], $secondPage);
    }

    public function test_invalid_sort_direction_and_page_size_are_rejected(): void
    {
        foreach (['sort=foo', 'dir=up', 'per_page=25', 'per_page=101', 'per_page=0'] as $query) {
            $this->getJson('/api/tracks?'.$query)
                ->assertStatus(422)
                ->assertJsonStructure(['errors']);
        }
    }

    public function test_filters_work_together_with_sort_and_page_size(): void
    {
        $mood = $this->makeTag('mood', 'Dark');
        $genre = $this->makeTag('genre', 'Rock');

        foreach ([
            ['Tango', 'Zed', 'A'],
            ['Alpha', 'Bob', 'B'],
            ['Mike', 'Bob', 'A'],
        ] as [$title, $artist, $album]) {
            Track::create([
                'path' => $title.'.mp3',
                'title' => $title,
                'artist' => $artist,
                'album' => $album,
                'size' => 1,
                'file_mtime' => 1,
            ])->tags()->attach($mood->id);
        }

        $this->getJson('/api/tracks?moods[]='.$mood->id.'&sort=title&dir=desc&per_page=10')
            ->assertOk()
            ->assertJsonPath('tracks.total', 3)
            ->assertJsonPath('tracks.per_page', 10)
            ->assertJsonPath('tracks.data.0.title', 'Tango')
            ->assertJsonPath('tracks.data.2.title', 'Alpha');

        $this->getJson('/api/tracks?moods[]='.$mood->id.'&genres[]='.$genre->id.'&sort=album&per_page=10')
            ->assertOk()
            ->assertJsonPath('tracks.total', 0);
    }
}
