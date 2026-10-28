<?php

namespace Splicewire\Composition\Music\Transcription;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One stage binding a transcription run resolved (music-capability-lift ticket 04 Q2): the stage it served, the
 * binding's name and its version. A run's stages are reported by its result, never chosen by the result.
 */
#[TypeScript]
#[Title('Transcription stage')]
class TranscriptionStageData extends Data
{
    public function __construct(
        #[Title('Stage')]
        #[Description('The stage served: beats, chords, or notes.<role>.')]
        public string $stage,
        #[Title('Name')]
        #[Description('The stage binding that ran, e.g. music.transcribe.stage.beats.librosa.')]
        public string $name,
        #[Title('Version')]
        #[Description('The stage binding version; a bump is a new run.')]
        public string $version,
    ) {}
}
