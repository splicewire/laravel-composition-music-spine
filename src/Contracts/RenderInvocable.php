<?php

declare(strict_types=1);

namespace Splicewire\Composition\Music\Contracts;

use Splicewire\Composition\Music\MusicIntent;

/**
 * The render seam for the music-intent format (ADR-0125 §4): **render is an invocable over
 * {@see MusicIntent}, NOT the kernel `RenderContract`.** An invocable is a *registry* contract, not a
 * kernel type — which is exactly what sidesteps the honest limitation that a local renderer (audiostud's
 * deterministic Arranger) never embeds the composition engine and returns a raw render artifact (a
 * `MidiFile`), not a kernel `Output`. audiostud registers its Arranger as the **local MIDI-render
 * invocable binding** over this port; a platform remote-invocable arranger is admitted by the same port
 * later. OTIO stays a separate projection (`SpineBinding` = `MusicSpine`), not this seam.
 *
 * Deliberately dependency-light (no popcorn, no kernel types) so it ships in the shared, satellite-
 * importable **spine** — a satellite consumes this port without ever depending on the engine. The render
 * artifact is target-specific, hence `mixed`: the caller that resolved a concrete binding knows the
 * concrete type (a `MidiFile` for the MIDI arranger).
 */
interface RenderInvocable
{
    /**
     * Stable registry name of this render target (e.g. `music.render.midi`), so the invocable registry
     * can resolve a binding by name.
     */
    public function name(): string;

    /**
     * Materialize a {@see MusicIntent} into a render artifact. The return is target-specific — a raw
     * `MidiFile` for the local MIDI arranger — hence untyped at this shared seam.
     */
    public function render(MusicIntent $intent): mixed;
}
