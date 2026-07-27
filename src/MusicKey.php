<?php

declare(strict_types=1);

namespace Splicewire\Composition\Music;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;

/**
 * Tonic + mode — the universal key micro-wheel embedded in {@see MusicIntent} (mirrors audiostud's
 * SongKey). Not a notation format: just the two coordinates the Arranger needs to spell chords.
 */
#[Title('Key')]
final class MusicKey extends Data
{
    public function __construct(
        #[Title('Tonic')]
        #[Description('Note name: "C", "G", "Eb", "F#".')]
        public string $tonic,
        #[Title('Mode')]
        #[Description('"major" or "minor".')]
        public string $mode = 'major',
    ) {}
}
