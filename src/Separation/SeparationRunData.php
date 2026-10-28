<?php

namespace Splicewire\Composition\Music\Separation;

use InvalidArgumentException;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Composition\Music\Notation\NotationRunData;

/**
 * The separation Read op's answer for one exact run key (music-capability-lift ticket 03 Q2 amended).
 * `result` is non-null exactly when `completed`, and `error` is a non-empty string exactly when `failed`;
 * any other pairing is refused at construction. The guard is notation's amended one
 * ({@see NotationRunData}): an empty error is still an error member on the wire, so it is refused
 * outside `failed` rather than read as absence.
 *
 * `run_key` is mapped per property (see {@see SeparationRequest}).
 */
#[TypeScript]
#[Title('Separation run')]
class SeparationRunData extends Data
{
    public function __construct(
        #[Title('Run key')]
        #[Description('The exact run: (media, stem_set, binding name, binding version, params digest).')]
        #[MapName('run_key')]
        public string $runKey,
        #[Title('State')]
        #[Description('none | pending | completed | failed. failed is terminal per attempt; a new POST retries.')]
        public SeparationRunState $state,
        #[Title('Result')]
        #[Description('Present exactly when completed.')]
        public ?SeparationResult $result = null,
        #[Title('Error')]
        #[Description('The named failure; present exactly when failed (e.g. stale).')]
        public ?string $error = null,
        #[Title('At')]
        #[Description('ISO-8601 time the state was written; null when none.')]
        public ?string $at = null,
    ) {
        $completed = $state === SeparationRunState::Completed;
        $failed = $state === SeparationRunState::Failed;

        if ($completed !== ($result !== null)) {
            throw new InvalidArgumentException($completed
                ? 'A completed separation run must carry its result.'
                : "A {$state->value} separation run carries no result; only completed does.");
        }

        if ($failed && ($error === null || $error === '')) {
            throw new InvalidArgumentException('A failed separation run must carry a non-empty error.');
        }

        if (! $failed && $error !== null) {
            throw new InvalidArgumentException("A {$state->value} separation run carries no error; only failed does.");
        }
    }
}
