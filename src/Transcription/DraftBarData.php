<?php

namespace Splicewire\Composition\Music\Transcription;

use Schemastud\DataSchemas\Attributes\ArrayItems;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One bar of a chart draft (music-capability-lift ticket 04 Q2, Q5): where it sits in the take, where it starts in
 * the draft coordinate, how many beats long it is, and how confident the transcription is in it, with the named
 * reasons any confidence component fell below the review threshold. A bar's `beats` is what
 * {@see ChartDraftSections::apply()} accumulates into section origins, so a meter change is a bar of another length.
 *
 * Wire names are snake_case, mapped per property.
 */
#[TypeScript]
#[Title('Draft bar')]
class DraftBarData extends Data
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        #[Title('Index')]
        #[Description('The bar index, 0-based; bar 0 starts at the draft downbeat.')]
        public int $index,
        #[Title('Start (s)')]
        #[Description('The bar\'s start in the take, in seconds.')]
        #[MapName('start_s')]
        public float $startS,
        #[Title('End (s)')]
        #[Description('The bar\'s end in the take, in seconds.')]
        #[MapName('end_s')]
        public float $endS,
        #[Title('Start beat')]
        #[Description('The bar\'s start in the draft coordinate: beats from draft bar 0\'s downbeat.')]
        #[MapName('start_beat')]
        public float $startBeat,
        #[Title('Beats')]
        #[Description('The bar\'s length in beats (quarter notes).')]
        public float $beats,
        #[Title('Confidence')]
        #[Description('The weakest of the bar\'s confidence components, in [0, 1].')]
        public float $confidence,
        #[Title('Reasons')]
        #[Description('Each component below the review threshold, by name, and any ungated reason.')]
        #[ArrayItems('string')]
        public array $reasons = [],
    ) {}
}
