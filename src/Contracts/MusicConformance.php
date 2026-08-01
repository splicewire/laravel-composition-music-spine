<?php

namespace Splicewire\Composition\Music\Contracts;

use Splicewire\Composition\Music\Conformance\ConformanceResult;
use Splicewire\Composition\Music\MusicIntent;

/**
 * The conformance-guardrail seam over a model-drafted {@see MusicIntent} (ADR-0125 §9, PRD §11.2). It
 * sits on the *analysis edge* exactly like timeline-schema's `OtioValidator`: the shared surface ships
 * the contract + a PHP-native default, and a host may override the binding with a config-gated popcorn
 * Process invocable (Tonal/`music21`) for a deeper pass. Kept dependency-light (no popcorn, no kernel
 * types) so it lives in the satellite-importable **spine** — consumable without the engine.
 */
interface MusicConformance
{
    /**
     * Check a drafted intent for musical sanity before it reaches the Arranger. Returns the verdict; a
     * result with mandatory violations should block the render, advisories are surfaced only.
     */
    public function check(MusicIntent $intent): ConformanceResult;
}
