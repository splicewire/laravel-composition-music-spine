<?php

namespace Splicewire\Composition\Music\Transcription;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One plan chord placed on the take by plan conformance's forced alignment (music-capability-lift ticket 04 Q6):
 * the chord and the span of the take it was aligned to. Wire names are snake_case, mapped per property.
 */
#[TypeScript]
#[Title('Aligned chord')]
class AlignedChordData extends Data
{
    public function __construct(
        #[Title('Chord')]
        #[Description('The plan\'s chord symbol.')]
        public string $chord,
        #[Title('Start (s)')]
        #[MapName('start_s')]
        public float $startS,
        #[Title('End (s)')]
        #[MapName('end_s')]
        public float $endS,
    ) {}
}
