<?php

declare(strict_types=1);

namespace Splicewire\Composition\Music\Conformance;

use Splicewire\Composition\Music\Contracts\MusicConformance;

/**
 * The conformance guardrail's binding mode (ADR-0125 §9 / §12). A host reads its own config key — the
 * platform's `composition-engine.music_conformance`, a satellite's `splicewire.render.conformance` — and
 * binds {@see MusicConformance} to the class this mode names, so the mode→implementation mapping lives in
 * ONE place across every host and the deeper Tonal/`music21` tier slots in as one more case.
 *
 * Off by default: an unknown or absent mode resolves to {@see NullMusicConformance} (no render-blocking
 * surprise). Framework-agnostic — the host owns the `config()` read; this only maps the resolved string.
 */
enum MusicConformanceMode: string
{
    /** No checks — every intent passes (the shipped default). */
    case Off = 'null';

    /** The built PHP-native structural tier ({@see StructuralMusicConformance}). */
    case Structural = 'structural';

    /** Resolve a host's configured mode string (or null) to a mode; unknown/absent → {@see self::Off}. */
    public static function fromConfig(?string $mode): self
    {
        return self::tryFrom((string) $mode) ?? self::Off;
    }

    /**
     * The concrete {@see MusicConformance} implementation this mode binds — a class-string so the host
     * container resolves it lazily.
     *
     * @return class-string<MusicConformance>
     */
    public function binding(): string
    {
        return match ($this) {
            self::Off => NullMusicConformance::class,
            self::Structural => StructuralMusicConformance::class,
        };
    }
}
