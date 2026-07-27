<?php

declare(strict_types=1);

namespace Splicewire\CompositionMusicSpineData\Synthesis;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;

/**
 * The stage-2 synthesis output (ADR-0128 §5): the synthesized audio artifact by reference plus the
 * **output render-seconds** (audio duration) that is the metered quantity — known only once synthesis
 * completes, which is why metering fires at reconcile, not at submission.
 *
 * `wallSeconds` is optional **internal cost telemetry** (execution time on the platform's hardware). It
 * is deliberately separate from {@see self::$renderSeconds} and must never become the tenant-facing
 * metered quantity: it varies by hardware/load/retries and would expose the platform's ops variance to
 * a tenant's invoice (ADR-0128 §5, rejected alternative).
 */
#[Title('Synthesis result')]
final class SynthesisResult extends Data
{
    public function __construct(
        #[Title('Audio artifact URL')]
        #[Description('The synthesized audio artifact by reference (target_url).')]
        public string $targetUrl,
        #[Title('Render-seconds')]
        #[Description('Output audio duration in seconds — the tenant-facing metered quantity (hardware-independent, reproducible).')]
        public float $renderSeconds,
        #[Title('Wall-seconds (internal)')]
        #[Description('Optional internal telemetry: wall/CPU seconds the synthesis took. Never the billed quantity.')]
        public ?float $wallSeconds = null,
    ) {}
}
