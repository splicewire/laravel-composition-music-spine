<?php

namespace Splicewire\Composition\Music\Notation;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The notate Task's whole answer (music-capability-lift ticket 02 Q6 amended): it only acknowledges. A Task's
 * response never sees the request, so the run's state is the Read op's ({@see NotationRunData}).
 */
#[TypeScript]
#[Title('Notation dispatch')]
class NotationDispatchData extends Data
{
    public function __construct(
        #[Title('Queued')]
        #[Description('True when the notation job was queued.')]
        public bool $queued,
    ) {}
}
