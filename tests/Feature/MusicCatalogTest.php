<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Track;
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

    public function test_filters_use_or_within_a_type_and_and_between_types(): void
    {
        $a = Track::create(['path'=>'a.mp3','title'=>'A','artist'=>'One','size'=>1,'file_mtime'=>1]);
        $b = Track::create(['path'=>'b.mp3','title'=>'B','artist'=>'Two','size'=>1,'file_mtime'=>1]);
        $rock = Tag::create(['type'=>'genre','name'=>'Rock','slug'=>'rock']);
        $jazz = Tag::create(['type'=>'genre','name'=>'Jazz','slug'=>'jazz']);
        $dark = Tag::create(['type'=>'mood','name'=>'Dark','slug'=>'dark']);
        $a->tags()->attach([$rock->id, $dark->id]);
        $b->tags()->attach([$jazz->id]);
        $this->get('/?genres[]='.$rock->id.'&genres[]='.$jazz->id.'&moods[]='.$dark->id)
            ->assertOk()->assertSee('One — A')->assertDontSee('Two — B');
    }
}
