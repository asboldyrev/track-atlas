<?php

namespace Tests\Unit;

use App\Services\TrackLookupMetadata;
use Tests\TestCase;

class TrackLookupMetadataTest extends TestCase
{
    public function test_duplicate_artist_prefix_is_removed_for_classification_only(): void
    {
        $lookup = app(TrackLookupMetadata::class);
        $this->assertSame(
            ['artist' => 'AURORA', 'title' => 'The Seed'],
            $lookup->prepare('AURORA', 'AURORA - The Seed'),
        );
        $this->assertSame(
            ['artist' => 'Adele', 'title' => 'Rolling in the Deep'],
            $lookup->prepare('Adele', 'Adele - Rolling in the Deep (Official Music Video)'),
        );
    }

    public function test_video_channel_can_be_replaced_by_artist_named_in_title(): void
    {
        $lookup = app(TrackLookupMetadata::class);
        $this->assertSame(
            ['artist' => 'Eminem', 'title' => 'Mockingbird'],
            $lookup->prepare('EminemMusic', 'Eminem - Mockingbird [Official Music Video]'),
        );
    }

    public function test_version_details_and_nonvideo_song_titles_are_preserved(): void
    {
        $lookup = app(TrackLookupMetadata::class);
        $this->assertSame(
            ['artist' => 'Artist', 'title' => 'Song (Live)'],
            $lookup->prepare('Artist', 'Song (Live)'),
        );
        $this->assertSame(
            ['artist' => 'Artist', 'title' => 'Song - Part Two'],
            $lookup->prepare('Artist', 'Song - Part Two'),
        );
    }
}
