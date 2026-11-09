<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Track;
use App\Services\FileGenreSynchronizer;
use App\Services\TagDecisionSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * B8: ручная разметка хранит решения в track_tag_decision и не переписывает
 * источник связи. B9: батч применяется транзакционно.
 */
class ManualTagDecisionTest extends TestCase
{
    use RefreshDatabase;

    private function makeTrack(string $title = 'Track'): Track
    {
        return Track::create([
            'path' => $title.'.mp3',
            'title' => $title,
            'artist' => 'Artist',
            'size' => 1,
            'file_mtime' => 1,
        ]);
    }

    public function test_manual_edit_preserves_the_source_of_existing_links(): void
    {
        $track = $this->makeTrack();
        $fromFile = Tag::create(['type' => 'genre', 'name' => 'Rock', 'slug' => 'rock']);
        $byHand = Tag::create(['type' => 'mood', 'name' => 'Dark', 'slug' => 'dark']);
        $track->tags()->attach($fromFile->id, ['source' => 'file']);

        $this->putJson('/tracks/'.$track->id.'/tags', ['tags' => [$fromFile->id, $byHand->id]])
            ->assertOk()->assertJsonPath('ok', true);

        // Источник файловой метки не перетёрт на manual.
        $this->assertDatabaseHas('track_tag', [
            'track_id' => $track->id, 'tag_id' => $fromFile->id, 'source' => 'file',
        ]);
        // Новая ручная метка действительно ручная.
        $this->assertDatabaseHas('track_tag', [
            'track_id' => $track->id, 'tag_id' => $byHand->id, 'source' => 'manual',
        ]);
        // Оба решения зафиксированы как accepted.
        $this->assertDatabaseHas('track_tag_decision', [
            'track_id' => $track->id, 'tag_id' => $fromFile->id, 'decision' => 'accepted',
        ]);
        $this->assertDatabaseHas('track_tag_decision', [
            'track_id' => $track->id, 'tag_id' => $byHand->id, 'decision' => 'accepted',
        ]);
    }

    public function test_manual_removal_records_ignored_and_survives_a_rescan(): void
    {
        $track = $this->makeTrack();
        $genre = Tag::create(['type' => 'genre', 'name' => 'Synthwave', 'slug' => 'synthwave']);
        $sync = app(FileGenreSynchronizer::class);

        $sync->sync($track, ['Synthwave']);
        $this->assertTrue($track->fresh()->tags->contains($genre->id));

        // Пользователь снимает метку вручную.
        $this->putJson('/tracks/'.$track->id.'/tags', ['tags' => []])->assertOk();
        $this->assertCount(0, $track->fresh()->tags);
        $this->assertDatabaseHas('track_tag_decision', [
            'track_id' => $track->id, 'tag_id' => $genre->id, 'decision' => 'ignored',
        ]);

        // Следующий проход сканера не должен вернуть снятую метку.
        $sync->sync($track->fresh(), ['Synthwave']);
        $this->assertCount(0, $track->fresh()->tags);
    }

    public function test_batch_update_records_decisions_and_keeps_sources(): void
    {
        $track = $this->makeTrack();
        $fromAi = Tag::create(['type' => 'genre', 'name' => 'Ambient', 'slug' => 'ambient']);
        $byHand = Tag::create(['type' => 'mood', 'name' => 'Dreamy', 'slug' => 'dreamy']);
        $track->tags()->attach($fromAi->id, ['source' => 'openrouter']);

        $this->postJson('/tracks/batch-tags', [
            'tracks' => [$track->id],
            'tags' => [$fromAi->id, $byHand->id],
            'mode' => 'add',
        ])->assertOk()->assertJsonPath('updated', 1);

        $this->assertDatabaseHas('track_tag', [
            'track_id' => $track->id, 'tag_id' => $fromAi->id, 'source' => 'openrouter',
        ]);
        $this->assertDatabaseHas('track_tag', [
            'track_id' => $track->id, 'tag_id' => $byHand->id, 'source' => 'manual',
        ]);
        $this->assertDatabaseHas('track_tag_decision', [
            'track_id' => $track->id, 'tag_id' => $fromAi->id, 'decision' => 'accepted',
        ]);

        $this->postJson('/tracks/batch-tags', [
            'tracks' => [$track->id],
            'tags' => [$byHand->id],
            'mode' => 'remove',
        ])->assertOk()->assertJsonPath('updated', 1);

        $this->assertCount(1, $track->fresh()->tags);
        $this->assertDatabaseHas('track_tag_decision', [
            'track_id' => $track->id, 'tag_id' => $byHand->id, 'decision' => 'ignored',
        ]);
    }

    public function test_re_adding_a_tag_clears_an_ignored_decision(): void
    {
        $track = $this->makeTrack();
        $tag = Tag::create(['type' => 'mood', 'name' => 'Dark', 'slug' => 'dark']);

        $this->postJson('/tracks/batch-tags', [
            'tracks' => [$track->id], 'tags' => [$tag->id], 'mode' => 'remove',
        ])->assertOk();
        $this->assertDatabaseHas('track_tag_decision', [
            'track_id' => $track->id, 'tag_id' => $tag->id, 'decision' => 'ignored',
        ]);

        $this->postJson('/tracks/batch-tags', [
            'tracks' => [$track->id], 'tags' => [$tag->id], 'mode' => 'add',
        ])->assertOk();

        $this->assertDatabaseHas('track_tag_decision', [
            'track_id' => $track->id, 'tag_id' => $tag->id, 'decision' => 'accepted',
        ]);
        $this->assertTrue($track->fresh()->tags->contains($tag->id));
    }

    public function test_batch_tags_are_applied_inside_a_transaction(): void
    {
        $track = $this->makeTrack();
        $tag = Tag::create(['type' => 'mood', 'name' => 'Dark', 'slug' => 'dark']);

        $sync = Mockery::mock(TagDecisionSynchronizer::class);
        $sync->shouldReceive('accept')->once()->andReturnUsing(function () use ($track, $tag) {
            DB::table('track_tag')->insert([
                'track_id' => $track->id, 'tag_id' => $tag->id, 'source' => 'manual',
            ]);
            throw new RuntimeException('boom');
        });
        $this->app->instance(TagDecisionSynchronizer::class, $sync);

        $this->postJson('/tracks/batch-tags', [
            'tracks' => [$track->id], 'tags' => [$tag->id], 'mode' => 'add',
        ])->assertStatus(500);

        // Транзакция откатила уже сделанную вставку.
        $this->assertDatabaseCount('track_tag', 0);
    }
}
