<?php

declare(strict_types=1);

namespace Splicewire\Composition\Music\Conformance;

use Splicewire\CompositionEngine\Music\Contracts\MusicConformance;

/**
 * The verdict of a {@see MusicConformance} check over a
 * MusicIntent: whether it's structurally sound, plus human-readable violation strings. Mirrors
 * timeline-schema's `ValidationResult` (the OTIO-validator popcorn shape, ADR-0125 §9): a plain value
 * object so the seam ships in the shared surface with no popcorn/kernel dependency.
 *
 * `mandatory` violations gate the render (a note out of MIDI range, un-parseable meter, bar/chord
 * mismatch); `advisory` violations are surfaced but do not block (in-key coherence, spelling) — the
 * mandatory-vs-advisory split is the build-effort call recorded in issue 07.
 */
final class ConformanceResult
{
    /**
     * @param  array<int, string>  $mandatory  blocking violations
     * @param  array<int, string>  $advisory  non-blocking violations
     */
    public function __construct(
        public readonly array $mandatory = [],
        public readonly array $advisory = [],
    ) {}

    /** Conformant when there are no *mandatory* violations (advisories never block). */
    public function passes(): bool
    {
        return $this->mandatory === [];
    }

    /**
     * @return array<int, string> every violation, mandatory first
     */
    public function all(): array
    {
        return array_values([...$this->mandatory, ...$this->advisory]);
    }
}
