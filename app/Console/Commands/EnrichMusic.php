<?php

namespace App\Console\Commands;

use App\Models\MetadataSuggestion;
use App\Models\Track;
use App\Services\MusicBrainzClient;
use Illuminate\Console\Command;

class EnrichMusic extends Command
{
    protected $signature = 'music:enrich
        {--limit=10 : Maximum number of recordings in this run (1-30)}
        {--track= : Process a particular track ID}
        {--force : Re-query tracks even when suggestions have already been checked}';

    protected $description = 'Fetch MusicBrainz genre suggestions without changing track tags';

    public function handle(MusicBrainzClient $client): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 30) {
            $this->error('--limit must be between 1 and 30.');

            return self::FAILURE;
        }

        $query = Track::query()->where('is_missing', false)
            ->whereNotNull('artist')->where('artist', '<>', '')
            ->where('title', '<>', '')->orderBy('id');

        if ($trackId = $this->option('track')) {
            if (! ctype_digit((string) $trackId)) {
                $this->error('--track must be a positive integer.');

                return self::FAILURE;
            }
            $query->whereKey((int) $trackId);
        }

        if (! $this->option('force')) {
            $query->whereDoesntHave('metadataSuggestions', fn ($q) => $q->where('provider', 'musicbrainz'));
        }

        $tracks = $query->limit($limit)->get();
        if ($tracks->isEmpty()) {
            $this->info('No eligible tracks. Use --force to re-check existing suggestions.');

            return self::SUCCESS;
        }

        $checked = 0;
        $failed = 0;
        foreach ($tracks as $track) {
            if ($checked > 0) {
                usleep(1200000);
            }

            try {
                $result = $client->findGenres($track->title, $track->artist);
                MetadataSuggestion::updateOrCreate(
                    ['track_id' => $track->id, 'provider' => 'musicbrainz'],
                    [
                        'recording_mbid' => $result['recording_mbid'] ?? null,
                        'match_score' => $result['match_score'] ?? null,
                        'genres' => $result['genres'] ?? [],
                        'status' => $result === null ? 'unmatched' : (($result['genres'] ?? []) === [] ? 'matched_no_genres' : 'pending'),
                        'error' => null,
                    ]
                );
                $list = implode(', ', $result['genres'] ?? []);
                $description = $result === null ? '(recording not matched)' : ($list ?: '(matched, no recording genres)');
                $this->line('#'.$track->id.' '.$track->artist.' — '.$track->title.': '.$description);
            } catch (\Throwable $e) {
                // A failed lookup is not cached: retrying the command is safe.
                $failed++;
                $this->warn('#'.$track->id.': '.$e->getMessage());
            }
            $checked++;
        }

        $this->info("Processed: $checked; errors: $failed. Existing track tags were not changed.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
