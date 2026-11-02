<?php

namespace App\Services;

use App\Models\Track;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use SplFileInfo;

class MusicScanner
{
    public function scan(string $root): array
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

            $absolute = $file->getPathname();
            $resolved = realpath($absolute);
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
                if ($previous && $previous->size === $size && $previous->file_mtime === $mtime) {
                    if ($previous->is_missing) {
                        $previous->update(['is_missing' => false]);
                    }
                    $stats['unchanged']++;
                    continue;
                }

                $name = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                $parts = preg_split('/\s+-\s+/', $name, 2);
                $artist = count($parts) === 2 ? trim($parts[0]) : null;
                $title = count($parts) === 2 ? trim($parts[1]) : trim($name);

                Track::updateOrCreate(
                    ['path' => $relative],
                    ['title' => $title ?: $name, 'artist' => $artist,
                        'size' => $size, 'file_mtime' => $mtime, 'is_missing' => false]
                );
                $stats[$previous ? 'updated' : 'added']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                report($e);
            }
        }

        // Only mark missing once traversal completed. Never delete user tags.
        $stats['missing'] = Track::query()
            ->where('is_missing', false)
            ->when($seen !== [], fn ($query) => $query->whereNotIn('path', $seen))
            ->update(['is_missing' => true]);

        return $stats;
    }
}
