<?php

namespace Splicewire\Composition\Music\Arrange;

use InvalidArgumentException;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Composition\Music\MusicIntent;
use Splicewire\Composition\Music\MusicKey;
use Splicewire\Composition\Music\MusicSection;

/**
 * The platform's signed call to a tenant's Arranger (music-capability-lift ticket 06 Q2): the intent to arrange,
 * its digest, and which run and pass asked. The Arranger answers an {@see ArrangeResult} or an
 * {@see ArrangeRefusal}. `intent_digest` is {@see MusicIntent::digest()}, the sha256 of the canonical intent JSON;
 * the platform keys its per-run memo and the `Idempotency-Key` (sha256 of conduit id and intent digest) on it, so
 * build one with {@see self::for()}.
 *
 * {@see self::probe()} is the fixed request the platform sends to activate a binding (06 Q1).
 *
 * The wire names are snake_case, mapped per property: laravel-data 4.23 lets the host's configured input mapper
 * (the flagship's CamelCaseMapper) override a class-level `#[MapName]` (measured in build issue 03).
 */
#[TypeScript]
#[Title('Arrange request')]
class ArrangeRequest extends Data
{
    /** The probe's run key: no augmentation run asks it. */
    public const PROBE_RUN_KEY = 'probe';

    public function __construct(
        #[Title('Intent')]
        #[Description('The music to arrange (music/intent v2), inline.')]
        public MusicIntent $intent,
        #[Title('Intent digest')]
        #[Description('sha256 of the canonical intent JSON.')]
        #[MapName('intent_digest')]
        public string $intentDigest,
        #[Title('Run key')]
        #[Description('The augmentation run asking, or "probe" for an activation probe.')]
        #[MapName('run_key')]
        public string $runKey,
        #[Title('Iteration')]
        #[Description('The loop pass asking, 0-based.')]
        public int $iteration,
    ) {
        if ($iteration < 0) {
            throw new InvalidArgumentException("iteration is 0-based; got {$iteration}.");
        }
    }

    /** The request for one pass of one run, its digest computed from the intent. */
    public static function for(MusicIntent $intent, string $runKey, int $iteration): self
    {
        return new self($intent, $intent->digest(), $runKey, $iteration);
    }

    /**
     * The activation probe (06 Q1): a fixed fixture intent, so every probe is the same request with the same
     * digest. It is v1-plain — no `pickup`, no `changes` — so an Arranger that still refuses those members by name
     * (06 I02) can answer it; it asks every role over a short major progression in common time.
     */
    public static function probe(): self
    {
        return self::for(new MusicIntent(
            key: new MusicKey('C', 'major'),
            sections: [new MusicSection('VERSE', ['C', 'Am', 'F', 'G'])],
            tempo: 96,
            meter: '4/4',
        ), self::PROBE_RUN_KEY, 0);
    }
}
