<?php

namespace Splicewire\Composition\Music\Arrange;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The Arranger's 200 answer (music-capability-lift ticket 06 Q2): the arranged MIDI inline as base64, its sha256 and
 * byte count, and the Arranger's identity. Inline rather than a media uuid because the call is synchronous and the
 * artifact is kilobytes. This Data carries the claim only; the platform checks it (the sha256, an `MThd` header,
 * a 1 MiB cap) and fails the run `arranger_invalid_result` when a check fails.
 *
 * The wire names are snake_case, mapped per property (see {@see ArrangeRequest}).
 */
#[TypeScript]
#[Title('Arrange result')]
class ArrangeResult extends Data
{
    public function __construct(
        #[Title('MIDI (base64)')]
        #[Description('The arranged Standard MIDI File, base64-encoded.')]
        #[MapName('midi_base64')]
        public string $midiBase64,
        #[Title('MIDI sha256')]
        #[Description('sha256 of the decoded MIDI bytes, lowercase hex.')]
        #[MapName('midi_sha256')]
        public string $midiSha256,
        #[Title('Bytes')]
        #[Description('Length of the decoded MIDI bytes.')]
        public int $bytes,
        #[Title('Arranger')]
        #[Description('The Arranger that made the MIDI, as it reports itself.')]
        public ArrangerData $arranger,
    ) {}

    /** The answer for a MIDI file: encodes it and states its sha256 and length. */
    public static function forMidi(string $midi, ArrangerData $arranger): self
    {
        return new self(base64_encode($midi), hash('sha256', $midi), strlen($midi), $arranger);
    }
}
