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
 *
 * **Stage 2** (build/26, ticket 05 E3, Q4 amended). A section's syllables are its complete ordered list of units, cut
 * at chord columns (a word cut by a chord is two units, the first with `hyphen`). `start` follows the melody's
 * negative-start rule: down to `-pickup` in section 0, and down to one bar of the previous section in a later one
 * (a lead-in). In a section **without melody** only a unit under a chord change has a `start` — that chord's onset —
 * and the others carry `null`; nothing is underlaid. In a section **with melody** every unit is timed and sits on a
 * note. {@see NotationRequest} refuses by name what breaks either rule.
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
        #[Description('Beat offset from the section start where the syllable is sung. Null only in a section without melody, for a unit under no chord change.')]
        public ?float $start,
        #[Title('Line')]
        #[Description('Index into the section lines this syllable belongs to (0-based).')]
        public int $line,
        #[Title('Hyphen')]
        #[Description('True when the word continues into the next syllable.')]
        public bool $hyphen = false,
    ) {}
}
