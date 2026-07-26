<?php

declare(strict_types=1);

namespace Splicewire\CompositionMusicSpineData\Contracts;

use Splicewire\CompositionMusicSpineData\MusicIntent;

/**
 * The persistence port for a {@see MusicIntent} (ADR-0125 T04). A stored intent is a **versioned draft
 * cell**: `store()` writes an intent and returns an opaque `draftId` handle; `fetch()` re-reads that
 * draft, migrated-on-read to the current `MusicIntent` shape. The port is deliberately storage-agnostic
 * — the shared spine-data package coins the seam; the *platform cell layer* supplies the concrete
 * `Composition`-cell-backed impl (the migrate-on-read concern lives there, over this plain payload).
 *
 * The `draftId` round-trip is the substrate of the iterate-loop (PRD §7 Door 1): a first transpose stores
 * a new draft (null `$draftId`), and each subsequent revision updates the SAME draft in place by passing
 * the prior handle back — so a running conversation collapses onto one versioned cell, not a pile of rows.
 */
interface MusicIntentStore
{
    /**
     * Persist an intent as a versioned draft cell; return an opaque draftId handle.
     *
     * @param  string|null  $draftId  null → mint a NEW draft; non-null → UPDATE that draft (the iterate-loop)
     */
    public function store(MusicIntent $intent, ?string $draftId = null): string;

    /**
     * Fetch a stored draft by id, migrated-on-read to the current {@see MusicIntent} shape.
     */
    public function fetch(string $draftId): MusicIntent;
}
