<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MetaApiController extends Controller
{
    /**
     * Служебные данные для фронтенда: единые лимиты и локали.
     * Лимиты берутся из config/api.php, чтобы клиент не дублировал числа у себя.
     */
    public function show(): JsonResponse
    {
        $limits = config('api.limits');

        return response()->json([
            'name' => 'track-atlas',
            'locales' => config('api.locales'),
            'limits' => [
                'batch_tracks_max' => (int) $limits['batch_tracks_max'],
                'ai_tracks_max' => (int) $limits['ai_tracks_max'],
                'tracks_per_page' => (int) $limits['tracks_per_page'],
                'tracks_per_page_options' => array_map('intval', $limits['tracks_per_page_options']),
                'tags_per_page' => (int) $limits['tags_per_page'],
                'search_max_length' => (int) $limits['search_max_length'],
                'tag_name_max_length' => (int) $limits['tag_name_max_length'],
                'tag_name_ru_max_length' => (int) $limits['tag_name_ru_max_length'],
            ],
            'ai' => [
                'model' => config('openrouter.model'),
            ],
        ]);
    }
}
