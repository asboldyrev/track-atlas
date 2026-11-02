<?php

namespace App\Console\Commands;

use App\Services\MusicScanner;
use Illuminate\Console\Command;

class ScanMusic extends Command
{
    protected $signature = 'music:scan {path? : Music folder; otherwise MUSIC_LIBRARY_PATH} {--refresh : Read tags again even for unchanged files}';
    protected $description = 'Index music files without changing the originals';

    public function handle(MusicScanner $scanner): int
    {
        $path = $this->argument('path') ?: config('music.path');
        if (! $path) {
            $this->error('Set MUSIC_LIBRARY_PATH or pass a folder.');
            return self::FAILURE;
        }

        try {
            $stats = $scanner->scan($path, (bool) $this->option('refresh'));
            foreach ($stats as $name => $value) {
                $this->line("$name: $value");
            }
            return $stats['errors'] ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
