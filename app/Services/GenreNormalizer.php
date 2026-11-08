<?php

namespace App\Services;

use Illuminate\Support\Str;

class GenreNormalizer
{
    public function canonical(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        $aliases = config('genre_aliases', []);
        $key = mb_strtolower($name);

        return $aliases[$key] ?? $name;
    }

    public function slug(string $name): string
    {
        return Str::slug($this->canonical($name));
    }
}
