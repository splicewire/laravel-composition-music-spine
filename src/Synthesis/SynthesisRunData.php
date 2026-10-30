<?php

namespace Splicewire\Composition\Music\Synthesis;

use InvalidArgumentException;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Composition\Music\Separation\SeparationRunData;

/**
 * The synthesis Read op's answer for one exact run key (music-capability-lift ticket 16 Q5, Q17 amended). The guard
 * is separation's ({@see SeparationRunData}) with one addition: `result` and `audio_ref` are non-null exactly when
 * `completed`, and `error` is a non-empty string exactly when `failed`; any other pairing is refused at
 * construction. An empty error or audio_ref is still a member on the wire, so it is refused where the member does
 * not belong rather than read as absence.
 *
 * On `completed`, `result` is the unchanged {@see SynthesisResult}: its URL is re-minted on every read and its
 * wall-seconds are null on this wire, internal telemetry (ADR-0128 §5). `audio_ref` is the composite's media uuid.
 *
 * The wire names are snake_case, mapped per property: a class-level mapper loses to a host's input mapper.
 */
#[TypeScript]
#[Title('Synthesis run')]
class SynthesisRunData extends Data
{
    public function __construct(
        #[Title('Run key')]
        #[Description('The exact run: media uuid | music.synthesize | binding version.')]
        #[MapName('run_key')]
        public string $runKey,
        #[Title('State')]
        #[Description('none | pending | completed | failed. none on an exact key is terminal; failed is terminal per attempt; a new POST retries.')]
        public SynthesisRunState $state,
        #[Title('Result')]
        #[Description('Present exactly when completed. Its URL is re-minted on every read; its wall-seconds are null on this wire.')]
        public ?SynthesisResult $result = null,
        #[Title('Audio reference')]
        #[Description('The synthesized composite\'s media uuid; present exactly when completed.')]
        #[MapName('audio_ref')]
        public ?string $audioRef = null,
        #[Title('Error')]
        #[Description('The named failure; present exactly when failed (e.g. stale).')]
        public ?string $error = null,
        #[Title('At')]
        #[Description('ISO-8601 time the state was written; null when none.')]
        public ?string $at = null,
    ) {
        $completed = $state === SynthesisRunState::Completed;
        $failed = $state === SynthesisRunState::Failed;

        if ($completed !== ($result !== null)) {
            throw new InvalidArgumentException($completed
                ? 'A completed synthesis run must carry its result.'
                : "A {$state->value} synthesis run carries no result; only completed does.");
        }

        if ($completed !== ($audioRef !== null)) {
            throw new InvalidArgumentException($completed
                ? 'A completed synthesis run must carry its audio_ref.'
                : "A {$state->value} synthesis run carries no audio_ref; only completed does.");
        }

        if ($failed && ($error === null || $error === '')) {
            throw new InvalidArgumentException('A failed synthesis run must carry a non-empty error.');
        }

        if (! $failed && $error !== null) {
            throw new InvalidArgumentException("A {$state->value} synthesis run carries no error; only failed does.");
        }
    }
}
