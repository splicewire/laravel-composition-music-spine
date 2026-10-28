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
 * What one transcription produced (music-capability-lift ticket 04 Q2 amended, E1, Q6). It reaches a caller only
 * inside the Read op's {@see TranscriptionRunData}, never as the Task's output.
 *
 * - `separation_run_key` cites the separation the stems came from; `stages` report each stage binding that ran.
 * - `draft` is the {@see ChartDraftData}; `notes` one entry per role, a gated stem recorded and not transcribed.
 * - `melody_ref` is the melody MIDI's media uuid, or null with `melody_reason` naming why (04 E1: `vocal leg
 *   uncalibrated` until the vocal leg's threshold is set). Exactly one of the two is present.
 * - `plan_conformance` is present exactly when the request named a plan.
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
    /**
     * @param  list<TranscriptionStageData>  $stages
     * @param  list<TranscriptionNotesData>  $notes
     */
    public function __construct(
        #[Title('Separation run key')]
        #[Description('The separation run the stems came from.')]
        #[MapName('separation_run_key')]
        public string $separationRunKey,
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
        #[Description('Present exactly when the request named a plan.')]
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
    }
}
