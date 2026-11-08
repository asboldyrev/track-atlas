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
    public function test_malformed_utf8_is_replaced_before_unicode_regex_processing(): void
    {
        $lookup = app(TrackLookupMetadata::class);
        $actual = $lookup->prepare(
            "AURORA \xC3\x28",
            "AURORA \xC3\x28 - The Seed (Official Music Video)",
        );

        $this->assertTrue(mb_check_encoding($actual['artist'], 'UTF-8'));
        $this->assertTrue(mb_check_encoding($actual['title'], 'UTF-8'));
        $this->assertStringContainsString('�', $actual['artist']);
        $this->assertSame('The Seed', $actual['title']);
    }

    public function test_video_quality_suffix_recovers_real_artist_from_channel_name(): void
    {
        $lookup = app(TrackLookupMetadata::class);

        $this->assertSame(
            ['artist' => 'PAIN', 'title' => 'Shut Your Mouth'],
            $lookup->prepare('HDFactory', 'PAIN - Shut Your Mouth HQ HD 4K'),
        );
        $this->assertSame(
            ['artist' => 'Eminem', 'title' => 'Mockingbird'],
            $lookup->prepare('EminemMusic', 'Eminem - Mockingbird [Official Music Video]'),
        );
    }

    public function test_quality_words_are_not_stripped_without_explicit_video_evidence(): void
    {
        $lookup = app(TrackLookupMetadata::class);

        $this->assertSame(
            ['artist' => 'Band', 'title' => 'Song HD'],
            $lookup->prepare('Band', 'Song HD'),
        );
        $this->assertSame(
            ['artist' => 'Channel', 'title' => 'Song - Part Two'],
            $lookup->prepare('Channel', 'Song - Part Two'),
        );
    }

}
