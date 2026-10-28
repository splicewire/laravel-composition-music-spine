<?php

namespace Splicewire\Composition\Music\Transcription;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The transcribe Task's whole answer (music-capability-lift ticket 04 Q2, on ticket 03 Q2): it only acknowledges.
 * A Task's response never sees the request, so the run's state is the Read op's ({@see TranscriptionRunData}).
 */
#[TypeScript]
#[Title('Transcription dispatch')]
class TranscriptionDispatchData extends Data
{
    public function __construct(
        #[Title('Queued')]
        #[Description('True when the transcription job was queued.')]
        public bool $queued,
    ) {}
}
