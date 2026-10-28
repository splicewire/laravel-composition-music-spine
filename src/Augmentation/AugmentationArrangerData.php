<?php

namespace Splicewire\Composition\Music\Augmentation;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Which Arranger made the run's MIDI (music-capability-lift ticket 06 Q2, Q4): the tenant's Satellite binding
 * (`conduit_id`, the `binding_revision` the run was pinned to) and the identity the Arranger reported in its
 * `ArrangeResult` (`name`, `version`).
 *
 * The wire names are snake_case, mapped per property (see {@see AugmentationMixData}).
 */
#[TypeScript]
#[Title('Augmentation arranger')]
class AugmentationArrangerData extends Data
{
    public function __construct(
        #[Title('Conduit id')]
        #[Description('The tenant binding the Arranger was called through.')]
        #[MapName('conduit_id')]
        public string $conduitId,
        #[Title('Binding revision')]
        #[Description('The binding revision the run was pinned to at dispatch.')]
        #[MapName('binding_revision')]
        public string $bindingRevision,
        #[Title('Name')]
        #[Description('The Arranger name it reported.')]
        public string $name,
        #[Title('Version')]
        #[Description('The Arranger version it reported.')]
        public string $version,
    ) {}
}
