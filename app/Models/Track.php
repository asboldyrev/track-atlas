<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Track extends Model
{
    protected $fillable = ['path', 'title', 'artist', 'album', 'size', 'file_mtime', 'is_missing'];

    protected function casts(): array
    {
        return ['is_missing' => 'boolean'];
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withPivot('source');
    }
}
