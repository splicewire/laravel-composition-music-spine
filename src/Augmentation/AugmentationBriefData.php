<?php

namespace Splicewire\Composition\Music\Augmentation;

use InvalidArgumentException;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Composition\Music\MusicIntent;
use Splicewire\Composition\Music\Support\CanonicalJson;

/**
 * What an augmentation run is asked to do (music-capability-lift ticket 06 Q4): the intent to realise, up to three
 * passes, optional mix overrides, and whether the AI listener measures too. The augment Task's input carries it,
 * and {@see self::digest()} is the run key's brief member `(composition, brief digest, loop VERSION,
 * binding_revision)`.
 *
 * Whether a brief fits the job's time budget (06 E1: a listener brief fits only at two passes) is the op entry's
 * `budget_exceeded` refusal, not this Data's: the ceilings are the engine's.
 *
 * The two-word wire names are snake_case, mapped per property (see {@see AugmentationMixData}).
 */
#[TypeScript]
#[Title('Augmentation brief')]
class AugmentationBriefData extends Data
{
    public const MIN_ITERATIONS = 1;

    public const MAX_ITERATIONS = 3;

    public function __construct(
        #[Title('Intent')]
        #[Description('The music to realise (music/intent v2), inline.')]
        public MusicIntent $intent,
        #[Title('Max iterations')]
        #[Description('Passes the loop may run, 1 to 3.')]
        #[MapName('max_iterations')]
        #[Between(self::MIN_ITERATIONS, self::MAX_ITERATIONS)]
        public int $maxIterations,
        #[Title('Mix')]
        #[Description('Optional per-instrument gain and pan overrides; absent keeps the mix defaults.')]
        public ?AugmentationMixData $mix = null,
        #[Title('AI listener')]
        #[Description('Also measure with the AI listener. It lengthens each pass and stays out of the loop thresholds until calibrated.')]
        #[MapName('ai_listener')]
        public bool $aiListener = false,
    ) {
        if ($maxIterations < self::MIN_ITERATIONS || $maxIterations > self::MAX_ITERATIONS) {
            throw new InvalidArgumentException("max_iterations must be 1 to 3; got {$maxIterations}.");
        }
    }

    /**
     * The brief's content digest: sha256 of the canonical JSON of every member, by property name, with the
     * intent read as {@see MusicIntent::digest()} reads it. An absent `mix` digests as null and an absent
     * `ai_listener` as false, so omitting a default is the same brief; any change to the intent, the pass count,
     * a gain, a pan or the listener flag is a new digest.
     */
    public function digest(): string
    {
        $payload = CanonicalJson::propertiesOf($this);
        $payload['intent'] = CanonicalJson::withoutEmptyIntentV2Members($payload['intent']);

        return CanonicalJson::sha256($payload);
    }
}
