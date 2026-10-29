<?php

namespace Splicewire\Composition\Music\Transcription;

use InvalidArgumentException;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The identity of the transcription pipeline that **actually executed** a run (music-capability-lift ticket 04 Q2,
 * "the whole-pipeline identity"; owner ruling O2 on build/15): the pipeline binding's name, its `version()` and the
 * `policy_digest` over the thresholds it applied. These are the pipeline members of the run key, and the chart
 * worksheet's draft-identity header reads them (build/28).
 *
 * The engine (build/17) writes them from the run that executed, never from the registry's current binding: a
 * pipeline bump is a new run, and an old completed run stays readable while it is cited, so the current registry can
 * name a different pipeline than the one that produced this result.
 *
 * Every member is a non-empty string, held at construction. `policy_digest` is mapped per property.
 */
#[TypeScript]
#[Title('Transcription pipeline')]
class TranscriptionPipelineData extends Data
{
    public function __construct(
        #[Title('Name')]
        #[Description('The pipeline binding that ran, e.g. music.transcribe.pipeline.')]
        public string $name,
        #[Title('Version')]
        #[Description('The pipeline binding\'s version() when it ran.')]
        public string $version,
        #[Title('Policy digest')]
        #[Description('sha256 over the TranscriptionPolicy thresholds the run applied.')]
        #[MapName('policy_digest')]
        public string $policyDigest,
    ) {
        foreach (['name' => $name, 'version' => $version, 'policy_digest' => $policyDigest] as $member => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("A transcription pipeline names its {$member}; an empty one is refused.");
            }
        }
    }
}
