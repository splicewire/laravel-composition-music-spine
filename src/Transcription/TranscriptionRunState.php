<?php

namespace Splicewire\Composition\Music\Transcription;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The state the transcription Read op answers for one exact run (music-capability-lift ticket 04 Q2, on ticket 03
 * Q5): `none` (never asked), `pending` (the Task took the queue lock), `completed` (the draft is stored and
 * metered), `failed` (the job failed — an unusable grid, a stage failure — or a pending citation's queue lock is
 * gone, stale). `failed` is terminal per attempt; a new `POST` is the retry.
 */
#[TypeScript]
enum TranscriptionRunState: string
{
    case None = 'none';
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
