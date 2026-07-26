<?php

declare(strict_types=1);

namespace Splicewire\CompositionMusicSpineData;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;

/**
 * One beat-relative note of an optional fenced melody (verbatim re-adoption of audiostud's MelodyNote).
 * Absolute MIDI pitch is the universal micro-wheel; timing is in beats from the section start so the
 * adapter can place it under any tempo/meter.
 */
#[Title('Melody note')]
final class MelodyNote extends Data
{
    public function __construct(
        #[Title('Pitch')]
        #[Description('Absolute MIDI pitch 0–127 (middle C = 60).')]
        public int $pitch,
        #[Title('Start (beats)')]
        #[Description('Beat offset from section start; 0.5 = mid-beat-1.')]
        public float $start,
        #[Title('Duration (beats)')]
        #[Description('Length in beats; 1.0 = quarter note.')]
        public float $duration,
    ) {}
}
