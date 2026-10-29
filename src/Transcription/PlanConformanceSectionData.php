<?php

namespace Splicewire\Composition\Music\Transcription;

use Schemastud\DataSchemas\Attributes\ArrayItems;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One plan section's conformance (music-capability-lift ticket 04 Q6): its plan chords, where the forced alignment
 * placed them, the mean ratio of the aligned chord's posterior to the best free chord's over the section's beats,
 * and the share of beats whose free root agrees with the aligned root. A null `conformance` names its reason
 * ({@see PlanConformanceData::REASONS}), held at construction. Wire names are snake_case, mapped per property.
 */
#[TypeScript]
#[Title('Plan conformance section')]
class PlanConformanceSectionData extends Data
{
    /**
     * @param  list<string>  $planChords
     * @param  list<AlignedChordData>  $aligned
     */
    public function __construct(
        #[Title('Label')]
        #[Description('The plan section\'s label.')]
        public string $label,
        #[Title('Plan chords')]
        #[MapName('plan_chords')]
        #[ArrayItems('string')]
        public array $planChords,
        #[Title('Aligned')]
        #[Description('The plan chords placed on the take, in order; empty when the take is unalignable.')]
        #[DataCollectionOf(AlignedChordData::class)]
        public array $aligned,
        #[Title('Conformance')]
        #[Description('Mean posterior(aligned) / posterior(best free) over the section\'s beats; null exactly when reason names why.')]
        public ?float $conformance,
        #[Title('Free root agreement')]
        #[Description('Share of the section\'s beats whose free-decode root equals the aligned chord\'s root.')]
        #[MapName('free_root_agreement')]
        public ?float $freeRootAgreement,
        #[Title('Reason')]
        #[Description('unalignable | compressed; present exactly when conformance is null.')]
        public ?string $reason = null,
    ) {
        PlanConformanceData::holdScoreOrReason("section \"{$label}\"", $conformance, $reason);
    }
}
