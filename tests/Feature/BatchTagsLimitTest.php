<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchTagsLimitTest extends TestCase
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

    public function test_more_than_two_hundred_selected_tracks_is_rejected(): void
    {
        $ids = [];
        for ($i = 1; $i <= 201; $i++) {
            $ids[] = $this->makeTrack('Track '.$i)->id;
        }
        $tag = Tag::create(['type' => 'mood', 'name' => 'Dark', 'slug' => 'dark']);

        $this->postJson('/tracks/batch-tags', [
            'tracks' => $ids,
            'tags' => [$tag->id],
            'mode' => 'add',
        ])->assertStatus(422)->assertJsonValidationErrors('tracks');

        $this->assertSame(0, $tag->tracks()->count());
    }

    public function test_batch_selection_touches_only_selected_tracks(): void
    {
        $tag = Tag::create(['type' => 'mood', 'name' => 'Dark', 'slug' => 'dark']);
        $selected = $this->makeTrack('Selected');
        $unselected = $this->makeTrack('Unselected');

        $this->postJson('/tracks/batch-tags', [
            'tracks' => [$selected->id],
            'tags' => [$tag->id],
            'mode' => 'add',
        ])->assertOk()->assertJsonPath('updated', 1);

        $this->assertCount(1, $selected->fresh()->tags);
        $this->assertCount(0, $unselected->fresh()->tags);
    }
}
