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
 * `start` is in beats (quarter notes, as {@see MelodyNote}) from the section start, and is never negative
 * until build/26 admits the pickup chord.
 */
#[Title('Chord change')]
class ChordChange extends Data
{
    public function __construct(
        #[Title('Chord symbol')]
        #[Description('The chord symbol, as authored: "Bb", "Dm7", "C/E".')]
        public string $symbol,
        #[Title('Start (beats)')]
        #[Description('Beat offset from the section start where this chord starts to sound; 0.0 = the first downbeat.')]
        public float $start,
    ) {}
}
