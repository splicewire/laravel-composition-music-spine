<?php

namespace Splicewire\Composition\Music\Notation;

use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** The bounded counts of what one notation run engraved (music-capability-lift ticket 02 Q2). */
#[TypeScript]
#[Title('Notation summary')]
class NotationSummary extends Data
{
    public function __construct(
        #[Title('Bars')]
        public int $bars,
        #[Title('Sections')]
        public int $sections,
        #[Title('Melody notes')]
        public int $melodyNotes,
        #[Title('Chord changes')]
        public int $chordChanges,
        #[Title('Pages')]
        public int $pages,
    ) {}
}
