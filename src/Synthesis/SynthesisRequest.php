<?php

declare(strict_types=1);

namespace Splicewire\CompositionMusicSpineData\Synthesis;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Splicewire\CompositionMusicSpineData\MusicIntent;

/**
 * The stage-2 synthesis input (ADR-0128 §1): a `MidiFile` artifact carried **by reference**, never the
 * inline binary and never the {@see MusicIntent} (sending the
 * intent would leak audiostud's arrangement moat across the seam — rejected). The satellite arranges the
 * MidiFile locally, then hands only this reference to the platform to synthesize audio.
 */
#[Title('Synthesis request')]
final class SynthesisRequest extends Data
{
    public function __construct(
        #[Title('MIDI artifact reference')]
        #[Description('The arranged MidiFile by reference — a Media uuid or target_url; never inline binary, never the MusicIntent.')]
        public string $midiRef,
        #[Title('Soundfont')]
        #[Description('Optional soundfont selector; a permissive GM soundfont (or host-provided) is the default. Choosing/bundling a specific soundfont is out of scope.')]
        public ?string $soundfont = null,
    ) {}
}
