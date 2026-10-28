<?php

namespace Splicewire\Composition\Music\Augmentation;

use InvalidArgumentException;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One named augmentation failure (music-capability-lift ticket 06 Q2 "Failure codes", Q4): the code from the closed
 * set ({@see AugmentationErrorCode}), a human `detail`, and the members only some codes carry. Each optional member
 * belongs to its code, and is refused on any other, so a reader never has to guess which code a member explains:
 *
 * - `stage` — required on `stage_failed`, naming one of {@see self::STAGES};
 * - `refusal_reason` — required (non-empty) on `arranger_refused`, the `ArrangeRefusal.reason`;
 * - `attempts` — required (at least 1) on `arranger_unreachable`;
 * - `members` — the refused intent members on `arranger_refused` (may be empty), the verdict's mandatory list on
 *   `mandatory_verdict` (non-empty); empty on every other code.
 *
 * `refusal_reason` is mapped per property (see {@see AugmentationMixData}).
 */
#[TypeScript]
#[Title('Augmentation error')]
class AugmentationErrorData extends Data
{
    /** The loop stages a `stage_failed` may name; the Arranger stage fails by its own `arranger_*` codes. */
    public const STAGES = [
        'music.loop.enrich',
        'music.loop.oneshot',
        'music.loop.sample',
        'music.loop.mix',
        'music.loop.measure',
    ];

    /**
     * @param  list<string>  $members
     */
    public function __construct(
        #[Title('Code')]
        #[Description('The failure, from the closed augmentation code set.')]
        public AugmentationErrorCode $code,
        #[Title('Detail')]
        #[Description('What happened, for a person; budget_exceeded carries the computed worst case and the job timeout.')]
        public string $detail,
        #[Title('Stage')]
        #[Description('On stage_failed only: music.loop.enrich | oneshot | sample | mix | measure.')]
        public ?string $stage = null,
        #[Title('Attempts')]
        #[Description('On arranger_unreachable only: the calls made before giving up.')]
        public ?int $attempts = null,
        #[Title('Refusal reason')]
        #[Description('On arranger_refused only: the Arranger\'s refusal reason, e.g. unsupported_intent_members.')]
        #[MapName('refusal_reason')]
        public ?string $refusalReason = null,
        #[Title('Members')]
        #[Description('On arranger_refused: the refused intent members. On mandatory_verdict: the mandatory failures. Empty otherwise.')]
        public array $members = [],
    ) {
        $code = $this->code->value;

        if (($this->code === AugmentationErrorCode::StageFailed) !== ($stage !== null)) {
            throw new InvalidArgumentException($stage === null
                ? 'A stage_failed error must name its stage.'
                : "A {$code} error names no stage; only stage_failed does.");
        }

        if ($stage !== null && ! in_array($stage, self::STAGES, true)) {
            throw new InvalidArgumentException('stage must be one of '.implode(', ', self::STAGES)."; got {$stage}.");
        }

        if (($this->code === AugmentationErrorCode::ArrangerRefused) !== ($refusalReason !== null)) {
            throw new InvalidArgumentException($refusalReason === null
                ? 'An arranger_refused error must carry its refusal_reason.'
                : "A {$code} error carries no refusal_reason; only arranger_refused does.");
        }

        if ($refusalReason === '') {
            throw new InvalidArgumentException('An arranger_refused error needs a non-empty refusal_reason.');
        }

        if (($this->code === AugmentationErrorCode::ArrangerUnreachable) !== ($attempts !== null)) {
            throw new InvalidArgumentException($attempts === null
                ? 'An arranger_unreachable error must carry its attempts.'
                : "A {$code} error carries no attempts; only arranger_unreachable does.");
        }

        if ($attempts !== null && $attempts < 1) {
            throw new InvalidArgumentException("attempts must be at least 1; got {$attempts}.");
        }

        if ($this->code === AugmentationErrorCode::MandatoryVerdict && $members === []) {
            throw new InvalidArgumentException('A mandatory_verdict error must list the mandatory failures in members.');
        }

        if ($members !== [] && ! in_array($this->code, [AugmentationErrorCode::ArrangerRefused, AugmentationErrorCode::MandatoryVerdict], true)) {
            throw new InvalidArgumentException("A {$code} error carries no members; only arranger_refused and mandatory_verdict do.");
        }
    }
}
