<?php

namespace App\Services;

/**
 * Builds search/classification metadata without modifying original track tags.
 */
class TrackLookupMetadata
{
    public function prepare(?string $artist, string $title): array
    {
        // PCRE /u needs valid UTF-8 *before* any regex or preg_quote call.
        // Convert only the search/request copy; original database tags remain intact.
        $originalArtist = trim($this->safeUtf8((string) $artist));
        $cleanArtist = $originalArtist;
        $cleanTitle = trim($this->safeUtf8($title));

        // Remove a trailing bracketed music-video label; leave song versions alone.
        $cleanTitle = preg_replace(
            '/\\s*[\\[(]\\s*(?:(?:official\\s+)?(?:music\\s+)?video|official\\s+audio|lyric\\s+video|lyrics?\\s+video|4k\\s+upgrade|hd\\s+video)[^\\])]*[\\])]/iu',
            '',
            $cleanTitle,
        ) ?? $cleanTitle;

        // Artist already present in ID3 and repeated at the beginning of Title.
        if ($cleanArtist !== '') {
            $prefix = preg_quote($cleanArtist, '/');
            $cleanTitle = preg_replace('/^\\s*'.$prefix.'\\s*[-–—]\\s*/iu', '', $cleanTitle) ?? $cleanTitle;
        }

        // A title originating from a video can have a channel as artist.
        // Only use the embedded "Artist - Title" when an obvious video annotation exists.
        $isVideo = preg_match('/\\b(?:official\\s+(?:music\\s+)?video|official\\s+audio|lyric\\s+video|lyrics?\\s+video)\\b/iu', $title) === 1;
        if ($isVideo && preg_match('/^\\s*([^–—-]+?)\\s*[-–—]\\s*(.+?)\\s*$/u', $cleanTitle, $parts)) {
            $embeddedArtist = trim($parts[1]);
            $embeddedTitle = trim($parts[2]);
            if ($embeddedArtist !== '' && $embeddedTitle !== '') {
                $cleanArtist = $embeddedArtist;
                $cleanTitle = $embeddedTitle;
            }
        }

        $cleanTitle = trim($cleanTitle, " \t\n\r\0\x0B\"'“”");

        return [
            'artist' => $cleanArtist !== '' ? $cleanArtist : $originalArtist,
            'title' => $cleanTitle !== '' ? $cleanTitle : $this->safeUtf8($title),
        ];
    }

    private function safeUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        // JSON's substitute flag inserts U+FFFD for each malformed sequence.
        // Round-tripping a single string avoids modifying mbstring global settings.
        return json_decode(
            json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
