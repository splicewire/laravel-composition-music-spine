<?php

namespace Splicewire\Composition\Music\Augmentation;

use InvalidArgumentException;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A completed augmentation run's outcome (music-capability-lift ticket 06 Q4): the kept take, its verdict, whether
 * the feedback policy's targets were met, every pass, and which Arranger made the MIDI. A passing verdict means
 * admissible only (ADR 0007 §6), which is why `policy_satisfied` is its own member. The kept verdict never carries a
 * mandatory failure: a mandatory verdict on any pass fails the run instead (`mandatory_verdict`), so one here is refused.
 *
 * `policy_satisfied` is mapped per property (see {@see AugmentationMixData}).
 */
#[TypeScript]
#[Title('Augmentation result')]
class AugmentationResultData extends Data
{
    /**
     * @param  list<AugmentationIterationData>  $iterations
     */
    public function __construct(
        #[Title('Take')]
        public AugmentationTakeData $take,
        #[Title('Verdict')]
        #[Description('The kept candidate\'s verdict; passing means admissible only.')]
        public AugmentationVerdictData $verdict,
        #[Title('Policy satisfied')]
        #[Description('True when the feedback policy\'s targets were met by the kept candidate.')]
        #[MapName('policy_satisfied')]
        public bool $policySatisfied,
        #[Title('Iterations')]
        #[Description('Every pass the loop ran, in order.')]
        #[DataCollectionOf(AugmentationIterationData::class)]
        public array $iterations,
        #[Title('Arranger')]
        public AugmentationArrangerData $arranger,
    ) {
        if ($verdict->mandatory !== []) {
            throw new InvalidArgumentException('A kept candidate carries no mandatory failure; a mandatory verdict fails the run (mandatory_verdict).');
        }
    }
}
