<?php

namespace App\Services;

use App\Models\Track;
use App\Models\TrackTagDecision;
use Illuminate\Support\Facades\DB;

/**
 * Ручная разметка (B8).
 *
 * Отделяет решение человека от источника связи: строки track_tag больше не
 * получают source = 'manual' поверх существующего значения, а решения
 * accepted/ignored пишутся в track_tag_decision. Снятие метки оставляет
 * ignored-решение, поэтому сканер не возвращает её при следующем проходе.
 *
 * Методы рассчитаны на массовые вызовы: работают пакетами и предполагают
 * внешнюю транзакцию (веб-контроллеры оборачивают вызовы в DB::transaction).
 */
class TagDecisionSynchronizer
{
    private const CHUNK = 500;

    /**
     * Подтвердить метки для выбранных треков и достроить отсутствующие связи.
     * Источник уже существующих связей не меняется.
     *
     * @param  array<int, int>  $trackIds
     * @param  array<int, int>  $tagIds
     */
    public function accept(array $trackIds, array $tagIds): void
    {
        $this->writeDecisions($trackIds, $tagIds, TrackTagDecision::ACCEPTED);

        $existing = [];
        foreach ($this->chunks($trackIds) as $trackChunk) {
            foreach ($this->chunks($tagIds) as $tagChunk) {
                $rows = DB::table('track_tag')
                    ->whereIn('track_id', $trackChunk)
                    ->whereIn('tag_id', $tagChunk)
                    ->get(['track_id', 'tag_id']);
                foreach ($rows as $row) {
                    $existing[$row->track_id.':'.$row->tag_id] = true;
                }
            }
        }

        $inserts = [];
        foreach ($trackIds as $trackId) {
            foreach ($tagIds as $tagId) {
                if (isset($existing[$trackId.':'.$tagId])) {
                    continue;
                }
                $inserts[] = ['track_id' => $trackId, 'tag_id' => $tagId, 'source' => 'manual'];
            }
        }

        foreach (array_chunk($inserts, self::CHUNK) as $chunk) {
            DB::table('track_tag')->insertOrIgnore($chunk);
        }
    }

    /**
     * Пометить метки как ignored и снять их с треков.
     *
     * @param  array<int, int>  $trackIds
     * @param  array<int, int>  $tagIds
     */
    public function ignore(array $trackIds, array $tagIds): void
    {
        if ($trackIds === [] || $tagIds === []) {
            return;
        }

        $this->writeDecisions($trackIds, $tagIds, TrackTagDecision::IGNORED);

        foreach ($this->chunks($trackIds) as $trackChunk) {
            foreach ($this->chunks($tagIds) as $tagChunk) {
                DB::table('track_tag')
                    ->whereIn('track_id', $trackChunk)
                    ->whereIn('tag_id', $tagChunk)
                    ->delete();
            }
        }
    }

    /**
     * Полная ручная правка одного трека: оставить ровно переданный набор меток.
     * Подтверждённые метки сохраняют свой источник, снятые получают ignored.
     *
     * @param  array<int, int>  $tagIds
     */
    public function replace(Track $track, array $tagIds): void
    {
        $tagIds = array_values(array_unique(array_map('intval', $tagIds)));

        $current = DB::table('track_tag')->where('track_id', $track->id)->pluck('tag_id')
            ->map('intval')->all();

        $removed = array_values(array_diff($current, $tagIds));
        if ($removed !== []) {
            $this->ignore([$track->id], $removed);
        }

        if ($tagIds !== []) {
            $this->accept([$track->id], $tagIds);
        }
    }

    /**
     * @param  array<int, int>  $trackIds
     * @param  array<int, int>  $tagIds
     */
    private function writeDecisions(array $trackIds, array $tagIds, string $decision): void
    {
        if ($trackIds === [] || $tagIds === []) {
            return;
        }

        $now = now();
        $rows = [];
        foreach ($trackIds as $trackId) {
            foreach ($tagIds as $tagId) {
                $rows[] = [
                    'track_id' => (int) $trackId,
                    'tag_id' => (int) $tagId,
                    'decision' => $decision,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table('track_tag_decision')->upsert($chunk, ['track_id', 'tag_id'], ['decision', 'updated_at']);
        }
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array<int, int>>
     */
    private function chunks(array $ids): array
    {
        return array_chunk(array_values($ids), self::CHUNK);
    }
}
