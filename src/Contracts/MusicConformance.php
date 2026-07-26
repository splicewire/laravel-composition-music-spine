<?php

declare(strict_types=1);

namespace Splicewire\CompositionMusicSpineData\Contracts;

use Splicewire\CompositionMusicSpineData\Conformance\ConformanceResult;
use Splicewire\CompositionMusicSpineData\MusicIntent;

/**
 * The conformance-guardrail seam over a model-drafted {@see MusicIntent} (ADR-0125 §9, PRD §11.2). It
 * sits on the *analysis edge* exactly like timeline-schema's `OtioValidator`: the shared surface ships
 * the contract + a PHP-native default, and a host may override the binding with a config-gated popcorn
 * Process invocable (Tonal/`music21`) for a deeper pass. Kept dependency-light (no popcorn, no kernel
 * types) so it lives in the satellite-importable surface.
 */
interface MusicConformance
{
    /**
     * Check a drafted intent for musical sanity before it reaches the Arranger. Returns the verdict; a
     * result with mandatory violations should block the render, advisories are surfaced only.
     */
    public function check(MusicIntent $intent): ConformanceResult;
}
