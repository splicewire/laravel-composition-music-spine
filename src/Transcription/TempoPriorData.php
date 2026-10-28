<?php

namespace Splicewire\Composition\Music\Transcription;

use InvalidArgumentException;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The **tempo prior** a draft was transcribed under (music-capability-lift ticket 08 Q3, Q6): one positive integer
 * BPM and its source. It tells transcription which metrical level to count at; it is never the tempo the draft
 * reports ({@see ChartDraftData::$tempoBpm}). An owner prior that disagrees with the plan wins and is recorded here.
 */
#[TypeScript]
#[Title('Tempo prior')]
class TempoPriorData extends Data
{
    public function __construct(
        #[Title('BPM')]
        #[Description('The prior, a positive integer BPM.')]
        public int $bpm,
        #[Title('Source')]
        #[Description('owner | plan | drums — the highest-precedence source present.')]
        public TempoPriorSource $source,
    ) {
        if ($bpm <= 0) {
            throw new InvalidArgumentException("A tempo prior is a positive integer BPM, got {$bpm}.");
        }
    }
}
