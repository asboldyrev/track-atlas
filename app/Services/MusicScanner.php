<?php

namespace App\Services;

use App\Models\Track;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class MusicScanner
{
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

            $relative = substr($resolved, strlen($realRoot) + 1);
            $seen[] = $relative;

            try {
                $size = $file->getSize();
                $mtime = $file->getMTime();
                $previous = Track::where('path', $relative)->first();
                if (! $refresh && $previous && $previous->size === $size && $previous->file_mtime === $mtime) {
                    if ($previous->is_missing) {
                        $previous->update(['is_missing' => false]);
                    }
                    $stats['unchanged']++;

                    continue;
                }

                $metadata = $this->reader->read($resolved);
                $track = Track::updateOrCreate(
                    ['path' => $relative],
                    ['title' => $metadata['title'], 'artist' => $metadata['artist'],
                        'album' => $metadata['album'], 'size' => $size,
                        'file_mtime' => $mtime, 'is_missing' => false]
                );
                $this->genres->sync($track, $metadata['genres']);
                $stats[$previous ? 'updated' : 'added']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                report($e);
            }
        }

        $stats['missing'] = Track::query()
            ->where('is_missing', false)
            ->when($seen !== [], fn ($query) => $query->whereNotIn('path', $seen))
            ->update(['is_missing' => true]);

        return $stats;
    }
}
