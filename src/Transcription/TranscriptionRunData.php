<?php

namespace Splicewire\Composition\Music\Transcription;

use InvalidArgumentException;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Composition\Music\Notation\NotationRunData;

/**
 * The transcription Read op's answer for one exact run key (music-capability-lift ticket 04 Q2, on ticket 03 Q2
 * amended). `result` is non-null exactly when `completed`, and `error` is a non-empty string exactly when
 * `failed`; any other pairing is refused at construction. The guard is notation's amended one
 * ({@see NotationRunData}): an empty error is still an error member on the wire, so it is refused outside
 * `failed` rather than read as absence.
 *
 * The wire names are snake_case, mapped per property: a class-level mapper loses to a host's input mapper.
 */
#[TypeScript]
#[Title('Transcription run')]
class TranscriptionRunData extends Data
{
    public function __construct(
        #[Title('Run key')]
        #[Description('The exact run: media, separation run key, pipeline identity, resolved stages, params digest, plan digest and tempo prior when present.')]
        #[MapName('run_key')]
        public string $runKey,
        #[Title('State')]
        #[Description('none | pending | completed | failed. failed is terminal per attempt; a new POST retries.')]
        public TranscriptionRunState $state,
        #[Title('Result')]
        #[Description('Present exactly when completed.')]
        public ?TranscriptionResult $result = null,
        #[Title('Error')]
        #[Description('The named failure; present exactly when failed (e.g. stale, grid unusable: <cause>).')]
        public ?string $error = null,
        #[Title('At')]
        #[Description('ISO-8601 time the state was written; null when none.')]
        public ?string $at = null,
    ) {
        $completed = $state === TranscriptionRunState::Completed;
        $failed = $state === TranscriptionRunState::Failed;

        if ($completed !== ($result !== null)) {
            throw new InvalidArgumentException($completed
                ? 'A completed transcription run must carry its result.'
                : "A {$state->value} transcription run carries no result; only completed does.");
        }

        if ($failed && ($error === null || $error === '')) {
            throw new InvalidArgumentException('A failed transcription run must carry a non-empty error.');
        }

        if (! $failed && $error !== null) {
            throw new InvalidArgumentException("A {$state->value} transcription run carries no error; only failed does.");
        }
    }
}
