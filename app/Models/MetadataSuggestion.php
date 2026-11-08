<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetadataSuggestion extends Model
{
    protected $fillable = [
        'track_id', 'provider', 'recording_mbid', 'match_score', 'genres', 'status', 'error',
    ];

    protected function casts(): array
    {
        return ['genres' => 'array', 'match_score' => 'integer'];
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }
}
