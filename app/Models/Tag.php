<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $fillable = ['type', 'name', 'slug', 'name_ru'];

    /**
     * Служебные ключи поиска (см. миграцию 2026_11_09_143000): наружу в JSON
     * они не отдаются — публичный контракт строки тега не меняется.
     */
    protected $hidden = ['name_search', 'name_ru_search'];

    protected static function booted(): void
    {
        // Ключи пересчитываются при каждой записи модели, поэтому `firstOrCreate`
        // и правка `name_ru` в других местах не оставляют их устаревшими.
        static::saving(function (self $tag): void {
            $tag->name_search = self::searchKey($tag->name);
            $tag->name_ru_search = self::searchKey($tag->name_ru);
        });
    }

    /**
     * Ключ поиска: значение без внешних пробелов в нижнем регистре.
     *
     * Регистр снимает PHP, а не SQL: в SQLite LIKE и lower() регистронезависимы
     * только для ASCII, поэтому кириллица («альт» / «Альт» / «АЛЬТ») без
     * предварительной свёртки сравнивалась бы посимвольно.
     * Отсутствующее значение остаётся null, иначе оно совпало бы с пустым поиском.
     */
    public static function searchKey(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_strtolower($value);
    }

    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(Track::class, 'track_tag')->withPivot('source');
    }
}
