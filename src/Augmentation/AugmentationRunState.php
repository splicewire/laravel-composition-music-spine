<?php

namespace Splicewire\Composition\Music\Augmentation;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The state the augmentation Read op answers for one exact run (music-capability-lift ticket 06 Q4, on ticket 03
 * Q5): `none` (never asked), `pending` (the Task took the queue lock), `completed` (the take is stored and
 * metered), `failed` (the job failed, or a pending citation's queue lock is gone). `failed` is terminal per
 * attempt; a new `POST` is the retry.
 */
#[TypeScript]
enum AugmentationRunState: string
{
    case None = 'none';
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
