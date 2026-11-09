<?php

namespace Tests\Feature;

use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * B7: поиск по title/artist обслуживает FTS5 (tracks_fts), короткие запросы —
 * прежний LIKE. Здесь проверяются и триггеры синхронизации индекса, и оба пути.
 */
class TrackSearchFtsTest extends TestCase
{
    use RefreshDatabase;

    private function makeTrack(string $title, string $artist, bool $missing = false): Track
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

    private function ftsCount(string $match): int
    {
        return DB::table('tracks_fts')->whereRaw('tracks_fts MATCH ?', [$match])->count();
    }

    public function test_triggers_keep_the_fts_index_in_sync_across_writes(): void
    {
        $track = $this->makeTrack('Nightcall', 'Kavinsky');
        $this->assertSame(1, $this->ftsCount('"nightcall"'));

        // Обычное обновление через модель.
        $track->update(['title' => 'Odd Look']);
        $this->assertSame(0, $this->ftsCount('"nightcall"'));
        $this->assertSame(1, $this->ftsCount('"odd"'));

        // Массовый UPDATE идёт мимо событий Eloquent — индекс обновляет триггер БД.
        Track::query()->where('id', $track->id)->update(['artist' => 'M83']);
        $this->assertSame(1, $this->ftsCount('"m83"'));

        $track->delete();
        $this->assertSame(0, $this->ftsCount('"odd"'));
        $this->assertSame(0, $this->ftsCount('"m83"'));
    }

    public function test_api_tracks_search_matches_title_and_artist_through_fts(): void
    {
        $this->makeTrack('Nightcall', 'Kavinsky');
        $this->makeTrack('Midnight City', 'M83');
        $this->makeTrack('Odd Look', 'Kavinsky');

        $this->getJson('/api/tracks?search=kavinsky')
            ->assertOk()->assertJsonPath('tracks.total', 2);

        // Регистр не важен, совпадение по префиксу токена.
        $this->getJson('/api/tracks?search=KAVINSKY')
            ->assertOk()->assertJsonPath('tracks.total', 2);

        $this->getJson('/api/tracks?search=midnight')
            ->assertOk()->assertJsonPath('tracks.total', 1)
            ->assertJsonPath('tracks.data.0.title', 'Midnight City');
    }

    public function test_search_is_served_by_api_tracks(): void
    {
        $this->makeTrack('Midnight City', 'M83');

        $this->getJson('/api/tracks?search=midnight')
            ->assertOk()->assertJsonPath('tracks.total', 1);
    }

    public function test_two_character_query_falls_back_to_like(): void
    {
        $this->makeTrack('Nightcall', 'Kavinsky');

        $this->getJson('/api/tracks?search=ka')
            ->assertOk()->assertJsonPath('tracks.total', 1)
            ->assertJsonPath('tracks.data.0.artist', 'Kavinsky');
    }

    public function test_punctuation_only_query_does_not_break_search(): void
    {
        $this->makeTrack('Nightcall', 'Kavinsky');

        $this->getJson('/api/tracks?search=!!!')
            ->assertOk()->assertJsonPath('tracks.total', 0);
    }

    public function test_missing_tracks_are_excluded_from_search_results(): void
    {
        $this->makeTrack('Nightcall', 'Kavinsky');
        $this->makeTrack('Kavinsky Hidden', 'Kavinsky', missing: true);

        $this->getJson('/api/tracks?search=kavinsky')
            ->assertOk()->assertJsonPath('tracks.total', 1);
    }
}
