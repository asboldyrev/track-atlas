<?php

namespace Tests\Unit;

use App\Services\GenreNormalizer;
use Tests\TestCase;

class GenreNormalizerTest extends TestCase
{
    public function test_known_aliases_map_to_one_canonical_genre(): void
    {
        $normalizer = app(GenreNormalizer::class);
        $this->assertSame('synthpop', $normalizer->canonical(' Synth-pop '));
        $this->assertSame($normalizer->slug('Synth-pop'), $normalizer->slug('Synthpop'));
        $this->assertSame('drum and bass', $normalizer->canonical('DnB'));
        $this->assertSame('darkwave', $normalizer->canonical('darkwave'));
    }
}
