<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TracksIndexRequest;
use App\Queries\TrackCatalogQuery;
use Illuminate\Http\JsonResponse;

class TrackApiController extends Controller
{
    /**
     * Список треков с фильтрами каталога. Справочник тегов и переводы отдаёт
     * /api/tags, поэтому здесь только строки списка.
     */
    public function index(TracksIndexRequest $request, TrackCatalogQuery $catalog): JsonResponse
    {
        $filters = $request->validated();

        // per_page приходит только из белого списка (см. config/api.php),
        // поэтому приведение к int безопасно.
        $perPage = (int) ($filters['per_page'] ?? config('api.limits.tracks_per_page'));

        return response()->json([
            'tracks' => $catalog->paginate($filters, $perPage),
        ]);
    }
}
