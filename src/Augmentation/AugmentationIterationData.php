<?php

namespace Splicewire\Composition\Music\Augmentation;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One pass of the loop's honest ledger (music-capability-lift ticket 06 Q4; the kernel's `IterationRecord`): the
 * stages that ran, a skipped one suffixed `(skipped)`, what the pass measured, its policy score and whether it
 * became the kept candidate. It carries the iteration-log provenance ADR 0007 wanted on the whole-song hook.
 *
 * `score` is null where the kernel scores a pass at infinity (a pass that failed a mandatory check): JSON has no
 * infinity. `stages_run` is mapped per property (see {@see AugmentationMixData}).
 */
#[TypeScript]
#[Title('Augmentation iteration')]
class AugmentationIterationData extends Data
{
    /**
     * @param  list<string>  $stagesRun
     * @param  array<string, float>  $metrics
     */
    public function __construct(
        #[Title('Iteration')]
        #[Description('The pass, 0-based as the loop numbers it.')]
        public int $iteration,
        #[Title('Stages run')]
        #[Description('The stage names executed this pass, in order; a skipped stage reads "name (skipped)".')]
        #[MapName('stages_run')]
        public array $stagesRun,
        #[Title('Metrics')]
        #[Description('What this pass measured, keyed by metric.')]
        public array $metrics,
        #[Title('Score')]
        #[Description('The feedback policy score, lower is better; null when the pass was not scored.')]
        public ?float $score,
        #[Title('Best')]
        #[Description('True when this pass became the kept candidate.')]
        public bool $best,
    ) {
        $this->metrics = array_map(floatval(...), $metrics);
    }
}
