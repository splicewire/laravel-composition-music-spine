<?php

namespace Splicewire\Composition\Music\Separation;

use InvalidArgumentException;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What one separation produced (music-capability-lift ticket 03 Q2, E1). It reaches a caller only inside the
 * Read op's {@see SeparationRunData}, never as the Task's output. A `six_stem` result lists the reused
 * two-stem run's `vocals` and `instrumental` beside the instrument leg's stems.
 *
 * `all_silent` is true exactly when every stem is silent, and then every `relative_db` is null: the run had no
 * reference to measure against. Such a run is `completed`, not `failed` — silence is a measurement (03 E1).
 * Any other pairing is refused at construction.
 *
 * `separated_seconds` is the metered quantity, read off the result; `wall_seconds` is internal telemetry,
 * never billed (the synthesis and analysis precedent). The wire names are snake_case, mapped per property
 * (see {@see SeparationRequest}).
 */
#[TypeScript]
#[Title('Separation result')]
class SeparationResult extends Data
{
    /**
     * @param  array<int, StemData>  $stems
     */
    public function __construct(
        #[Title('Stem set')]
        #[Description('The stem set this result answers: two_stem or six_stem. A bare string.')]
        #[MapName('stem_set')]
        public string $stemSet,
        #[Title('Separator')]
        #[Description('The binding that actually ran — reported by the result, never chosen by the request.')]
        public string $separator,
        #[Title('Separator version')]
        #[Description('The binding version; a bump is a new run.')]
        #[MapName('separator_version')]
        public string $separatorVersion,
        #[Title('Params digest')]
        #[Description('SeparationRequest::paramsDigest() of the producing request, completing the run key.')]
        #[MapName('params_digest')]
        public string $paramsDigest,
        #[Title('Separated-seconds')]
        #[Description('Seconds of audio actually separated — the metered quantity, never wall time.')]
        #[MapName('separated_seconds')]
        public float $separatedSeconds,
        #[Title('Wall-seconds (internal)')]
        #[Description('Optional internal telemetry. Never the billed quantity.')]
        #[MapName('wall_seconds')]
        public ?float $wallSeconds,
        #[Title('All silent')]
        #[Description('True exactly when every stem is silent; every relative_db is then null. Still completed.')]
        #[MapName('all_silent')]
        public bool $allSilent,
        #[Title('Stems')]
        #[DataCollectionOf(StemData::class)]
        public array $stems,
    ) {
        $everyStemSilent = $stems !== [];

        foreach ($stems as $stem) {
            if (! $stem instanceof StemData || ! $stem->silent) {
                $everyStemSilent = false;
            }
        }

        if ($allSilent !== $everyStemSilent) {
            throw new InvalidArgumentException($allSilent
                ? 'A separation result is all_silent only when it has stems and every one is silent.'
                : 'A separation result whose every stem is silent must say all_silent.');
        }

        if ($allSilent) {
            foreach ($stems as $stem) {
                if ($stem->relativeDb !== null) {
                    throw new InvalidArgumentException("An all_silent separation result has no reference; stem {$stem->name} carries a relative_db.");
                }
            }
        }
    }
}
