<?php

namespace App\Services;

use App\Models\Track;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class MusicScanner
{
    /**
     * Размер пакета для bulk-запросов. Ограничение SQLite в 32766 параметров
     * (SQLITE_MAX_VARIABLE_NUMBER) не даёт строить whereIn по всем путям
     * библиотеки, поэтому работа идёт чанками.
     */
    private const CHUNK = 500;

    public function __construct(
        private AudioMetadataReader $reader,
        private FileGenreSynchronizer $genres,
    ) {}

    public function scan(string $root, bool $refresh = false): array
    {
        $realRoot = realpath($root);
        if (! $realRoot || ! is_dir($realRoot) || ! is_readable($realRoot)) {
            throw new \InvalidArgumentException('Music directory is missing or unreadable.');
        }

        $stats = ['added' => 0, 'updated' => 0, 'unchanged' => 0, 'missing' => 0, 'errors' => 0];

        /** @var array<string, string> $seen относительный путь => абсолютный */
        $seen = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realRoot, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->isLink()) {
                continue;
            }

            $resolved = realpath($file->getPathname());
            if ($resolved === false || ! str_starts_with($resolved, $realRoot.DIRECTORY_SEPARATOR)) {
                continue;
            }
            if (! in_array(strtolower($file->getExtension()), config('music.extensions'), true)) {
                continue;
            }

            $seen[substr($resolved, strlen($realRoot) + 1)] = $resolved;
        }

        $restoreIds = [];

        foreach (array_chunk(array_keys($seen), self::CHUNK) as $paths) {
            $existing = Track::query()->whereIn('path', $paths)
                ->get(['id', 'path', 'size', 'file_mtime', 'is_missing'])
                ->keyBy('path');

            $rows = [];
            $genres = [];

            foreach ($paths as $relative) {
                try {
                    $size = filesize($seen[$relative]);
                    $mtime = filemtime($seen[$relative]);
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    report($e);

                    continue;
                }

                $previous = $existing->get($relative);
                if (! $refresh && $previous && (int) $previous->size === $size && (int) $previous->file_mtime === $mtime) {
                    if ($previous->is_missing) {
                        $restoreIds[] = $previous->id;
                    }
                    $stats['unchanged']++;

                    continue;
                }

                $metadata = $this->reader->read($seen[$relative]);
                $rows[] = [
                    'path' => $relative,
                    'title' => $metadata['title'],
                    'artist' => $metadata['artist'],
                    'album' => $metadata['album'],
                    'size' => $size,
                    'file_mtime' => $mtime,
                    'is_missing' => false,
                ];
                $genres[$relative] = $metadata['genres'] ?? [];
                $stats[$previous ? 'updated' : 'added']++;
            }

            if ($rows !== []) {
                // created_at/updated_at добавляет Eloquent; updated_at попадает в SET сам.
                Track::upsert($rows, ['path'], ['title', 'artist', 'album', 'size', 'file_mtime', 'is_missing']);
            }

            $this->syncGenres($genres);
        }

        foreach (array_chunk($restoreIds, self::CHUNK) as $chunk) {
            Track::query()->whereIn('id', $chunk)->update(['is_missing' => false]);
        }

        $stats['missing'] = $this->markMissing($seen);

        return $stats;
    }

    /**
     * Проставляет жанры для затронутых треков. Отдельный проход нужен потому,
     * что upsert не возвращает модели треков.
     *
     * @param  array<string, array<int, string>>  $genres  относительный путь => жанры
     */
    private function syncGenres(array $genres): void
    {
        if ($genres === []) {
            return;
        }

        foreach (array_chunk(array_keys($genres), self::CHUNK) as $paths) {
            foreach (Track::query()->whereIn('path', $paths)->get() as $track) {
                $this->genres->sync($track, $genres[$track->path] ?? []);
            }
        }
    }

    /**
     * Помечает отсутствующими треки, которых нет среди найденных файлов.
     * Идём чанками по существующим записям вместо whereNotIn по всем путям.
     *
     * @param  array<string, string>  $seen
     */
    private function markMissing(array $seen): int
    {
        $missingIds = [];

        Track::query()
            ->where('is_missing', false)
            ->select(['id', 'path'])
            ->chunkById(self::CHUNK, function ($tracks) use (&$missingIds, $seen) {
                foreach ($tracks as $track) {
                    if (! isset($seen[$track->path])) {
                        $missingIds[] = $track->id;
                    }
                }
            });

        foreach (array_chunk($missingIds, self::CHUNK) as $chunk) {
            Track::query()->whereIn('id', $chunk)->update(['is_missing' => true]);
        }

        return count($missingIds);
    }
}
