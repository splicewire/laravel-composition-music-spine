<?php

namespace Splicewire\Composition\Music\Synthesis;

use InvalidArgumentException;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Composition\Music\Separation\SeparationDispatchData;

/**
 * The synthesize Task's whole answer (music-capability-lift ticket 16 Q5, Q17 amended): the acknowledgement plus the
 * exact run key `handle` admitted or found. The run's state is still the Read op's ({@see SynthesisRunData}).
 *
 * It departs from the `{queued}`-only siblings ({@see SeparationDispatchData}) on purpose. A Task's response never
 * sees the job or the request, so it cannot report the run's state; but the key is not state. `handle` knows it
 * before it dispatches, and the caller cannot recompute it, because it embeds a host-internal binding version: a
 * caller left to compute it would poll a different run after a version change (Q17). So the acknowledgement names
 * the run, and `run_key` is never empty. It is mapped per property (see {@see SynthesisRunData}).
 */
#[TypeScript]
#[Title('Synthesis dispatch')]
class SynthesisDispatchData extends Data
{
    public function __construct(
        #[Title('Queued')]
        #[Description('True when the synthesis job was queued.')]
        public bool $queued,
        #[Title('Run key')]
        #[Description('The exact run the Task admitted or found: media uuid | music.synthesize | binding version. Poll the Read with it.')]
        #[MapName('run_key')]
        public string $runKey,
    ) {
        if ($runKey === '') {
            throw new InvalidArgumentException('A synthesis dispatch names the run_key it admitted or found; an empty run_key is refused.');
        }
    }
}
