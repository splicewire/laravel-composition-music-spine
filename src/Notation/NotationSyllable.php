<?php

namespace Splicewire\Composition\Music\Notation;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A **syllable onset**: a lyric syllable and the beat in its section where it is sung (music-capability-lift
 * ticket 02 Q1). The score underlays it on the note that starts there; the chart anchors each chord above
 * the syllable sounding at the chord's onset. Lyrics belong to what is printed, never to the intent.
 */
#[TypeScript]
#[Title('Syllable')]
class NotationSyllable extends Data
{
    public function __construct(
        #[Title('Text')]
        #[Description('The syllable as sung, without its hyphen.')]
        public string $text,
        #[Title('Start (beats)')]
        #[Description('Beat offset from the section start where the syllable is sung.')]
        public float $start,
        #[Title('Line')]
        #[Description('Index into the section lines this syllable belongs to (0-based).')]
        public int $line,
        #[Title('Hyphen')]
        #[Description('True when the word continues into the next syllable.')]
        public bool $hyphen = false,
    ) {}
}
