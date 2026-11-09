<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function makeTrack(string $title, string $artist): Track
    {
        return Track::create([
            'path' => $title.'.mp3',
            'title' => $title,
            'artist' => $artist,
            'size' => 1,
            'file_mtime' => 1,
        ]);
    }

    public function test_search_filter_matches_title_and_artist(): void
    {
        $this->makeTrack('Nightcall', 'Kavinsky');
        $this->makeTrack('Midnight City', 'M83');
        $this->makeTrack('Odd Look', 'Kavinsky');

        $this->getJson('/catalog?search=kavinsky')
            ->assertOk()
            ->assertJsonPath('tracks.total', 2);

        $this->getJson('/catalog?search=midnight')
            ->assertOk()
            ->assertJsonPath('tracks.total', 1)
            ->assertJsonPath('tracks.data.0.title', 'Midnight City');
    }

    public function test_mood_filter_and_page_parameter_paginate_thirty_per_page(): void
    {
        $mood = Tag::create(['type' => 'mood', 'name' => 'Dark', 'slug' => 'dark']);
        for ($i = 1; $i <= 35; $i++) {
            $this->makeTrack(sprintf('Track %02d', $i), 'Artist')->tags()->attach($mood->id);
        }

        $this->getJson('/catalog?moods[]='.$mood->id)
            ->assertOk()
            ->assertJsonPath('tracks.total', 35)
            ->assertJsonPath('tracks.current_page', 1)
            ->assertJsonCount(30, 'tracks.data');

        $this->getJson('/catalog?moods[]='.$mood->id.'&page=2')
            ->assertOk()
            ->assertJsonPath('tracks.current_page', 2)
            ->assertJsonCount(5, 'tracks.data');
    }

    public function test_untagged_all_and_untagged_type_are_distinct(): void
    {
        $genre = Tag::create(['type' => 'genre', 'name' => 'Rock', 'slug' => 'rock']);
        $mood = Tag::create(['type' => 'mood', 'name' => 'Dark', 'slug' => 'dark']);

        $this->makeTrack('Untagged', 'Artist');
        $this->makeTrack('Only Genre', 'Artist')->tags()->attach($genre->id);
        $this->makeTrack('Only Mood', 'Artist')->tags()->attach($mood->id);

        // untagged=all only returns tracks without any tag.
        $this->getJson('/catalog?untagged=all')
            ->assertOk()
            ->assertJsonPath('tracks.total', 1)
            ->assertJsonPath('tracks.data.0.title', 'Untagged');

        // untagged=mood returns tracks lacking a mood tag (Untagged + Only Genre).
        $this->getJson('/catalog?untagged=mood')
            ->assertOk()
            ->assertJsonPath('tracks.total', 2);

        // untagged=genre returns tracks lacking a genre tag (Untagged + Only Mood).
        $this->getJson('/catalog?untagged=genre')
            ->assertOk()
            ->assertJsonPath('tracks.total', 2);
    }

    public function test_invalid_untagged_value_is_rejected(): void
    {
        $this->getJson('/catalog?untagged=unknown')
            ->assertStatus(422)
            ->assertJsonValidationErrors('untagged');
    }
}
