<?php

namespace Splicewire\Composition\Music\Augmentation;

use InvalidArgumentException;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Composition\Music\Notation\NotationRunData;

/**
 * The augmentation Read op's answer for one exact run (music-capability-lift ticket 06 Q4, amended): the run key
 * `(composition, brief digest, loop VERSION, binding_revision)`, its brief digest and pinned binding revision, the
 * state, and exactly one of the result or the error. `result` is non-null exactly when `completed`, and `error`
 * exactly when `failed`; any other pairing is refused at construction. The guard is notation's amended one
 * ({@see NotationRunData}) with a structured error: an error member present outside `failed`, whatever its
 * content, is refused rather than read as absence, and an empty one (`''`, `[]`) never hydrates.
 *
 * Two pairings follow from the run key and are refused too: a failed run's code must be one a run can carry
 * (`budget_exceeded` is refused at op entry, where no run exists), and a completed result's Arranger must be the
 * binding revision the run was pinned to.
 *
 * The two-word wire names are snake_case, mapped per property (see {@see AugmentationMixData}).
 */
#[TypeScript]
#[Title('Augmentation run')]
class AugmentationRunData extends Data
{
    public function __construct(
        #[Title('Run key')]
        #[Description('The exact run: (composition, brief digest, loop VERSION, binding_revision).')]
        #[MapName('run_key')]
        public string $runKey,
        #[Title('Brief digest')]
        #[Description('The brief\'s content digest, the run key\'s brief member.')]
        #[MapName('brief_digest')]
        public string $briefDigest,
        #[Title('Binding revision')]
        #[Description('The Satellite binding revision the run is pinned to, read at dispatch.')]
        #[MapName('binding_revision')]
        public string $bindingRevision,
        #[Title('State')]
        #[Description('none | pending | completed | failed. failed is terminal per attempt; a new POST retries.')]
        public AugmentationRunState $state,
        #[Title('Result')]
        #[Description('Present exactly when completed.')]
        public ?AugmentationResultData $result = null,
        #[Title('Error')]
        #[Description('The named failure; present exactly when failed.')]
        public ?AugmentationErrorData $error = null,
        #[Title('At')]
        #[Description('ISO-8601 time the state was written; null when none.')]
        public ?string $at = null,
    ) {
        $completed = $state === AugmentationRunState::Completed;
        $failed = $state === AugmentationRunState::Failed;

        if ($completed !== ($result !== null)) {
            throw new InvalidArgumentException($completed
                ? 'A completed augmentation run must carry its result.'
                : "A {$state->value} augmentation run carries no result; only completed does.");
        }

        if ($failed !== ($error !== null)) {
            throw new InvalidArgumentException($failed
                ? 'A failed augmentation run must carry its error.'
                : "A {$state->value} augmentation run carries no error; only failed does.");
        }

        if ($error !== null && ! $error->code->atRunTime()) {
            throw new InvalidArgumentException("A failed augmentation run never carries {$error->code->value}; it is refused at op entry, where no run exists.");
        }

        if ($result !== null && $result->arranger->bindingRevision !== $bindingRevision) {
            throw new InvalidArgumentException("A completed augmentation run's arranger must be its pinned binding revision {$bindingRevision}; got {$result->arranger->bindingRevision}.");
        }
    }
}
