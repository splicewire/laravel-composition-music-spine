<?php

namespace Splicewire\Composition\Music\Transcription;

use Schemastud\DataSchemas\Attributes\ArrayItems;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Composition\Music\ChordChange;

/**
 * The confidence beside one draft chord change (music-capability-lift ticket 04 Q2 amended): `index` points into
 * {@see ChartDraftData::$changes}. Confidence never enters the {@see ChordChange}
 * itself, and never reaches a song.
 */
#[TypeScript]
#[Title('Draft change confidence')]
class DraftChangeMetaData extends Data
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        #[Title('Index')]
        #[Description('The change this describes: its index in the draft\'s changes.')]
        public int $index,
        #[Title('Confidence')]
        #[Description('The change\'s confidence, in [0, 1].')]
        public float $confidence,
        #[Title('Reasons')]
        #[Description('Named reasons, e.g. "ungated: sub-bar change", "ungated: pickup".')]
        #[ArrayItems('string')]
        public array $reasons = [],
    ) {}
}
