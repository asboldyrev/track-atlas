<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class MusicBrainzClient
{
    private const BASE = 'https://musicbrainz.org/ws/2';

    /**
     * The recording itself must match the stored title and artist; a high search
     * score alone is insufficient to safely classify a recording.
     */
    public function findGenres(string $title, string $artist): ?array
    {
        $query = 'recording:"'.$this->escape($title).'" AND artist:"'.$this->escape($artist).'"';
        $search = $this->request('/recording', [
            'query' => $query,
            'fmt' => 'json',
            'limit' => 5,
        ]);

        $matches = array_values(array_filter($search['recordings'] ?? [], function ($recording) use ($title, $artist) {
            if (! is_array($recording) || ($recording['score'] ?? 0) < 90) {
                return false;
            }

            $credit = implode(' ', array_map(
                fn ($item) => is_array($item) ? ($item['name'] ?? '') : (is_string($item) ? $item : ''),
                $recording['artist-credit'] ?? [],
            ));

            return $this->normalize($recording['title'] ?? '') === $this->normalize($title)
                && $this->normalize($credit) === $this->normalize($artist)
                && isset($recording['id']);
        }));

        if (! $matches) {
            return null;
        }

        usort($matches, fn ($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
        $recording = $matches[0];

        // Public MusicBrainz endpoint: keep requests at least one second apart.
        usleep(1200000);
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

    private function request(string $path, array $params): array
    {
        $response = Http::withHeaders([
            'User-Agent' => 'TrackAtlas/0.1 (https://github.com/asboldyrev/track-atlas)',
            'Accept' => 'application/json',
        ])->timeout(15)->get(self::BASE.$path, $params);

        if (! $response->successful()) {
            throw new RuntimeException('MusicBrainz HTTP '.$response->status());
        }

        return $response->json() ?? [];
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
