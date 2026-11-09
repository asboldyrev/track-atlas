<?php

namespace Tests\Feature\Api;

use App\Models\Tag;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
