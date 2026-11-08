<?php

namespace App\Http\Controllers;

use App\Models\AiUsageLog;
use App\Models\MetadataSuggestion;
use App\Models\Tag;
use App\Models\Track;
use App\Services\GenreNormalizer;
use App\Services\OpenRouterClassifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AiClassificationController extends Controller
{
    public function classify(Request $request, OpenRouterClassifier $classifier): JsonResponse
    {
        $data = $request->validate([
            'tracks' => ['required', 'array', 'min:1', 'max:10'],
            'tracks.*' => ['required', 'integer', 'distinct', 'exists:tracks,id'],
            'retry_empty' => ['sometimes', 'boolean'],
        ]);

        $ids = array_map('intval', $data['tracks']);
        $tracks = Track::query()->whereIn('id', $ids)->where('is_missing', false)->with('tags')->get();

        if ($tracks->count() !== count($ids)) {
            return response()->json(['message' => 'Выбраны отсутствующие треки.'], 422);
        }

        $existing = MetadataSuggestion::query()
            ->where('provider', 'openrouter')->whereIn('track_id', $ids)
            ->get()->keyBy('track_id');

        $retryEmpty = (bool) ($data['retry_empty'] ?? false);
        $eligible = $tracks->filter(function ($track) use ($existing, $retryEmpty) {
            $suggestion = $existing->get($track->id);
            if ($suggestion === null) {
                return true;
            }

            // Allow a paid retry only for empty, still-pending suggestions.
            // Accepted/rejected and nonempty suggestions are never overwritten here.
            return $retryEmpty && $suggestion->status === 'pending'
                && ($suggestion->genres ?? []) === []
                && ($suggestion->moods ?? []) === [];
        })->values();
        if ($eligible->isEmpty()) {
            return response()->json([
                'message' => 'Все выбранные треки уже проверялись. Новые запросы не отправлены.',
                'processed' => 0,
                'skipped' => count($ids),
                'usage' => null,
            ]);
        }

        try {
            $result = $classifier->classify($eligible);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Не удалось получить классификацию OpenRouter.'], 502);
        }

        DB::transaction(function () use ($eligible, $result) {
            foreach ($eligible as $track) {
                $proposal = $result['tracks'][$track->id];
                MetadataSuggestion::updateOrCreate(
                    ['track_id' => $track->id, 'provider' => 'openrouter'],
                    [
                        'model' => $result['model'],
                        'genres' => $proposal['genres'],
                        'moods' => $proposal['moods'],
                        'status' => 'pending',
                        'error' => null,
                    ],
                );
            }

            AiUsageLog::create([
                'provider' => 'openrouter',
                'model' => $result['model'],
                'track_ids' => $eligible->pluck('id')->all(),
                'prompt_tokens' => $result['usage']['prompt_tokens'],
                'completion_tokens' => $result['usage']['completion_tokens'],
                'cost_usd' => $result['usage']['cost_usd'],
                'outcome' => 'completed',
            ]);
        });

        return response()->json([
            'processed' => $eligible->count(),
            'skipped' => count($ids) - $eligible->count(),
            'usage' => $result['usage'],
        ]);
    }

    public function decide(Request $request, MetadataSuggestion $suggestion, GenreNormalizer $normalizer): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:accept,reject'],
        ]);

        if ($suggestion->provider !== 'openrouter') {
            return response()->json(['message' => 'Недоступный тип предложения.'], 422);
        }

        try {
            DB::transaction(function () use ($suggestion, $data, $normalizer) {
                $locked = MetadataSuggestion::query()->lockForUpdate()->findOrFail($suggestion->id);
                if ($locked->status !== 'pending') {
                    throw new RuntimeException('Предложение уже обработано.');
                }

                if ($data['decision'] === 'accept') {
                    $pairs = ['genre' => $locked->genres ?? [], 'mood' => $locked->moods ?? []];
                    foreach ($pairs as $type => $labels) {
                        foreach ($labels as $label) {
                            $name = $type === 'genre' ? $normalizer->canonical($label) : trim($label);
                            $slug = $type === 'genre' ? $normalizer->slug($name) : Str::slug($name);
                            if ($slug === '' || mb_strlen($name) > 80) {
                                continue;
                            }
                            $tag = Tag::firstOrCreate(['type' => $type, 'slug' => $slug], ['name' => $name]);
                            if (! $locked->track->tags()->where('tags.id', $tag->id)->exists()) {
                                $locked->track->tags()->attach($tag->id, ['source' => 'openrouter']);
                            }
                        }
                    }
                }

                $locked->update(['status' => $data['decision'] === 'accept' ? 'accepted' : 'rejected']);
            });
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['ok' => true]);
    }
}
