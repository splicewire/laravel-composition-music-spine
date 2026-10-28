<?php

namespace Splicewire\Composition\Music\Augmentation;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The closed set of augmentation failure codes (music-capability-lift ticket 06 Q2 "Failure codes", amended). A
 * code outside it is refused at hydration. Two places speak it:
 *
 * - **op entry**, a 422 on the augment `POST`, where no run is created: `binding_absent`, `binding_inactive`,
 *   `budget_exceeded` (its detail carries the computed worst case and the job timeout);
 * - **run time**, a `failed` run on the Read: every code but `budget_exceeded`.
 *
 * `binding_absent` and `binding_inactive` belong to both: refused at entry, and failed when the job's re-check at
 * start finds the binding gone or no longer active.
 */
#[TypeScript]
enum AugmentationErrorCode: string
{
    // Op entry and run time (the job re-check).
    case BindingAbsent = 'binding_absent';
    case BindingInactive = 'binding_inactive';

    // Op entry only.
    case BudgetExceeded = 'budget_exceeded';

    // Run time only.
    case Stale = 'stale';
    case BindingChanged = 'binding_changed';
    case BindingStale = 'binding_stale';
    case ArrangerRefused = 'arranger_refused';
    case ArrangerRejectedSignature = 'arranger_rejected_signature';
    case ArrangerInvalidResult = 'arranger_invalid_result';
    case ArrangerUnreachable = 'arranger_unreachable';
    case StageFailed = 'stage_failed';
    case MandatoryVerdict = 'mandatory_verdict';
    case TimedOut = 'timed_out';

    /** True when the augment `POST` may refuse with this code (422; no run is created). */
    public function atOpEntry(): bool
    {
        return in_array($this, [self::BindingAbsent, self::BindingInactive, self::BudgetExceeded], true);
    }

    /** True when a `failed` run may carry this code. */
    public function atRunTime(): bool
    {
        return $this !== self::BudgetExceeded;
    }
}
