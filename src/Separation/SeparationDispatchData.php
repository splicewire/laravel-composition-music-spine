<?php

namespace Splicewire\Composition\Music\Separation;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The separate Task's whole answer (music-capability-lift ticket 03 Q2 amended): it only acknowledges. A Task's
 * response never sees the request, so the run's state is the Read op's ({@see SeparationRunData}). It sits in
 * the spine beside notation's `NotationDispatchData`.
 */
#[TypeScript]
#[Title('Separation dispatch')]
class SeparationDispatchData extends Data
{
    public function __construct(
        #[Title('Queued')]
        #[Description('True when the separation job was queued.')]
        public bool $queued,
    ) {}
}
