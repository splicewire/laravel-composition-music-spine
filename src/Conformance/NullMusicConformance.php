<?php

namespace Splicewire\Composition\Music\Conformance;

use Splicewire\Composition\Music\Contracts\MusicConformance;
use Splicewire\Composition\Music\MusicIntent;

/**
 * The PHP-native no-op default (mirrors timeline-schema's `NullOtioValidator`): every intent passes.
 * This is the shipped default so the guardrail is off unless a host opts into a real binding — no
 * surprise render-blocking. A host binds {@see StructuralMusicConformance} (cheap mandatory checks) or a
 * config-gated popcorn Process override (Tonal/`music21`) over the same {@see MusicConformance} port.
 */
class NullMusicConformance implements MusicConformance
{
    public function check(MusicIntent $intent): ConformanceResult
    {
        return new ConformanceResult;
    }
}
