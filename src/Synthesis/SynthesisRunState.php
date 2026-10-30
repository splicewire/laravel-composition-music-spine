<?php

namespace Splicewire\Composition\Music\Synthesis;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Composition\Music\Separation\SeparationRunState;

/**
 * The state the synthesis Read op answers for one exact run key (music-capability-lift ticket 16 Q5, Q17), as the
 * separation pair has it ({@see SeparationRunState}): `none` (no citation for the key — on an exact key the caller
 * holds, terminal), `pending` (admitted, not yet finished), `completed` (the composite is stored and metered),
 * `failed` (the job failed, or the run went stale). `failed` is terminal per attempt; a new `POST` is the retry.
 */
#[TypeScript]
enum SynthesisRunState: string
{
    case None = 'none';
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
