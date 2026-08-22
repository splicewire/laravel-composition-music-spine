<?php

namespace Splicewire\Composition\Music\Analysis;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Composition\Music\Synthesis\SynthesisResult;

/**
 * The analysis output (voice-profile ticket 03 §4, §5) — the mirror of {@see SynthesisResult}, with
 * analysed-seconds where synthesis has render-seconds.
 *
 * **Two payloads, on purpose.** {@see self::$artifactRef} points at the dense frame-level body (ticket
 * 02's OTIO-shaped dialect: packed arrays, declared anchor + hop, per-measurement method identity), and
 * {@see self::$summary} carries the small bounded aggregate a caller acts on without dereferencing
 * anything. This is ticket 02's AcousticBrainz rule read precisely: aggregates are not the problem —
 * aggregates-*only* is, because 30M records with no recoverable series and no confidence data could never
 * be re-examined. The series stays recoverable; the summary is a convenience over it, never a substitute.
 *
 * The summary is an **opaque map, keyed by the measurement**. This tier does not know what an `f0_range`
 * contains and must not learn: the measurement vocabulary is the caller's and the binding's, exactly as
 * {@see AnalysisRequest::$params} is. audiostud's `VocalProfile` (ticket 15) is what interprets it.
 *
 * `wallSeconds` is internal cost telemetry and must never become the metered quantity, for the same
 * reason it must not in synthesis: it varies by hardware, load and retries, and would expose the
 * platform's ops variance to a tenant's invoice.
 */
#[TypeScript]
#[Title('Analysis result')]
class AnalysisResult extends Data
{
    public function __construct(
        #[Title('Analysis artifact URL')]
        #[Description('The dense analysis body by reference (target_url) — ticket 02s dialect. The frame-level series lives here and stays recoverable.')]
        public string $artifactRef,
        #[Title('Analyser')]
        #[Description('The binding that actually ran — reported by the result, never chosen by the request.')]
        public string $analyser,
        #[Title('Analyser version')]
        #[Description('The bindings own version. This is what a profile going stale keys on: the identity of the code that produced the numbers, not a string somebody typed.')]
        public string $analyserVersion,
        #[Title('Params digest')]
        #[Description('Digest of the requests opaque params, completing the per-run idempotence key (sample set, analyser, version, params_digest).')]
        public string $paramsDigest,
        #[Title('Analysed-seconds')]
        #[Description('Seconds of audio actually analysed — the tenant-facing metered quantity, read off the produced result and never off wall time.')]
        public float $analysedSeconds,
        #[Title('Summary')]
        #[Description('The bounded aggregate for this measurement. Opaque to this tier; the caller owns the vocabulary. Never a replacement for the series in the artifact.')]
        /** @var array<string, mixed> */
        public array $summary = [],
        #[Title('Wall-seconds (internal)')]
        #[Description('Optional internal telemetry: wall seconds the analysis took. Never the billed quantity.')]
        public ?float $wallSeconds = null,
    ) {}
}
