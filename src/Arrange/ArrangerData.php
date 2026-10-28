<?php

namespace Splicewire\Composition\Music\Arrange;

use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The identity an Arranger reports with every {@see ArrangeResult} (music-capability-lift ticket 06 Q1, Q2). The
 * platform records the probe's `version` on the binding revision it mints at activation, and fails a run whose
 * reported version differs from it (`binding_stale`).
 */
#[TypeScript]
#[Title('Arranger')]
class ArrangerData extends Data
{
    public function __construct(
        #[Title('Name')]
        public string $name,
        #[Title('Version')]
        public string $version,
    ) {}
}
