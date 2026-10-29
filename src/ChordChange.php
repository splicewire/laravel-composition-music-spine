<?php

namespace Splicewire\Composition\Music;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;

/**
 * A **chord change**: a chord and the beat in its section where it starts to sound (`music/intent` v2,
 * music-capability-lift ticket 02 Q1). A section's `changes` carry its sub-bar harmony; its `chords` stay the
 * chord sounding at each bar's downbeat, and {@see Conformance\StructuralMusicConformance} holds the two
 * consistent. The symbol prints as authored.
 *
 * `start` is in beats (quarter notes, as {@see MelodyNote}) from the section start. It is negative only for the
 * **pickup chord** (build/26, ticket 05 E3): a change in section 0 with `-pickup ≤ start < 0`, sounding before the
 * first bar. Everywhere else a change starts at or after its section's first downbeat.
 *
 * A {@see Transcription\ChartDraftData} reuses this class unchanged in its own **draft coordinate**: `start` in
 * beats from draft bar 0's downbeat, negative in the pickup. {@see Transcription\ChartDraftSections::apply()}
 * rebases it to the section coordinate above.
 */
#[Title('Chord change')]
class ChordChange extends Data
{
    public function __construct(
        #[Title('Chord symbol')]
        #[Description('The chord symbol, as authored: "Bb", "Dm7", "C/E".')]
        public string $symbol,
        #[Title('Start (beats)')]
        #[Description('Beat offset from the section start where this chord starts to sound; 0.0 = the first downbeat. Negative only in section 0, for a chord sounding in the pickup (down to -pickup).')]
        public float $start,
    ) {}
}
