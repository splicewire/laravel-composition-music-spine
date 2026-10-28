<?php

namespace Splicewire\Composition\Music\Notation;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What one notation run produced (music-capability-lift ticket 02 Q2). It reaches a caller only inside the
 * Read op's {@see NotationRunData}, never as the Task's output.
 *
 * `wallSeconds` is internal telemetry, never the metered quantity (the synthesis and analysis precedent).
 */
#[TypeScript]
#[Title('Notation result')]
class NotationResult extends Data
{
    /**
     * @param  array<int, NotationArtifact>  $artifacts
     * @param  array<string, string>  $engraver
     */
    public function __construct(
        #[Title('Artifacts')]
        #[DataCollectionOf(NotationArtifact::class)]
        public array $artifacts,
        #[Title('Engraver')]
        #[Description('The engraving components and their versions, e.g. {"music21": "10.5.1", "verovio": "6.3.0", "pdf": "…"}.')]
        public array $engraver,
        #[Title('Engraver version')]
        #[Description('The binding VERSION; it bumps whenever MusicXML, SVG or chart bytes, or PDF content, change.')]
        public string $engraverVersion,
        #[Title('Request digest')]
        #[Description('NotationRequest::digest() of the request that produced this.')]
        public string $requestDigest,
        #[Title('Run key')]
        public string $runKey,
        #[Title('Source')]
        #[Description('The producing request provenance.')]
        public NotationSource $source,
        #[Title('Summary')]
        public NotationSummary $summary,
        #[Title('Wall-seconds (internal)')]
        #[Description('Optional internal telemetry. Never the billed quantity.')]
        public ?float $wallSeconds = null,
    ) {}
}
