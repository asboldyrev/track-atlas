<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Track;
use App\Services\AudioMetadataReader;
use App\Services\MusicScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MusicCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanner_is_repeatable_and_does_not_delete_manual_tags(): void
    {
        $folder = sys_get_temp_dir().'/track-atlas-test-'.bin2hex(random_bytes(6));
        File::makeDirectory($folder);
        try {
            file_put_contents($folder.'/Artist - Song.mp3', 'fake-audio');
            $scanner = app(MusicScanner::class);
            $this->assertSame(1, $scanner->scan($folder)['added']);
            $track = Track::firstOrFail();
            $tag = Tag::create(['type' => 'mood', 'name' => 'Dark', 'slug' => 'dark']);
            $track->tags()->attach($tag->id, ['source' => 'manual']);
            $this->assertSame(1, $scanner->scan($folder)['unchanged']);
            $this->assertDatabaseCount('tracks', 1);
            unlink($folder.'/Artist - Song.mp3');
            $this->assertSame(1, $scanner->scan($folder)['missing']);
            $this->assertTrue($track->fresh()->is_missing);
            $this->assertCount(1, $track->fresh()->tags);
        } finally {
            File::deleteDirectory($folder);
        }
    }

    public function test_scanner_recursively_indexes_nested_subfolders(): void
    {
        $folder = sys_get_temp_dir().'/track-atlas-nested-'.bin2hex(random_bytes(6));
        File::makeDirectory($folder.'/Artist/Album/Disc 1', 0755, true);
        try {
            file_put_contents($folder.'/Artist/Album/Disc 1/Artist - Deep Song.flac', 'fake-audio');
            file_put_contents($folder.'/Artist/Artist - Shallow Song.mp3', 'fake-audio');

            $scanner = app(MusicScanner::class);
            $this->assertSame(2, $scanner->scan($folder)['added']);
            $this->assertDatabaseHas('tracks', [
                'path' => 'Artist/Album/Disc 1/Artist - Deep Song.flac',
                'title' => 'Deep Song',
                'artist' => 'Artist',
            ]);
            $this->assertSame(2, $scanner->scan($folder)['unchanged']);
            $this->assertDatabaseCount('tracks', 2);
        } finally {
            File::deleteDirectory($folder);
        }
    }

    public function test_refresh_imports_metadata_and_preserves_manual_tags(): void
    {
        $folder = sys_get_temp_dir().'/track-atlas-metadata-'.bin2hex(random_bytes(6));
        File::makeDirectory($folder);
        try {
            file_put_contents($folder.'/Unknown.mp3', 'fake-audio');
            $reader = \Mockery::mock(AudioMetadataReader::class);
            $reader->shouldReceive('read')->twice()->andReturn([
                'title' => 'Tagged title', 'artist' => 'Tagged artist',
                'album' => 'Tagged album', 'genres' => ['Synthwave', 'Electronic'],
            ]);
            app()->instance(AudioMetadataReader::class, $reader);
            $scanner = app(MusicScanner::class);
            $this->assertSame(1, $scanner->scan($folder)['added']);
            $track = Track::firstOrFail();
            $this->assertSame('Tagged title', $track->title);
            $this->assertSame('Tagged album', $track->album);
            $this->assertSame(2, $track->tags()->count());

            $manual = Tag::create(['type' => 'mood', 'name' => 'Dreamy', 'slug' => 'dreamy']);
            $track->tags()->attach($manual->id, ['source' => 'manual']);
            $this->assertSame(1, $scanner->scan($folder, true)['updated']);
            $this->assertDatabaseCount('tags', 3);
            $this->assertSame(3, $track->fresh()->tags()->count());
            $this->assertSame('manual', $track->tags()->where('tags.id', $manual->id)->first()->pivot->source);
        } finally {
            File::deleteDirectory($folder);
        }
    }

    public function test_filters_use_or_within_a_type_and_and_between_types(): void
    {
        $a = Track::create(['path' => 'a.mp3', 'title' => 'A', 'artist' => 'One', 'size' => 1, 'file_mtime' => 1]);
        $b = Track::create(['path' => 'b.mp3', 'title' => 'B', 'artist' => 'Two', 'size' => 1, 'file_mtime' => 1]);
        $rock = Tag::create(['type' => 'genre', 'name' => 'Rock', 'slug' => 'rock']);
        $jazz = Tag::create(['type' => 'genre', 'name' => 'Jazz', 'slug' => 'jazz']);
        $dark = Tag::create(['type' => 'mood', 'name' => 'Dark', 'slug' => 'dark']);
        $a->tags()->attach([$rock->id, $dark->id]);
        $b->tags()->attach([$jazz->id]);
        $this->getJson('/api/tracks?genres[]='.$rock->id.'&genres[]='.$jazz->id.'&moods[]='.$dark->id)
            ->assertOk()
            ->assertJsonPath('tracks.total', 1)
            ->assertJsonPath('tracks.data.0.artist', 'One')
            ->assertJsonPath('tracks.data.0.title', 'A');
    }

    public function test_page_shell_loads_and_url_filters_are_applied_by_the_api(): void
    {
        $track = Track::create(['path' => 'a.mp3', 'title' => 'Nightcall', 'artist' => 'Kavinsky', 'size' => 1, 'file_mtime' => 1]);
        $genre = Tag::create(['type' => 'genre', 'name' => 'Synthwave', 'slug' => 'synthwave']);
        $track->tags()->attach($genre->id);
        $this->get('/?genres[]='.$genre->id)->assertOk()->assertSee('id="app"', false);
        $this->getJson('/api/tracks?genres[]='.$genre->id)
            ->assertOk()->assertJsonPath('tracks.total', 1);
        $this->getJson('/api/tracks?untagged=genre')
            ->assertOk()->assertJsonPath('tracks.total', 0);
    }

    public function test_batch_tagging_modifies_only_selected_tracks(): void
    {
        $first = Track::create(['path' => 'one.mp3', 'title' => 'One', 'size' => 1, 'file_mtime' => 1]);
        $second = Track::create(['path' => 'two.mp3', 'title' => 'Two', 'size' => 1, 'file_mtime' => 1]);
        $tag = Tag::create(['type' => 'mood', 'name' => 'Dark', 'slug' => 'dark']);

        $this->postJson('/tracks/batch-tags', [
            'tracks' => [$first->id], 'tags' => [$tag->id], 'mode' => 'add',
        ])->assertOk()->assertJsonPath('updated', 1);

        $this->assertTrue($first->fresh()->tags->contains($tag->id));
        $this->assertFalse($second->fresh()->tags->contains($tag->id));

        $this->postJson('/tracks/batch-tags', [
            'tracks' => [$first->id], 'tags' => [$tag->id], 'mode' => 'remove',
        ])->assertOk();

        $this->assertCount(0, $first->fresh()->tags);
    }

    public function test_manual_edit_can_clear_all_tags(): void
    {
        $track = Track::create(['path' => 'one.mp3', 'title' => 'One', 'size' => 1, 'file_mtime' => 1]);
        $tag = Tag::create(['type' => 'genre', 'name' => 'Rock', 'slug' => 'rock']);
        $track->tags()->attach($tag->id);

        $this->putJson('/tracks/'.$track->id.'/tags', ['tags' => []])
            ->assertOk()->assertJsonPath('ok', true);

        $this->assertCount(0, $track->fresh()->tags);
    }

    public function test_api_tags_expose_name_ru_without_changing_canonical_names_or_filter_ids(): void
    {
        $track = Track::create(['path' => 'translated.mp3', 'title' => 'Translated', 'size' => 1, 'file_mtime' => 1]);
        $tag = Tag::create(['type' => 'mood', 'name' => 'Melancholic', 'slug' => 'melancholic']);
        $track->tags()->attach($tag->id);

        // Треки фильтруются по id тега — справочник их не подменяет.
        $this->getJson('/api/tracks?moods[]='.$tag->id)
            ->assertOk()
            ->assertJsonPath('tracks.total', 1);

        // Русское название отдаёт справочник тегов колонкой name_ru.
        $this->getJson('/api/tags?type=mood')
            ->assertOk()
            ->assertJsonPath('tags.data.0.name', 'Melancholic')
            ->assertJsonPath('tags.data.0.name_ru', null);

        $this->putJson('/tags/'.$tag->id.'/translation', [
            'name_ru' => 'Тоскливый',
        ])->assertOk()->assertJsonPath('tag.name_ru', 'Тоскливый');

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => 'Melancholic',
            'slug' => 'melancholic',
            'name_ru' => 'Тоскливый',
        ]);
        $this->getJson('/api/tags?type=mood')
            ->assertOk()
            ->assertJsonPath('tags.data.0.name_ru', 'Тоскливый');
    }

    public function test_translation_can_be_cleared_and_invalid_translation_is_rejected(): void
    {
        $tag = Tag::create([
            'type' => 'genre', 'name' => 'Darkwave', 'slug' => 'darkwave',
            'name_ru' => 'Дарквейв',
        ]);

        $this->putJson('/tags/'.$tag->id.'/translation', [
            'name_ru' => str_repeat('a', 121),
        ])->assertUnprocessable();

        $this->putJson('/tags/'.$tag->id.'/translation', [
            'name_ru' => null,
        ])->assertOk()->assertJsonPath('tag.name_ru', null);

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id, 'name' => 'Darkwave', 'slug' => 'darkwave',
            'name_ru' => null,
        ]);
    }
}
