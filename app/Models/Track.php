<?php

namespace App\Models;

use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Track extends Model
{
    /**
     * Минимальная длина запроса для полнотекстового поиска. Более короткие
     * строки (1–2 символа) уходят в LIKE: FTS5 ищет по токенам и на таком
     * вводе бесполезен.
     */
    public const FTS_MIN_LENGTH = 3;

    protected $fillable = ['path', 'title', 'artist', 'album', 'size', 'file_mtime', 'is_missing'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'file_mtime' => 'integer',
            'is_missing' => 'boolean',
        ];
    }

    public function metadataSuggestions(): HasMany
    {
        return $this->hasMany(MetadataSuggestion::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'track_tag')->withPivot('source');
    }

    public function tagDecisions(): HasMany
    {
        return $this->hasMany(TrackTagDecision::class);
    }

    /**
     * Поиск по названию и исполнителю.
     *
     * Длинные запросы обслуживает FTS5 (tracks_fts, внешний контент tracks),
     * короткие — прежний LIKE. Условие добавлено отдельным whereIn-подзапросом,
     * чтобы не мешать соединениям и фильтрам вызывающего кода.
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $match = self::ftsMatchExpression($term);
        if ($match === null) {
            // Короткие запросы уходят в LIKE. Подстановочные знаки экранированы,
            // иначе «%» или «_» совпадали бы с любой строкой.
            $pattern = LikePattern::contains($term);
            $query->where(fn ($q) => $q
                ->whereRaw(LikePattern::expression('title'), [$pattern])
                ->orWhereRaw(LikePattern::expression('artist'), [$pattern]));

            return;
        }

        $query->whereIn('tracks.id', function ($sub) use ($match) {
            $sub->select('rowid')->from('tracks_fts')->whereRaw('tracks_fts MATCH ?', [$match]);
        });
    }

    /**
     * Преобразует произвольный ввод в выражение FTS5: каждый токен — это фраза
     * в кавычках с префиксным «*». Возвращает null, если искать нечего
     * (короткий запрос или одни знаки препинания).
     */
    private static function ftsMatchExpression(string $term): ?string
    {
        if (mb_strlen($term) < self::FTS_MIN_LENGTH) {
            return null;
        }

        $tokens = [];
        foreach (preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY) as $token) {
            if (! preg_match('/[\p{L}\p{N}]/u', $token)) {
                continue;
            }
            $tokens[] = '"'.str_replace('"', '""', $token).'"*';
        }

        return $tokens === [] ? null : implode(' ', $tokens);
    }
}
