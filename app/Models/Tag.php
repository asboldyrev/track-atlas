<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $fillable = ['type', 'name', 'slug'];

    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(Track::class, 'track_tag')->withPivot('source');
    }
}
