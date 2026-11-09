<?php

namespace Tests\Feature;

use App\Models\Track;
use App\Services\MusicScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * B9: сканер обрабатывает библиотеку чанками и помечает отсутствующие треки,
 * не строя whereNotIn по всем путям (иначе SQLite упирается в лимит параметров).
 */
class MusicScannerChunkTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_detection_works_beyond_a_single_chunk(): void
    {
        // 600 записей — больше размера чанка сканера (500).
        $rows = [];
        for ($i = 0; $i < 600; $i++) {
            $rows[] = [
                'path' => 'track-'.$i.'.mp3',
                'title' => 'Track '.$i,
                'artist' => 'Artist',
                'size' => 1,
                'file_mtime' => 1,
                'is_missing' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        Track::insert($rows);

        $folder = sys_get_temp_dir().'/track-atlas-chunk-'.bin2hex(random_bytes(6));
        File::makeDirectory($folder);

        try {
            // Каталог пуст — все записи должны стать отсутствующими.
            $stats = app(MusicScanner::class)->scan($folder);

            $this->assertSame(600, $stats['missing']);
            $this->assertSame(600, Track::where('is_missing', true)->count());
        } finally {
            File::deleteDirectory($folder);
        }
    }
}
