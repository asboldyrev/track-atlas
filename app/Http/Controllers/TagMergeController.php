<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TagMergeController extends Controller
{
    public function merge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source_id' => ['required', 'integer', 'exists:tags,id'],
            'target_id' => ['required', 'integer', 'different:source_id', 'exists:tags,id'],
        ]);

        return DB::transaction(function () use ($data) {
            $tags = Tag::query()
                ->whereIn('id', [$data['source_id'], $data['target_id']])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $source = $tags->get($data['source_id']);
            $target = $tags->get($data['target_id']);
            if (! $source || ! $target || $source->type !== $target->type) {
                throw ValidationException::withMessages([
                    'target_id' => 'Объединять можно только категории одного типа.',
                ]);
            }

            $count = 0;
            // Work with the pivot relation directly so manual provenance wins
            // over an existing file/AI association on the destination tag.
            $links = DB::table('track_tag')->where('tag_id', $source->id)->get();
            foreach ($links as $link) {
                $existing = DB::table('track_tag')
                    ->where('track_id', $link->track_id)
                    ->where('tag_id', $target->id)
                    ->first();
                if (! $existing) {
                    DB::table('track_tag')->insert([
                        'track_id' => $link->track_id,
                        'tag_id' => $target->id,
                        'source' => $link->source,
                    ]);
                } elseif ($link->source === 'manual' && $existing->source !== 'manual') {
                    DB::table('track_tag')->where('track_id', $link->track_id)
                        ->where('tag_id', $target->id)->update(['source' => 'manual']);
                }
                $count++;
            }

            // The target category remains canonical. Keep its Russian override,
            // or inherit the source override when the target has none.
            if (! $target->name_ru && $source->name_ru) {
                $target->update(['name_ru' => $source->name_ru]);
            }

            $source->delete();

            return response()->json([
                'merged' => $count,
                'target' => $target->fresh(),
                'removed_id' => $source->id,
            ]);
        });
    }
}
