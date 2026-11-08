<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class MusicBrainzClient
{
    private const BASE = 'https://musicbrainz.org/ws/2';

    private static ?float $lastRequestAt = null;

    /**
     * Return null for an unmatched recording; matched recordings may have no genres.
     * Never infer a track's genre solely from the artist's general style.
     */
    public function findGenres(string $title, string $artist): ?array
    {
        // Prefer the embedded artist tag. Only fall back to an explicit
        // "Artist - Title" prefix if this looks like a music-video filename.
        $candidates = [[
            'title' => $this->searchTitle($title, $artist),
            'artist' => $artist,
        ]];

        if (preg_match('/\\b(?:official\\s+(?:music\\s+)?video|official\\s+audio|lyric\\s+video|lyrics?\\s+video)\\b/iu', $title)) {
            $withoutAnnotation = preg_replace('/\\s*[\\[(](?:official\\s+(?:music\\s+)?video|official\\s+audio|lyric\\s+video|lyrics?\\s+video)[^\\])]*[\\])]/iu', '', $title);
            if (preg_match('/^\\s*([^–—-]+?)\\s*[-–—]\\s*(.+?)\\s*$/u', trim($withoutAnnotation), $parts)) {
                $prefixArtist = trim($parts[1]);
                $prefixTitle = trim($parts[2]);
                if ($prefixArtist !== '' && $prefixTitle !== ''
                    && $this->normalize($prefixArtist) !== $this->normalize($artist)) {
                    $candidates[] = ['title' => $prefixTitle, 'artist' => $prefixArtist];
                }
            }
        }

        $recording = null;
        foreach ($candidates as $candidate) {
            $query = 'recording:"'.$this->escape($candidate['title']).'" AND artist:"'.$this->escape($candidate['artist']).'"';
            $search = $this->request('/recording', [
                'query' => $query,
                'fmt' => 'json',
                'limit' => 5,
            ]);

            $matches = array_values(array_filter($search['recordings'] ?? [], function ($item) use ($candidate) {
                if (! is_array($item) || ($item['score'] ?? 0) < 90 || ! isset($item['id'])) {
                    return false;
                }

                $credit = implode('', array_map(
                    fn ($part) => is_array($part) ? ($part['name'] ?? '') : (is_string($part) ? $part : ''),
                    $item['artist-credit'] ?? [],
                ));

                return $this->normalize($item['title'] ?? '') === $this->normalize($candidate['title'])
                    && $this->normalize($credit) === $this->normalize($candidate['artist']);
            }));

            if ($matches !== []) {
                usort($matches, fn ($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
                $recording = $matches[0];
                break;
            }
        }

        if ($recording === null) {
            return null;
        }

        $detail = $this->request('/recording/'.$recording['id'], [
            'inc' => 'genres',
            'fmt' => 'json',
        ]);

        $genres = [];
        foreach (($detail['genres'] ?? []) as $genre) {
            $name = trim((string) ($genre['name'] ?? ''));
            if ($name !== '' && ($genre['count'] ?? 0) > 0) {
                $genres[] = $name;
            }
        }

        return [
            'recording_mbid' => $recording['id'],
            'match_score' => (int) $recording['score'],
            'genres' => array_values(array_unique($genres)),
        ];
    }

    private function searchTitle(string $title, string $artist): string
    {
        $clean = trim($title);
        $original = $clean;

        // Common YouTube annotations and an optional trailing artist credit.
        $clean = preg_replace('/\s*[\[(](?:official\s+(?:music\s+)?video|official\s+audio|lyric\s+video|lyrics?\s+video|4k\s+upgrade|hd\s+video)[^\])]*[\])]/iu', '', $clean);
        $hadVideoAnnotation = $clean !== $original;

        $credit = preg_quote(trim($artist), '/');
        $clean = preg_replace('/^'.$credit.'\s*[-–—]\s*/iu', '', $clean);
        if ($hadVideoAnnotation) {
            $clean = preg_replace('/\s*[-–—]\s*'.$credit.'$/iu', '', $clean);
        }

        $clean = trim($clean, " \t\n\r\0\x0B\"'“”");
        return $clean !== '' ? $clean : $original;
    }

    private function request(string $path, array $params): array
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->pace();
            $response = Http::withHeaders([
                'User-Agent' => 'TrackAtlas/0.1 (https://github.com/asboldyrev/track-atlas)',
                'Accept' => 'application/json',
            ])->timeout(15)->get(self::BASE.$path, $params);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            // 503 can be a MusicBrainz rate-limit response. Retry only transient responses.
            if (! in_array($response->status(), [429, 502, 503, 504], true) || $attempt === 3) {
                throw new RuntimeException('MusicBrainz HTTP '.$response->status());
            }

            sleep($attempt * 2);
        }

        throw new RuntimeException('MusicBrainz unavailable');
    }

    private function pace(): void
    {
        if (self::$lastRequestAt !== null) {
            $wait = 1.2 - (microtime(true) - self::$lastRequestAt);
            if ($wait > 0) {
                usleep((int) ($wait * 1000000));
            }
        }
        self::$lastRequestAt = microtime(true);
    }

    private function escape(string $value): string
    {
        return addcslashes($value, '\\":');
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)));
    }
}
