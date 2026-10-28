<?php

namespace Splicewire\Composition\Music\Notation;

use InvalidArgumentException;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The notation Read op's answer for one exact run (music-capability-lift ticket 02 Q6 amended; ticket 03's
 * `SeparationRunData` semantics). `result` is non-null exactly when `completed`, and `error` exactly when
 * `failed`; any other pairing is refused at construction.
 *
 * The wire names are snake_case as the tickets write them (`run_key`, `request_digest`). Each two-word
 * member is mapped **per property** in both directions: laravel-data 4.23 lets the configured input mapper
 * (the flagship's CamelCaseMapper) override a class-level `#[MapName]`, measured in this package's suite, so
 * only the property attribute holds the round trip under every host.
 */
#[TypeScript]
#[Title('Notation run')]
class NotationRunData extends Data
{
    public function __construct(
        #[Title('Run key')]
        #[MapName('run_key')]
        public string $runKey,
        #[Title('Request digest')]
        #[MapName('request_digest')]
        public string $requestDigest,
        #[Title('State')]
        #[Description('none | pending | completed | failed. failed is terminal per attempt; a new POST retries.')]
        public NotationRunState $state,
        #[Title('Result')]
        #[Description('Present exactly when completed.')]
        public ?NotationResult $result = null,
        #[Title('Error')]
        #[Description('The named failure; present exactly when failed (e.g. stale).')]
        public ?string $error = null,
        #[Title('At')]
        #[Description('ISO-8601 time the state was written; null when none.')]
        public ?string $at = null,
    ) {
        $completed = $state === NotationRunState::Completed;
        $failed = $state === NotationRunState::Failed;

        if ($completed !== ($result !== null)) {
            throw new InvalidArgumentException($completed
                ? 'A completed notation run must carry its result.'
                : "A {$state->value} notation run carries no result; only completed does.");
        }

        if ($failed !== ($error !== null && $error !== '')) {
            throw new InvalidArgumentException($failed
                ? 'A failed notation run must carry a non-empty error.'
                : "A {$state->value} notation run carries no error; only failed does.");
        }
    }
}
