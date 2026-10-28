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
 * **Plan conformance** (music-capability-lift ticket 04 Q6): how closely a take's sounding chords follow its plan,
 * the intent it was generated from. It exists only where a take has a plan, on
 * {@see TranscriptionResult::$planConformance}.
 *
 * A score that could not honestly be given is **null with a named reason**, never zero:
 * - {@see self::UNALIGNABLE} — the take has fewer beats than the plan has chords;
 * - {@see self::COMPRESSED} — a section's aligned span is the one-beat-per-chord minimum (a skipped or reordered
 *   section the forced path cannot represent);
 * - {@see self::CONFORMANCE_UNGATED} — the gate's controls failed, so conformance is not emitted.
 *
 * `overall` and each section hold that pairing at construction: a score has no reason, a null score has one of
 * {@see self::REASONS}. Wire names are snake_case, mapped per property.
 */
#[TypeScript]
#[Title('Plan conformance')]
class PlanConformanceData extends Data
{
    public const UNALIGNABLE = 'unalignable';

    public const COMPRESSED = 'compressed';

    public const CONFORMANCE_UNGATED = 'conformance ungated';

    public const REASONS = [self::UNALIGNABLE, self::COMPRESSED, self::CONFORMANCE_UNGATED];

    /**
     * @param  list<PlanConformanceSectionData>  $sections
     */
    public function __construct(
        #[Title('Plan digest')]
        #[Description('The digest of the plan the take was scored against; it is in the run key.')]
        #[MapName('plan_digest')]
        public string $planDigest,
        #[Title('Sections')]
        #[Description('One entry per plan section, in plan order.')]
        #[DataCollectionOf(PlanConformanceSectionData::class)]
        public array $sections,
        #[Title('Overall')]
        #[Description('The take\'s conformance; null exactly when reason names why.')]
        public ?float $overall,
        #[Title('Reason')]
        #[Description('unalignable | compressed | conformance ungated; present exactly when overall is null.')]
        public ?string $reason = null,
    ) {
        self::holdScoreOrReason('overall', $overall, $reason);
    }

    /** A score carries no reason; a null score carries one of {@see self::REASONS}. */
    public static function holdScoreOrReason(string $where, ?float $score, ?string $reason): void
    {
        if ($score !== null && $reason !== null) {
            throw new InvalidArgumentException("Plan conformance {$where} is scored, so it carries no reason (got \"{$reason}\").");
        }

        if ($score === null && ! in_array($reason, self::REASONS, true)) {
            throw new InvalidArgumentException("Plan conformance {$where} is null, so it names its reason: one of "
                .implode(', ', self::REASONS).' (got '.var_export($reason, true).').');
        }
    }
}
