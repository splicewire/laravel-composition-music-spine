<?php

namespace Splicewire\Composition\Music\Notation;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An inline performance cue at an onset — the owner's `(ring)`, `(fill)`, `(hang)` (music-capability-lift
 * ticket 02 E5). The chart prints it in parentheses at its onset's column; the score as a direction's words.
 */
#[TypeScript]
#[Title('Cue')]
class NotationCue extends Data
{
    public function __construct(
        #[Title('Start (beats)')]
        #[Description('Beat offset from the section start.')]
        public float $start,
        #[Title('Text')]
        #[Description('The cue text, without parentheses: "ring", "fill", "hang".')]
        public string $text,
    ) {}
}
