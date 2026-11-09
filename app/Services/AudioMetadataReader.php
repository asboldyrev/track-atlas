<?php

namespace App\Services;

class AudioMetadataReader
{
    /**
     * Read file tags, falling back to a conservative Artist - Title filename parser.
     * Invalid or untagged audio must not prevent cataloguing its filename.
     */
    public function read(string $path): array
    {
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $parts = preg_split('/\s+-\s+/', $filename, 2);
        $fallbackArtist = count($parts) === 2 ? trim($parts[0]) : null;
        $fallbackTitle = count($parts) === 2 ? trim($parts[1]) : trim($filename);

        $result = [
            'title' => $fallbackTitle ?: $filename,
            'artist' => $fallbackArtist,
            'album' => null,
            'genres' => [],
        ];

        // getID3 is installed separately by Composer; read failures leave filename fallback.
        if (! class_exists(\getID3::class)) {
            return $result;
        }

        try {
            $data = (new \getID3)->analyze($path);
            \getid3_lib::CopyTagsToComments($data);
            $comments = is_array($data['comments'] ?? null) ? $data['comments'] : [];

            $result['title'] = $this->first($comments['title'] ?? null) ?: $result['title'];
            $result['artist'] = $this->first($comments['artist'] ?? null)
                ?: $this->first($comments['band'] ?? null)
                ?: $result['artist'];
            $result['album'] = $this->first($comments['album'] ?? null);
            $result['genres'] = $this->values($comments['genre'] ?? null);
        } catch (\Throwable $e) {
            report($e);
        }

        return $result;
    }

    private function first(mixed $values): ?string
    {
        return $this->values($values)[0] ?? null;
    }

    private function values(mixed $values): array
    {
        $items = is_array($values) ? $values : [$values];
        $clean = [];
        foreach ($items as $item) {
            if (! is_scalar($item)) {
                continue;
            }
            $value = trim((string) $item);
            if ($value !== '' && ! in_array($value, $clean, true)) {
                $clean[] = $value;
            }
        }

        return $clean;
    }
}
