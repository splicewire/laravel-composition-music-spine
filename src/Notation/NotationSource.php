<?php

namespace Splicewire\Composition\Music\Notation;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Where a notation request's chart came from — **provenance only** (music-capability-lift ticket 02 Q1/Q2
 * amended). It is recorded on the artifacts of the run that produced them and never enters
 * {@see NotationRequest::digest()}: keeping another take of the same chart is the same run.
 */
#[TypeScript]
#[Title('Notation source')]
class NotationSource extends Data
{
    public function __construct(
        #[Title('Chart digest')]
        #[Description('sha256 of the canonical song payload the projector read (a song has no revision id but its content).')]
        public string $chartDigest,
        #[Title('Take run id')]
        #[Description('The kept take run_id the chart was exported from.')]
        public string $takeRunId,
    ) {}
}
