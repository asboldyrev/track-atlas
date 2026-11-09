<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Решение ручной разметки по паре (трек, тег). Хранится отдельно от track_tag,
 * чтобы правки пользователя не переписывали источник связи (B8).
 */
class TrackTagDecision extends Model
{
    public const ACCEPTED = 'accepted';

    public const IGNORED = 'ignored';

    protected $table = 'track_tag_decision';

    protected $fillable = ['track_id', 'tag_id', 'decision'];
}
