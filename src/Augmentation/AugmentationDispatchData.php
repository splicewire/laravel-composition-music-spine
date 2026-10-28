<?php

namespace Splicewire\Composition\Music\Augmentation;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The augment Task's whole answer (music-capability-lift ticket 06 Q4, on ticket 03's Task + Read pair): it only
 * acknowledges. A Task's response never sees the request, so the run's state is the Read op's
 * ({@see AugmentationRunData}). It sits in the spine beside the notation and separation dispatch Data.
 */
#[TypeScript]
#[Title('Augmentation dispatch')]
class AugmentationDispatchData extends Data
{
    public function __construct(
        #[Title('Queued')]
        #[Description('True when the augmentation job was queued.')]
        public bool $queued,
    ) {}
}
