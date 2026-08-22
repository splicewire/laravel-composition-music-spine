<?php

namespace Splicewire\Composition\Music\Analysis;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Composition\Music\Synthesis\SynthesisRequest;

/**
 * The analysis input (voice-profile ticket 03 §4) — the audio-IN half of the audio-work seam this pair
 * was re-chartered to own (ADR-0211). It is the deliberate inverse of {@see SynthesisRequest}, and copies
 * its one non-negotiable rule verbatim: **audio crosses by reference, never as inline binary.**
 *
 * The caller names the **measurement it wants**, never the analyser that should produce it. That is what
 * makes the seam pluggable: a second binding is registered platform-side and every existing caller keeps
 * working unchanged. {@see AnalysisResult::$analyser} then reports which binding actually ran, so the
 * question "who measured this?" is answered by the result rather than assumed by the request.
 */
#[TypeScript]
#[Title('Analysis request')]
class AnalysisRequest extends Data
{
    public function __construct(
        #[Title('Audio artifact reference')]
        #[Description('The audio to analyse, by reference — a Media uuid or target_url; never inline binary.')]
        public string $audioRef,
        #[Title('Measurement')]
        #[Description('The capability wanted, e.g. f0_range. A bare string the spine never validates: an enum here would mean a spine release per new measurement, which is exactly the coupling the pluggable seam exists to avoid.')]
        public string $measurement,
        #[Title('Binding params')]
        #[Description('Opaque per-binding parameters. Carried and digested, never interpreted by this tier — the ResolvedSteering dials contract.')]
        /** @var array<string, mixed> */
        public array $params = [],
        #[Title('Explicit binding')]
        #[Description('Optional binding name that MUST serve this request. Not the default path — it exists so a profile recomputed for comparison can demand the same binding that produced the numbers it is compared against.')]
        public ?string $binding = null,
    ) {}

    /**
     * The stable digest of {@see self::$params} that keys per-run idempotence, alongside the sample set
     * and the binding's own name + version (ticket 03 §5). Key order must not change the digest — two
     * callers spelling the same params differently describe the same run — so keys are sorted, deeply.
     */
    public function paramsDigest(): string
    {
        return substr(hash('sha256', json_encode(self::sortDeep($this->params)) ?: '[]'), 0, 16);
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private static function sortDeep(array $value): array
    {
        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sortDeep($item);
            }
        }

        return $value;
    }
}
