<?php

namespace Splicewire\Composition\Music\Transcription;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Where a draft's tempo prior came from (music-capability-lift ticket 08 Q6), in precedence order: the owner's
 * request, then the plan's tempo, then the drum stem's. A run with none of them records no prior at all.
 */
#[TypeScript]
enum TempoPriorSource: string
{
    case Owner = 'owner';
    case Plan = 'plan';
    case Drums = 'drums';
}
