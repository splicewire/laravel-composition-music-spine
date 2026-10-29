<?php

namespace Splicewire\Composition\Music\Transcription;

use InvalidArgumentException;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What one transcription produced (music-capability-lift ticket 04 Q2 amended, E1, Q6; owner rulings O1, O2 on
 * build/15). It reaches a caller only inside the Read op's {@see TranscriptionRunData}, never as the Task's output.
 *
 * - `separation_run_key` cites the separation the stems came from.
 * - `pipeline` is the identity of the pipeline that actually executed the run ({@see TranscriptionPipelineData}),
 *   written from that run, never from the current registry. `stages` report each stage binding that ran.
 * - `draft` is the {@see ChartDraftData}; `notes` one entry per role, a gated stem recorded and not transcribed.
 * - `melody_ref` is the melody MIDI's media uuid, or null with `melody_reason` naming why (04 E1: `vocal leg
 *   uncalibrated` until the vocal leg's threshold is set). Exactly one of the two is present.
 * - `plan_conformance` exists only where the take has a plan (04 Q6). When the gate's controls fail, conformance is
 *   **not emitted**: `plan_conformance` is null and `plan_conformance_reason` is {@see self::CONFORMANCE_UNGATED}
 *   (O1). A reason never sits beside an emitted `plan_conformance`. A run with no plan carries neither: the result
 *   cannot tell "no plan" from "ungated" by itself, so the engine must name the reason whenever it withholds one.
 * - `transcribed_seconds` is the metered quantity (`music.transcription`), never wall time.
 * - `gated` is the transcription gate's admission: true only for the gated default stage set. A run with any
 *   non-default stage is answered, with `gated: false`, because the gate measured the defaults only.
 *
 * Wire names are snake_case, mapped per property.
 */
#[TypeScript]
#[Title('Transcription result')]
class TranscriptionResult extends Data
{
    /** The one reason a planned take's conformance is withheld: the gate's controls failed (04 Q6). */
    public const CONFORMANCE_UNGATED = 'conformance ungated';

    /**
     * @param  list<TranscriptionStageData>  $stages
     * @param  list<TranscriptionNotesData>  $notes
     */
    public function __construct(
        #[Title('Separation run key')]
        #[Description('The separation run the stems came from.')]
        #[MapName('separation_run_key')]
        public string $separationRunKey,
        #[Title('Pipeline')]
        #[Description('The pipeline that actually executed this run: name, version and policy digest.')]
        public TranscriptionPipelineData $pipeline,
        #[Title('Stages')]
        #[Description('Each stage binding that ran, by name and version.')]
        #[DataCollectionOf(TranscriptionStageData::class)]
        public array $stages,
        #[Title('Draft')]
        public ChartDraftData $draft,
        #[Title('Notes')]
        #[Description('One entry per role; a gated stem is recorded and not transcribed.')]
        #[DataCollectionOf(TranscriptionNotesData::class)]
        public array $notes,
        #[Title('Melody ref')]
        #[Description('The melody MIDI media uuid; null exactly when melody_reason names why.')]
        #[MapName('melody_ref')]
        public ?string $melodyRef,
        #[Title('Plan conformance')]
        #[Description('Present only where the take has a plan and the gate\'s controls passed; null otherwise.')]
        #[MapName('plan_conformance')]
        public ?PlanConformanceData $planConformance,
        #[Title('Transcribed-seconds')]
        #[Description('Seconds of audio transcribed — the metered quantity, never wall time.')]
        #[MapName('transcribed_seconds')]
        public float $transcribedSeconds,
        #[Title('Gated')]
        #[Description('True when the run used the gated default stage set; a non-default run answers false.')]
        public bool $gated,
        #[Title('Melody reason')]
        #[Description('Why there is no melody, e.g. "vocal leg uncalibrated"; present exactly when melody_ref is null.')]
        #[MapName('melody_reason')]
        public ?string $melodyReason = null,
        #[Title('Plan conformance reason')]
        #[Description('"conformance ungated" when a planned take\'s conformance is withheld; never beside a plan_conformance.')]
        #[MapName('plan_conformance_reason')]
        public ?string $planConformanceReason = null,
    ) {
        if ($melodyRef !== null && $melodyReason !== null) {
            throw new InvalidArgumentException('A transcription with a melody carries no melody_reason.');
        }

        if ($melodyRef === null && ($melodyReason === null || $melodyReason === '')) {
            throw new InvalidArgumentException('A transcription without a melody names why (melody_reason).');
        }

        if ($melodyRef === '') {
            throw new InvalidArgumentException('A transcription\'s melody_ref is a media uuid or null, never empty.');
        }

        if ($planConformance !== null && $planConformanceReason !== null) {
            throw new InvalidArgumentException('An emitted plan_conformance carries no plan_conformance_reason.');
        }

        if ($planConformanceReason !== null && $planConformanceReason !== self::CONFORMANCE_UNGATED) {
            throw new InvalidArgumentException('A withheld plan_conformance names its reason: "'.self::CONFORMANCE_UNGATED."\" (got \"{$planConformanceReason}\").");
        }
    }
}
