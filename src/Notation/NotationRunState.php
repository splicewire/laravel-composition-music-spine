<?php

namespace Splicewire\Composition\Music\Notation;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The state the Read op answers for one exact notation run (music-capability-lift ticket 02 Q6, ticket 03's
 * Task + Read semantics): `none` (never asked), `pending` (the Task took the queue lock), `completed` (the
 * artifacts are stored and metered), `failed` (the job failed, or a pending citation's queue lock is gone —
 * stale). `failed` is terminal per attempt; a new `POST` is the retry.
 */
#[TypeScript]
enum NotationRunState: string
{
    case None = 'none';
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
