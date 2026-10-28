<?php

namespace Splicewire\Composition\Music\Separation;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The state the separation Read op answers for one exact run (music-capability-lift ticket 03 Q2, Q5 amended):
 * `none` (never asked), `pending` (the Task took the queue lock), `completed` (the stems are stored and metered),
 * `failed` (the job failed, or a pending citation's queue lock is gone — stale). `failed` is terminal per
 * attempt; a new `POST` is the retry.
 */
#[TypeScript]
enum SeparationRunState: string
{
    case None = 'none';
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
