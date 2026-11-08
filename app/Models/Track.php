<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Track extends Model
{
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
}
