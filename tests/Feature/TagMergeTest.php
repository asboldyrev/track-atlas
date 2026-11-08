<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagMergeTest extends TestCase
{
    use RefreshDatabase;

    private function track(string $name): Track
    {
        return Track::create([
            'path' => $name.'.mp3',
            'title' => $name,
            'size' => 100,
            'file_mtime' => 1,
        ]);
    }

    public function test_merge_moves_links_and_preserves_manual_provenance(): void
    {
        $source = Tag::create([
            'type' => 'genre', 'name' => 'Synth Pop', 'slug' => 'synth-pop',
            'name_ru' => 'Синти-поп',
        ]);
        $target = Tag::create([
            'type' => 'genre', 'name' => 'Synthpop', 'slug' => 'synthpop',
        ]);
        $first = $this->track('First');
        $second = $this->track('Second');
        $first->tags()->attach($source->id, ['source' => 'manual']);
        $first->tags()->attach($target->id, ['source' => 'file']);
        $second->tags()->attach($source->id, ['source' => 'openrouter']);

        $this->postJson('/tags/merge', [
            'source_id' => $source->id, 'target_id' => $target->id,
        ])->assertOk()->assertJsonPath('merged', 2);

        $this->assertDatabaseMissing('tags', ['id' => $source->id]);
        $this->assertSame('manual', $first->fresh()->tags->sole()->pivot->source);
        $this->assertSame('openrouter', $second->fresh()->tags->sole()->pivot->source);
        $this->assertSame($target->id, $first->fresh()->tags->sole()->id);
        $this->assertSame('Синти-поп', $target->fresh()->name_ru);
        $this->assertDatabaseCount('track_tag', 2);
    }

    public function test_merging_different_category_types_is_rejected_without_data_loss(): void
    {
        $genre = Tag::create(['type' => 'genre', 'name' => 'Dark', 'slug' => 'dark']);
        $mood = Tag::create(['type' => 'mood', 'name' => 'Dark', 'slug' => 'dark']);
        $track = $this->track('Dark Song');
        $track->tags()->attach($genre->id);

        $this->postJson('/tags/merge', [
            'source_id' => $genre->id, 'target_id' => $mood->id,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('tags', 2);
        $this->assertCount(1, $track->fresh()->tags);
    }

    public function test_tag_cannot_be_merged_into_itself(): void
    {
        $tag = Tag::create(['type' => 'genre', 'name' => 'Jazz', 'slug' => 'jazz']);

        $this->postJson('/tags/merge', [
            'source_id' => $tag->id, 'target_id' => $tag->id,
        ])->assertUnprocessable();
        $this->assertDatabaseCount('tags', 1);
    }
}
