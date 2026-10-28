<?php

namespace Splicewire\Composition\Music\Augmentation;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The kept candidate's measured judgement (music-capability-lift ticket 06 Q4; the kernel's `Verdict`, ADR 0007 §6):
 * `mandatory` hard failures (empty on any kept candidate, since a mandatory failure fails the run), `advisory`
 * notes that never block, and the numeric `metrics` the feedback policy scores. Passing means admissible only;
 * whether the policy's targets were met is the result's separate `policy_satisfied`.
 */
#[TypeScript]
#[Title('Augmentation verdict')]
class AugmentationVerdictData extends Data
{
    /**
     * @param  list<string>  $mandatory
     * @param  list<string>  $advisory
     * @param  array<string, float>  $metrics
     */
    public function __construct(
        #[Title('Mandatory')]
        #[Description('Hard failures; empty means the candidate is admissible.')]
        public array $mandatory = [],
        #[Title('Advisory')]
        #[Description('Soft notes that never block, e.g. a degraded measure announcing itself.')]
        public array $advisory = [],
        #[Title('Metrics')]
        #[Description('The numeric readout the feedback policy scores, keyed by metric.')]
        public array $metrics = [],
    ) {
        $this->metrics = array_map(floatval(...), $metrics);
    }
}
